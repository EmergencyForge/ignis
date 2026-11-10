<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Documents\Editor\FieldChoices;
use App\Documents\Editor\TemplateImageSource;
use App\Documents\Editor\VariableCatalog;
use App\Exceptions\UploadException;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveDocumentTemplateRequest;
use App\Models\EditorTemplate;
use App\Models\Letterhead;
use App\Policies\DocumentPolicy;
use App\Session\SessionManager;
use App\Utils\AuditLogger;
use EmergencyForge\Editor\Package;
use EmergencyForge\Editor\PackageGuard;
use EmergencyForge\Http\Exceptions\ValidationException;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use InvalidArgumentException;

/**
 * Die Vorlagen des Dokumenten-Editors verwalten.
 *
 * Eine Vorlage ist ein Dokumentpaket (editor 0.6): Layout, Kopf- und
 * Fußzeilen und ein Textkörper aus festem Text, Feldern und
 * Eingabebereichen. validateContent() prüft es unabhängig vom Editor im
 * Browser. Gespeichert wird über EditorTemplate::saveContent(), das eine
 * neue Fassung anlegt, sobald an der jüngsten schon ein Dokument hängt.
 *
 * Gelöscht wird eine Vorlage nur, solange kein Dokument auf sie zeigt.
 * Sonst wird sie deaktiviert. Ein ausgestelltes Dokument soll seine
 * Herkunft behalten, ein Entwurf braucht seine Fassung zum Speichern.
 */
