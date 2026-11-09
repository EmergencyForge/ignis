<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Documents\Editor\VariableCatalog;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveLetterheadRequest;
use App\Models\EditorTemplate;
use App\Models\Letterhead;
use App\Session\SessionManager;
use App\Utils\AuditLogger;
use EmergencyForge\Editor\Package;
use EmergencyForge\Http\Exceptions\ValidationException;
use EmergencyForge\Http\Request;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Collection;

/**
 * Briefbögen unter `/settings/documents/letterheads`: Seitenränder, Kopf-
 * und Fußzeilen, die mehrere Vorlagen teilen. Pflegen darf sie, wer die
 * Dokumentvorlagen pflegt.
 *
 * Vorlagen tragen die Bänder ihres Briefbogens aufgelöst in sich. Ändert
 * sich ein Briefbogen, bekommt jede Vorlage, die ihn nutzt, über
 * EditorTemplate::saveContent() den neuen Stand; laufende Entwürfe
 * behalten ihre Fassung. Löschen geht nur, solange keine Vorlage ihn nutzt.
 */
final class LetterheadController extends Controller
{
    private const LIST = 'settings/documents/letterheads';

    /** GET /settings/documents/letterheads */
    public function index(): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'settings/index');

        $letterheads = Letterhead::query()->get()->sortBy('name')->values();
        $usage       = [];
        foreach ($letterheads as $letterhead) {
            $usage[$letterhead->id] = $this->templatesUsing($letterhead)->count();
        }

        $this->renderView('settings/documents/letterheads', [
            'letterheads' => $letterheads,
            'usage'       => $usage,
        ]);
    }

    /** GET /settings/documents/letterheads/create */
    public function createView(): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'settings/index');

        $this->renderView('settings/documents/letterhead-edit', [
            'letterhead' => null,
            'formAction' => BASE_PATH . self::LIST . '/create',
            'usage'      => 0,
        ] + $this->editorData());
    }

    /** POST /settings/documents/letterheads/create */
    public function store(Request $request): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'settings/index');

        $back    = self::LIST . '/create';
        $data    = $this->validated($request, $back);
        $content = $this->decode($data['content']);
        if ($content === null) {
            $this->redirect($back);
        }

        $letterhead             = new Letterhead();
        $letterhead->name       = $data['name'];
        $letterhead->content    = $content;
        $letterhead->created_by = SessionManager::userId();
        $letterhead->save();

        $this->audit('Briefbogen angelegt', $letterhead);
        Flash::success('Briefbogen „' . $letterhead->name . '" wurde angelegt.');
        $this->redirect(self::LIST);
    }

    /** GET /settings/documents/letterheads/{id} */
    public function editView(Request $request, string $id): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'settings/index');

        $letterhead = $this->findOrRedirect((int) $id);

        $this->renderView('settings/documents/letterhead-edit', [
            'letterhead' => $letterhead,
            'formAction' => BASE_PATH . self::LIST . '/' . $letterhead->id,
            'usage'      => $this->templatesUsing($letterhead)->count(),
        ] + $this->editorData());
    }

    /** POST /settings/documents/letterheads/{id} */
    public function update(Request $request, string $id): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'settings/index');

        $letterhead = $this->findOrRedirect((int) $id);
        $back       = self::LIST . '/' . $letterhead->id;
        $data       = $this->validated($request, $back);
        $content    = $this->decode($data['content']);
        if ($content === null) {
            $this->redirect($back);
        }

        $userId = SessionManager::userId();
        $count  = Capsule::connection()->transaction(function () use ($letterhead, $data, $content, $userId): int {
            $letterhead->name    = $data['name'];
            $letterhead->content = $content;
            $letterhead->save();

            $templates = $this->templatesUsing($letterhead);
            foreach ($templates as $template) {
                $template->saveContent($letterhead->applyTo($template->content), $userId);
            }

            return $templates->count();
        });

        $this->audit('Briefbogen gespeichert', $letterhead, $count > 0 ? ', Vorlagen: ' . $count : '');
        Flash::success('Briefbogen „' . $letterhead->name . '" wurde gespeichert'
            . ($count > 0 ? ', ' . $count . ' Vorlage(n) nutzen ihn jetzt.' : '.'));
        $this->redirect(self::LIST);
    }

    /** POST /settings/documents/letterheads/{id}/delete */
    public function destroy(Request $request, string $id): void
    {
        $this->requireAuth();
        $this->ensure('document.manage', null, 'settings/index');

        $letterhead = $this->findOrRedirect((int) $id);

        $count = $this->templatesUsing($letterhead)->count();
        if ($count > 0) {
            Flash::error('Briefbogen „' . $letterhead->name . '" wird noch von ' . $count . ' Vorlage(n) genutzt.');
            $this->redirect(self::LIST);
        }

        $letterhead->delete();
        $this->audit('Briefbogen gelöscht', $letterhead);
        Flash::success('Briefbogen „' . $letterhead->name . '" wurde gelöscht.');
        $this->redirect(self::LIST);
    }

    /**
     * Probetext im Briefbogen-Editor darf Platzhalter zeigen; Bilder lädt
     * er über denselben Weg wie der Vorlageneditor hoch.
     *
     * @return array{variables: array<string,string>, uploadUrl: string}
     */
    private function editorData(): array
    {
        return [
            'variables' => VariableCatalog::catalog(),
            'uploadUrl' => BASE_PATH . 'settings/documents/editor-templates/images',
        ];
    }

    /**
     * @return array{name: string, content: string}
     */
    private function validated(Request $request, string $back): array
    {
        try {
            return SaveLetterheadRequest::validate($request->post);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect($back);
        }
    }

    /**
     * Vorlagen, deren Layout auf diesen Briefbogen zeigt.
     *
     * @return Collection<int, EditorTemplate>
     */
    private function templatesUsing(Letterhead $letterhead): Collection
    {
        // ponytail: filtert alle Vorlagen in PHP, bei wenigen Dutzend egal;
        // bei Tausenden auf JSON_EXTRACT in der Abfrage umstellen.
        return EditorTemplate::query()->get()
            ->filter(static fn (EditorTemplate $t): bool => (int) ($t->content['layout']['letterheadId'] ?? 0) === $letterhead->id)
            ->values()
            ->toBase();
    }

    /**
     * Gespeichert werden nur Layout und Bänder, der Textkörper im Editor ist
     * bloß Probe.
     *
     * @return array<string,mixed>|null
     */
    private function decode(string $json): ?array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !Package::isPackage($decoded)) {
            Flash::error('Der Briefbogen ist kein gültiges Dokumentpaket.');

            return null;
        }
        $package                           = Package::normalize($decoded);
        $package['layout']['letterheadId'] = null;
        $package['body']                   = Package::emptyDoc();

        return $package;
    }

    private function findOrRedirect(int $id): Letterhead
    {
        $letterhead = Letterhead::find($id);
        if ($letterhead === null) {
            Flash::error('Briefbogen wurde nicht gefunden.');
            $this->redirect(self::LIST);
        }

        return $letterhead;
    }

    private function audit(string $action, Letterhead $letterhead, string $extra = ''): void
    {
        (new AuditLogger())->log(
            (int) SessionManager::userId(),
            $action,
            'Name: ' . $letterhead->name . ' [ID: ' . $letterhead->id . ']' . $extra,
            'Dokumente',
            1,
        );
    }
}
