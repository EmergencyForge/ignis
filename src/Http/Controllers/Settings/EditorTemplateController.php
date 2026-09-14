<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Documents\Editor\VariableCatalog;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveDocumentTemplateRequest;
use App\Models\EditorTemplate;
use App\Session\SessionManager;
use App\Utils\AuditLogger;
use EmergencyForge\Editor\SectionGuard;
use EmergencyForge\Http\Exceptions\ValidationException;
use EmergencyForge\Http\Request;
use InvalidArgumentException;

/**
 * Die Vorlagen des Dokumenten-Editors verwalten.
 *
 * Eine Vorlage besteht auf oberster Ebene ausschließlich aus Abschnitten:
 * gesperrte, die der Aussteller nicht anfassen darf, und freie, in denen er
 * schreibt. Alles andere lehnt {@see validateContent()} ab, weil der
 * SectionGuard beim Speichern eines Dokuments sonst keine Referenz hätte,
 * gegen die er den gesperrten Text zurückholen könnte.
 *
 * Gelöscht wird eine Vorlage nur, solange kein Dokument auf sie zeigt.
 * Sonst wird sie deaktiviert — ein ausgestelltes Dokument soll seine
 * Herkunft behalten.
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

        $this->renderView('settings/documents/editor-template-edit', [
            'template'       => null,
            'formAction'     => BASE_PATH . 'settings/documents/editor-templates/create',
            'variables'      => VariableCatalog::catalog(),
            'initialContent' => $this->defaultContent(),
        ]);
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
        $template->content    = $content;
        $template->is_active  = true;
        $template->created_by = SessionManager::userId();
        $template->save();

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
            'variables'      => VariableCatalog::catalog(),
            'initialContent' => $template->content,
        ]);
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
        $template->content  = $content;
        $template->save();

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
     * Prüft das JSON aus dem Editor und liefert es als Array zurück, oder
     * `null` samt gesetzter Fehlermeldung.
     *
     * @return array<string,mixed>|null
     */
    private function validateContent(string $json): ?array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            Flash::error('Vorlageninhalt ist kein gültiges JSON.');

            return null;
        }

        if (!$this->hasOnlySectionsAtTopLevel($decoded)) {
            Flash::error('Eine Vorlage darf auf oberster Ebene nur Abschnitte enthalten.');

            return null;
        }

        try {
            (new SectionGuard())->assertUniqueSectionIds($decoded);
        } catch (InvalidArgumentException $e) {
            Flash::error($this->describeIdProblem($decoded, $e));

            return null;
        }

        return $decoded;
    }

    /**
     * Die Meldung des SectionGuard nennt das Problem, nicht die Stelle.
     * Für jemanden, der gerade eine Vorlage baut, ist die Stelle das
     * Wichtigere — also hier noch einmal durchgehen und sagen, welcher
     * Abschnitt gemeint ist.
     *
     * @param  array<string,mixed>  $decoded
     */
    private function describeIdProblem(array $decoded, InvalidArgumentException $e): string
    {
        $sections = is_array($decoded['content'] ?? null) ? $decoded['content'] : [];
        $sections = array_values(array_filter(
            $sections,
            static fn (mixed $node): bool => is_array($node) && ($node['type'] ?? null) === 'docSection',
        ));

        $seenSectionIds = [];
        $seenGroupIds   = [];

        foreach ($sections as $index => $section) {
            $where     = $this->sectionLabel($index, $section);
            $attrs     = is_array($section['attrs'] ?? null) ? $section['attrs'] : [];
            $sectionId = is_string($attrs['sectionId'] ?? null) ? $attrs['sectionId'] : '';

            if ($sectionId === '' || isset($seenSectionIds[$sectionId])) {
                return $where . ' hat keine gültige Kennung. Entferne ihn und füge ihn neu ein.';
            }
            $seenSectionIds[$sectionId] = true;

            $groupId = (is_string($attrs['templateSectionId'] ?? null) ? $attrs['templateSectionId'] : null)
                ?? $sectionId;
            if (isset($seenGroupIds[$groupId])) {
                return $where . ' teilt sich seine Gruppe mit einem anderen Abschnitt. Ein wiederholbarer '
                    . 'Abschnitt darf in der Vorlage nur einmal stehen — entferne einen der beiden und füge '
                    . 'ihn neu ein.';
            }
            $seenGroupIds[$groupId] = true;

            $fieldIds = [];
            $this->collectFieldIds($section['content'] ?? null, $fieldIds, 0);

            if (in_array('', $fieldIds, true)) {
                return 'In ' . $where . ' hat ein Feld keine Kennung. Entferne es und füge es neu ein.';
            }
            if (count($fieldIds) !== count(array_unique($fieldIds))) {
                return 'In ' . $where . ' kommt dieselbe Feld-Kennung zweimal vor. Entferne eins der '
                    . 'beiden Felder und füge es neu ein.';
            }
        }

        return $e->getMessage();
    }

    /**
     * @param  array<string,mixed>  $section
     */
    private function sectionLabel(int $index, array $section): string
    {
        $attrs = is_array($section['attrs'] ?? null) ? $section['attrs'] : [];
        $title = is_string($attrs['title'] ?? null) ? trim($attrs['title']) : '';

        return 'Abschnitt ' . ($index + 1) . ($title !== '' ? ' „' . $title . '"' : '');
    }

    /**
     * @param  list<string>  $found
     */
    private function collectFieldIds(mixed $content, array &$found, int $depth): void
    {
        if ($depth > 64 || !is_array($content)) {
            return;
        }

        foreach ($content as $node) {
            if (!is_array($node)) {
                continue;
            }
            if (($node['type'] ?? null) === 'docField') {
                $attrs   = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
                $found[] = is_string($attrs['fieldId'] ?? null) ? $attrs['fieldId'] : '';
                continue;
            }
            $this->collectFieldIds($node['content'] ?? null, $found, $depth + 1);
        }
    }

    /**
     * @param  array<string,mixed>  $decoded
     */
    private function hasOnlySectionsAtTopLevel(array $decoded): bool
    {
        if (($decoded['type'] ?? null) !== 'doc') {
            return false;
        }

        $content = $decoded['content'] ?? null;
        if (!is_array($content) || $content === []) {
            return false;
        }

        foreach ($content as $node) {
            if (!is_array($node) || ($node['type'] ?? null) !== 'docSection') {
                return false;
            }
        }

        return true;
    }

    /**
     * Eine frische Vorlage beginnt mit einem freien Abschnitt — sonst
     * stünde der Editor vor einem Dokument, in dem sich nichts schreiben
     * lässt.
     *
     * @return array<string,mixed>
     */
    private function defaultContent(): array
    {
        return [
            'type'    => 'doc',
            'content' => [[
                'type'    => 'docSection',
                'attrs'   => ['mode' => 'free', 'sectionId' => $this->sectionId()],
                'content' => [['type' => 'paragraph']],
            ]],
        ];
    }

    /** UUIDv4 als Abschnitts-Kennung, dieselbe Form wie im Editor. */
    private function sectionId(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex     = bin2hex($data);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
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
