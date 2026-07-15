<?php

declare(strict_types=1);

namespace Plugin\KnowledgeBase\Controllers;

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use Plugin\KnowledgeBase\KBHelper;
use PDO;
use PDOException;

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

        $allCategories = $this->pdo->query(
            "SELECT id, parent_id, name, icon FROM intra_kb_categories
             ORDER BY sort_order ASC, name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $allTags = $this->pdo->query(
            "SELECT t.id, t.name, t.color, COUNT(et.entry_id) as cnt
             FROM intra_kb_tags t
             LEFT JOIN intra_kb_entry_tags et ON t.id = et.tag_id
             GROUP BY t.id ORDER BY t.name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Eintraege via dynamic SQL aufbauen (deckt alle Filter-Kombinationen)
        $sql = "SELECT kb.*,
                kc.name as category_name, kc.icon as category_icon,
                COALESCE(creator_m.fullname, creator.fullname) as creator_name,
                COALESCE(updater_m.fullname, updater.fullname) as updater_name
                FROM intra_kb_entries kb
                LEFT JOIN intra_kb_categories kc ON kb.category_id = kc.id
                LEFT JOIN intra_users creator ON kb.created_by = creator.id
                LEFT JOIN intra_mitarbeiter creator_m ON creator.discord_id = creator_m.discordtag
                LEFT JOIN intra_users updater ON kb.updated_by = updater.id
                LEFT JOIN intra_mitarbeiter updater_m ON updater.discord_id = updater_m.discordtag
                WHERE 1=1";
        $params = [];

        if (!$showArchived) {
            $sql .= " AND kb.is_archived = 0";
        }
        if ($typeFilter !== 'all') {
            $sql .= " AND kb.type = :type";
            $params['type'] = $typeFilter;
        }
        if ($categoryFilter > 0) {
            $childIds = [$categoryFilter];
            $childStmt = $this->pdo->prepare("SELECT id FROM intra_kb_categories WHERE parent_id = :pid");
            $childStmt->execute(['pid' => $categoryFilter]);
            while ($childId = $childStmt->fetchColumn()) {
                $childIds[] = (int) $childId;
            }
            $placeholders = implode(',', array_fill(0, count($childIds), '?'));
            $sql .= " AND kb.category_id IN ($placeholders)";
            foreach ($childIds as $cid) {
                $params[] = $cid;
            }
        }
        if ($tagFilter > 0) {
            $sql .= " AND kb.id IN (SELECT entry_id FROM intra_kb_entry_tags WHERE tag_id = :tag_id)";
            $params['tag_id'] = $tagFilter;
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
                $sql .= " AND (
                    MATCH(kb.title, kb.subtitle, kb.content) AGAINST(:ft_main IN BOOLEAN MODE)
                    OR MATCH(kb.med_wirkstoff, kb.med_wirkstoffgruppe, kb.med_indikationen, kb.med_kontraindikationen, kb.med_dosierung, kb.med_besonderheiten) AGAINST(:ft_med IN BOOLEAN MODE)
                    OR MATCH(kb.mass_indikationen, kb.mass_kontraindikationen, kb.mass_durchfuehrung, kb.mass_risiken) AGAINST(:ft_mass IN BOOLEAN MODE)
                    OR kb.id IN (SELECT et.entry_id FROM intra_kb_entry_tags et JOIN intra_kb_tags t ON et.tag_id = t.id WHERE t.name LIKE :ft_tag)
                )";
                $params['ft_main'] = $ftQuery;
                $params['ft_med']  = $ftQuery;
                $params['ft_mass'] = $ftQuery;
                $params['ft_tag']  = '%' . $searchQuery . '%';
            } else {
                $sql .= " AND (kb.title LIKE :search1 OR kb.subtitle LIKE :search2)";
                $params['search1'] = '%' . $searchQuery . '%';
                $params['search2'] = '%' . $searchQuery . '%';
            }
        }
        $sql .= " ORDER BY kb.is_pinned DESC, kb.updated_at DESC, kb.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $entryTagsMap = [];
        $entryIds = array_column($entries, 'id');
        if ($entryIds !== []) {
            $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
            $tagMapStmt = $this->pdo->prepare(
                "SELECT et.entry_id, t.name, t.color
                 FROM intra_kb_entry_tags et
                 JOIN intra_kb_tags t ON et.tag_id = t.id
                 WHERE et.entry_id IN ($placeholders)"
            );
            $tagMapStmt->execute($entryIds);
            foreach ($tagMapStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
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

        $stmt = $this->pdo->prepare("
            SELECT kb.*,
                   kc.name as category_name, kc.icon as category_icon,
                   kc_parent.name as parent_category_name, kc_parent.icon as parent_category_icon,
                   COALESCE(creator_m.fullname, creator.fullname) as creator_name,
                   COALESCE(updater_m.fullname, updater.fullname) as updater_name
            FROM intra_kb_entries kb
            LEFT JOIN intra_kb_categories kc ON kb.category_id = kc.id
            LEFT JOIN intra_kb_categories kc_parent ON kc.parent_id = kc_parent.id
            LEFT JOIN intra_users creator ON kb.created_by = creator.id
            LEFT JOIN intra_mitarbeiter creator_m ON creator.discord_id = creator_m.discordtag
            LEFT JOIN intra_users updater ON kb.updated_by = updater.id
            LEFT JOIN intra_mitarbeiter updater_m ON updater.discord_id = updater_m.discordtag
            WHERE kb.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) {
            Flash::error('Eintrag nicht gefunden');
            $this->redirect('lexicon/index');
        }

        if ($entry['is_archived'] && (!$isLoggedIn || !Permissions::check(['admin', 'kb.archive']))) {
            Flash::error('Dieser Eintrag ist archiviert');
            $this->redirect('lexicon/index');
        }

        $tagStmt = $this->pdo->prepare(
            "SELECT t.id, t.name, t.color FROM intra_kb_entry_tags et
             JOIN intra_kb_tags t ON et.tag_id = t.id
             WHERE et.entry_id = :id ORDER BY t.name"
        );
        $tagStmt->execute(['id' => $id]);
        $entryTags = $tagStmt->fetchAll(PDO::FETCH_ASSOC);

        $relStmt = $this->pdo->prepare("
            SELECT kb.id, kb.title, kb.subtitle, kb.type, kb.competency_level,
                   kc.name as category_name, kc.icon as category_icon
            FROM intra_kb_entry_relations r
            JOIN intra_kb_entries kb ON kb.id = CASE WHEN r.entry_id = :id1 THEN r.related_entry_id ELSE r.entry_id END
            LEFT JOIN intra_kb_categories kc ON kb.category_id = kc.id
            WHERE (r.entry_id = :id2 OR r.related_entry_id = :id3)
            AND kb.is_archived = 0
            ORDER BY kb.title ASC
        ");
        $relStmt->execute(['id1' => $id, 'id2' => $id, 'id3' => $id]);
        $relatedEntries = $relStmt->fetchAll(PDO::FETCH_ASSOC);

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

        $allCategories = $this->pdo->query(
            "SELECT id, parent_id, name, icon FROM intra_kb_categories ORDER BY sort_order ASC, name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $allTags = $this->pdo->query(
            "SELECT id, name, color FROM intra_kb_tags ORDER BY name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $editId         = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $entry          = null;
        $updaterName    = null;
        $entryTags      = [];
        $entryRelations = [];

        if ($isEdit && $editId) {
            $stmt = $this->pdo->prepare("
                SELECT kb.*, u.fullname as updater_name
                FROM intra_kb_entries kb
                LEFT JOIN intra_users u ON kb.updated_by = u.id
                WHERE kb.id = :id
            ");
            $stmt->execute(['id' => $editId]);
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$entry) {
                Flash::error('Eintrag nicht gefunden');
                $this->redirect('lexicon/index');
            }
            $updaterName = $entry['updater_name'] ?? null;

            $tagStmt = $this->pdo->prepare("SELECT tag_id FROM intra_kb_entry_tags WHERE entry_id = :id");
            $tagStmt->execute(['id' => $editId]);
            $entryTags = $tagStmt->fetchAll(PDO::FETCH_COLUMN);

            $relStmt = $this->pdo->prepare("
                SELECT kb.id, kb.title, kb.type
                FROM intra_kb_entry_relations r
                JOIN intra_kb_entries kb ON kb.id = CASE WHEN r.entry_id = :id1 THEN r.related_entry_id ELSE r.entry_id END
                WHERE (r.entry_id = :id2 OR r.related_entry_id = :id3)
                ORDER BY kb.title ASC
            ");
            $relStmt->execute(['id1' => $editId, 'id2' => $editId, 'id3' => $editId]);
            $entryRelations = $relStmt->fetchAll(PDO::FETCH_ASSOC);
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
                $sql = "UPDATE intra_kb_entries SET
                        type = :type, category_id = :category_id, title = :title, subtitle = :subtitle,
                        competency_level = :competency_level, content = :content,
                        med_wirkstoff = :med_wirkstoff, med_wirkstoffgruppe = :med_wirkstoffgruppe,
                        med_wirkmechanismus = :med_wirkmechanismus, med_indikationen = :med_indikationen,
                        med_kontraindikationen = :med_kontraindikationen, med_uaw = :med_uaw,
                        med_dosierung = :med_dosierung, med_besonderheiten = :med_besonderheiten,
                        mass_wirkprinzip = :mass_wirkprinzip, mass_indikationen = :mass_indikationen,
                        mass_kontraindikationen = :mass_kontraindikationen, mass_risiken = :mass_risiken,
                        mass_alternativen = :mass_alternativen, mass_durchfuehrung = :mass_durchfuehrung,
                        is_pinned = :is_pinned, hide_editor = :hide_editor,
                        updated_by = :user_id, updated_at = NOW()
                        WHERE id = :id";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(array_merge($detail, [
                    'type' => $type, 'category_id' => $category_id, 'title' => $title,
                    'subtitle' => $subtitle, 'competency_level' => $competency_level, 'content' => $content,
                    'is_pinned' => $is_pinned, 'hide_editor' => $hide_editor,
                    'user_id' => $_SESSION['userid'], 'id' => $editId,
                ]));

                $this->pdo->prepare("DELETE FROM intra_kb_entry_tags WHERE entry_id = :id")->execute(['id' => $editId]);
                $this->insertTags($editId, $selectedTags);
                $this->pdo->prepare("DELETE FROM intra_kb_entry_relations WHERE entry_id = :id1 OR related_entry_id = :id2")
                    ->execute(['id1' => $editId, 'id2' => $editId]);
                $this->insertRelations($editId, $selectedRels);

                return [[], $editId];
            }

            $sql = "INSERT INTO intra_kb_entries (
                    type, category_id, title, subtitle, competency_level, content,
                    med_wirkstoff, med_wirkstoffgruppe, med_wirkmechanismus,
                    med_indikationen, med_kontraindikationen, med_uaw, med_dosierung, med_besonderheiten,
                    mass_wirkprinzip, mass_indikationen, mass_kontraindikationen,
                    mass_risiken, mass_alternativen, mass_durchfuehrung,
                    created_by
                ) VALUES (
                    :type, :category_id, :title, :subtitle, :competency_level, :content,
                    :med_wirkstoff, :med_wirkstoffgruppe, :med_wirkmechanismus,
                    :med_indikationen, :med_kontraindikationen, :med_uaw, :med_dosierung, :med_besonderheiten,
                    :mass_wirkprinzip, :mass_indikationen, :mass_kontraindikationen,
                    :mass_risiken, :mass_alternativen, :mass_durchfuehrung,
                    :user_id
                )";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($detail, [
                'type' => $type, 'category_id' => $category_id, 'title' => $title,
                'subtitle' => $subtitle, 'competency_level' => $competency_level, 'content' => $content,
                'user_id' => $_SESSION['userid'],
            ]));
            $newId = (int) $this->pdo->lastInsertId();
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
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO intra_kb_entry_tags (entry_id, tag_id) VALUES (:entry_id, :tag_id)");
        foreach ($tagIds as $tagId) {
            $stmt->execute(['entry_id' => $entryId, 'tag_id' => (int) $tagId]);
        }
    }

    /** @param array<int,int|string> $relIds */
    private function insertRelations(int $entryId, array $relIds): void
    {
        if ($relIds === []) {
            return;
        }
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO intra_kb_entry_relations (entry_id, related_entry_id) VALUES (:eid, :rid)");
        foreach ($relIds as $relId) {
            $relId = (int) $relId;
            if ($relId === $entryId || $relId <= 0) {
                continue;
            }
            $a = min($entryId, $relId);
            $b = max($entryId, $relId);
            $stmt->execute(['eid' => $a, 'rid' => $b]);
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
            $stmt = $this->pdo->prepare(
                "UPDATE intra_kb_entries SET is_archived = :archived, updated_by = :user_id, updated_at = NOW()
                 WHERE id = :id"
            );
            $stmt->execute(['archived' => $isArchived, 'user_id' => $_SESSION['userid'], 'id' => $id]);
            if ($stmt->rowCount() > 0) {
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
            $stmt = $this->pdo->prepare(
                "UPDATE intra_kb_entries SET is_pinned = :pinned, updated_by = :user_id, updated_at = NOW()
                 WHERE id = :id"
            );
            $stmt->execute(['pinned' => $isPinned, 'user_id' => $_SESSION['userid'], 'id' => $id]);
            if ($stmt->rowCount() > 0) {
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
            $this->pdo->prepare("UPDATE intra_kb_entries SET hide_editor = NOT hide_editor WHERE id = :id")
                ->execute(['id' => $id]);
            $row = $this->pdo->prepare("SELECT hide_editor FROM intra_kb_entries WHERE id = :id");
            $row->execute(['id' => $id]);
            $entry = $row->fetch(PDO::FETCH_ASSOC);
            Flash::success(!empty($entry['hide_editor'])
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

        $categories = $this->pdo->query(
            "SELECT kc.*, kc_parent.name as parent_name,
                    (SELECT COUNT(*) FROM intra_kb_entries WHERE category_id = kc.id) as entry_count
             FROM intra_kb_categories kc
             LEFT JOIN intra_kb_categories kc_parent ON kc.parent_id = kc_parent.id
             ORDER BY kc.sort_order ASC, kc.name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $tags = $this->pdo->query(
            "SELECT t.*, (SELECT COUNT(*) FROM intra_kb_entry_tags WHERE tag_id = t.id) as usage_count
             FROM intra_kb_tags t
             ORDER BY t.name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

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
