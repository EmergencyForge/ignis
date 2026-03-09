<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';

use App\Auth\Permissions;
use App\KnowledgeBase\KBHelper;

header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht authentifiziert']);
    exit;
}

$query = trim($_GET['q'] ?? '');

if (mb_strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$searchParam = '%' . $query . '%';
$results = [];

try {
    // 1. Wissensdatenbank (FULLTEXT search)
    $kbResults = searchKnowledgeBase($pdo, $query, $searchParam);
    if (!empty($kbResults)) {
        $results[] = [
            'module' => 'Wissensdatenbank',
            'icon' => 'fa-book-medical',
            'items' => $kbResults
        ];
    }

    // 2. Mitarbeiter
    if (Permissions::check(['admin', 'personnel.view'])) {
        $items = searchMitarbeiter($pdo, $searchParam);
        if (!empty($items)) {
            $results[] = [
                'module' => 'Mitarbeiter',
                'icon' => 'fa-users',
                'items' => $items
            ];
        }
    }

    // 3. Brandeinsätze
    if (Permissions::check(['admin', 'fire.incident.qm'])) {
        $items = searchFireIncidents($pdo, $searchParam);
        if (!empty($items)) {
            $results[] = [
                'module' => 'Brandeinsätze',
                'icon' => 'fa-fire',
                'items' => $items
            ];
        }
    }

    // 4. eNOTF Protokolle
    if (Permissions::check(['admin', 'edivi.view'])) {
        $items = searchEnotf($pdo, $searchParam);
        if (!empty($items)) {
            $results[] = [
                'module' => 'eNOTF Protokolle',
                'icon' => 'fa-file-medical',
                'items' => $items
            ];
        }
    }

    // 5. Dokumente
    if (Permissions::check(['admin', 'personnel.view'])) {
        $items = searchDocuments($pdo, $searchParam);
        if (!empty($items)) {
            $results[] = [
                'module' => 'Dokumente',
                'icon' => 'fa-file-lines',
                'items' => $items
            ];
        }
    }

    // 6. Dokumentvorlagen
    if (Permissions::check(['admin'])) {
        $items = searchTemplates($pdo, $searchParam);
        if (!empty($items)) {
            $results[] = [
                'module' => 'Dokumentvorlagen',
                'icon' => 'fa-file-contract',
                'items' => $items
            ];
        }
    }

    // 7. Fahrzeuge
    if (Permissions::check(['admin', 'vehicles.view'])) {
        $items = searchVehicles($pdo, $searchParam);
        if (!empty($items)) {
            $results[] = [
                'module' => 'Fahrzeuge',
                'icon' => 'fa-truck',
                'items' => $items
            ];
        }
    }

    // 8. Fahrzeug-Defekte
    if (Permissions::check(['admin', 'vehicles.view'])) {
        $items = searchDefects($pdo, $searchParam);
        if (!empty($items)) {
            $results[] = [
                'module' => 'Defekt-Meldungen',
                'icon' => 'fa-triangle-exclamation',
                'items' => $items
            ];
        }
    }

    echo json_encode(['results' => $results]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankfehler']);
}

/**
 * Search Knowledge Base using FULLTEXT
 */
function searchKnowledgeBase(PDO $pdo, string $query, string $searchParam): array
{
    // Build FULLTEXT query
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

    if ($ftQuery !== '') {
        $sql = "SELECT kb.id, kb.title, kb.subtitle, kb.content
                FROM intra_kb_entries kb
                WHERE kb.is_archived = 0
                AND (
                    MATCH(kb.title, kb.subtitle, kb.content) AGAINST(:ft_main IN BOOLEAN MODE)
                    OR kb.title LIKE :search
                )
                ORDER BY MATCH(kb.title, kb.subtitle, kb.content) AGAINST(:ft_rel IN BOOLEAN MODE) DESC, kb.title ASC
                LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'ft_main' => $ftQuery,
            'ft_rel' => $ftQuery,
            'search' => $searchParam
        ]);
    } else {
        $sql = "SELECT kb.id, kb.title, kb.subtitle, kb.content
                FROM intra_kb_entries kb
                WHERE kb.is_archived = 0
                AND kb.title LIKE :search
                ORDER BY kb.title ASC
                LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['search' => $searchParam]);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $items = [];
    foreach ($rows as $row) {
        $snippet = KBHelper::createSearchSnippet($row['content'], $query, 100);
        $items[] = [
            'title' => $row['title'],
            'subtitle' => $snippet ?? ($row['subtitle'] ?: ''),
            'url' => 'wissensdb/entry.php?id=' . $row['id']
        ];
    }
    return $items;
}