final class EditorTemplateController extends Controller
{
    /** GET /settings/documents/editor-templates */
    public function index(): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'settings/index');

        $this->renderView('settings/documents/editor-templates', [
            'templates' => EditorTemplate::query()->withCount('documents')->orderBy('name')->get(),
        ]);
    }

    /** GET /settings/documents/editor-templates/create */
    public function createView(): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'settings/index');

        // Ohne Inhalt fragt die Seite zuerst „Frei, mit festen Teilen“ oder
        // „Fest, mit Lücken“ und baut das Paket im Browser.
        $this->renderView('settings/documents/editor-template-edit', [
            'template'       => null,
            'formAction'     => BASE_PATH . 'settings/documents/editor-templates/create',
            'initialContent' => null,
        ] + $this->editorData());
    }

    /** POST /settings/documents/editor-templates/create */
    public function store(Request $request): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'settings/index');

        $back = 'settings/documents/editor-templates/create';

        try {
            $data = SaveDocumentTemplateRequest::validate($request->post);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect($back);
        }

        $content = $this->validateContent($data['content']);
        if ($content === null) {
            $this->redirect($back);
        }

        $template             = new EditorTemplate();
        $template->name       = $data['name'];
        $template->category   = $data['category'];
        $template->print_verification = $data['print_verification'];
        $template->is_active  = true;
        $template->created_by = SessionManager::userId();
        $template->saveContent($content, $template->created_by);

        $this->audit('Dokumentvorlage erstellt', $template);
        Flash::success('Vorlage „' . $template->name . '" wurde angelegt.');
        $this->redirect('settings/documents/editor-templates');
    }

    /** GET /settings/documents/editor-templates/{id} */
    public function editView(Request $request, string $id): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'settings/index');

        $template = $this->findOrRedirect((int) $id);

        $this->renderView('settings/documents/editor-template-edit', [
            'template'       => $template,
            'formAction'     => BASE_PATH . 'settings/documents/editor-templates/' . $template->id,
            'initialContent' => FieldChoices::fill($template->content),
        ] + $this->editorData());
    }

    /** POST /settings/documents/editor-templates/{id} */
    public function update(Request $request, string $id): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'settings/index');

        $template = $this->findOrRedirect((int) $id);
        $back     = 'settings/documents/editor-templates/' . $template->id;

        try {
            $data = SaveDocumentTemplateRequest::validate($request->post);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect($back);
        }

        $content = $this->validateContent($data['content']);
        if ($content === null) {
            $this->redirect($back);
        }

        $template->name     = $data['name'];
        $template->category = $data['category'];
        $template->print_verification = $data['print_verification'];
        $template->saveContent($content, SessionManager::userId());

        $this->audit('Dokumentvorlage aktualisiert', $template);
        Flash::success('Vorlage „' . $template->name . '" wurde aktualisiert.');
        $this->redirect('settings/documents/editor-templates');
    }

    /** POST /settings/documents/editor-templates/{id}/deactivate */
    public function deactivate(Request $request, string $id): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'settings/index');

        $template = $this->findOrRedirect((int) $id);
        $usage    = $template->documents()->count();
        $name     = $template->name;

        if ($usage > 0) {
            $template->is_active = false;
            $template->save();
            $this->audit('Dokumentvorlage deaktiviert', $template);
            Flash::success(
                'Vorlage „' . $name . '" wird noch von ' . $usage . ' Dokument(en) verwendet und wurde '
                . 'deaktiviert statt gelöscht.',
            );
        } else {
            $this->audit('Dokumentvorlage gelöscht', $template);
            $template->delete();
            Flash::success('Vorlage „' . $name . '" wurde gelöscht.');
        }

        $this->redirect('settings/documents/editor-templates');
    }

    /**
     * POST /settings/documents/editor-templates/images: Bild für eine
     * Vorlage hochladen (Logo, Wappen). Antwortet als JSON mit `src`, den
     * der Editor ins Dokument setzt.
     */
    public function uploadImage(Request $request): Response
    {
        $this->requireAuth();
        if (\App\Auth\Gate::denies('document.manage')) {
            return Response::json(['success' => false, 'message' => 'Keine Berechtigung.'], 403);
        }

        $file = $request->files['image'] ?? null;
        if (!is_array($file)) {
            return Response::json(['success' => false, 'message' => 'Es wurde keine Datei ausgewählt.'], 422);
        }

        try {
            $name = TemplateImageSource::store($file);
        } catch (UploadException $e) {
            return Response::json(['success' => false, 'message' => $e->getMessage()], $e->status);
        }

        (new AuditLogger())->log((int) SessionManager::userId(), 'Vorlagenbild hochgeladen', 'Datei: ' . $name, 'Dokumente', 1);

        return Response::json(['success' => true, 'src' => BASE_PATH . TemplateImageSource::ROUTE . $name]);
    }

    /**
     * Was der Vorlageneditor außer dem Paket braucht.
     *
     * @return array{variables: array<string,string>, letterheads: list<array<string,mixed>>, who: list<array{key: string, label: string}>, uploadUrl: string}
     */
    private function editorData(): array
    {
        return [
            'variables'   => VariableCatalog::catalog(),
            'letterheads' => Letterhead::query()->get()->sortBy('name')->map(static fn (Letterhead $l): array => $l->forEditor())->values()->all(),
            'who'         => DocumentPolicy::whoOptions(),
            'uploadUrl'   => BASE_PATH . 'settings/documents/editor-templates/images',
        ];
    }

    /**
     * Prüft den `content`-JSON-String unabhängig vom Editor im Browser: ein
     * Dokumentpaket, das `PackageGuard::assertValidTemplate()` annimmt. Die
     * Meldung des Pakets nennt den betroffenen Bereich an seiner Beschriftung.
     * Kommt das Layout aus einem Briefbogen, gelten dessen Bänder und Ränder,
     * nicht die geschickten. Setzt bei Fehlschlag selbst die Meldung und
     * liefert `null`.
     *
     * @return array<string,mixed>|null
     */
    private function validateContent(string $json): ?array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !Package::isPackage($decoded)) {
            Flash::error('Vorlageninhalt ist kein gültiges Dokumentpaket.');

            return null;
        }

        $letterheadId = (int) ($decoded['layout']['letterheadId'] ?? 0);
        $letterhead   = $letterheadId > 0 ? Letterhead::find($letterheadId) : null;
        if ($letterhead !== null) {
            $decoded = $letterhead->applyTo($decoded);
        } elseif ($letterheadId > 0) {
            $decoded['layout']['letterheadId'] = null;
        }

        try {
            (new PackageGuard())->assertValidTemplate($decoded);
        } catch (InvalidArgumentException $e) {
            Flash::error($e->getMessage());

            return null;
        }

        return $decoded;
    }

    private function findOrRedirect(int $id): EditorTemplate
    {
        $template = EditorTemplate::find($id);
        if ($template === null) {
            Flash::error('Vorlage wurde nicht gefunden.');
            $this->redirect('settings/documents/editor-templates');
        }

        return $template;
    }

    private function audit(string $action, EditorTemplate $template): void
    {
        (new AuditLogger())->log(
            (int) SessionManager::userId(),
            $action,
            'Name: ' . $template->name . ' [ID: ' . $template->id . ']',
            'Dokumente',
            1,
        );
    }
}
