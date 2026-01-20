<?php

/**
 * Telemetrie & Announcements Einstellungen
 */

require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Telemetry\TelemetryManager;
use App\Telemetry\GlobalAnnouncementManager;

// Nur Admins erlauben
if (!Permissions::check(['admin'])) {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$telemetry = new TelemetryManager($pdo);
$announcements = new GlobalAnnouncementManager($pdo);

$message = '';
$messageType = '';

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'toggle_telemetry':
            if ($telemetry->isEnabled()) {
                $telemetry->disable();
                $message = 'Telemetrie wurde deaktiviert.';
            } else {
                $telemetry->enable();
                $message = 'Telemetrie wurde aktiviert. Vielen Dank für deine Unterstützung!';
            }
            $messageType = 'success';
            break;

        case 'toggle_announcements':
            $configManager = new \App\Config\ConfigManager($pdo);
            $newValue = $announcements->isEnabled() ? 'false' : 'true';
            $configManager->update('ANNOUNCEMENTS_ENABLED', $newValue, $_SESSION['userid'] ?? null);
            $message = $newValue === 'true' ? 'Announcements aktiviert.' : 'Announcements deaktiviert.';
            $messageType = 'success';
            break;

        case 'send_heartbeat':
            if ($telemetry->isEnabled()) {
                $result = $telemetry->sendHeartbeat(true);
                $message = $result['success'] ? 'Heartbeat erfolgreich gesendet.' : ($result['message'] ?? 'Heartbeat konnte nicht gesendet werden.');
                $messageType = $result['success'] ? 'success' : 'danger';
            } else {
                $message = 'Telemetrie ist deaktiviert.';
                $messageType = 'warning';
            }
            break;

        case 'refresh_announcements':
            $result = $announcements->refreshCache();
            if ($result['success']) {
                $message = 'Announcements-Cache aktualisiert. ' . ($result['count'] ?? 0) . ' Ankündigungen geladen.';
                $messageType = 'success';
            } else {
                $message = 'Cache-Aktualisierung fehlgeschlagen: ' . ($result['message'] ?? 'Unbekannter Fehler');
                $messageType = 'danger';
            }
            break;

        case 'update_hub_url':
            $newUrl = trim($_POST['hub_url'] ?? '');
            if (filter_var($newUrl, FILTER_VALIDATE_URL)) {
                $configManager = new \App\Config\ConfigManager($pdo);
                $configManager->update('HUB_URL', $newUrl, $_SESSION['userid'] ?? null);
                $message = 'Hub-URL aktualisiert.';
                $messageType = 'success';
            } else {
                $message = 'Ungültige URL.';
                $messageType = 'danger';
            }
            break;
    }

    // Objekte neu laden
    $telemetry = new TelemetryManager($pdo);
    $announcements = new GlobalAnnouncementManager($pdo);
}

