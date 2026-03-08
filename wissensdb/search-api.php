<?php
/**
 * Search suggestions API for Knowledge Base
 * Returns article suggestions based on search query
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';

use App\KnowledgeBase\KBHelper;

// Check if public access is enabled or user is logged in
$publicAccess = defined('KB_PUBLIC_ACCESS') && KB_PUBLIC_ACCESS === true;
$isLoggedIn = isset($_SESSION['userid']) && isset($_SESSION['permissions']);

if (!$publicAccess && !$isLoggedIn) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get search query
$query = $_GET['q'] ?? '';

// Require at least 2 characters
if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit();
}

try {
    // FULLTEXT-Suche mit BOOLEAN MODE
    $ftQuery = '';
    $words = preg_split('/\s+/', trim($query));
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

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'ft_rel' => $ftQuery,
            'ft_main' => $ftQuery,
            'ft_med' => $ftQuery,
            'ft_mass' => $ftQuery,
            'ft_tag' => $searchParam
        ]);
    } else {
        // Fallback für sehr kurze Suchbegriffe
        $sql = "SELECT kb.id, kb.type, kb.title, kb.subtitle, kb.competency_level, kb.content,
                    kb.med_indikationen, kb.med_dosierung, kb.mass_indikationen, kb.mass_durchfuehrung,
                    0 as relevance
                FROM intra_kb_entries kb
                WHERE kb.is_archived = 0
                AND (kb.title LIKE :search1 OR kb.subtitle LIKE :search2)
                ORDER BY kb.is_pinned DESC, kb.title ASC
                LIMIT 10";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'search1' => $searchParam,
            'search2' => $searchParam
        ]);
    }

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add type labels, colors, and snippets
    foreach ($results as &$result) {
        $result['type_label'] = KBHelper::getTypeLabel($result['type']);
        $result['type_color'] = KBHelper::getTypeColor($result['type']);
        if ($result['competency_level']) {
            $competency = KBHelper::getCompetencyInfo($result['competency_level']);
            $result['competency_label'] = $competency['label'];
            $result['competency_color'] = $competency['color'];
        }

        // Generate snippet from best matching field
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

        // Remove raw content fields from response
        unset($result['content'], $result['med_indikationen'], $result['med_dosierung'],
            $result['mass_indikationen'], $result['mass_durchfuehrung'], $result['relevance']);
    }

    echo json_encode(['results' => $results]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
