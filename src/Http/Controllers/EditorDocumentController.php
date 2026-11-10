<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Documents\Editor\DocumentId;
use App\Documents\Editor\DocumentIssuer;
use App\Documents\Editor\DocumentNotIssued;
use App\Documents\Editor\FieldChoices;
use App\Documents\Editor\PdfGenerator;
use App\Documents\Editor\VariableCatalog;
use App\Helpers\Flash;
use App\Http\Requests\Documents\SaveDocumentRequest;
use App\Logging\Logger;
use App\Models\EditorDocument;
use App\Models\EditorTemplate;
use App\Models\EditorTemplateVersion;
use App\Models\Personnel;
use App\Personnel\AccountLink;
use App\Policies\DocumentPolicy;
use App\Session\SessionManager;
use App\Utils\AuditLogger;
use EmergencyForge\Editor\PackageGuard;
use EmergencyForge\Http\Exceptions\ValidationException;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;

/**
 * Dokumente am Mitarbeiter: aus einer Vorlage anlegen, im Entwurf
 * bearbeiten, ausstellen und als PDF ausliefern.
 *
 * Die Rechte kommen aus {@see \App\Policies\DocumentPolicy}, dieselben
 * wie für die Dokumente des alten Systems, weil es dieselbe Befugnis ist:
 * wer Urkunden ausstellen darf, darf es hier wie dort.
 *
 * `save()` bedient zwei Aufrufer über denselben Endpunkt: den Klick auf
 * „Speichern" und die Autosave alle 30 Sekunden, beide per fetch mit
 * `Accept: application/json`. Deshalb antwortet die Methode je nach
 * Aufrufer als JSON oder mit Flash und Weiterleitung.
 *
 * Eine Stelle ist heikler, als sie aussieht:
 *
 * Der Statuswechsel läuft nie über `Model::save()` (das kennt nur
 * `WHERE id = ?`), sondern über ein bedingtes
 * `UPDATE … WHERE id = ? AND status = 'entwurf'`. Die betroffene
 * Zeilenzahl ist die einzige verlässliche Auskunft darüber, wer das Rennen
 * gewonnen hat, etwa wenn ein Autosave-Tick und der Ausstellen-Klick fast
 * gleichzeitig ankommen. Der Verlierer bekommt eine Meldung statt den
 * Gewinner stillschweigend zu überschreiben.
 */
final class EditorDocumentController extends Controller
{
    public function __construct(private readonly DocumentIssuer $issuer)
    {
    }

    /**
     * POST /personnel/{id}/documents: Dokument aus einer aktiven Vorlage
     * anlegen. Es hängt an der jüngsten Fassung und startet mit deren
     * Inhalt; jedes Speichern prüft gegen genau diese Fassung.
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

        $template = EditorTemplate::active()->find((int) $data['template_id']);
        if ($template === null) {
            Flash::error('Vorlage wurde nicht gefunden oder ist nicht aktiv.');
            $this->redirect($back);
        }

        $title     = $data['title'] !== '' ? $data['title'] : $template->name;
        $createdBy = SessionManager::userId();

        $document = DocumentId::create(
            static fn (string $docid): EditorDocument => EditorDocument::create($template->newDocumentAttributes() + [
                'docid'          => $docid,
                'mitarbeiter_id' => $mitarbeiter->id,
                'title'          => $title,
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

        $document = $this->findOrRedirect((int) $id, ['template', 'templateVersion', 'mitarbeiter']);

        if ($document->isIssued()) {
            $this->redirect('documents/' . $document->id);
        }

        $upgrade           = $this->pendingUpgrade($document);
        $document->content = FieldChoices::fill($document->content);
        $who               = DocumentPolicy::whoOptions();

        $this->renderView('documents/edit', [
            'document'       => $document,
            'variables'      => VariableCatalog::resolve($this->variableContext($document)),
            'catalog'        => VariableCatalog::catalog(),
            'missingReasons' => VariableCatalog::missingReasons($this->variableContext($document)),
            'who'            => $who,
            'grants'         => array_values(array_filter(
                array_column($who, 'key'),
                static fn (string $key): bool => DocumentPolicy::mayFill($key),
            )),
            'upgrade'        => $upgrade,
            'saveAction'     => BASE_PATH . 'documents/' . $document->id . '/save',
        ]);
    }

    /**
     * Neuere Fassung der Vorlage, auf die der Entwurf umgestellt werden kann,
     * samt dem, was dabei verloren ginge. Null, wenn er aktuell ist.
     *
     * @return array{version: EditorTemplateVersion, lost: list<array{type: string, key: string, label: string, length: int}>}|null
     */
    private function pendingUpgrade(EditorDocument $document): ?array
    {
        $current = $document->templateVersion;
        $latest  = $document->template?->latestVersion();
        if ($current === null || $latest === null || $latest->version <= $current->version) {
            return null;
        }

        try {
            $lost = (new PackageGuard())->lostEntries($document->content, $current->content, $latest->content);
        } catch (InvalidArgumentException $e) {
            Logger::warning('Fassung ' . $latest->id . ' ist beschädigt: ' . $e->getMessage());

            return null;
        }

        return ['version' => $latest, 'lost' => $lost];
    }

