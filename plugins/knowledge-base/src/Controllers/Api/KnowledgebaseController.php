<?php

declare(strict_types=1);

namespace Plugin\KnowledgeBase\Controllers\Api;

use App\Auth\Gate;
use App\Http\Request;
use App\Http\Response;
use Plugin\KnowledgeBase\KBHelper;
use App\Logging\Logger;
use PDO;
use PDOException;

/**
 * Knowledgebase-Endpoints: Kategorien, Tags, Suche.
 *
 * Die GET-Operationen (list-categories, list-tags, search) sind für
 * Lesezugriff auch unter `KB_PUBLIC_ACCESS=true` erreichbar. Write-
 * Operationen (POST/DELETE für Kategorien/Tags) erfordern immer
 * `admin` oder `kb.edit`.
 */
final class KnowledgebaseController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // ── Categories ────────────────────────────────────────────────────

    /**
     * GET /api/knowledgebase/categories
     */
    public function listCategories(Request $request): Response
    {
        $stmt = $this->pdo->query("SELECT * FROM intra_kb_categories ORDER BY sort_order ASC, name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Baum-Struktur aufbauen
        $tree = [];
        $map  = [];
        foreach ($categories as &$cat) {
            $cat['children'] = [];
            $map[$cat['id']] = &$cat;
        }
        unset($cat);

        foreach ($categories as &$cat) {
            if ($cat['parent_id'] && isset($map[$cat['parent_id']])) {
                $map[$cat['parent_id']]['children'][] = &$cat;
            } else {
                $tree[] = &$cat;
            }
        }
        unset($cat);

        return Response::json(['flat' => $categories, 'tree' => $tree]);
    }

    /**
     * POST /api/knowledgebase/categories
     */
    public function saveCategory(Request $request): Response
    {
        if (!$this->requireAdmin()) {
            return Response::json(['error' => 'Keine Berechtigung'], 403);
        }

        $input = $request->json();
        if (!is_array($input) || empty($input['name'])) {
            return Response::json(['error' => 'Name ist erforderlich'], 400);
        }

        $name      = trim((string) $input['name']);
        $slug      = preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower($name)));
        $parentId  = !empty($input['parent_id']) ? (int) $input['parent_id'] : null;
        $icon      = !empty($input['icon']) ? trim((string) $input['icon']) : null;
        $sortOrder = (int) ($input['sort_order'] ?? 0);

        if (!empty($input['id'])) {
            $this->pdo->prepare(
                "UPDATE intra_kb_categories SET name = :name, slug = :slug, parent_id = :parent_id, icon = :icon, sort_order = :sort_order WHERE id = :id"
            )->execute([
                'id'         => (int) $input['id'],
                'name'       => $name,
                'slug'       => $slug,
                'parent_id'  => $parentId,
                'icon'       => $icon,
                'sort_order' => $sortOrder,
            ]);
            return Response::json(['success' => true, 'id' => (int) $input['id']]);
        }

        $this->pdo->prepare(
            "INSERT INTO intra_kb_categories (name, slug, parent_id, icon, sort_order) VALUES (:name, :slug, :parent_id, :icon, :sort_order)"
        )->execute([
            'name'       => $name,
            'slug'       => $slug,
            'parent_id'  => $parentId,
            'icon'       => $icon,
            'sort_order' => $sortOrder,
        ]);
        return Response::json(['success' => true, 'id' => (int) $this->pdo->lastInsertId()]);
    }

    /**
     * DELETE /api/knowledgebase/categories?id=N
     */
    public function deleteCategory(Request $request): Response
    {
        if (!$this->requireAdmin()) {
            return Response::json(['error' => 'Keine Berechtigung'], 403);
        }

        $id = (int) ($request->query['id'] ?? 0);
        if (!$id) {
            return Response::json(['error' => 'Keine ID'], 400);
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM intra_kb_entries WHERE category_id = :id");
        $stmt->execute(['id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            return Response::json(['error' => 'Kategorie wird von Einträgen verwendet.'], 409);
        }

        $this->pdo->prepare("UPDATE intra_kb_categories SET parent_id = NULL WHERE parent_id = :id")
            ->execute(['id' => $id]);
        $this->pdo->prepare("DELETE FROM intra_kb_categories WHERE id = :id")
            ->execute(['id' => $id]);

        return Response::json(['success' => true]);
    }

    // ── Tags ──────────────────────────────────────────────────────────

    /**
     * GET /api/knowledgebase/tags
     */
    public function listTags(Request $request): Response
    {
        $stmt = $this->pdo->query(
            "SELECT t.*, (SELECT COUNT(*) FROM intra_kb_entry_tags WHERE tag_id = t.id) as usage_count
             FROM intra_kb_tags t ORDER BY t.name ASC"
        );
        return Response::json($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * POST /api/knowledgebase/tags
     */
    public function saveTag(Request $request): Response
    {
        if (!$this->requireAdmin()) {
            return Response::json(['error' => 'Keine Berechtigung'], 403);
        }

        $input = $request->json();
        if (!is_array($input) || empty($input['name'])) {
            return Response::json(['error' => 'Name ist erforderlich'], 400);
        }

        $name  = trim((string) $input['name']);
        $color = $input['color'] ?? '#6c757d';

        if (!empty($input['id'])) {
            $this->pdo->prepare("UPDATE intra_kb_tags SET name = :name, color = :color WHERE id = :id")
                ->execute(['id' => (int) $input['id'], 'name' => $name, 'color' => $color]);
            return Response::json(['success' => true, 'id' => (int) $input['id']]);
        }

        $stmt = $this->pdo->prepare("SELECT id FROM intra_kb_tags WHERE name = :name");
        $stmt->execute(['name' => $name]);
        if ($stmt->fetch()) {
            return Response::json(['error' => 'Tag existiert bereits'], 409);
        }

        $this->pdo->prepare("INSERT INTO intra_kb_tags (name, color) VALUES (:name, :color)")
            ->execute(['name' => $name, 'color' => $color]);
        return Response::json(['success' => true, 'id' => (int) $this->pdo->lastInsertId()]);
    }

    /**
     * DELETE /api/knowledgebase/tags?id=N
     */
    public function deleteTag(Request $request): Response
    {
        if (!$this->requireAdmin()) {
            return Response::json(['error' => 'Keine Berechtigung'], 403);
        }

        $id = (int) ($request->query['id'] ?? 0);
        if (!$id) {
            return Response::json(['error' => 'Keine ID'], 400);
        }

        $this->pdo->prepare("DELETE FROM intra_kb_tags WHERE id = :id")
            ->execute(['id' => $id]);
        return Response::json(['success' => true]);
    }

    // ── Search ────────────────────────────────────────────────────────

    /**
     * GET /api/knowledgebase/search?q=<query>
     *
     * Knowledgebase-Volltextsuche mit MATCH...AGAINST. Erlaubt Public
     * Access wenn `KB_PUBLIC_ACCESS=true`, sonst Session-Auth.
     */
    public function search(Request $request): Response
    {
        $query = (string) ($request->query['q'] ?? '');
        if (strlen($query) < 2) {
            return Response::json(['results' => []]);
        }

        try {
            $ftQuery = '';
            $words = preg_split('/\s+/', trim($query)) ?: [];
            foreach ($words as $w) {
                $w = trim($w);
                if (mb_strlen($w) >= 2) {
                    $w = preg_replace('/[+\-><()~*"@]+/', '', $w);
                    if ($w !== '') {
                        $ftQuery .= '+' . $w . '* ';
                    }
                }
            }
            $ftQuery = trim($ftQuery);

            $searchParam = '%' . $query . '%';

            if ($ftQuery !== '') {
                $sql = "SELECT kb.id, kb.type, kb.title, kb.subtitle, kb.competency_level, kb.content,
                        kb.med_indikationen, kb.med_dosierung, kb.mass_indikationen, kb.mass_durchfuehrung,
                        MATCH(kb.title, kb.subtitle, kb.content) AGAINST(:ft_rel IN BOOLEAN MODE) as relevance
                    FROM intra_kb_entries kb
                    WHERE kb.is_archived = 0
                    AND (
                        MATCH(kb.title, kb.subtitle, kb.content) AGAINST(:ft_main IN BOOLEAN MODE)
                        OR MATCH(kb.med_wirkstoff, kb.med_wirkstoffgruppe, kb.med_indikationen, kb.med_kontraindikationen, kb.med_dosierung, kb.med_besonderheiten) AGAINST(:ft_med IN BOOLEAN MODE)
                        OR MATCH(kb.mass_indikationen, kb.mass_kontraindikationen, kb.mass_durchfuehrung, kb.mass_risiken) AGAINST(:ft_mass IN BOOLEAN MODE)
                        OR kb.id IN (SELECT et.entry_id FROM intra_kb_entry_tags et JOIN intra_kb_tags t ON et.tag_id = t.id WHERE t.name LIKE :ft_tag)
                    )
                    ORDER BY kb.is_pinned DESC, relevance DESC, kb.title ASC
                    LIMIT 10";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    'ft_rel'  => $ftQuery,
                    'ft_main' => $ftQuery,
                    'ft_med'  => $ftQuery,
                    'ft_mass' => $ftQuery,
                    'ft_tag'  => $searchParam,
                ]);
            } else {
                $sql = "SELECT kb.id, kb.type, kb.title, kb.subtitle, kb.competency_level, kb.content,
                        kb.med_indikationen, kb.med_dosierung, kb.mass_indikationen, kb.mass_durchfuehrung,
                        0 as relevance
                    FROM intra_kb_entries kb
                    WHERE kb.is_archived = 0
                    AND (kb.title LIKE :search1 OR kb.subtitle LIKE :search2)
                    ORDER BY kb.is_pinned DESC, kb.title ASC
                    LIMIT 10";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['search1' => $searchParam, 'search2' => $searchParam]);
            }

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($results as &$result) {
                $result['type_label'] = KBHelper::getTypeLabel($result['type']);
                $result['type_color'] = KBHelper::getTypeColor($result['type']);
                if ($result['competency_level']) {
                    $competency = KBHelper::getCompetencyInfo($result['competency_level']);
                    $result['competency_label'] = $competency['label'];
                    $result['competency_color'] = $competency['color'];
                }

                $snippet = null;
                $snippetFields = [$result['content'], $result['med_indikationen'], $result['med_dosierung'],
                    $result['mass_indikationen'], $result['mass_durchfuehrung']];
                foreach ($snippetFields as $field) {
                    $snippet = KBHelper::createSearchSnippet($field, $query, 120);
                    if ($snippet !== null) {
                        break;
                    }
                }
                $result['snippet'] = $snippet;

                unset($result['content'], $result['med_indikationen'], $result['med_dosierung'],
                    $result['mass_indikationen'], $result['mass_durchfuehrung'], $result['relevance']);
            }
            unset($result);

            return Response::json(['results' => $results]);
        } catch (PDOException $e) {
            Logger::error('KnowledgebaseSearch: DB-Fehler', ['error' => $e->getMessage(), 'query' => $query]);
            return Response::json(['error' => 'Database error'], 500);
        }
    }

    /**
     * Prüft Login + kb.edit Permission für write-Operationen auf Categories/Tags.
     */
    private function requireAdmin(): bool
    {
        if (!isset($_SESSION['userid'])) {
            return false;
        }
        return Gate::allows('knowledgebase.edit');
    }
}