/**
 * Search Mitarbeiter (personnel)
 */
function searchMitarbeiter(PDO $pdo, string $searchParam): array
{
    $sql = "SELECT id, fullname, dienstnr
            FROM intra_mitarbeiter
            WHERE fullname LIKE :s1 OR dienstnr LIKE :s2
            ORDER BY fullname ASC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['s1' => $searchParam, 's2' => $searchParam]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'title' => $row['fullname'],
            'subtitle' => $row['dienstnr'] ? 'DNr: ' . $row['dienstnr'] : '',
            'url' => 'mitarbeiter/view.php?id=' . $row['id']
        ];
    }
    return $items;
}

/**
 * Search fire incidents
 */
function searchFireIncidents(PDO $pdo, string $searchParam): array
{
    try {
        $sql = "SELECT id, incident_number, location, keyword, started_at
                FROM intra_fire_incidents
                WHERE incident_number LIKE :s1 OR location LIKE :s2 OR keyword LIKE :s3
                ORDER BY started_at DESC
                LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['s1' => $searchParam, 's2' => $searchParam, 's3' => $searchParam]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }

    $items = [];
    foreach ($rows as $row) {
        $subtitle = $row['keyword'] ?: $row['location'] ?: '';
        if ($row['started_at']) {
            $subtitle .= $subtitle ? ' — ' : '';
            $subtitle .= date('d.m.Y', strtotime($row['started_at']));
        }
        $items[] = [
            'title' => $row['incident_number'],
            'subtitle' => $subtitle,
            'url' => 'einsatz/admin/view.php?id=' . $row['id']
        ];
    }
    return $items;
}

/**
 * Search eNOTF protocols
 */
function searchEnotf(PDO $pdo, string $searchParam): array
{
    $sql = "SELECT id, enr, patname, diagnose, edatum
            FROM intra_edivi
            WHERE enr LIKE :s1 OR patname LIKE :s2 OR diagnose LIKE :s3
            ORDER BY edatum DESC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['s1' => $searchParam, 's2' => $searchParam, 's3' => $searchParam]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rows as $row) {
        $subtitle = $row['patname'] ?: '';
        if ($row['edatum']) {
            $subtitle .= $subtitle ? ' — ' : '';
            $subtitle .= date('d.m.Y', strtotime($row['edatum']));
        }
        $items[] = [
            'title' => 'Protokoll ' . $row['enr'],
            'subtitle' => $subtitle,
            'url' => 'enotf/admin/view.php?id=' . $row['id']
        ];
    }
    return $items;
}

/**
 * Search documents (Mitarbeiter-Dokumente)
 */
function searchDocuments(PDO $pdo, string $searchParam): array
{
    $sql = "SELECT d.id, d.docid, d.erhalter, d.ausstellungsdatum, d.profileid, t.name AS template_name
            FROM intra_mitarbeiter_dokumente d
            LEFT JOIN intra_dokument_templates t ON d.template_id = t.id
            WHERE d.erhalter LIKE :s1 OR d.docid LIKE :s2 OR t.name LIKE :s3
            ORDER BY d.timestamp DESC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['s1' => $searchParam, 's2' => $searchParam, 's3' => $searchParam]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rows as $row) {
        $title = $row['erhalter'] ?: 'Dokument #' . $row['docid'];
        $subtitle = $row['template_name'] ?: '';
        if ($row['ausstellungsdatum']) {
            $subtitle .= $subtitle ? ' — ' : '';
            $subtitle .= date('d.m.Y', strtotime($row['ausstellungsdatum']));
        }
        $url = $row['profileid']
            ? 'mitarbeiter/profile.php?id=' . $row['profileid']
            : 'mitarbeiter/list.php';
        $items[] = [
            'title' => $title,
            'subtitle' => $subtitle,
            'url' => $url
        ];
    }
    return $items;
}

