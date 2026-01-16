<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\SystemUpdater;
use App\Utils\AuditLogger;

if (!Permissions::check(['admin'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$updater = new SystemUpdater();
$currentVersion = $updater->getCurrentVersion();
$updateInfo = null;
$checking = false;
$versionAge = $updater->getVersionAge();
$isUpdateRecommended = $updater->isUpdateRecommended();
$isPreRelease = $updater->isPreRelease();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        Flash::set('error', 'Ungültiger CSRF-Token. Bitte versuchen Sie es erneut.');
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    if (isset($_POST['check_updates'])) {
        $checking = true;
        $forceRefresh = isset($_POST['force_refresh']);
        $includePreRelease = isset($_POST['include_prerelease']) && $_POST['include_prerelease'] === '1' ? true : null;
        $updateInfo = $updater->checkForUpdatesCached($forceRefresh, $includePreRelease);

        // Log the check action
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log(
            $_SESSION['userid'],
            'system_update_check',
            json_encode(['result' => $updateInfo, 'cached' => $updateInfo['cached'] ?? false, 'force_refresh' => $forceRefresh, 'include_prerelease' => $includePreRelease !== null]),
            'System',
            0
        );
    } elseif (isset($_POST['clear_cache'])) {
        if ($updater->clearCache()) {
            Flash::set('success', 'Update-Cache wurde erfolgreich geleert.');
        } else {
            Flash::set('error', 'Fehler beim Leeren des Update-Caches.');
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    } elseif (isset($_POST['install_update'])) {
        $downloadUrl = $_POST['download_url'] ?? '';
        $newVersion = $_POST['new_version'] ?? '';
        $isPreRelease = isset($_POST['is_prerelease']) && $_POST['is_prerelease'] === '1';

        // Check if this is an AJAX request
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        // Input validation
        if (!filter_var($downloadUrl, FILTER_VALIDATE_URL) || !str_starts_with($downloadUrl, 'https://api.github.com/')) {
            $errorMsg = 'Ungültige Download-URL. Updates können nur von GitHub heruntergeladen werden.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => true, 'message' => $errorMsg]);
                session_write_close();
                exit();
            } else {
                Flash::set('error', $errorMsg);
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            }
        }

        if (!preg_match('/^v?\d+(\.\d+){0,4}(-[a-zA-Z0-9.-]+)?$/', $newVersion)) {
            $errorMsg = 'Ungültiges Versionsformat.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => true, 'message' => $errorMsg]);
                session_write_close();
                exit();
            } else {
                Flash::set('error', $errorMsg);
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            }
        }

        if ($downloadUrl && $newVersion) {
            $installResult = $updater->downloadAndApplyUpdate($downloadUrl, $newVersion, $isPreRelease);

            // Log the installation attempt
            $auditLogger = new AuditLogger($pdo);
            $auditLogger->log(
                $_SESSION['userid'],
                'system_update_install',
                json_encode(['version' => $newVersion, 'result' => $installResult]),
                'System',
                0
            );

            // Handle AJAX vs normal form submission differently
            if ($isAjax) {
                // Return JSON for AJAX request
                header('Content-Type: application/json');
                if ($installResult['success']) {
                    $_SESSION['composer_pending'] = isset($installResult['composer_pending']) && $installResult['composer_pending'];
                    echo json_encode([
                        'success' => true,
                        'message' => $installResult['message'],
                        'composer_pending' => $_SESSION['composer_pending']
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => true,
                        'message' => $installResult['message']
                    ]);
                }
                session_write_close();
                exit();
            } else {
                // Normal form submission - use redirects and flash messages
                if ($installResult['success']) {
                    $_SESSION['composer_pending'] = isset($installResult['composer_pending']) && $installResult['composer_pending'];
                    Flash::set('success', $installResult['message']);
                } else {
                    Flash::set('error', $installResult['message']);
                }
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            }
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => true,
                    'message' => 'Ungültige Update-Parameter. Bitte versuchen Sie es erneut.'
                ]);
                session_write_close();
                exit();
            } else {
                Flash::set('error', 'Ungültige Update-Parameter. Bitte versuchen Sie es erneut.');
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    $SITE_TITLE = 'System Updates';
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
                        <h1 class="mb-0">System-Updates</h1>
                    </div>
                    <?php Flash::render(); ?>

                    <!-- Current Version Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Aktuelle Version</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($isPreRelease): ?>
                                <div class="alert alert-warning mb-3">
                                    <i class="fa-solid fa-flask"></i> <strong>Pre-Release Version:</strong>
                                    Sie verwenden eine Entwickler- oder Vorschau-Version.
                                </div>
                            <?php endif; ?>

                            <?php if ($isUpdateRecommended && !$checking): ?>
                                <div class="alert alert-info mb-3">
                                    <strong>Update empfohlen:</strong>
                                    Ihre Version ist <?= $versionAge ?> Tage alt. Es wird empfohlen, auf Updates zu prüfen.
                                </div>
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6">
                                    <dl class="row mb-0">
                                        <dt class="col-sm-4">Version:</dt>
                                        <dd class="col-sm-8">
                                            <strong><?= htmlspecialchars($currentVersion['version']) ?></strong>
                                            <?php if ($isPreRelease): ?>
                                                <span class="badge bg-warning text-dark ms-1">Pre-Release</span>
                                            <?php endif; ?>
                                        </dd>

                                        <dt class="col-sm-4">Aktualisiert am:</dt>
                                        <dd class="col-sm-8">
                                            <?= htmlspecialchars($currentVersion['updated_at']) ?>
                                            <small class="text-muted">(vor <?= $versionAge ?> Tagen)</small>
                                        </dd>

                                        <dt class="col-sm-4">Build-Nummer:</dt>
                                        <dd class="col-sm-8"><?= htmlspecialchars($currentVersion['build_number']) ?></dd>

                                        <dt class="col-sm-4">Commit-Hash:</dt>
                                        <dd class="col-sm-8"><code><?= htmlspecialchars(substr($currentVersion['commit_hash'], 0, 8)) ?></code></dd>
                                    </dl>
                                </div>
                                <div class="col-md-6 d-flex align-items-center">
                                    <div class="w-100">
                                        <form method="post" id="check-updates-form" class="mb-2">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="check_updates" value="1">
                                            <input type="hidden" name="include_prerelease" id="include-prerelease-hidden" value="0">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fa-solid fa-sync"></i> Auf Updates prüfen
                                            </button>
                                        </form>

                                        <?php if (!$isPreRelease): ?>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" id="include-prerelease-check" name="include_prerelease_ui" value="1">
                                                <label class="form-check-label" for="include-prerelease-check">
                                                    <small><i class="fa-solid fa-flask"></i> Pre-Release-Versionen einschließen</small>
                                                </label>
                                            </div>
                                        <?php endif; ?>

                                        <div class="d-flex gap-2">
                                            <form method="post" class="flex-fill" id="force-refresh-form">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="check_updates" value="1">
                                                <input type="hidden" name="force_refresh" value="1">
                                                <input type="hidden" name="include_prerelease" id="force-refresh-prerelease" value="0">
                                                <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                                    <i class="fa-solid fa-sync"></i> Neu laden
                                                </button>
                                            </form>
                                            <form method="post" class="flex-fill">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <button type="submit" name="clear_cache" class="btn btn-outline-secondary btn-sm w-100">
                                                    <i class="fa-solid fa-trash"></i> Cache leeren
                                                </button>
                                            </form>
                                        </div>

                                        <?php if (!$isPreRelease): ?>
                                            <script>
                                                // Sync checkbox with hidden inputs in both forms
                                                const prereleaseCheckbox = document.getElementById('include-prerelease-check');
                                                const mainFormHidden = document.getElementById('include-prerelease-hidden');
                                                const forceRefreshHidden = document.getElementById('force-refresh-prerelease');

                                                if (prereleaseCheckbox) {
                                                    prereleaseCheckbox.addEventListener('change', function() {
                                                        const value = this.checked ? '1' : '0';
                                                        if (mainFormHidden) mainFormHidden.value = value;
                                                        if (forceRefreshHidden) forceRefreshHidden.value = value;
                                                    });
                                                }
                                            </script>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($checking && $updateInfo): ?>
                        <!-- Update Information Card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    Update-Informationen
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($updateInfo['error'])): ?>
                                    <div class="alert alert-danger">
                                        <i class="fa-solid fa-exclamation-triangle"></i>
                                        <?= htmlspecialchars($updateInfo['message']) ?>
                                    </div>
                                <?php elseif ($updateInfo['available']): ?>
                                    <?php
                                    $urgency = $updater->getUpdateUrgency();
                                    $urgencyColors = [
                                        'low' => 'info',
                                        'medium' => 'warning',
                                        'high' => 'danger',
                                        'critical' => 'danger'
                                    ];
                                    $urgencyLabels = [
                                        'low' => 'Niedrige Priorität',
                                        'medium' => 'Mittlere Priorität',
                                        'high' => 'Hohe Priorität',
                                        'critical' => 'Kritisch'
                                    ];
                                    $alertClass = $urgencyColors[$urgency] ?? 'success';
                                    ?>
                                    <div class="alert alert-<?= $alertClass ?>">
                                        <h5><i class="fa-solid fa-check-circle"></i> Neues Update verfügbar!</h5>
                                        <p class="mb-0">
                                            Eine neue Version ist verfügbar: <strong><?= htmlspecialchars($updateInfo['latest_version']) ?></strong>
                                            <?php if (isset($updateInfo['is_prerelease']) && $updateInfo['is_prerelease']): ?>
                                                <span class="badge bg-warning text-dark ms-1"><i class="fa-solid fa-flask"></i> Pre-Release</span>
                                            <?php endif; ?>
                                            <span class="badge bg-<?= $alertClass ?> ms-2"><?= $urgencyLabels[$urgency] ?? 'Update verfügbar' ?></span>
                                            <?php if (isset($updateInfo['cached']) && $updateInfo['cached']): ?>
                                                <span class="badge bg-secondary ms-1" title="Gecachte Daten"><i class="fa-solid fa-clock"></i> Gecacht</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>

                                    <?php if (isset($updateInfo['is_prerelease']) && $updateInfo['is_prerelease'] && $isPreRelease): ?>
                                        <div class="alert alert-warning mb-3">
                                            <i class="fa-solid fa-exclamation-triangle"></i>
                                            <strong>Pre-Release zu Pre-Release Update:</strong>
                                            Sie wechseln von einer Pre-Release zur nächsten Pre-Release Version.
                                            Diese Versionen können instabil sein und unerwartete Fehler enthalten.
                                        </div>
                                    <?php elseif (isset($updateInfo['is_prerelease']) && $updateInfo['is_prerelease'] && !$isPreRelease): ?>
                                        <div class="alert alert-warning mb-3">
                                            <i class="fa-solid fa-exclamation-triangle"></i>
                                            <strong>Pre-Release Update:</strong>
                                            Diese Version ist eine Vorabversion und kann instabil sein oder unerwartete Fehler enthalten.
                                        </div>
                                    <?php endif; ?>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Version Details:</h6>
                                            <dl class="row">
                                                <dt class="col-sm-5">Aktuelle Version:</dt>
                                                <dd class="col-sm-7"><?= htmlspecialchars($updateInfo['current_version']) ?></dd>

                                                <dt class="col-sm-5">Neue Version:</dt>
                                                <dd class="col-sm-7"><strong><?= htmlspecialchars($updateInfo['latest_version']) ?></strong></dd>

                                                <dt class="col-sm-5">Release-Name:</dt>
                                                <dd class="col-sm-7"><?= htmlspecialchars($updateInfo['release_name']) ?></dd>

                                                <?php if (isset($updateInfo['published_at'])): ?>
                                                    <dt class="col-sm-5">Veröffentlicht:</dt>
                                                    <dd class="col-sm-7"><?= date('d.m.Y H:i', strtotime($updateInfo['published_at'])) ?></dd>
                                                <?php endif; ?>
                                            </dl>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Aktionen:</h6>

                                            <!-- Install Update Button -->
                                            <form method="post" id="install-update-form" class="mb-2">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="install_update" value="1">
                                                <input type="hidden" name="download_url" value="<?= htmlspecialchars($updateInfo['download_url']) ?>">
                                                <input type="hidden" name="new_version" value="<?= htmlspecialchars($updateInfo['latest_version']) ?>">
                                                <input type="hidden" name="is_prerelease" value="<?= isset($updateInfo['is_prerelease']) && $updateInfo['is_prerelease'] ? '1' : '0' ?>">
                                                <button type="button" id="install-update-btn" class="btn btn-success w-100">
                                                    <i class="fa-solid fa-download"></i> Update jetzt installieren
                                                </button>
                                            </form>

                                            <!-- Progress Modal -->
                                            <div class="modal fade" id="update-progress-modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">
                                                                <i class="fa-solid fa-download me-2"></i>
                                                                Update wird installiert
                                                            </h5>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="text-center mb-3">
                                                                <div class="spinner-border text-primary" role="status">
                                                                    <span class="visually-hidden">Wird geladen...</span>
                                                                </div>
                                                            </div>
                                                            <div class="progress mb-3" style="height: 25px;">
                                                                <div class="progress-bar progress-bar-striped progress-bar-animated"
                                                                    role="progressbar"
                                                                    id="update-progress-bar"
                                                                    style="width: 0%">
                                                                    <span id="update-progress-text">0%</span>
                                                                </div>
                                                            </div>
                                                            <div id="update-status-text" class="text-center">
                                                                <small class="text-muted">Update wird vorbereitet...</small>
                                                            </div>
                                                            <div class="alert alert-info mt-3 mb-0">
                                                                <small>
                                                                    <i class="fa-solid fa-info-circle me-1"></i>
                                                                    <strong>Hinweis:</strong> Bitte schließen Sie dieses Fenster nicht.
                                                                    Der Vorgang kann mehrere Minuten dauern.
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <script>
                                                document.getElementById('install-update-btn').addEventListener('click', async function() {
                                                    const newVersion = <?= json_encode($updateInfo['latest_version']) ?>;
                                                    const confirmed = await showConfirm(
                                                        'Update auf Version ' + newVersion + ' installieren?\n\n' +
                                                        'Ein Backup wird automatisch erstellt.\n' +
                                                        'Dieser Vorgang kann einige Minuten dauern.', {
                                                            title: 'Update installieren?',
                                                            confirmText: 'Installieren',
                                                            cancelText: 'Abbrechen',
                                                            confirmClass: 'btn-success',
                                                            danger: false
                                                        }
                                                    );

                                                    if (confirmed) {
                                                        // Show progress modal
                                                        const progressModal = new bootstrap.Modal(document.getElementById('update-progress-modal'));
                                                        progressModal.show();

                                                        // Disable backdrop dismiss
                                                        const modalElement = document.getElementById('update-progress-modal');
                                                        modalElement.setAttribute('data-bs-backdrop', 'static');
                                                        modalElement.setAttribute('data-bs-keyboard', 'false');

                                                        // Simulate progress (since we can't get real-time updates from PHP)
                                                        const progressBar = document.getElementById('update-progress-bar');
                                                        const progressText = document.getElementById('update-progress-text');
                                                        const statusText = document.getElementById('update-status-text');

                                                        const steps = [{
                                                                percent: 10,
                                                                text: 'Download wird vorbereitet...'
                                                            },
                                                            {
                                                                percent: 25,
                                                                text: 'Update wird heruntergeladen...'
                                                            },
                                                            {
                                                                percent: 40,
                                                                text: 'Dateien werden extrahiert...'
                                                            },
                                                            {
                                                                percent: 55,
                                                                text: 'Backup wird erstellt...'
                                                            },
                                                            {
                                                                percent: 70,
                                                                text: 'Update wird installiert...'
                                                            },
                                                            {
                                                                percent: 85,
                                                                text: 'Dateien werden kopiert...'
                                                            },
                                                            {
                                                                percent: 95,
                                                                text: 'Installation wird abgeschlossen...'
                                                            }
                                                        ];

                                                        let currentStep = 0;
                                                        const updateProgress = () => {
                                                            if (currentStep < steps.length) {
                                                                const step = steps[currentStep];
                                                                progressBar.style.width = step.percent + '%';
                                                                progressText.textContent = step.percent + '%';
                                                                statusText.innerHTML = '<small class="text-muted">' + step.text + '</small>';
                                                                currentStep++;
                                                            }
                                                        };

                                                        // Update progress every 2 seconds
                                                        const progressInterval = setInterval(updateProgress, 2000);
                                                        updateProgress(); // Start immediately

                                                        // Submit via AJAX instead of form submit to keep modal visible
                                                        const formData = new FormData(document.getElementById('install-update-form'));

                                                        try {
                                                            const response = await fetch(window.location.href, {
                                                                method: 'POST',
                                                                body: formData,
                                                                headers: {
                                                                    'X-Requested-With': 'XMLHttpRequest'
                                                                }
                                                            });

                                                            clearInterval(progressInterval);

                                                            // Check HTTP response status
                                                            if (!response.ok) {
                                                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                                                            }

                                                            // Parse JSON response
                                                            const result = await response.json();

                                                            if (result.success) {
                                                                // Set to 100%
                                                                progressBar.style.width = '100%';
                                                                progressText.textContent = '100%';
                                                                statusText.innerHTML = '<small class="text-success"><i class="fa-solid fa-check-circle"></i> Update abgeschlossen!</small>';

                                                                // Wait a moment then reload
                                                                setTimeout(() => {
                                                                    window.location.reload();
                                                                }, 1500);
                                                            } else {
                                                                // Show error
                                                                progressBar.classList.remove('progress-bar-animated');
                                                                progressBar.classList.add('bg-danger');
                                                                const errorMsg = result.message || 'Unbekannter Fehler beim Update.';
                                                                statusText.innerHTML = '<small class="text-danger"><i class="fa-solid fa-exclamation-triangle"></i> </small>';
                                                                const errorTextNode = document.createTextNode(errorMsg);
                                                                statusText.querySelector('small').appendChild(errorTextNode);

                                                                // Show close button
                                                                setTimeout(() => {
                                                                    modalElement.querySelector('.modal-header').innerHTML = `
                                                                <h5 class="modal-title text-danger">
                                                                    <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                                                    Update fehlgeschlagen
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            `;
                                                                    modalElement.querySelector('.modal-body .alert-info').classList.add('d-none');
                                                                }, 1000);
                                                            }

                                                        } catch (error) {
                                                            clearInterval(progressInterval);
                                                            progressBar.classList.remove('progress-bar-animated');
                                                            progressBar.classList.add('bg-danger');
                                                            statusText.innerHTML = '<small class="text-danger"><i class="fa-solid fa-exclamation-triangle"></i> Netzwerkfehler: </small>';
                                                            const errorTextNode = document.createTextNode(error.message);
                                                            statusText.querySelector('small').appendChild(errorTextNode);

                                                            // Show close button
                                                            setTimeout(() => {
                                                                modalElement.querySelector('.modal-header').innerHTML = `
                                                            <h5 class="modal-title text-danger">
                                                                <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                                                Update fehlgeschlagen
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        `;
                                                                modalElement.querySelector('.modal-body .alert-info').classList.add('d-none');
                                                            }, 1000);
                                                        }
                                                    }
                                                });
                                            </script>

                                            <?php if (isset($updateInfo['html_url'])): ?>
                                                <a href="<?= htmlspecialchars($updateInfo['html_url']) ?>"
                                                    target="_blank"
                                                    class="btn btn-outline-primary w-100 mb-2">
                                                    <i class="fa-solid fa-external-link-alt"></i> Release auf GitHub ansehen
                                                </a>
                                            <?php endif; ?>

                                            <?php if (isset($updateInfo['download_url'])): ?>
                                                <a href="<?= htmlspecialchars($updateInfo['download_url']) ?>"
                                                    class="btn btn-outline-secondary w-100">
                                                    <i class="fa-solid fa-file-zipper"></i> ZIP manuell herunterladen
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($updateInfo['release_notes'])): ?>
                                        <hr>
                                        <h6>Release-Notizen:</h6>
                                        <div class="border rounded p-3 bg-dark" style="max-height: 400px; overflow-y: auto;">
                                            <?= $updater->getFormattedReleaseNotes($updateInfo['release_notes']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="alert alert-info mt-3">
                                        <strong><i class="fa-solid fa-info-circle"></i> Hinweis:</strong>
                                        Das Update wird automatisch installiert und ein Backup wird im Verzeichnis <code>system/updates/</code> erstellt.
                                        Bei Problemen können Sie das Backup manuell wiederherstellen.
                                        <br><strong>Wichtig:</strong> Erstellen Sie zusätzlich ein manuelles Backup Ihrer Datenbank!
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fa-solid fa-check-circle"></i>
                                        Ihr System ist auf dem neuesten Stand. Es sind keine Updates verfügbar.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Composer Installation Modal -->
    <div class="modal fade" id="composer-modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-box-open me-2"></i>
                        Composer-Abhängigkeiten werden installiert
                    </h5>
                </div>
                <div class="modal-body">
                    <div id="composer-status-content">
                        <div class="text-center mb-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Wird geladen...</span>
                            </div>
                        </div>
                        <div id="composer-status-text" class="text-center">
                            <p class="mb-2">Composer-Abhängigkeiten werden installiert...</p>
                            <small class="text-muted">Dies kann einige Minuten dauern. Bitte warten Sie.</small>
                        </div>
                    </div>

                    <div id="composer-success-content" style="display: none;">
                        <div class="alert alert-success mb-3">
                            <i class="fa-solid fa-check-circle me-2"></i>
                            <strong>Erfolg!</strong> Composer-Abhängigkeiten wurden erfolgreich installiert.
                        </div>
                        <p class="mb-3">Das Update ist vollständig abgeschlossen. Bitte laden Sie die Seite neu, um die Änderungen zu übernehmen.</p>
                        <button type="button" id="reload-page-btn" class="btn btn-primary w-100">
                            <i class="fa-solid fa-sync me-2"></i>Seite neu laden
                        </button>
                    </div>

                    <div id="composer-error-content" style="display: none;">
                        <div class="alert alert-danger mb-3">
                            <i class="fa-solid fa-exclamation-triangle me-2"></i>
                            <strong>Fehler!</strong> Composer-Installation fehlgeschlagen.
                        </div>
                        <p id="composer-error-message" class="mb-3"></p>
                        <p class="mb-3">
                            <strong>Manuelle Installation erforderlich:</strong><br>
                            Bitte führen Sie im Terminal im Anwendungsverzeichnis aus:<br>
                            <code>composer install --no-dev --optimize-autoloader</code>
                        </p>
                        <button type="button" id="retry-composer-btn" class="btn btn-secondary w-100 mb-2">
                            <i class="fa-solid fa-rotate-right me-2"></i>Erneut versuchen
                        </button>
                        <button type="button" id="dismiss-composer-btn" class="btn btn-outline-secondary w-100">
                            <i class="fa-solid fa-times me-2"></i>Schließen (Update manuell abschließen)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let composerModal = null;
        let composerCheckInterval = null;

        // Check if we should show the composer modal on page load
        <?php if (isset($_SESSION['composer_pending']) && $_SESSION['composer_pending']): ?>
            <?php unset($_SESSION['composer_pending']); ?>

            document.addEventListener('DOMContentLoaded', function() {
                showComposerModal();
            });
        <?php endif; ?>

        function showComposerModal() {
            composerModal = new bootstrap.Modal(document.getElementById('composer-modal'));
            composerModal.show();

            // Start checking composer status
            checkComposerStatus();
        }

        function checkComposerStatus() {
            fetch('<?= BASE_PATH ?>api/composer-status.php?action=check', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.pending) {
                        // Composer installation is pending, trigger it
                        executeComposerInstall();
                    } else {
                        // No pending installation, close modal
                        dismissComposerModal();
                    }
                })
                .catch(error => {
                    console.error('Error checking composer status:', error);
                    showComposerError('Fehler beim Prüfen des Composer-Status: ' + error.message);
                });
        }

        function executeComposerInstall() {
            fetch('<?= BASE_PATH ?>api/composer-status.php?action=execute', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showComposerSuccess();
                    } else {
                        showComposerError(data.message || 'Unbekannter Fehler bei der Composer-Installation.');
                    }
                })
                .catch(error => {
                    console.error('Error executing composer install:', error);
                    showComposerError('Fehler beim Ausführen von Composer: ' + error.message);
                });
        }

        function showComposerSuccess() {
            document.getElementById('composer-status-content').style.display = 'none';
            document.getElementById('composer-error-content').style.display = 'none';
            document.getElementById('composer-success-content').style.display = 'block';
        }

        function showComposerError(message) {
            document.getElementById('composer-status-content').style.display = 'none';
            document.getElementById('composer-success-content').style.display = 'none';
            document.getElementById('composer-error-message').textContent = message;
            document.getElementById('composer-error-content').style.display = 'block';
        }

        function retryComposerInstall() {
            // Reset to status view
            document.getElementById('composer-status-content').style.display = 'block';
            document.getElementById('composer-success-content').style.display = 'none';
            document.getElementById('composer-error-content').style.display = 'none';

            // Retry installation
            executeComposerInstall();
        }

        function dismissComposerModal() {
            if (composerModal) {
                composerModal.hide();
            }
        }

        // Attach event listeners for buttons (replacing inline onclick)
        document.addEventListener('DOMContentLoaded', function() {
            const reloadBtn = document.getElementById('reload-page-btn');
            if (reloadBtn) {
                reloadBtn.addEventListener('click', function() {
                    location.reload();
                });
            }

            const retryBtn = document.getElementById('retry-composer-btn');
            if (retryBtn) {
                retryBtn.addEventListener('click', retryComposerInstall);
            }

            const dismissBtn = document.getElementById('dismiss-composer-btn');
            if (dismissBtn) {
                dismissBtn.addEventListener('click', dismissComposerModal);
            }
        });
    </script>

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>