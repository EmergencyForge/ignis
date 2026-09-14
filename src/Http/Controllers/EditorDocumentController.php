<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Documents\Editor\DocumentId;
use App\Documents\Editor\PdfGenerator;
use App\Documents\Editor\VariableCatalog;
use App\Helpers\Flash;
use App\Http\Requests\Documents\SaveDocumentRequest;
use App\Logging\Logger;
use App\Models\EditorDocument;
use App\Models\EditorTemplate;
use App\Models\Personnel;
use App\Security\CsrfProtection;
use App\Session\SessionManager;
use App\Utils\AuditLogger;
use EmergencyForge\Editor\Renderer;
use EmergencyForge\Editor\SectionGuard;
use EmergencyForge\Http\Exceptions\ValidationException;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;
use RuntimeException;

/**
 * Dokumente am Mitarbeiter: aus einer Vorlage anlegen, im Entwurf
 * bearbeiten, ausstellen und als PDF ausliefern.
 *
 * Die Rechte kommen aus {@see \App\Policies\DocumentPolicy} — dieselben
 * wie für die Dokumente des alten Systems, weil es dieselbe Befugnis ist:
 * wer Urkunden ausstellen darf, darf es hier wie dort.
 *
 * `save()` bedient zwei Aufrufer über denselben Endpunkt: den Klick auf
 * „Speichern" und die Autosave alle 30 Sekunden, beide per fetch mit
 * `Accept: application/json`. Deshalb antwortet die Methode je nach
 * Aufrufer als JSON oder mit Flash und Weiterleitung.
 *
 * Zwei Stellen sind heikler, als sie aussehen:
 *
 * CsrfProtection::validateToken() dreht den Session-Token bei jeder
 * erfolgreichen Prüfung weiter. Die Autosave hätte ab dem zweiten Versuch
 * dauerhaft einen veralteten Token geschickt und wäre still gescheitert —
 * der Nutzer sieht eine allgemeine Fehlermeldung und verliert beim
 * Schließen des Tabs seine Änderungen. Darum trägt jede JSON-Antwort den
 * aktuellen Token im Body, und der Editor schreibt ihn zurück.
 *
 * Der Statuswechsel läuft nie über `Model::save()` (das kennt nur
 * `WHERE id = ?`), sondern über ein bedingtes
 * `UPDATE … WHERE id = ? AND status = 'entwurf'`. Die betroffene
 * Zeilenzahl ist die einzige verlässliche Auskunft darüber, wer das Rennen
 * gewonnen hat — etwa wenn ein Autosave-Tick und der Ausstellen-Klick fast
 * gleichzeitig ankommen. Der Verlierer bekommt eine Meldung statt den
 * Gewinner stillschweigend zu überschreiben.
 */