    /**
     * POST /documents/{id}/upgrade: stellt einen Entwurf auf die jüngste
     * Fassung seiner Vorlage um. Werte bleiben über ihre IDs erhalten. Die
     * Seite hat vorher genannt, was verloren geht (`lost`); weicht das
     * inzwischen ab (jemand hat weitergeschrieben oder die Vorlage geändert),
     * stellt ignis nicht um, sondern zeigt die neue Liste.
     */
    public function upgrade(Request $request, string $id): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'index');

        $document = $this->findOrRedirect((int) $id, ['template', 'templateVersion']);
        $editUrl  = 'documents/' . $document->id . '/edit';

        if ($document->isIssued()) {
            $this->redirect('documents/' . $document->id);
        }

        $pending = $this->pendingUpgrade($document);
        if ($pending === null) {
            Flash::info('Der Entwurf nutzt schon die aktuelle Fassung der Vorlage.');
            $this->redirect($editUrl);
        }

        $expected = array_values(array_filter(
            explode(',', (string) ($request->post['lost'] ?? '')),
            static fn (string $key): bool => $key !== '',
        ));
        $actual = array_column($pending['lost'], 'key');
        sort($expected);
        sort($actual);
        if ($expected !== $actual) {
            Flash::error('Seit dem Laden der Seite hat sich geändert, was beim Umstellen verloren ginge. Bitte prüf die Liste noch einmal.');
            $this->redirect($editUrl);
        }

        $version = $pending['version'];
        $package = (new PackageGuard())->restore(
            FieldChoices::fill($version->content, $document->content),
            $document->content,
            $document->content,
        )->package;

        $affected = Capsule::table('intra_documents')
            ->where('id', $document->id)
            ->where('status', EditorDocument::STATUS_DRAFT)
            ->where('template_version_id', $document->template_version_id)
            ->update([
                'content'             => json_encode($package, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'template_version_id' => $version->id,
                'updated_at'          => date('Y-m-d H:i:s'),
            ]);
        if ($affected === 0) {
            Flash::error('Der Entwurf wurde inzwischen geändert oder ausgestellt. Bitte lade die Seite neu.');
            $this->redirect($editUrl);
        }

        $lost = array_column($pending['lost'], 'label');
        $this->audit(
            'Dokument auf neue Vorlagenfassung umgestellt',
            $document,
            ', Fassung: ' . $version->version . ($lost !== [] ? ', verloren: ' . implode(', ', $lost) : ''),
        );
        Flash::success('Der Entwurf nutzt jetzt Fassung ' . $version->version . ' der Vorlage.');
        $this->redirect($editUrl);
    }

    /** POST /documents/{id}/save: Klick auf Speichern und Autosave. */
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

        // Ohne Fassung gibt es keine Referenz, gegen die sich feste Teile
        // wiederherstellen liessen. Speichern zuzulassen hiesse, sie in
        // Wahrheit ungeschuetzt zu lassen. Also bleibt der Entwurf lesbar
        // und unveraenderlich.
        $version = $document->templateVersion;
        if ($version === null) {
            return $this->failure(
                $request,
                'Die Vorlage dieses Dokuments wurde gelöscht. Der Entwurf ist schreibgeschützt.',
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
            // Alles außer Eingaben kommt aus der Fassung, Felder mit `who`
            // nur mit dem Recht, sonst bleibt der gespeicherte Stand.
            $result = (new PackageGuard())->restore(
                template: FieldChoices::fill($version->content, $document->content),
                submitted: $decoded,
                previous: $document->content,
                may: DocumentPolicy::mayFill(...),
            );
        } catch (InvalidArgumentException $e) {
            // Ein feindliches Submit wirft nie, nur eine kaputte Fassung.
            Logger::warning('Fassung ' . $version->id . ' von Dokument ' . $document->id . ' ist beschädigt: ' . $e->getMessage());

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
            'content'    => json_encode($result->package, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
        // Neues bringt, trifft seine Zeile und meldet trotzdem 0. Deshalb
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
        $document = $this->findOrRedirect((int) $id, ['mitarbeiter']);
        if (!$this->ownIssued($document)) {
            $this->ensure('document.view', null, 'index');
        }

        if (!$document->isIssued()) {
            $this->redirect('documents/' . $document->id . '/edit');
        }

        $this->renderView('documents/show', ['document' => $document]);
    }

    /**
     * POST /documents/{id}/issue: aus dem Entwurf wird ein Dokument. Die
     * Arbeit macht {@see DocumentIssuer}, hier bleiben Rechte, Meldung und
     * Weiterleitung.
     */
    public function issue(Request $request, string $id): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'index');

        $document = $this->findOrRedirect((int) $id, ['mitarbeiter']);

        $showUrl = 'documents/' . $document->id;

        try {
            $leftEmpty = $this->issuer->issue(
                $document,
                AccountLink::currentId(),
                SessionManager::userId(),
                acceptMissing: ($request->post['accept_missing'] ?? '') === '1',
            );
        } catch (DocumentNotIssued $e) {
            Flash::error($e->getMessage());
            $this->redirect($e->alreadyIssued ? $showUrl : $showUrl . '/edit');
        }

        $this->audit(
            'Dokument ausgestellt',
            $document,
            $leftEmpty !== [] ? ', leer gelassen: ' . implode(', ', $leftEmpty) : '',
        );
        Flash::success('Dokument „' . $document->title . '" wurde ausgestellt.');
        $this->redirect($showUrl);
    }

    /** GET /documents/{id}/pdf */
    public function pdf(Request $request, string $id): Response
    {
        $this->requireAuth();
        $document = $this->findOrRedirect((int) $id);
        if (!$this->ownIssued($document)) {
            $this->ensure('document.view', null, 'index');
        }

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

    /** Das eigene, ausgestellte Dokument sieht jeder, auch ohne Dokumentenrecht. */
    private function ownIssued(EditorDocument $document): bool
    {
        $own = AccountLink::currentId();

        return $own !== null && $document->isIssued() && (int) $document->mitarbeiter_id === $own;
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
     * @param  array<string,mixed>  $payload
     */
    private function json(array $payload, int $status = 200): Response
    {
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

    private function audit(string $action, EditorDocument $document, string $extra = ''): void
    {
        (new AuditLogger())->log(
            (int) SessionManager::userId(),
            $action,
            'Kennung: ' . $document->docid . ', Titel: ' . $document->title . $extra,
            'Dokumente',
            1,
        );
    }
}