// Aktuelle Werte laden
$telemetryEnabled = $telemetry->isEnabled();
$announcementsEnabled = $announcements->isEnabled();
$hubUrl = $telemetry->getHubUrl();
$installationId = $telemetry->getInstallationId();
$lastHeartbeat = $telemetry->getLastHeartbeat();
$previewData = $telemetryEnabled ? $telemetry->collectData() : null;
$cacheInfo = $announcements->getCacheInfo();
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    $SITE_TITLE = 'Telemetrie & Announcements';
    include __DIR__ . '/../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <hr class="text-light my-3">
                    <div class="d-flex justify-content-between align-items-center mb-5">
                        <h1 class="mb-0">Telemetrie & Announcements</h1>
                        <a href="<?= BASE_PATH ?>settings/system/" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Zurück
                        </a>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                            <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row g-4">
                        <!-- Telemetrie -->
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-chart-line me-2"></i>Telemetrie</span>
                                    <span class="badge bg-<?= $telemetryEnabled ? 'success' : 'secondary' ?>">
                                        <?= $telemetryEnabled ? 'Aktiviert' : 'Deaktiviert' ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted">
                                        Telemetrie hilft uns, intraRP weiterzuentwickeln.
                                        Es werden nur <strong>anonymisierte</strong> Statistiken übermittelt -
                                        keine persönlichen Daten, Namen oder IP-Adressen.
                                        Du kannst die Telemetrie jederzeit deaktivieren.
                                    </p>

                                    <div class="d-flex gap-2 mb-3">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_telemetry">
                                            <button type="submit" class="btn btn-<?= $telemetryEnabled ? 'warning' : 'success' ?>">
                                                <i class="fas fa-<?= $telemetryEnabled ? 'toggle-off' : 'toggle-on' ?> me-1"></i>
                                                <?= $telemetryEnabled ? 'Deaktivieren' : 'Aktivieren' ?>
                                            </button>
                                        </form>

                                        <?php if ($telemetryEnabled): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="send_heartbeat">
                                                <button type="submit" class="btn btn-outline-primary">
                                                    <i class="fas fa-paper-plane me-1"></i> Jetzt senden
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <hr>

                                    <h6>Status</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td class="text-muted">Installation-ID:</td>
                                            <td><code class="small"><?= htmlspecialchars($installationId) ?></code></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Letzter Heartbeat:</td>
                                            <td><?= $lastHeartbeat ? date('d.m.Y H:i', strtotime($lastHeartbeat)) : '<span class="text-muted">Noch nie</span>' ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Hub-Server:</td>
                                            <td><code class="small"><?= htmlspecialchars($hubUrl) ?></code></td>
                                        </tr>
                                    </table>

                                    <?php if ($previewData): ?>
                                        <details class="mt-3">
                                            <summary class="text-muted" style="cursor: pointer;">Datenvorschau anzeigen</summary>
                                            <pre class="bg-dark text-light p-3 rounded mt-2 small" style="max-height: 300px; overflow: auto;"><?= htmlspecialchars(json_encode($previewData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Globale Announcements -->
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-bullhorn me-2"></i>Globale Announcements</span>
                                    <span class="badge bg-<?= $announcementsEnabled ? 'success' : 'secondary' ?>">
                                        <?= $announcementsEnabled ? 'Aktiviert' : 'Deaktiviert' ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted">
                                        Globale Announcements informieren dich über wichtige Updates,
                                        Sicherheitshinweise und News vom intraRP-Team.
                                    </p>

                                    <div class="d-flex gap-2 mb-3">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_announcements">
                                            <button type="submit" class="btn btn-<?= $announcementsEnabled ? 'warning' : 'success' ?>">
                                                <i class="fas fa-<?= $announcementsEnabled ? 'toggle-off' : 'toggle-on' ?> me-1"></i>
                                                <?= $announcementsEnabled ? 'Deaktivieren' : 'Aktivieren' ?>
                                            </button>
                                        </form>

                                        <?php if ($announcementsEnabled): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="refresh_announcements">
                                                <button type="submit" class="btn btn-outline-primary">
                                                    <i class="fas fa-sync me-1"></i> Cache aktualisieren
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <hr>

                                    <h6>Cache-Status</h6>
                                    <table class="table table-sm mb-3">
                                        <tr>
                                            <td class="text-muted">Einträge im Cache:</td>
                                            <td><?= $cacheInfo['count'] ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Letzter Abruf:</td>
                                            <td><?= $cacheInfo['last_fetch'] ? date('d.m.Y H:i', strtotime($cacheInfo['last_fetch'])) : '<span class="text-muted">Noch nie</span>' ?></td>
                                        </tr>
                                    </table>

                                    <h6>Aktuelle Ankündigungen</h6>
                                    <?php
                                    $currentAnnouncements = $announcementsEnabled ? $announcements->getActiveAnnouncements(null, true) : [];
                                    $allCached = $announcements->getAllCached();

                                    // Debug: Zeige was im Cache ist vs. was gefiltert wird
                                    if (empty($currentAnnouncements) && !empty($allCached)):
                                    ?>
                                        <div class="alert alert-warning small">
                                            <strong>Debug:</strong> <?= count($allCached) ?> Eintrag/Einträge im Cache, aber durch Filter ausgeblendet.
                                            <details class="mt-2">
                                                <summary>Cache-Inhalt anzeigen</summary>
                                                <pre class="mt-2 mb-0" style="font-size: 0.75rem;"><?= htmlspecialchars(json_encode($allCached, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                            </details>
                                        </div>
                                    <?php elseif (empty($currentAnnouncements)): ?>
                                        <p class="text-muted small mb-0">Keine aktuellen Ankündigungen.</p>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($currentAnnouncements as $ann): ?>
                                                <div class="list-group-item px-0">
                                                    <div class="d-flex align-items-center flex-wrap gap-1">
                                                        <span class="badge bg-<?= $ann['type'] === 'critical' ? 'danger' : ($ann['type'] === 'warning' ? 'warning' : 'info') ?>">
                                                            <?= htmlspecialchars($ann['type']) ?>
                                                        </span>
                                                        <?php if (!empty($ann['admin_only'])): ?>
                                                            <span class="badge bg-dark"><i class="fas fa-shield-halved"></i></span>
                                                        <?php endif; ?>
                                                        <strong><?= htmlspecialchars($ann['title']) ?></strong>
                                                    </div>
                                                    <?php if (!empty($ann['message'])): ?>
                                                        <small class="text-muted"><?= htmlspecialchars($ann['message']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Hub-URL Konfiguration -->
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-cog me-2"></i>Erweiterte Einstellungen
                                </div>
                                <div class="card-body">
                                    <form method="POST" class="row g-3 align-items-end">
                                        <input type="hidden" name="action" value="update_hub_url">
                                        <div class="col-md-8">
                                            <label class="form-label">Hub-Server URL</label>
                                            <input type="url" name="hub_url" class="form-control"
                                                value="<?= htmlspecialchars($hubUrl) ?>"
                                                placeholder="https://hub.intrarp.de">
                                            <small class="text-muted">Nur ändern, wenn du einen eigenen Hub-Server betreibst.</small>
                                        </div>
                                        <div class="col-md-4">
                                            <button type="submit" class="btn btn-outline-primary">
                                                <i class="fas fa-save me-1"></i> Speichern
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Datenschutz-Info -->
                        <div class="col-12">
                            <div class="card border-info">
                                <div class="card-header bg-info bg-opacity-10">
                                    <i class="fas fa-shield-alt me-2"></i>Datenschutz
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-success"><i class="fas fa-check me-1"></i> Was wir sammeln:</h6>
                                            <ul class="small mb-0">
                                                <li>Anonyme Installation-ID (UUID)</li>
                                                <li>Server- und Systemname</li>
                                                <li>intraRP- und PHP-Version</li>
                                                <li>Anzahl Mitarbeiter, User, Fahrzeuge</li>
                                                <li>Aktivitätsstatistiken (eNOTF, Einsätze)</li>
                                                <li>Aktive Module</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-danger"><i class="fas fa-times me-1"></i> Was wir NICHT sammeln:</h6>
                                            <ul class="small mb-0">
                                                <li>Namen, E-Mails, Discord-IDs</li>
                                                <li>IP-Adressen der Nutzer</li>
                                                <li>Passwörter oder API-Keys</li>
                                                <li>Konkrete Einsatz- oder Protokolldaten</li>
                                                <li>Persönliche Informationen jeglicher Art</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>