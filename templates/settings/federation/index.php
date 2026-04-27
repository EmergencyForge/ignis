<?php
/**
 * View: Federation-Konfiguration
 *
 * @var \PDO $pdo
 */

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Config\ConfigManager;
use App\Federation\FederationPairingService;
use App\Security\CsrfProtection;
use App\Session\SessionManager;

$csrfToken = CsrfProtection::getToken();
$userId    = SessionManager::userId();

$configManager = new ConfigManager($pdo);
$pairingService = new FederationPairingService($pdo);

$federationEnabled = \App\Federation\FederationMiddleware::isEnabled();
$instanceId = \App\Federation\FederationMiddleware::config('FEDERATION_INSTANCE_ID');
$instanceName = \App\Federation\FederationMiddleware::config('FEDERATION_INSTANCE_NAME');

$links = [];
$generatedToken = null;
$pairError = null;
$pairSuccess = null;

// Load links if federation is enabled
if ($federationEnabled) {
    try {
        $links = $pairingService->getAllLinks();
    } catch (\Exception $e) {
        $links = [];
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        Flash::set('error', 'Ungültiger CSRF-Token.');
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    $action = $_POST['action'] ?? '';

    // Toggle federation
    if ($action === 'toggle_federation') {
        $newState = \App\Federation\FederationMiddleware::isEnabled() ? 'false' : 'true';
        $configManager->update('FEDERATION_ENABLED', $newState, $userId);

        if ($newState === 'true') {
            $pairingService->ensureInstanceId();
        }

        Flash::set('success', $newState === 'true' ? 'Federation aktiviert.' : 'Federation deaktiviert.');
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    // Update instance name
    if ($action === 'update_name') {
        $name = trim($_POST['instance_name'] ?? '');
        if (!empty($name)) {
            $configManager->update('FEDERATION_INSTANCE_NAME', $name, $userId);
            Flash::set('success', 'Instanzname aktualisiert.');
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    // Generate connection token
    if ($action === 'generate_token') {
        $result = $pairingService->generateConnectionToken();
        $generatedToken = $result['token'];
        // Store the API key temporarily so we can create the link when someone pairs
        SessionManager::set('federation_pending_token_key', $result['api_key']);
    }

    // Pair with remote instance via token
    if ($action === 'pair_with_token') {
        $token = trim($_POST['connection_token'] ?? '');
        $remoteInfo = FederationPairingService::parseConnectionToken($token);

        if (!$remoteInfo) {
            $pairError = 'Ungültiger Verbindungsschlüssel.';
        } else {
            try {
                // Generate a key for us to authenticate with them
                $ourKeyForThem = FederationPairingService::generateApiKey();

                // Call their pair endpoint
                $endpoint = rtrim($remoteInfo['url'], '/') . '/api/federation/pair';
                $payload = json_encode([
                    'instance_id' => $pairingService->ensureInstanceId(),
                    'instance_name' => $instanceName ?: (\App\Federation\FederationMiddleware::config('SYSTEM_NAME', 'ıgnıs')),
                    'instance_url' => \App\Federation\FederationMiddleware::config('SYSTEM_URL'),
                    'api_key_for_you' => $ourKeyForThem,
                    'your_token_key' => $remoteInfo['api_key'],
                ], JSON_UNESCAPED_UNICODE);

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $endpoint,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Accept: application/json',
                    ],
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_POSTREDIR => CURL_REDIR_POST_ALL,
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                $curlErrno = curl_errno($ch);
                $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                curl_close($ch);

                if ($response === false || $curlErrno !== 0) {
                    throw new \RuntimeException('Verbindung fehlgeschlagen (URL: ' . $effectiveUrl . '): ' . $curlError . ' (Code: ' . $curlErrno . ')');
                }

                if ($httpCode >= 400) {
                    $errData = json_decode($response, true);
                    $errMsg = $errData['error'] ?? "HTTP {$httpCode}";
                    throw new \RuntimeException('Remote-Fehler: ' . $errMsg . ' (HTTP ' . $httpCode . ')');
                }

                $data = json_decode($response, true);

                if (!is_array($data)) {
                    throw new \RuntimeException('Ungültige Antwort (kein JSON): ' . mb_substr($response, 0, 300));
                }

                if (!($data['success'] ?? false)) {
                    throw new \RuntimeException($data['error'] ?? 'Unbekannter Fehler. Response: ' . mb_substr($response, 0, 300));
                }

                // Store the link on our side:
                // - outgoing = the key THEY gave us (api_key_for_you), so we can call THEM
                // - incoming = the key WE generated (ourKeyForThem), so they can call US
                $pairingService->createLink(
                    [
                        'instance_id' => $remoteInfo['instance_id'],
                        'instance_name' => $data['instance_name'] ?? $remoteInfo['instance_name'],
                        'url' => $remoteInfo['url'],
                    ],
                    $data['api_key_for_you'],        // outgoing: key they gave us to call them
                    $ourKeyForThem                    // incoming: key they must send to call us
                );

                Flash::set('success', 'Verbindung mit "' . htmlspecialchars($data['instance_name'] ?? $remoteInfo['instance_name']) . '" hergestellt.');
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            } catch (\Exception $e) {
                $pairError = $e->getMessage();
            }
        }
    }

    // Update link settings
    if ($action === 'update_link') {
        $linkId = (int) ($_POST['link_id'] ?? 0);
        if ($linkId > 0) {
            $settings = [
                'consume_personnel' => isset($_POST['consume_personnel']) ? 1 : 0,
                'consume_enotf' => isset($_POST['consume_enotf']) ? 1 : 0,
                'consume_fire' => isset($_POST['consume_fire']) ? 1 : 0,
                'provide_personnel' => isset($_POST['provide_personnel']) ? 1 : 0,
                'provide_enotf' => isset($_POST['provide_enotf']) ? 1 : 0,
                'provide_fire' => isset($_POST['provide_fire']) ? 1 : 0,
                'sync_interval_minutes' => max(5, (int) ($_POST['sync_interval_minutes'] ?? 15)),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            $pairingService->updateLinkSettings($linkId, $settings);
            Flash::set('success', 'Verbindungseinstellungen aktualisiert.');
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    // Manual sync trigger
    if ($action === 'sync_now') {
        $linkId = (int) ($_POST['link_id'] ?? 0);
        if ($linkId > 0) {
            $syncService = new \App\Federation\FederationSyncService($pdo);
            $messages = [];

            // Sync all enabled data types
            $link = $pairingService->getLink($linkId);
            if ($link && $link['consume_personnel']) {
                $r = $syncService->syncPersonnel($linkId);
                $messages[] = 'Personal: ' . ($r['success'] ? $r['records'] . ' Einträge' : 'Fehler — ' . ($r['error'] ?? ''));
            }
            if ($link && $link['consume_enotf']) {
                $r = $syncService->syncEnotf($linkId);
                $messages[] = 'eNOTF: ' . ($r['success'] ? $r['records'] . ' Protokolle' : 'Fehler — ' . ($r['error'] ?? ''));
            }
            if ($link && $link['consume_fire']) {
                $r = $syncService->syncFireIncidents($linkId);
                $messages[] = 'Einsätze: ' . ($r['success'] ? $r['records'] . ' Einträge' : 'Fehler — ' . ($r['error'] ?? ''));
            }

            if (empty($messages)) {
                Flash::set('info', 'Keine Datentypen zum Synchronisieren aktiviert.');
            } else {
                Flash::set('success', 'Sync abgeschlossen: ' . implode(' | ', $messages));
            }
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    // Delete link
    if ($action === 'delete_link') {
        $linkId = (int) ($_POST['link_id'] ?? 0);
        if ($linkId > 0) {
            try {
                $pairingService->deleteLink($linkId);
                Flash::set('success', 'Verbindung und zugehörige Daten gelöscht.');
            } catch (\Exception $e) {
                Flash::set('error', 'Fehler beim Löschen: ' . $e->getMessage());
            }
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    // Reload links after actions
    if ($federationEnabled) {
        try { $links = $pairingService->getAllLinks(); } catch (\Exception $e) {}
    }
}

// Reload current state after potential changes
$federationEnabled = $configManager->get('FEDERATION_ENABLED', false);
$instanceId = $configManager->get('FEDERATION_INSTANCE_ID', '');
$instanceName = $configManager->get('FEDERATION_INSTANCE_NAME', '');
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    $SITE_TITLE = 'Instanzvernetzung';
    include __DIR__ . '/../../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-6">
                    <div class="mb-6 flex items-center justify-between">
                        <h1 class="mb-0"><i class="fa-solid fa-link" style="color:var(--main-color);margin-right:0.5rem"></i>Instanzvernetzung</h1>
                    </div>
                    <?php Flash::render(); ?>

                    <!-- Federation Toggle -->
                    <div class="card mb-4">
                        <div class="card-header flex items-center justify-between">
                            <h5 class="mb-0">Instanzübergreifende Vernetzung</h5>
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="toggle_federation">
                                <button type="submit" class="ignis-btn ignis-btn--sm <?= $federationEnabled ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                    <?= $federationEnabled ? '<i class="fa-solid fa-power-off"></i> Deaktivieren' : '<i class="fa-solid fa-power-off"></i> Aktivieren' ?>
                                </button>
                            </form>
                        </div>
                        <div class="card-body">
                            <p style="color:var(--text-dimmed);font-size:var(--fs-sm);margin-bottom:0.75rem;">
                                Verbinde diese Instanz mit anderen ıgnıs-Installationen, um Personal, eNOTF-Protokolle und Einsätze instanzübergreifend zu nutzen.
                            </p>
                            <?php if ($federationEnabled): ?>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div>
                                        <label class="ignis-field__label" style="font-size:var(--fs-sm);">Instanz-ID</label>
                                        <input type="text" class="ignis-input ignis-input--sm" value="<?= htmlspecialchars($instanceId) ?>" readonly style="font-family:var(--font-mono);font-size:var(--fs-xs);">
                                    </div>
                                    <div>
                                        <form method="post" class="flex items-end gap-2">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="update_name">
                                            <div class="flex-1">
                                                <label class="ignis-field__label" style="font-size:var(--fs-sm);">Instanzname</label>
                                                <input type="text" name="instance_name" class="ignis-input ignis-input--sm"
                                                       value="<?= htmlspecialchars($instanceName) ?>"
                                                       placeholder="z.B. Berufsfeuerwehr Berlin">
                                            </div>
                                            <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--outline-primary whitespace-nowrap">Speichern</button>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-gray-400" style="font-size:var(--fs-sm);">
                                    <i class="fa-solid fa-circle-info"></i> Instanzvernetzung ist deaktiviert. Aktiviere sie, um Verbindungen zu anderen Instanzen herzustellen.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($federationEnabled): ?>

                    <!-- Connection Actions -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Verbindung herstellen</h5>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <!-- Generate Token -->
                                <div>
                                    <h6><i class="fa-solid fa-key" style="color:var(--main-color);margin-right:0.3rem"></i> Schlüssel generieren</h6>
                                    <p style="font-size:var(--fs-xs);color:var(--text-dimmed);">
                                        Generiere einen Verbindungsschlüssel und teile ihn mit dem Admin der anderen Instanz.
                                    </p>
                                    <?php if ($generatedToken): ?>
                                        <div class="mb-2">
                                            <textarea class="ignis-input ignis-input--sm" rows="3" readonly
                                                      style="font-family:var(--font-mono);font-size:var(--fs-xs);word-break:break-all;"
                                                      onclick="this.select()"><?= htmlspecialchars($generatedToken) ?></textarea>
                                        </div>
                                        <div class="ignis-alert ignis-alert--warning" style="font-size:var(--fs-xs);padding:0.5rem 0.75rem;">
                                            <i class="fa-solid fa-triangle-exclamation"></i>
                                            Dieser Schlüssel wird nur einmal angezeigt. Kopiere ihn jetzt.
                                        </div>
                                    <?php else: ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="generate_token">
                                            <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--outline-primary">
                                                <i class="fa-solid fa-wand-magic-sparkles"></i> Schlüssel generieren
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <!-- Pair with Token -->
                                <div>
                                    <h6><i class="fa-solid fa-plug" style="color:var(--main-color);margin-right:0.3rem"></i> Verbindung eingehen</h6>
                                    <p style="font-size:var(--fs-xs);color:var(--text-dimmed);">
                                        Füge einen Verbindungsschlüssel einer anderen Instanz ein.
                                    </p>
                                    <?php if ($pairError): ?>
                                        <div class="ignis-alert ignis-alert--danger" style="font-size:var(--fs-xs);padding:0.5rem 0.75rem;">
                                            <i class="fa-solid fa-circle-xmark"></i> <?= htmlspecialchars($pairError) ?>
                                        </div>
                                    <?php endif; ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="pair_with_token">
                                        <textarea name="connection_token" class="form-control form-control-sm mb-2" rows="3"
                                                  placeholder="Verbindungsschlüssel einfügen..."
                                                  style="font-family:var(--font-mono);font-size:var(--fs-xs);"></textarea>
                                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--outline-success">
                                            <i class="fa-solid fa-handshake"></i> Verbinden
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Connected Instances -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Verbundene Instanzen <span class="ignis-chip"><?= count($links) ?></span></h5>
                        </div>
                        <?php if (empty($links)): ?>
                            <div class="card-body flex flex-col items-center justify-center" style="color:var(--text-dimmed);font-size:var(--fs-sm);padding:2rem;">
                                <i class="fa-solid fa-link-slash" style="font-size:1.5rem;margin-bottom:0.5rem;"></i>
                                <span>Noch keine Verbindungen hergestellt.</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($links as $link): ?>
                            <div class="card-body border-b" style="border-color:var(--darkgray) !important;">
                                <div class="mb-3 flex items-start justify-between">
                                    <div>
                                        <h6 class="mb-1">
                                            <?= htmlspecialchars($link['instance_name']) ?>
                                            <?php if ($link['is_active']): ?>
                                                <?php if ($link['last_sync_status'] === 'success'): ?>
                                                    <span class="ignis-chip ignis-chip--success" style="font-size:0.65rem;">Online</span>
                                                <?php elseif ($link['last_sync_status'] === 'error'): ?>
                                                    <span class="ignis-chip ignis-chip--danger" style="font-size:0.65rem;">Fehler</span>
                                                <?php else: ?>
                                                    <span class="ignis-chip" style="font-size:0.65rem;">Ausstehend</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="ignis-chip" style="font-size:0.65rem;">Deaktiviert</span>
                                            <?php endif; ?>
                                        </h6>
                                        <div style="font-size:var(--fs-xs);color:var(--text-dimmed);">
                                            <?= htmlspecialchars($link['instance_url']) ?>
                                            &middot; ID: <code style="font-size:0.65rem;"><?= htmlspecialchars(substr($link['instance_id'], 0, 8)) ?>...</code>
                                        </div>
                                        <?php if ($link['last_sync_at']): ?>
                                            <div style="font-size:var(--fs-xs);color:var(--text-dimmed);margin-top:0.2rem;">
                                                Letzter Sync: <?= \App\Helpers\DateTimeHelper::formatShortLocal($link['last_sync_at']) ?>
                                                <?php if ($link['last_sync_error']): ?>
                                                    &middot; <span style="color:var(--bs-danger);"><?= htmlspecialchars($link['last_sync_error']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="update_link">
                                    <input type="hidden" name="link_id" value="<?= $link['id'] ?>">

                                    <div class="mb-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <div>
                                            <label class="ignis-field__label" style="font-size:var(--fs-xs);font-weight:600;">
                                                <i class="fa-solid fa-download" style="color:var(--main-color)"></i> Von dort abrufen
                                            </label>
                                            <div class="flex flex-col gap-1">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="consume_personnel" id="consume_personnel_<?= $link['id'] ?>" <?= $link['consume_personnel'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="consume_personnel_<?= $link['id'] ?>" style="font-size:var(--fs-xs);">Personal</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="consume_enotf" id="consume_enotf_<?= $link['id'] ?>" <?= $link['consume_enotf'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="consume_enotf_<?= $link['id'] ?>" style="font-size:var(--fs-xs);">eNOTF-Protokolle</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="consume_fire" id="consume_fire_<?= $link['id'] ?>" <?= $link['consume_fire'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="consume_fire_<?= $link['id'] ?>" style="font-size:var(--fs-xs);">Einsätze</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="ignis-field__label" style="font-size:var(--fs-xs);font-weight:600;">
                                                <i class="fa-solid fa-upload" style="color:var(--main-color)"></i> Dorthin bereitstellen
                                            </label>
                                            <div class="flex flex-col gap-1">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="provide_personnel" id="provide_personnel_<?= $link['id'] ?>" <?= $link['provide_personnel'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="provide_personnel_<?= $link['id'] ?>" style="font-size:var(--fs-xs);">Personal</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="provide_enotf" id="provide_enotf_<?= $link['id'] ?>" <?= $link['provide_enotf'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="provide_enotf_<?= $link['id'] ?>" style="font-size:var(--fs-xs);">eNOTF-Protokolle</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="provide_fire" id="provide_fire_<?= $link['id'] ?>" <?= $link['provide_fire'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="provide_fire_<?= $link['id'] ?>" style="font-size:var(--fs-xs);">Einsätze</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-end gap-3">
                                        <div>
                                            <label class="ignis-field__label" style="font-size:var(--fs-xs);">Sync-Intervall (Min.)</label>
                                            <input type="number" name="sync_interval_minutes" class="ignis-input ignis-input--sm" style="width:80px;"
                                                   value="<?= (int)$link['sync_interval_minutes'] ?>" min="5" max="1440">
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active_<?= $link['id'] ?>" <?= $link['is_active'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_active_<?= $link['id'] ?>" style="font-size:var(--fs-xs);">Aktiv</label>
                                        </div>
                                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--outline-primary">
                                            <i class="fa-solid fa-floppy-disk"></i> Speichern
                                        </button>
                                    </div>
                                </form>

                                <div class="mt-3 flex gap-2 pt-2" style="border-top:1px solid var(--darkgray);">
                                    <?php if ($link['consume_personnel'] || $link['consume_enotf'] || $link['consume_fire']): ?>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="sync_now">
                                        <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--outline-primary">
                                            <i class="fa-solid fa-arrows-rotate"></i> Jetzt synchronisieren
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="post" class="inline" id="delete-link-<?= $link['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="delete_link">
                                        <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                                        <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--outline-danger"
                                                onclick="showConfirm('Verbindung und alle gecachten Daten dieser Instanz wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Verbindung löschen'}).then(r => { if(r) this.closest('form').submit(); });">
                                            <i class="fa-solid fa-trash"></i> Verbindung löschen
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php endif; ?>

            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>
</html>
