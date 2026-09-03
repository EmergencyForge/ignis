<?php

declare(strict_types=1);

namespace Plugin\KnowledgeBase\Controllers;

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;
use Plugin\KnowledgeBase\KBHelper;
use Plugin\KnowledgeBase\Models\KbCategory;
use Plugin\KnowledgeBase\Models\KbEntry;
use Plugin\KnowledgeBase\Models\KbEntryRelation;

/**
 * LexiconController — frueher als Legacy-Folder `wissensdb/` am Webroot,
 * jetzt als richtiger Controller mit slim Templates unter
 * `templates/lexicon/`.
 *
 * Public-Read ist via `KB_PUBLIC_ACCESS`-Flag steuerbar (siehe
 * AuthMiddleware-Inversion in routes/web.php). Edit-/Manage-Operationen
 * setzen `kb.edit`/`kb.archive`-Permissions voraus.
 */
class LexiconController extends Controller
{
    /**
     * Views liegen im templates/-Verzeichnis des Plugins.
     */
    protected function viewBasePath(): string
    {
        return dirname(__DIR__, 2) . '/templates';
    }

    /**
     * GET /lexicon — Listen-Seite mit Filter (Kategorie, Tag, Suche, Typ).
     */
    public function index(): void
    {
        $this->ensurePublicOrAuth();

        $publicAccess  = defined('KB_PUBLIC_ACCESS') && KB_PUBLIC_ACCESS === true;
        $isLoggedIn    = isset($_SESSION['userid']) && isset($_SESSION['permissions']);

        $typeFilter     = $_GET['type'] ?? 'all';
        $searchQuery    = $_GET['search'] ?? '';
        $categoryFilter = isset($_GET['category']) ? (int) $_GET['category'] : 0;
        $tagFilter      = isset($_GET['tag']) ? (int) $_GET['tag'] : 0;
        $showArchived   = isset($_GET['archived']) && $_GET['archived'] === '1'
            && $isLoggedIn && Permissions::check(['admin', 'kb.archive']);

        $allCategories = Capsule::table('intra_kb_categories')
            ->select(['id', 'parent_id', 'name', 'icon'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $allTags = Capsule::table('intra_kb_tags as t')
            ->leftJoin('intra_kb_entry_tags as et', 't.id', '=', 'et.tag_id')
            ->selectRaw('t.id, t.name, t.color, COUNT(et.entry_id) as cnt')
            ->groupBy('t.id')
            ->orderBy('t.name', 'asc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        // Eintraege via Query Builder aufbauen (deckt alle Filter-Kombinationen)
        $query = Capsule::table('intra_kb_entries as kb')
            ->leftJoin('intra_kb_categories as kc', 'kb.category_id', '=', 'kc.id')
            ->leftJoin('intra_users as creator', 'kb.created_by', '=', 'creator.id')
            ->leftJoin('intra_mitarbeiter as creator_m', 'creator.discord_id', '=', 'creator_m.discordtag')
            ->leftJoin('intra_users as updater', 'kb.updated_by', '=', 'updater.id')
            ->leftJoin('intra_mitarbeiter as updater_m', 'updater.discord_id', '=', 'updater_m.discordtag')
            ->selectRaw('kb.*,
                kc.name as category_name, kc.icon as category_icon,
                COALESCE(creator_m.fullname, creator.fullname) as creator_name,
                COALESCE(updater_m.fullname, updater.fullname) as updater_name');

        if (!$showArchived) {
            $query->where('kb.is_archived', 0);
        }
        if ($typeFilter !== 'all') {
            $query->where('kb.type', $typeFilter);
        }
        if ($categoryFilter > 0) {
            $childIds = [$categoryFilter];
            $children = Capsule::table('intra_kb_categories')
                ->where('parent_id', $categoryFilter)
                ->pluck('id');
            foreach ($children as $childId) {
                $childIds[] = (int) $childId;
            }
            $query->whereIn('kb.category_id', $childIds);
        }
        if ($tagFilter > 0) {
            $query->whereIn('kb.id', function ($sub) use ($tagFilter) {
                $sub->select('entry_id')
                    ->from('intra_kb_entry_tags')
                    ->where('tag_id', $tagFilter);
            });
        }
        if (!empty($searchQuery)) {
            $ftQuery = '';
            foreach (preg_split('/\s+/', trim($searchQuery)) ?: [] as $w) {
                $w = trim($w);
                if (mb_strlen($w) >= 2) {
                    $w = preg_replace('/[+\-><()~*"@]+/', '', $w);
                    if ($w !== '') {
                        $ftQuery .= '+' . $w . '* ';
                    }
                }
            }
            $ftQuery = trim($ftQuery);
            if ($ftQuery !== '') {
                $query->where(function ($q) use ($ftQuery, $searchQuery) {
                    $q->whereRaw(
                        'MATCH(kb.title, kb.subtitle, kb.content) AGAINST(? IN BOOLEAN MODE)',
                        [$ftQuery]
                    )->orWhereRaw(
                        'MATCH(kb.med_wirkstoff, kb.med_wirkstoffgruppe, kb.med_indikationen, kb.med_kontraindikationen, kb.med_dosierung, kb.med_besonderheiten) AGAINST(? IN BOOLEAN MODE)',
                        [$ftQuery]
                    )->orWhereRaw(
                        'MATCH(kb.mass_indikationen, kb.mass_kontraindikationen, kb.mass_durchfuehrung, kb.mass_risiken) AGAINST(? IN BOOLEAN MODE)',
                        [$ftQuery]
                    )->orWhereIn('kb.id', function ($sub) use ($searchQuery) {
                        $sub->select('et.entry_id')
                            ->from('intra_kb_entry_tags as et')
                            ->join('intra_kb_tags as t', 'et.tag_id', '=', 't.id')
                            ->where('t.name', 'LIKE', '%' . $searchQuery . '%');
                    });
                });
            } else {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('kb.title', 'LIKE', '%' . $searchQuery . '%')
                        ->orWhere('kb.subtitle', 'LIKE', '%' . $searchQuery . '%');
                });
            }
        }

        $entries = $query
            ->orderBy('kb.is_pinned', 'desc')
            ->orderBy('kb.updated_at', 'desc')
            ->orderBy('kb.created_at', 'desc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $entryTagsMap = [];
        $entryIds = array_column($entries, 'id');
        if ($entryIds !== []) {
            $tagRows = Capsule::table('intra_kb_entry_tags as et')
                ->join('intra_kb_tags as t', 'et.tag_id', '=', 't.id')
                ->whereIn('et.entry_id', $entryIds)
                ->select(['et.entry_id', 't.name', 't.color'])
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
            foreach ($tagRows as $row) {
                $entryTagsMap[$row['entry_id']][] = $row;
            }
        }

        $this->renderView('lexicon/index', [
            'entries'        => $entries,
            'allCategories'  => $allCategories,
            'allTags'        => $allTags,
            'entryTagsMap'   => $entryTagsMap,
            'typeFilter'     => $typeFilter,
            'searchQuery'    => $searchQuery,
            'categoryFilter' => $categoryFilter,
            'tagFilter'      => $tagFilter,
            'showArchived'   => $showArchived,
            'publicAccess'   => $publicAccess,
            'isLoggedIn'     => $isLoggedIn,
        ]);
    }

    /**
     * GET /lexicon/view?id=X — Detailansicht eines Eintrags.
     */
    public function view(): void
    {
        $this->ensurePublicOrAuth();

        $publicAccess = defined('KB_PUBLIC_ACCESS') && KB_PUBLIC_ACCESS === true;
        $isLoggedIn   = isset($_SESSION['userid']) && isset($_SESSION['permissions']);

        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            Flash::error('Ungültige ID');
            $this->redirect('lexicon/index');
        }

        $entry = Capsule::table('intra_kb_entries as kb')
            ->leftJoin('intra_kb_categories as kc', 'kb.category_id', '=', 'kc.id')
            ->leftJoin('intra_kb_categories as kc_parent', 'kc.parent_id', '=', 'kc_parent.id')
            ->leftJoin('intra_users as creator', 'kb.created_by', '=', 'creator.id')
            ->leftJoin('intra_mitarbeiter as creator_m', 'creator.discord_id', '=', 'creator_m.discordtag')
            ->leftJoin('intra_users as updater', 'kb.updated_by', '=', 'updater.id')
            ->leftJoin('intra_mitarbeiter as updater_m', 'updater.discord_id', '=', 'updater_m.discordtag')
            ->where('kb.id', $id)
            ->selectRaw('kb.*,
                   kc.name as category_name, kc.icon as category_icon,
                   kc_parent.name as parent_category_name, kc_parent.icon as parent_category_icon,
                   COALESCE(creator_m.fullname, creator.fullname) as creator_name,
                   COALESCE(updater_m.fullname, updater.fullname) as updater_name')
            ->first();
        $entry = $entry ? (array) $entry : null;

        if (!$entry) {
            Flash::error('Eintrag nicht gefunden');
            $this->redirect('lexicon/index');
        }

        if ($entry['is_archived'] && (!$isLoggedIn || !Permissions::check(['admin', 'kb.archive']))) {
            Flash::error('Dieser Eintrag ist archiviert');
            $this->redirect('lexicon/index');
        }

        $entryTags = Capsule::table('intra_kb_entry_tags as et')
            ->join('intra_kb_tags as t', 'et.tag_id', '=', 't.id')
            ->where('et.entry_id', $id)
            ->select(['t.id', 't.name', 't.color'])
            ->orderBy('t.name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $relatedEntries = array_map(
            fn ($row) => (array) $row,
            Capsule::connection()->select("
                SELECT kb.id, kb.title, kb.subtitle, kb.type, kb.competency_level,
                       kc.name as category_name, kc.icon as category_icon
                FROM intra_kb_entry_relations r
                JOIN intra_kb_entries kb ON kb.id = CASE WHEN r.entry_id = ? THEN r.related_entry_id ELSE r.entry_id END
                LEFT JOIN intra_kb_categories kc ON kb.category_id = kc.id
                WHERE (r.entry_id = ? OR r.related_entry_id = ?)
                AND kb.is_archived = 0
                ORDER BY kb.title ASC
            ", [$id, $id, $id])
        );

        $competency = KBHelper::getCompetencyInfo($entry['competency_level']);

        $this->renderView('lexicon/view', [
            'entry'          => $entry,
            'entryTags'      => $entryTags,
            'relatedEntries' => $relatedEntries,
            'competency'     => $competency,
            'publicAccess'   => $publicAccess,
            'isLoggedIn'     => $isLoggedIn,
        ]);
    }

    /**
     * GET /lexicon/create — Form fuer neuen Eintrag.
     * GET /lexicon/edit?id=X — Form fuer Edit (gleicher Controller, $isEdit-Flag).
     * POST handelt beide Faelle (entscheidet anhand Query-Param `id`).
     */
    public function create(): void
    {
        $this->renderForm(false);
    }

    public function edit(): void
    {
        $this->renderForm(true);
    }

    /**
     * Combined create/edit-Form-Handler. POST → save + redirect zu view.
     * GET → renderView('lexicon/form', ...).
     */
    private function renderForm(bool $isEdit): void
    {
        $this->requireAuth();
        if (!Permissions::check(['admin', 'kb.edit'])) {
            Flash::error('Keine Berechtigung');
            $this->redirect('lexicon/index');
        }

        $allCategories = Capsule::table('intra_kb_categories')
            ->select(['id', 'parent_id', 'name', 'icon'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
        $allTags = Capsule::table('intra_kb_tags')
            ->select(['id', 'name', 'color'])
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $editId         = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $entry          = null;
        $updaterName    = null;
        $entryTags      = [];
        $entryRelations = [];

        if ($isEdit && $editId) {
            $entry = Capsule::table('intra_kb_entries as kb')
                ->leftJoin('intra_users as u', 'kb.updated_by', '=', 'u.id')
                ->where('kb.id', $editId)
                ->selectRaw('kb.*, u.fullname as updater_name')
                ->first();
            $entry = $entry ? (array) $entry : null;
            if (!$entry) {
                Flash::error('Eintrag nicht gefunden');
                $this->redirect('lexicon/index');
            }
            $updaterName = $entry['updater_name'] ?? null;

            $entryTags = Capsule::table('intra_kb_entry_tags')
                ->where('entry_id', $editId)
                ->pluck('tag_id')
                ->all();

            $entryRelations = array_map(
                fn ($row) => (array) $row,
                Capsule::connection()->select("
                    SELECT kb.id, kb.title, kb.type
                    FROM intra_kb_entry_relations r
                    JOIN intra_kb_entries kb ON kb.id = CASE WHEN r.entry_id = ? THEN r.related_entry_id ELSE r.entry_id END
                    WHERE (r.entry_id = ? OR r.related_entry_id = ?)
                    ORDER BY kb.title ASC
                ", [$editId, $editId, $editId])
            );
        }

        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            [$errors, $newId] = $this->saveEntry($isEdit, $editId);
            if ($errors === [] && $newId !== null) {
                Flash::success($isEdit ? 'Eintrag erfolgreich aktualisiert' : 'Eintrag erfolgreich erstellt');
                $this->redirect('lexicon/view?id=' . $newId);
            }
        }

        // Prefill für GET / Re-Render bei Validation-Fehler
        $formData = $entry ?? [
            'type'                    => $_POST['type'] ?? 'general',
            'category_id'             => $_POST['category_id'] ?? '',
            'title'                   => $_POST['title'] ?? '',
            'subtitle'                => $_POST['subtitle'] ?? '',
            'competency_level'        => $_POST['competency_level'] ?? '',
            'content'                 => $_POST['content'] ?? '',
            'med_wirkstoff'           => $_POST['med_wirkstoff'] ?? '',
            'med_wirkstoffgruppe'     => $_POST['med_wirkstoffgruppe'] ?? '',
            'med_wirkmechanismus'     => $_POST['med_wirkmechanismus'] ?? '',
            'med_indikationen'        => $_POST['med_indikationen'] ?? '',
            'med_kontraindikationen'  => $_POST['med_kontraindikationen'] ?? '',
            'med_uaw'                 => $_POST['med_uaw'] ?? '',
            'med_dosierung'           => $_POST['med_dosierung'] ?? '',
            'med_besonderheiten'      => $_POST['med_besonderheiten'] ?? '',
            'mass_wirkprinzip'        => $_POST['mass_wirkprinzip'] ?? '',
            'mass_indikationen'       => $_POST['mass_indikationen'] ?? '',
            'mass_kontraindikationen' => $_POST['mass_kontraindikationen'] ?? '',
            'mass_risiken'            => $_POST['mass_risiken'] ?? '',
            'mass_alternativen'       => $_POST['mass_alternativen'] ?? '',
            'mass_durchfuehrung'      => $_POST['mass_durchfuehrung'] ?? '',
        ];

        $this->renderView('lexicon/form', [
            'isEdit'         => $isEdit,
            'editId'         => $editId,
            'entry'          => $entry,
            'updaterName'    => $updaterName,
            'allCategories'  => $allCategories,
            'allTags'        => $allTags,
            'entryTags'      => $entryTags,
            'entryRelations' => $entryRelations,
            'formData'       => $formData,
            'errors'         => $errors,
        ]);
    }

    /**
     * Speichert den Eintrag (Create oder Update). Gibt [errors[], newId|null].
     * @return array{0: array<int,string>, 1: int|null}
     */
    private function saveEntry(bool $isEdit, ?int $editId): array
    {
        $type             = $_POST['type'] ?? 'general';
        $title            = trim($_POST['title'] ?? '');
        $subtitle         = trim($_POST['subtitle'] ?? '');
        $competency_level = !empty($_POST['competency_level']) ? $_POST['competency_level'] : null;
        $content          = $_POST['content'] ?? '';
        $category_id      = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
        $selectedTags     = $_POST['tags'] ?? [];
        $selectedRels     = $_POST['relations'] ?? [];

        $fields = [
            'med_wirkstoff', 'med_wirkstoffgruppe', 'med_wirkmechanismus',
            'med_indikationen', 'med_kontraindikationen', 'med_uaw',
            'med_dosierung', 'med_besonderheiten',
            'mass_wirkprinzip', 'mass_indikationen', 'mass_kontraindikationen',
            'mass_risiken', 'mass_alternativen', 'mass_durchfuehrung',
        ];
        $detail = [];
        foreach ($fields as $f) {
            $detail[$f] = trim($_POST[$f] ?? '');
        }

        $errors = [];
        if ($title === '') {
            $errors[] = 'Titel ist erforderlich';
        }
        if (!in_array($type, ['general', 'medication', 'measure'], true)) {
            $errors[] = 'Ungültiger Typ';
        }
        if ($errors !== []) {
            return [$errors, null];
        }

        $is_pinned   = isset($_POST['is_pinned']) ? 1 : 0;
        $hide_editor = isset($_POST['hide_editor']) ? 1 : 0;

        try {
            if ($isEdit && $editId) {
                KbEntry::where('id', $editId)->update(array_merge($detail, [
                    'type'             => $type,
                    'category_id'      => $category_id,
                    'title'            => $title,
                    'subtitle'         => $subtitle,
                    'competency_level' => $competency_level,
                    'content'          => $content,
                    'is_pinned'        => $is_pinned,
                    'hide_editor'      => $hide_editor,
                    'updated_by'       => $_SESSION['userid'],
                    'updated_at'       => Capsule::raw('NOW()'),
                ]));

                Capsule::table('intra_kb_entry_tags')->where('entry_id', $editId)->delete();
                $this->insertTags($editId, $selectedTags);
                KbEntryRelation::where('entry_id', $editId)
                    ->orWhere('related_entry_id', $editId)
                    ->delete();
                $this->insertRelations($editId, $selectedRels);

                return [[], $editId];
            }

            $newEntry = KbEntry::create(array_merge($detail, [
                'type'             => $type,
                'category_id'      => $category_id,
                'title'            => $title,
                'subtitle'         => $subtitle,
                'competency_level' => $competency_level,
                'content'          => $content,
                'created_by'       => $_SESSION['userid'],
            ]));
            $newId = (int) $newEntry->id;
            $this->insertTags($newId, $selectedTags);
            $this->insertRelations($newId, $selectedRels);

            return [[], $newId];
        } catch (PDOException $e) {
            return [['Datenbankfehler: ' . $e->getMessage()], null];
        }
    }

    /** @param array<int,int|string> $tagIds */
    private function insertTags(int $entryId, array $tagIds): void
    {
        if ($tagIds === []) {
            return;
        }
        $rows = [];
        foreach ($tagIds as $tagId) {
            $rows[] = ['entry_id' => $entryId, 'tag_id' => (int) $tagId];
        }
        Capsule::table('intra_kb_entry_tags')->insertOrIgnore($rows);
    }

    /** @param array<int,int|string> $relIds */
    private function insertRelations(int $entryId, array $relIds): void
    {
        if ($relIds === []) {
            return;
        }
        $rows = [];
        foreach ($relIds as $relId) {
            $relId = (int) $relId;
            if ($relId === $entryId || $relId <= 0) {
                continue;
            }
            $rows[] = [
                'entry_id'         => min($entryId, $relId),
                'related_entry_id' => max($entryId, $relId),
            ];
        }
        if ($rows !== []) {
            Capsule::table('intra_kb_entry_relations')->insertOrIgnore($rows);
        }
    }

    /**
     * POST /lexicon/archive — archive | restore.
     */
    public function archive(): void
    {
        $this->requireAuth();
        if (!Permissions::check(['admin', 'kb.archive'])) {
            Flash::error('Keine Berechtigung');
            $this->redirect('lexicon/index');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('lexicon/index');
        }

        $id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $action = $_POST['action'] ?? '';
        if (!$id || !in_array($action, ['archive', 'restore'], true)) {
            Flash::error('Ungültige Anfrage');
            $this->redirect('lexicon/index');
        }

        try {
            $isArchived = $action === 'archive' ? 1 : 0;
            $affected = KbEntry::where('id', $id)->update([
                'is_archived' => $isArchived,
                'updated_by'  => $_SESSION['userid'],
                'updated_at'  => Capsule::raw('NOW()'),
            ]);
            if ($affected > 0) {
                Flash::success($action === 'archive' ? 'Eintrag archiviert' : 'Eintrag wiederhergestellt');
            } else {
                Flash::error('Eintrag nicht gefunden');
            }
        } catch (PDOException $e) {
            Flash::error('Datenbankfehler: ' . $e->getMessage());
        }
        $this->redirect('lexicon/view?id=' . $id);
    }

    /**
     * POST /lexicon/pin — pin | unpin.
     */
    public function pin(): void
    {
        $this->requireAuth();
        if (!Permissions::check(['admin', 'kb.edit'])) {
            Flash::error('Keine Berechtigung');
            $this->redirect('lexicon/index');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('lexicon/index');
        }

        $id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $action = $_POST['action'] ?? '';
        if (!$id || !in_array($action, ['pin', 'unpin'], true)) {
            Flash::error('Ungültige Anfrage');
            $this->redirect('lexicon/index');
        }

        try {
            $isPinned = $action === 'pin' ? 1 : 0;
            $affected = KbEntry::where('id', $id)->update([
                'is_pinned'  => $isPinned,
                'updated_by' => $_SESSION['userid'],
                'updated_at' => Capsule::raw('NOW()'),
            ]);
            if ($affected > 0) {
                Flash::success($action === 'pin' ? 'Eintrag angepinnt' : 'Eintrag gelöst');
            } else {
                Flash::error('Eintrag nicht gefunden');
            }
        } catch (PDOException $e) {
            Flash::error('Datenbankfehler: ' . $e->getMessage());
        }

        $referer = preg_replace('/[\r\n]+/', '', (string) ($_SERVER['HTTP_REFERER'] ?? ''));
        if ($referer !== '' && BASE_PATH !== '' && strpos($referer, (string) BASE_PATH) !== false) {
            header('Location: ' . $referer);
            exit;
        }
        $this->redirect('lexicon/index');
    }

    /**
     * POST /lexicon/toggle-editor — Admin-only Toggle des hide_editor-Flags.
     */
    public function toggleEditor(): void
    {
        $this->requireAuth();
        if (!Permissions::check(['admin'])) {
            Flash::error('Keine Berechtigung');
            $this->redirect('lexicon/index');
        }
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            Flash::error('Ungültige ID');
            $this->redirect('lexicon/index');
        }

        try {
            KbEntry::where('id', $id)->update([
                'hide_editor' => Capsule::raw('NOT hide_editor'),
            ]);
            $hideEditor = Capsule::table('intra_kb_entries')->where('id', $id)->value('hide_editor');
            Flash::success(!empty($hideEditor)
                ? 'Bearbeiternamen werden für diesen Eintrag ausgeblendet'
                : 'Bearbeiternamen werden für diesen Eintrag angezeigt');
        } catch (PDOException $e) {
            Flash::error('Fehler beim Aktualisieren: ' . $e->getMessage());
        }
        $this->redirect('lexicon/view?id=' . $id);
    }

    /**
     * GET /lexicon/manage-taxonomy — Kategorien + Tags verwalten (Admin).
     */
    public function manageTaxonomy(): void
    {
        $this->requireAuth();
        if (!Permissions::check(['admin', 'kb.edit'])) {
            Flash::set('error', 'no-permissions');
            $this->redirect('lexicon/index');
        }

        $categories = Capsule::table('intra_kb_categories as kc')
            ->leftJoin('intra_kb_categories as kc_parent', 'kc.parent_id', '=', 'kc_parent.id')
            ->selectRaw('kc.*, kc_parent.name as parent_name,
                    (SELECT COUNT(*) FROM intra_kb_entries WHERE category_id = kc.id) as entry_count')
            ->orderBy('kc.sort_order', 'asc')
            ->orderBy('kc.name', 'asc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $tags = Capsule::table('intra_kb_tags as t')
            ->selectRaw('t.*, (SELECT COUNT(*) FROM intra_kb_entry_tags WHERE tag_id = t.id) as usage_count')
            ->orderBy('t.name', 'asc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $this->renderView('lexicon/manage-taxonomy', [
            'categories' => $categories,
            'tags'       => $tags,
        ]);
    }

    /**
     * Auth-Check fuer public-readable Pages: bei aktivem KB_PUBLIC_ACCESS-Flag
     * passieren auch nicht eingeloggte Besucher; sonst Redirect zu Login.
     */
    private function ensurePublicOrAuth(): void
    {
        $publicAccess = defined('KB_PUBLIC_ACCESS') && KB_PUBLIC_ACCESS === true;
        $isLoggedIn   = isset($_SESSION['userid']) && isset($_SESSION['permissions']);
        if (!$publicAccess && !$isLoggedIn) {
            \App\Session\SessionManager::setRedirectFromRequest();
            $this->redirect('login');
        }
    }
}
