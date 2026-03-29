<?php
/**
 * Federation Fire Incidents API
 * GET: Returns fire incidents for consumption by linked instances.
 * Supports delta sync via ?since=ISO8601 timestamp.
 *
 * Query params:
 *   since    (ISO8601 datetime, optional — only return records updated after this)
 *   page     (int, default 1)
 *   per_page (int, default 50, max 200)
 */

require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';

use App\Api\ApiResponse;
use App\Federation\FederationMiddleware;

header('Content-Type: application/json');

$link = FederationMiddleware::authenticate($pdo);
FederationMiddleware::requireProvidePermission($link, 'fire');

$since = $_GET['since'] ?? null;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(200, max(1, (int) ($_GET['per_page'] ?? 50)));
$offset = ($page - 1) * $perPage;

try {
    $where = "WHERE i.archived = 0";
    $params = [];

    if ($since) {
        $where .= " AND i.updated_at > ?";
        $params[] = $since;
    }

    // Total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM intra_fire_incidents i {$where}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // Fetch incidents with leader name
    $stmt = $pdo->prepare("
        SELECT
            i.id, i.incident_number, i.keyword, i.location,
            i.status, i.finalized,
            i.leader_id, m.fullname AS leader_name,
            i.owner_type, i.owner_name, i.owner_contact,
            i.gta_x, i.gta_y, i.gta_z,
            i.created_at, i.updated_at
        FROM intra_fire_incidents i
        LEFT JOIN intra_mitarbeiter m ON i.leader_id = m.id
        {$where}
        ORDER BY i.updated_at ASC
        LIMIT ? OFFSET ?
    ");
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt->execute($allParams);
    $incidents = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $syncCursor = null;
    if (!empty($incidents)) {
        $syncCursor = end($incidents)['updated_at'];
    }

    ApiResponse::success([
        'instance_id' => defined('FEDERATION_INSTANCE_ID') ? FEDERATION_INSTANCE_ID : '',
        'synced_at' => date('c'),
        'sync_cursor' => $syncCursor,
        'data' => $incidents,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int) ceil($total / $perPage),
        ],
    ]);
} catch (\PDOException $e) {
    ApiResponse::error('Datenbankfehler: ' . $e->getMessage(), 500);
}
