<?php
/**
 * View: Telemetrie & Announcements Einstellungen
 *
 * @var \PDO $pdo
 */

use App\Auth\Permissions;
use App\Telemetry\TelemetryManager;
use App\Telemetry\GlobalAnnouncementManager;

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
            if ($announcements->isEnabled()) {
                $announcements->disable();
                $message = 'Ankündigungen wurden deaktiviert.';
            } else {
                $announcements->enable();
                $message = 'Ankündigungen wurden aktiviert.';
            }
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
                $message = 'Ankündigungen-Cache aktualisiert. ' . ($result['count'] ?? 0) . ' Ankündigungen geladen.';
                $messageType = 'success';
            } else {
                $message = 'Cache-Aktualisierung fehlgeschlagen: ' . ($result['message'] ?? 'Unbekannter Fehler');
                $messageType = 'danger';
            }
            break;

        case 'update_hub_url':
            $newUrl = trim($_POST['hub_url'] ?? '');
            if (filter_var($newUrl, FILTER_VALIDATE_URL)) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE intra_config 
                        SET config_value = ?, updated_at = NOW()
                        WHERE config_key = 'HUB_URL'
                    ");
                    $stmt->execute([$newUrl]);
                    $message = 'Hub-URL aktualisiert.';
                    $messageType = 'success';
                } catch (\PDOException $e) {
                    $message = 'Fehler beim Speichern: ' . $e->getMessage();
                    $messageType = 'danger';
                }
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
    $SITE_TITLE = 'Telemetrie & Ankündigungen';
    include __DIR__ . '/../../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h1 class="mb-0">Telemetrie & Ankündigungen</h1>
                    </div>

                    <?php
                    // $installationId kommt aus SystemController::telemetry() —
                    // Fallback für den Fall, dass das Template direkt ohne den
                    // Controller gerendert wird (z.B. alter Stub).
                    if (!isset($installationId) || !$installationId) {
                        $installationId = (new \App\Telemetry\TelemetryManager($pdo))->getInstallationId();
                    }
                    ?>
                    <style>
                        /* Support-UUID Banner: kompakt, UUID per Default versteckt */
                        .uuid-banner { border-left: 3px solid var(--bs-primary, #0d6efd); }
                        .uuid-banner .uuid-label {
                            font-size: 0.72rem;
                            text-transform: uppercase;
                            letter-spacing: 0.06em;
                            opacity: 0.55;
                            font-weight: 600;
                        }
                        .uuid-banner code.uuid-value {
                            font-family: var(--font-mono, 'Inconsolata', 'JetBrains Mono', Consolas, monospace);
                            font-size: 0.82rem;
                            background: var(--bs-tertiary-bg, rgba(255,255,255,0.05));
                            padding: 0.22rem 0.55rem;
                            border-radius: 4px;
                            user-select: all;
                        }
                        /* Hover-to-reveal Blur (Status-Tabelle) */
                        .uuid-blur {
                            filter: blur(5px);
                            transition: filter 0.15s ease-out;
                            cursor: help;
                        }
                        .uuid-blur:hover,
                        .uuid-blur:focus-within {
                            filter: blur(0);
                        }
                    </style>
                    <div class="intra__tile uuid-banner mb-4 p-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="shrink-0" style="font-size: 1.1rem; color: var(--bs-primary, #0d6efd);">
                                <i class="fa-solid fa-id-card"></i>
                            </div>
                            <div class="flex-1" style="min-width: 240px;">
                                <div class="uuid-label mb-1">Support &amp; Telemetrie — Deine Installations-UUID</div>
                                <div class="text-gray-400" style="font-size: 0.78rem; line-height: 1.45;">
                                    Für schnellen Support: Im ıgnıs-Discord <code style="font-size: 0.75rem;">/telemetry connect &lt;UUID&gt; [label]</code> nutzen — damit kann unser Support-Team direkt auf die unten beschriebenen Daten zugreifen und dir ggf. schneller mit deinem Anliegen helfen.
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2" id="uuidBannerControls">
                                <code id="installationUuidValue" class="uuid-value" style="display: none;">
                                    <?= htmlspecialchars($installationId) ?>
                                </code>
                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--soft-primary" id="toggleUuidBtn" onclick="toggleInstallationUuid()">
                                    <i class="fa-regular fa-eye mr-1"></i>Einblenden
                                </button>
                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost" id="copyUuidBtn" onclick="copyInstallationUuid()" title="UUID kopieren">
                                    <i class="fa-regular fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <script>
                        (function () {
                            const uuidEl   = document.getElementById('installationUuidValue');
                            const toggleBtn = document.getElementById('toggleUuidBtn');

                            window.toggleInstallationUuid = function () {
                                const visible = uuidEl.style.display !== 'none';
                                if (visible) {
                                    uuidEl.style.display = 'none';
                                    toggleBtn.innerHTML = '<i class="fa-regular fa-eye mr-1"></i>Einblenden';
                                } else {
                                    uuidEl.style.display = '';
                                    toggleBtn.innerHTML = '<i class="fa-regular fa-eye-slash mr-1"></i>Verbergen';
                                }
                            };

                            function copyText(text, btn) {
                                const done = () => {
                                    const orig = btn.innerHTML;
                                    btn.innerHTML = '<i class="fa-solid fa-check"></i>';
                                    setTimeout(() => { btn.innerHTML = orig; }, 1500);
                                };
                                if (navigator.clipboard && navigator.clipboard.writeText) {
                                    navigator.clipboard.writeText(text).then(done).catch(fallback);
                                } else {
                                    fallback();
                                }
                                function fallback() {
                                    const ta = document.createElement('textarea');
                                    ta.value = text;
                                    ta.style.position = 'fixed';
                                    ta.style.opacity = '0';
                                    document.body.appendChild(ta);
                                    ta.select();
                                    try { document.execCommand('copy'); } catch (e) {}
                                    document.body.removeChild(ta);
                                    done();
                                }
                            }

                            window.copyInstallationUuid = function () {
                                const uuid = (uuidEl?.textContent || '').trim();
                                if (!uuid) return;
                                copyText(uuid, document.getElementById('copyUuidBtn'));
                            };
                        })();
                    </script>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                            <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <!-- Telemetrie -->
                        <div>
                            <div class="card h-full">
                                <div class="card-header flex items-center justify-between">
                                    <span><i class="fas fa-chart-line mr-2"></i>Telemetrie</span>
                                    <span class="badge bg-<?= $telemetryEnabled ? 'success' : 'secondary' ?>">
                                        <?= $telemetryEnabled ? 'Aktiviert' : 'Deaktiviert' ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p class="text-gray-400">
                                        Telemetrie hilft uns, ıgnıs weiterzuentwickeln.
                                        Es werden nur <strong>anonymisierte</strong> Statistiken übermittelt -
                                        keine persönlichen Daten, Namen oder IP-Adressen.
                                        Du kannst die Telemetrie jederzeit deaktivieren.
                                    </p>

                                    <div class="mb-3 flex flex-wrap gap-2">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="toggle_telemetry">
                                            <button type="submit" class="btn btn-<?= $telemetryEnabled ? 'warning' : 'success' ?>">
                                                <i class="fas fa-<?= $telemetryEnabled ? 'toggle-off' : 'toggle-on' ?> mr-1"></i>
                                                <?= $telemetryEnabled ? 'Deaktivieren' : 'Aktivieren' ?>
                                            </button>
                                        </form>

                                        <button type="button" class="ignis-btn ignis-btn--outline-info" data-bs-toggle="modal" data-bs-target="#datenschutzModal">
                                            <i class="fas fa-shield-alt mr-1"></i> Datenschutz
                                        </button>

                                        <?php if ($telemetryEnabled): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="send_heartbeat">
                                                <button type="submit" class="ignis-btn ignis-btn--outline-primary">
                                                    <i class="fas fa-paper-plane mr-1"></i> Jetzt senden
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <hr>

                                    <h6>Status</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td class="text-gray-400">Installation-ID:</td>
                                            <td>
                                                <code class="text-sm uuid-blur" title="Hover zum Einblenden"><?= htmlspecialchars($installationId) ?></code>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-gray-400">Letzter Heartbeat:</td>
                                            <td><?= $lastHeartbeat ? \App\Helpers\DateTimeHelper::formatShortLocal($lastHeartbeat) : '<span class="text-gray-400">Noch nie</span>' ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-gray-400">Hub-Server:</td>
                                            <td><code class="text-sm"><?= htmlspecialchars($hubUrl) ?></code></td>
                                        </tr>
                                    </table>

                                    <?php if ($previewData): ?>
                                        <details class="mt-3">
                                            <summary class="text-gray-400" style="cursor: pointer;">Datenvorschau anzeigen</summary>
                                            <pre class="bg-[rgba(0,0,0,0.3)] text-white p-3 rounded mt-2 text-sm" style="max-height: 300px; overflow: auto;"><?= htmlspecialchars(json_encode($previewData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Globale Announcements -->
                        <div>
                            <div class="card h-full">
                                <div class="card-header flex items-center justify-between">
                                    <span><i class="fas fa-bullhorn mr-2"></i>Globale Ankündigungen</span>
                                    <span class="badge bg-<?= $announcementsEnabled ? 'success' : 'secondary' ?>">
                                        <?= $announcementsEnabled ? 'Aktiviert' : 'Deaktiviert' ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p class="text-gray-400">
                                        Globale Ankündigungen informieren dich über wichtige Updates,
                                        Sicherheitshinweise und News vom ıgnıs-Team.
                                    </p>

                                    <div class="mb-3 flex gap-2">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="toggle_announcements">
                                            <button type="submit" class="btn btn-<?= $announcementsEnabled ? 'warning' : 'success' ?>">
                                                <i class="fas fa-<?= $announcementsEnabled ? 'toggle-off' : 'toggle-on' ?> mr-1"></i>
                                                <?= $announcementsEnabled ? 'Deaktivieren' : 'Aktivieren' ?>
                                            </button>
                                        </form>

                                        <?php if ($announcementsEnabled): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="refresh_announcements">
                                                <button type="submit" class="ignis-btn ignis-btn--outline-primary">
                                                    <i class="fas fa-sync mr-1"></i> Cache aktualisieren
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <hr>

                                    <h6>Cache-Status</h6>
                                    <table class="table table-sm mb-3">
                                        <tr>
                                            <td class="text-gray-400">Einträge im Cache:</td>
                                            <td><?= $cacheInfo['count'] ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-gray-400">Letzter Abruf:</td>
                                            <td><?= $cacheInfo['last_fetch'] ? \App\Helpers\DateTimeHelper::formatShortLocal($cacheInfo['last_fetch']) : '<span class="text-gray-400">Noch nie</span>' ?></td>
                                        </tr>
                                    </table>

                                    <h6>Aktuelle Ankündigungen</h6>
                                    <?php
                                    // Defensiv: Sicherstellen dass $announcements ein Objekt ist
                                    if (!($announcements instanceof \App\Telemetry\GlobalAnnouncementManager)) {
                                        $announcements = new GlobalAnnouncementManager($pdo);
                                    }

                                    $currentAnnouncements = [];
                                    $allCached = [];
                                    $debugError = null;

                                    if ($announcementsEnabled) {
                                        try {
                                            $currentAnnouncements = $announcements->getActiveAnnouncements(null, true);
                                            $allCached = $announcements->getAllCached();
                                        } catch (\Throwable $e) {
                                            $debugError = $e->getMessage();
                                        }
                                    }
                                    ?>

                                    <!-- Debug (kann später entfernt werden) -->
                                    <div class="ignis-alert text-sm py-1 mb-2">
                                        Cache: <?= count($allCached) ?> | Aktiv: <?= count($currentAnnouncements) ?>
                                        <?php if ($debugError): ?> | <span class="text-[#d46b6b]">Error: <?= htmlspecialchars($debugError) ?></span><?php endif; ?>
                                    </div>

                                    <?php if (!empty($currentAnnouncements)): ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($currentAnnouncements as $ann): ?>
                                                <div class="list-group-item px-0">
                                                    <div class="flex flex-wrap items-center gap-1">
                                                        <span class="badge bg-<?= $ann['type'] === 'critical' ? 'danger' : ($ann['type'] === 'warning' ? 'warning' : 'info') ?>">
                                                            <?= htmlspecialchars($ann['type']) ?>
                                                        </span>
                                                        <?php if (!empty($ann['admin_only'])): ?>
                                                            <span class="ignis-chip ignis-chip--dark"><i class="fas fa-shield-halved"></i></span>
                                                        <?php endif; ?>
                                                        <strong><?= htmlspecialchars($ann['title']) ?></strong>
                                                    </div>
                                                    <?php if (!empty($ann['message'])): ?>
                                                        <small class="text-gray-400"><?= htmlspecialchars($ann['message']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php elseif (!empty($allCached)): ?>
                                        <div class="ignis-alert ignis-alert--warning text-sm">
                                            <?= count($allCached) ?> im Cache, aber durch Filter ausgeblendet.
                                            <details class="mt-2">
                                                <summary>Cache-Inhalt</summary>
                                                <pre class="mt-2 mb-0" style="font-size: 0.7rem;"><?= htmlspecialchars(json_encode($allCached, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                            </details>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-gray-400 text-sm mb-0">Keine Ankündigungen.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
            </div>
        </div>
    </div>

    <!-- Datenschutz-Modal -->
    <div class="modal fade" id="datenschutzModal" tabindex="-1" aria-labelledby="datenschutzModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="datenschutzModalLabel">
                        <i class="fas fa-shield-alt mr-2 text-[#5bb8cc]"></i>Datenschutz
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body">
                    <p class="text-gray-400 text-sm mb-3">
                        Wir nehmen den Schutz deiner Daten ernst. Hier siehst du genau, was die Telemetrie überträgt — und was nicht.
                    </p>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <h6 class="text-[#6abf76]"><i class="fas fa-check mr-1"></i> Was wir sammeln:</h6>
                            <ul class="text-sm mb-0">
                                <li>Anonyme Installation-ID (UUID)</li>
                                <li>Server- und Systemname</li>
                                <li>ıgnıs- und PHP-Version</li>
                                <li>Anzahl Mitarbeiter, User, Fahrzeuge</li>
                                <li>Aktivitätsstatistiken (eNOTF, Einsätze)</li>
                                <li>Aktive Module</li>
                            </ul>
                        </div>
                        <div>
                            <h6 class="text-[#d46b6b]"><i class="fas fa-times mr-1"></i> Was wir NICHT sammeln:</h6>
                            <ul class="text-sm mb-0">
                                <li>Namen, E-Mails, Discord-IDs</li>
                                <li>IP-Adressen der Nutzer</li>
                                <li>Passwörter oder API-Keys</li>
                                <li>Konkrete Einsatz- oder Protokolldaten</li>
                                <li>Persönliche Informationen jeglicher Art</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ignis-btn" data-bs-dismiss="modal">Schließen</button>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>