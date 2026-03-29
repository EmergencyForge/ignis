<?php
/**
 * Federation Personnel API
 * GET: Returns this instance's personnel for consumption by linked instances.
 * Requires valid X-Federation-Key with provide_personnel permission.
 *
 * Query params:
 *   page     (int, default 1)
 *   per_page (int, default 100, max 500)
 */

require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';

use App\Api\ApiResponse;
use App\Federation\FederationMiddleware;

header('Content-Type: application/json');

$link = FederationMiddleware::authenticate($pdo);
FederationMiddleware::requireProvidePermission($link, 'personnel');

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(500, max(1, (int) ($_GET['per_page'] ?? 100)));
$offset = ($page - 1) * $perPage;

try {
    // Total count
    $total = (int) $pdo->query("SELECT COUNT(*) FROM intra_mitarbeiter")->fetchColumn();

    // Fetch personnel with rank and qualifications
    // Use only base columns that exist on all intraRP versions
    $stmt = $pdo->prepare("
        SELECT
            m.id,
            m.fullname,
            m.dienstnr,
            d.name AS dienstgrad_name,
            d.badge AS dienstgrad_badge,
            rd.name AS quali_rd,
            rd.abkuerzung AS quali_rd_short,
            fw.name AS quali_fw,
            m.fachdienste AS quali_fd_json
        FROM intra_mitarbeiter m
        LEFT JOIN intra_mitarbeiter_dienstgrade d ON m.dienstgrad = d.id
        LEFT JOIN intra_mitarbeiter_rdquali rd ON m.qualird = rd.id
        LEFT JOIN intra_mitarbeiter_fwquali fw ON m.qualifw2 = fw.id
        ORDER BY m.fullname ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$perPage, $offset]);
    $personnel = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    ApiResponse::success([
        'instance_id' => defined('FEDERATION_INSTANCE_ID') ? FEDERATION_INSTANCE_ID : '',
        'synced_at' => date('c'),
        'data' => $personnel,
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
