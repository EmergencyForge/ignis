<?php
/**
 * Federation eNOTF Protocols API
 * GET: Returns released (freigegeben) eNOTF protocols for consumption by linked instances.
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
FederationMiddleware::requireProvidePermission($link, 'enotf');

$since = $_GET['since'] ?? null;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(200, max(1, (int) ($_GET['per_page'] ?? 50)));
$offset = ($page - 1) * $perPage;

try {
    // Only share released, non-hidden protocols
    $where = "WHERE freigegeben = 1 AND hidden = 0 AND hidden_user = 0";
    $params = [];

    if ($since) {
        $where .= " AND updated_at > ?";
        $params[] = $since;
    }

    // Total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM intra_edivi {$where}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // Fetch protocols — expose a safe subset of columns
    $stmt = $pdo->prepare("
        SELECT
            id, enr, edatum, ezeit,
            patname, pfname, patgebdat, geschlecht_pat,
            einsatzort, elokation,
            fzg_transp, fzg_na,
            ziel_poi, ziel_adresse,
            naca,
            sendezeit, updated_at,
            fahrername, fahrerquali,
            beifahrername, beifahrerquali,
            praktikantname, praktikantquali
        FROM intra_edivi
        {$where}
        ORDER BY updated_at ASC
        LIMIT ? OFFSET ?
    ");
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt->execute($allParams);
    $protocols = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Determine sync cursor (latest updated_at in result set)
    $syncCursor = null;
    if (!empty($protocols)) {
        $syncCursor = end($protocols)['updated_at'];
    }

    ApiResponse::success([
        'instance_id' => \App\Federation\FederationMiddleware::config('FEDERATION_INSTANCE_ID'),
        'synced_at' => date('c'),
        'sync_cursor' => $syncCursor,
        'data' => $protocols,
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