final class EditorDocumentController extends Controller
{
    /**
     * POST /personnel/{id}/documents — Dokument aus einer aktiven Vorlage
     * anlegen. Das Vorlagen-JSON wird unverändert übernommen, samt der
     * Abschnitts-Kennungen; der erste Speichervorgang prüft dann gegen
     * dieselbe Vorlage.
     */
    public function create(Request $request, string $id): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'personnel/list');

        $mitarbeiter = Personnel::find((int) $id);
        if ($mitarbeiter === null) {
            Flash::error('Der Mitarbeiter wurde nicht gefunden.');
            $this->redirect('personnel/list');
        }

        $back = 'personnel/profile?id=' . $mitarbeiter->id;

        try {
            $data = SaveDocumentRequest::validate($request->post);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect($back);
        }

        $template = EditorTemplate::query()->active()->find((int) $data['template_id']);
        if ($template === null) {
            Flash::error('Vorlage wurde nicht gefunden oder ist nicht aktiv.');
            $this->redirect($back);
        }

        $title     = $data['title'] !== '' ? $data['title'] : $template->name;
        $createdBy = SessionManager::userId();

        $document = DocumentId::create(
            static fn (string $docid): EditorDocument => EditorDocument::create([
                'docid'          => $docid,
                'template_id'    => $template->id,
                'mitarbeiter_id' => $mitarbeiter->id,
                'title'          => $title,
                'content'        => $template->content,
                'status'         => EditorDocument::STATUS_DRAFT,
                'created_by'     => $createdBy,
            ]),
        );

        $this->audit('Dokument angelegt', $document);
        Flash::success('Dokument „' . $document->title . '" wurde angelegt.');
        $this->redirect('documents/' . $document->id . '/edit');
    }

    /** GET /documents/{id}/edit */
    public function editView(Request $request, string $id): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'index');

        $document = $this->findOrRedirect((int) $id, ['template', 'mitarbeiter']);

        if ($document->isIssued()) {
            $this->redirect('documents/' . $document->id);
        }

        $this->renderView('documents/edit', [
            'document'   => $document,
            'variables'  => VariableCatalog::resolve($this->variableContext($document)),
            'catalog'    => VariableCatalog::catalog(),
            'saveAction' => BASE_PATH . 'documents/' . $document->id . '/save',
        ]);
    }

    /** POST /documents/{id}/save — Klick auf Speichern und Autosave. */
    public function save(Request $request, string $id): Response
    {
        $this->requireAuth();

        $document = EditorDocument::find((int) $id);
        if ($document === null) {
            return $this->failure($request, 'Dokument wurde nicht gefunden.', 404, 'index');
        }

        $editUrl = 'documents/' . $document->id . '/edit';
        $showUrl = 'documents/' . $document->id;

        if (\App\Auth\Gate::denies('document.manage')) {
            return $this->failure($request, 'Keine Berechtigung, dieses Dokument zu bearbeiten.', 403, 'index');
        }

        if ($document->isIssued()) {
            return $this->failure($request, 'Nur Entwürfe können gespeichert werden.', 403, $showUrl);
        }

        // Ohne Vorlage gibt es keine Referenz, gegen die sich gesperrte
        // Abschnitte wiederherstellen liessen. Speichern zuzulassen hiesse,
        // sie in Wahrheit ungeschuetzt zu lassen — also bleibt der Entwurf
        // lesbar und unveraenderlich.
        $template = $document->template;
        if ($template === null) {
            return $this->failure(
                $request,
                'Die Vorlage dieses Dokuments wurde gelöscht — der Entwurf ist schreibgeschützt.',
                422,
                $editUrl,
            );
        }

        try {
            $data = SaveDocumentRequest::validate($request->post);
        } catch (ValidationException $e) {
            return $this->failure($request, $e->firstError() ?? 'Ungültige Eingabe.', 422, $editUrl);
        }

        $decoded = json_decode($data['content'], true);
        if (!is_array($decoded)) {
            return $this->failure($request, 'Dokumentinhalt ist kein gültiges JSON.', 422, $editUrl);
        }

        try {
            $result = (new SectionGuard())->restoreLockedSections($template->content, $decoded);
        } catch (InvalidArgumentException $e) {
            Logger::warning('Vorlage von Dokument ' . $document->id . ' ist beschädigt: ' . $e->getMessage());

            return $this->failure(
                $request,
                'Die Vorlage dieses Dokuments ist beschädigt und kann nicht mehr verarbeitet werden.',
                422,
                $editUrl,
            );
        }

        foreach ($result->warnings as $warning) {
            Logger::warning('Dokument ' . $document->id . ' beim Speichern: ' . $warning);
        }

        $update = [
            'content'    => json_encode($result->document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($data['title'] !== '') {
            $update['title'] = $data['title'];
        }

        $affected = Capsule::table('intra_documents')
            ->where('id', $document->id)
            ->where('status', EditorDocument::STATUS_DRAFT)
            ->update($update);

        // MySQL zaehlt nur geaenderte Zeilen. Ein Autosave, der nichts
        // Neues bringt, trifft seine Zeile und meldet trotzdem 0 — deshalb
        // gilt das Rennen erst als verloren, wenn der Status tatsaechlich
        // nicht mehr auf Entwurf steht.
        if ($affected === 0 && !$this->stillDraft($document->id)) {
            return $this->failure(
                $request,
                'Dieses Dokument wurde inzwischen ausgestellt und kann nicht mehr gespeichert werden.',
                403,
                $showUrl,
            );
        }

        if ($data['autosave'] !== '1') {
            $this->audit('Dokument gespeichert', $document);
        }

        if ($this->wantsJson($request)) {
            return $this->json(['success' => true, 'message' => 'Dokument wurde gespeichert.', 'saved_at' => date('H:i')]);
        }

        Flash::success('Dokument wurde gespeichert.');
        $this->redirect($editUrl);
    }

    /** GET /documents/{id} */
    public function show(Request $request, string $id): void
    {
        $this->requireAuth();
        $this->ensure('document.view', null, 'index');

        $document = $this->findOrRedirect((int) $id, ['mitarbeiter']);

        if (!$document->isIssued()) {
            $this->redirect('documents/' . $document->id . '/edit');
        }

        $this->renderView('documents/show', ['document' => $document]);
    }

    /**
     * POST /documents/{id}/issue — aus dem Entwurf wird ein Dokument.
     *
     * Statuswechsel und PDF-Erzeugung liegen in einer Transaktion: scheitert
     * das Rendern, rollt der bereits geschriebene Statuswechsel mit zurück
     * und der Entwurf bleibt wirklich ein Entwurf.
     */
    public function issue(Request $request, string $id): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'index');

        $document = $this->findOrRedirect((int) $id, ['mitarbeiter']);

        $showUrl = 'documents/' . $document->id;
        $editUrl = 'documents/' . $document->id . '/edit';

        if ($document->isIssued()) {
            Flash::error('Dieses Dokument wurde bereits ausgestellt.');
            $this->redirect($showUrl);
        }

        $frozen  = VariableCatalog::resolve($this->variableContext($document));
        $missing = $this->emptyRequiredFields($document, $frozen);
        if ($missing !== []) {
            Flash::error(
                'Dokument „' . $document->title . '" kann noch nicht ausgestellt werden — diese '
                . 'Pflichtfelder sind leer: ' . implode(', ', $missing) . '.',
            );
            $this->redirect($editUrl);
        }

        $userId = SessionManager::userId();

        try {
            $issued = Capsule::connection()->transaction(static function () use ($document, $frozen, $userId): bool {
                $now = date('Y-m-d H:i:s');

                $affected = Capsule::table('intra_documents')
                    ->where('id', $document->id)
                    ->where('status', EditorDocument::STATUS_DRAFT)
                    ->update([
                        'status'        => EditorDocument::STATUS_ISSUED,
                        'frozen_values' => json_encode($frozen, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'issued_by'     => $userId,
                        'issued_at'     => $now,
                        'updated_at'    => $now,
                    ]);

                if ($affected === 0) {
                    return false;
                }

                $pdfPath = (new PdfGenerator())->generate($document, $frozen);
                Capsule::table('intra_documents')->where('id', $document->id)->update(['pdf_path' => $pdfPath]);

                return true;
            });
        } catch (RuntimeException $e) {
            Logger::error('Ausstellen von Dokument ' . $document->docid . ' fehlgeschlagen: ' . $e->getMessage());
            Flash::error('Das Dokument konnte nicht als PDF ausgestellt werden — der Entwurf bleibt unverändert.');
            $this->redirect($editUrl);
        }

        if (!$issued) {
            Flash::error('Dieses Dokument wurde inzwischen ausgestellt.');
            $this->redirect($showUrl);
        }

        $this->audit('Dokument ausgestellt', $document);
        Flash::success('Dokument „' . $document->title . '" wurde ausgestellt.');
        $this->redirect($showUrl);
    }

    /** GET /documents/{id}/pdf */
    public function pdf(Request $request, string $id): Response
    {
        $this->requireAuth();
        $this->ensure('document.view', null, 'index');

        $document = $this->findOrRedirect((int) $id);

        $contents = (new PdfGenerator())->read($document);
        if ($contents === null) {
            Flash::error('Zu diesem Dokument liegt keine PDF-Datei vor.');
            $this->redirect('documents/' . $document->id);
        }

        return new Response(200, $contents, [
            'Content-Type'           => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=300',
        ]);
    }

    private function stillDraft(int $id): bool
    {
        return Capsule::table('intra_documents')
            ->where('id', $id)
            ->where('status', EditorDocument::STATUS_DRAFT)
            ->exists();
    }

    /**
     * Welche Pflichtfelder noch leer sind. Der Renderer meldet sie als
     * Warnung — ihn zu fragen ist verlässlicher, als das Dokument-JSON hier
     * ein zweites Mal zu durchlaufen.
     *
     * @param  array<string,string>  $variables
     * @return list<string>
     */
    private function emptyRequiredFields(EditorDocument $document, array $variables): array
    {
        $prefix = 'Pflichtfeld leer: ';
        $labels = [];

        foreach ((new Renderer())->render($document->content, $variables)->warnings as $warning) {
            if (str_starts_with($warning, $prefix)) {
                $labels[] = substr($warning, strlen($prefix));
            }
        }

        return $labels;
    }

    /**
     * @param  list<string>  $with
     */
    private function findOrRedirect(int $id, array $with = []): EditorDocument
    {
        $document = EditorDocument::with($with)->find($id);
        if ($document === null) {
            Flash::error('Das Dokument wurde nicht gefunden.');
            $this->redirect('index');
        }

        return $document;
    }

    /**
     * @return array{mitarbeiter: Personnel|null, docid: string}
     */
    private function variableContext(EditorDocument $document): array
    {
        return ['mitarbeiter' => $document->mitarbeiter, 'docid' => $document->docid];
    }

    private function wantsJson(Request $request): bool
    {
        return str_contains($request->header('Accept') ?? '', 'application/json');
    }

    /**
     * Jede JSON-Antwort trägt den aktuellen CSRF-Token, damit die Autosave
     * nach der Token-Rotation weiterarbeiten kann.
     *
     * @param  array<string,mixed>  $payload
     */
    private function json(array $payload, int $status = 200): Response
    {
        $payload['csrf_token'] = CsrfProtection::getToken();

        return Response::json($payload, $status);
    }

    /**
     * @return Response  JSON-Aufrufer bekommen die Antwort, alle anderen
     *                   eine Weiterleitung (die als Exception fliegt).
     */
    private function failure(Request $request, string $message, int $status, string $redirectTo): Response
    {
        if ($this->wantsJson($request)) {
            return $this->json(['success' => false, 'message' => $message], $status);
        }

        Flash::error($message);
        $this->redirect($redirectTo);
    }

    private function audit(string $action, EditorDocument $document): void
    {
        (new AuditLogger())->log(
            (int) SessionManager::userId(),
            $action,
            'Kennung: ' . $document->docid . ', Titel: ' . $document->title,
            'Dokumente',
            1,
        );
    }
}