/**
 * Search document templates
 */
function searchTemplates(PDO $pdo, string $searchParam): array
{
    try {
        $sql = "SELECT id, name, category, description
                FROM intra_dokument_templates
                WHERE name LIKE :s1 OR description LIKE :s2
                ORDER BY name ASC
                LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['s1' => $searchParam, 's2' => $searchParam]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }

    $categoryLabels = [
        'urkunde' => 'Urkunde',
        'zertifikat' => 'Zertifikat',
        'schreiben' => 'Schreiben',
        'sonstiges' => 'Sonstiges'
    ];

    $items = [];
    foreach ($rows as $row) {
        $subtitle = $categoryLabels[$row['category']] ?? $row['category'] ?? '';
        if ($row['description']) {
            $subtitle .= $subtitle ? ' — ' : '';
            $subtitle .= mb_substr($row['description'], 0, 60);
        }
        $items[] = [
            'title' => $row['name'],
            'subtitle' => $subtitle,
            'url' => 'settings/documents/templates.php'
        ];
    }
    return $items;
}

/**
 * Search vehicle defects
 */
function searchDefects(PDO $pdo, string $searchParam): array
{
    $statusLabels = [
        'open' => 'Offen', 'in_progress' => 'In Bearbeitung',
        'deferred' => 'Aufgeschoben', 'resolved' => 'Gelöst'
    ];

    try {
        $sql = "SELECT d.id, d.title, d.description, d.status, d.created_at,
                       f.name AS vehicle_name, f.identifier AS vehicle_identifier
                FROM intra_fahrzeuge_defects d
                JOIN intra_fahrzeuge f ON d.vehicle_id = f.id
                WHERE d.title LIKE :s1 OR d.description LIKE :s2
                   OR f.name LIKE :s3 OR f.identifier LIKE :s4
                ORDER BY d.created_at DESC
                LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['s1' => $searchParam, 's2' => $searchParam, 's3' => $searchParam, 's4' => $searchParam]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }

    $items = [];
    foreach ($rows as $row) {
        $status = $statusLabels[$row['status']] ?? $row['status'];
        $subtitle = $row['vehicle_name'] . ' (' . $row['vehicle_identifier'] . ') — ' . $status;
        if ($row['created_at']) {
            $subtitle .= ' — ' . date('d.m.Y', strtotime($row['created_at']));
        }
        $items[] = [
            'title' => $row['title'],
            'subtitle' => $subtitle,
            'url' => 'settings/fahrzeuge/defekte/index.php'
        ];
    }
    return $items;
}

/**
 * Search vehicles
 */
function searchVehicles(PDO $pdo, string $searchParam): array
{
    try {
        $sql = "SELECT id, identifier, name, kennzeichen
                FROM intra_fahrzeuge
                WHERE identifier LIKE :s1 OR name LIKE :s2 OR kennzeichen LIKE :s3
                ORDER BY identifier ASC
                LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['s1' => $searchParam, 's2' => $searchParam, 's3' => $searchParam]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }

    $items = [];
    foreach ($rows as $row) {
        $subtitle = $row['name'] ?: '';
        if ($row['kennzeichen']) {
            $subtitle .= $subtitle ? ' — ' : '';
            $subtitle .= $row['kennzeichen'];
        }
        $items[] = [
            'title' => $row['identifier'],
            'subtitle' => $subtitle,
            'url' => 'settings/fahrzeuge/fahrzeuge/index.php'
        ];
    }
    return $items;
}
