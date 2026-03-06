<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

// Für CitizenFX: Nur Header entfernen, KEINE neuen setzen!
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    // Entferne CSP Header - .htaccess kümmert sich um den Rest
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');
    // KEIN neuer CSP wird gesetzt!
}
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth\Permissions;
use App\Helpers\Flash;

// Check if user authentication is required for vehicle login
if (defined('FIRE_INCIDENT_REQUIRE_USER_AUTH') && FIRE_INCIDENT_REQUIRE_USER_AUTH === true) {
    if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: " . BASE_PATH . "login.php");
        exit();
    }
}

// Check if logged into vehicle
if (!isset($_SESSION['einsatz_vehicle_id']) || !isset($_SESSION['einsatz_operator_id'])) {
    Flash::error('Bitte melden Sie sich zuerst auf einem Fahrzeug an.');
    header("Location: " . BASE_PATH . "einsatz/login-fahrzeug.php");
    exit();
}

// Clear all einsatz_viewed session variables when returning to list
foreach (array_keys($_SESSION) as $key) {
    if (strpos($key, 'einsatz_viewed_') === 0) {
        unset($_SESSION[$key]);
    }
}

require __DIR__ . '/../assets/config/database.php';

// Load incidents for this vehicle
$incidents = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT i.*, 
               m.fullname AS leader_name,
               COUNT(DISTINCT v.id) AS vehicle_count,
               COUNT(DISTINCT s.id) AS sitrep_count
        FROM intra_fire_incidents i
        LEFT JOIN intra_mitarbeiter m ON i.leader_id = m.id
        LEFT JOIN intra_fire_incident_vehicles v ON i.id = v.incident_id
        LEFT JOIN intra_fire_incident_sitreps s ON i.id = s.incident_id
        WHERE EXISTS (
            SELECT 1 FROM intra_fire_incident_vehicles iv 
            WHERE iv.incident_id = i.id 
            AND iv.vehicle_id = ?
        )
        AND i.finalized = 0
        AND i.archived = 0
        GROUP BY i.id
        ORDER BY i.started_at DESC, i.created_at DESC
    ");
    $stmt->execute([$_SESSION['einsatz_vehicle_id']]);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    Flash::error('Fehler beim Laden der Einsätze: ' . $e->getMessage());
}

function fmt_dt(?string $ts): string
{
    if (!$ts) return '-';
    try {
        $dt = new DateTime($ts, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $dt->format('d.m.Y H:i');
    } catch (Exception $e) {
        return $ts;
    }
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../assets/components/_base/admin/head.php'; ?>
    <style>
        .incident-card { transition: all 0.2s; }
        .incident-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); }
        .status-badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
    </style>
</head>

<body data-bs-theme="dark" data-page="einsatzliste">
    <div class="d-flex">
        <?php $einsatzActivePage = 'list'; include __DIR__ . '/../assets/components/einsatz-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-grow-1" style="overflow-y: auto;">
            <div class="container my-4">
                <h1 class="mb-4">
                    <i class="fa-solid fa-list me-2"></i>
                    Meine Einsätze
                </h1>

                <?php App\Helpers\Flash::render(); ?>

                <?php if (empty($incidents)): ?>
                    <div class="alert alert-info">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        Noch keine Einsätze für Ihr Fahrzeug vorhanden. Erstellen Sie einen neuen Einsatz über den Button in der Navigation.
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($incidents as $inc): ?>
                            <?php
                            // Status badge
                            if (!$inc['finalized']) {
                                $statusBadge = 'bg-warning';
                                $statusText = 'In Bearbeitung';
                            } else {
                                $statusMap = [
                                    0 => ['bg-secondary', 'Ungesehen'],
                                    1 => ['bg-warning', 'In Prüfung'],
                                    2 => ['bg-success', 'Freigegeben'],
                                    3 => ['bg-danger', 'Ungenügend'],
                                    4 => ['bg-dark', 'Ausgeblendet'],
                                ];
                                $s = (int)$inc['status'];
                                [$statusBadge, $statusText] = $statusMap[$s] ?? ['bg-secondary', 'Unbekannt'];
                            }
                            ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card incident-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title mb-0">
                                                #<?= htmlspecialchars($inc['incident_number'] ?? 'Keine Nummer') ?>
                                            </h5>
                                            <span class="badge <?= $statusBadge ?> status-badge">
                                                <?= $statusText ?>
                                            </span>
                                        </div>

                                        <h6 class="text-muted mb-3">
                                            <i class="fa-solid fa-fire me-1"></i>
                                            <?= htmlspecialchars($inc['keyword']) ?>
                                        </h6>

                                        <div class="row">
                                            <div class="col">
                                                <div class="mb-2">
                                                    <small class="text-muted">
                                                        <i class="fa-solid fa-map-marker-alt me-1"></i>
                                                        <?= htmlspecialchars($inc['location']) ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="mb-2">
                                                    <small class="text-muted">
                                                        <i class="fa-solid fa-clock me-1"></i>
                                                        <?= fmt_dt($inc['started_at']) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if ($inc['leader_name']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <i class="fa-solid fa-user-tie me-1"></i>
                                                    <?= htmlspecialchars($inc['leader_name']) ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>

                                        <hr class="my-2">

                                        <div class="d-flex justify-content-between text-muted small mb-3">
                                            <span>
                                                <i class="fa-solid fa-truck me-1"></i>
                                                <?= (int)$inc['vehicle_count'] ?> Bet. EM
                                            </span>
                                            <span>
                                                <i class="fa-solid fa-comment me-1"></i>
                                                <?= (int)$inc['sitrep_count'] ?> Lagemeldung(en)
                                            </span>
                                        </div>

                                        <a href="<?= BASE_PATH ?>einsatz/view.php?id=<?= (int)$inc['id'] ?>" class="btn btn-main-color w-100">
                                            <i class="fa-solid fa-eye me-1"></i>
                                            Einsatz öffnen
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>