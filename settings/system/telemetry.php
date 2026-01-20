<?php
/**
 * Telemetrie & Announcements Einstellungen
 */

require_once __DIR__ . '/../../src/SessionManager.php';
\IntraRP\SessionManager::init();
\IntraRP\SessionManager::requireLogin();

require_once __DIR__ . '/../../assets/includes/databaseconnection.php';
require_once __DIR__ . '/../../src/Telemetry/TelemetryManager.php';
require_once __DIR__ . '/../../src/Telemetry/GlobalAnnouncementManager.php';

use IntraRP\Telemetry\TelemetryManager;
use IntraRP\Telemetry\GlobalAnnouncementManager;

// Nur Admins erlauben
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /dashboard.php');
    exit;
}

$telemetry = new TelemetryManager($conn);
$announcements = new GlobalAnnouncementManager($conn);

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
            $stmt = $conn->prepare("UPDATE intra_config SET config_value = ? WHERE config_key = 'ANNOUNCEMENTS_ENABLED'");
            $newValue = $announcements->isEnabled() ? 'false' : 'true';
            $stmt->execute([$newValue]);
            $message = $newValue === 'true' ? 'Announcements aktiviert.' : 'Announcements deaktiviert.';
            $messageType = 'success';
            break;
            
        case 'send_heartbeat':
            if ($telemetry->isEnabled()) {
                $result = $telemetry->sendHeartbeat(true); // Force send
                $message = $result ? 'Heartbeat erfolgreich gesendet.' : 'Heartbeat konnte nicht gesendet werden.';
                $messageType = $result ? 'success' : 'danger';
            } else {
                $message = 'Telemetrie ist deaktiviert.';
                $messageType = 'warning';
            }
            break;
            
        case 'refresh_announcements':
            $announcements->refreshCache();
            $message = 'Announcements-Cache aktualisiert.';
            $messageType = 'success';
            break;
            
        case 'update_hub_url':
            $newUrl = trim($_POST['hub_url'] ?? '');
            if (filter_var($newUrl, FILTER_VALIDATE_URL)) {
                $stmt = $conn->prepare("UPDATE intra_config SET config_value = ? WHERE config_key = 'HUB_URL'");
                $stmt->execute([$newUrl]);
                $message = 'Hub-URL aktualisiert.';
                $messageType = 'success';
            } else {
                $message = 'Ungültige URL.';
                $messageType = 'danger';
            }
            break;
    }
    
    // Objekte neu laden
    $telemetry = new TelemetryManager($conn);
    $announcements = new GlobalAnnouncementManager($conn);
}

// Aktuelle Werte laden
$telemetryEnabled = $telemetry->isEnabled();
$announcementsEnabled = $announcements->isEnabled();
$hubUrl = $telemetry->getHubUrl();
$installationId = $telemetry->getInstallationId();
$lastHeartbeat = $telemetry->getLastHeartbeat();

// Vorschau der zu sendenden Daten
$previewData = $telemetryEnabled ? $telemetry->collectData() : null;

include __DIR__ . '/../../assets/includes/head.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i class="fas fa-satellite-dish me-2"></i>Telemetrie & Announcements
        </h4>
        <a href="/settings/system/" class="btn btn-outline-secondary btn-sm">
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
                            <summary class="text-muted cursor-pointer">Datenvorschau anzeigen</summary>
                            <pre class="bg-dark text-light p-3 rounded mt-2 small" style="max-height: 300px; overflow: auto;"><?= htmlspecialchars(json_encode($previewData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Announcements -->
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
                    
                    <h6>Aktuelle Announcements</h6>
                    <?php 
                    $currentAnnouncements = $announcementsEnabled ? $announcements->getActiveAnnouncements() : [];
                    if (!empty($currentAnnouncements)): 
                    ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($currentAnnouncements as $a): ?>
                                <div class="list-group-item px-0">
                                    <span class="badge bg-<?= $a['type'] === 'critical' ? 'danger' : ($a['type'] === 'warning' ? 'warning' : 'primary') ?> me-2"><?= $a['type'] ?></span>
                                    <strong><?= htmlspecialchars($a['title']) ?></strong>
                                    <p class="mb-0 small text-muted"><?= htmlspecialchars(substr($a['message'], 0, 100)) ?>...</p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Keine aktuellen Announcements.</p>
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

<?php include __DIR__ . '/../../assets/includes/footer.php'; ?>
