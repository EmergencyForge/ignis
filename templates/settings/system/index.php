<?php
/**
 * View: System-Einstellungen
 *
 * @var \PDO $pdo
 */

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Security\CsrfProtection;
use App\Session\SessionManager;
use App\Utils\SystemUpdater;
use App\Utils\AuditLogger;

// CSRF-Token sicherstellen (idempotent — generiert beim ersten Aufruf,
// liefert den existierenden Token für alle Folge-Renders).
$csrfToken = CsrfProtection::getToken();

$updater = new SystemUpdater();
$currentVersion = $updater->getCurrentVersion();
$updateInfo = null;
$checking = false;
$versionAge = $updater->getVersionAge();
$isUpdateRecommended = $updater->isUpdateRecommended();
$isPreRelease = $updater->isPreRelease();
$isDevMode = isset($_GET['dev']);
$devBranches = [];
$devBranchInfo = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token validation — hash_equals statt validateToken(), damit
    // der Token NICHT rotiert wird (mehrere Formulare auf der Page teilen
    // sich denselben Token; rotation würde den 2. Submit nach Reload sprengen).
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
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
            SessionManager::userId(),
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
        if (!filter_var($downloadUrl, FILTER_VALIDATE_URL) || (!str_starts_with($downloadUrl, 'https://api.github.com/') && !str_starts_with($downloadUrl, 'https://github.com/'))) {
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

        if (!preg_match('/^v?\d+(\.\d+){0,4}(-[a-zA-Z0-9.-]+)?$/', $newVersion) &&
            !preg_match('/^dev-[a-zA-Z0-9._\/-]+-[a-f0-9]{7,8}$/', $newVersion)) {
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

            // Fallback: Wenn Release-Asset-URL fehlschlägt, Zipball-URL versuchen
            // (Abwärtskompatibilität für ältere SystemUpdater-Versionen die nur Zipball akzeptieren)
            $fallbackUrl = $_POST['download_url_fallback'] ?? '';
            if (!$installResult['success'] && $fallbackUrl && $fallbackUrl !== $downloadUrl
                && filter_var($fallbackUrl, FILTER_VALIDATE_URL)
                && str_starts_with($fallbackUrl, 'https://api.github.com/')) {
                $installResult = $updater->downloadAndApplyUpdate($fallbackUrl, $newVersion, $isPreRelease);
            }

            // Log the installation attempt
            $auditLogger = new AuditLogger($pdo);
            $auditLogger->log(
                SessionManager::userId(),
                'system_update_install',
                json_encode(['version' => $newVersion, 'result' => $installResult]),
                'System',
                0
            );

            // Handle AJAX vs normal form submission differently
            $composerPending = isset($installResult['composer_pending']) && $installResult['composer_pending'];
            if ($isAjax) {
                // Return JSON for AJAX request
                header('Content-Type: application/json');
                if ($installResult['success']) {
                    SessionManager::setComposerPending($composerPending);
                    echo json_encode([
                        'success' => true,
                        'message' => $installResult['message'],
                        'composer_pending' => $composerPending
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
                    SessionManager::setComposerPending($composerPending);
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
    } elseif (isset($_POST['dev_install_branch'])) {
        $branch = $_POST['dev_branch'] ?? '';
        $commitSha = $_POST['dev_commit_sha'] ?? '';

        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (empty($branch) || !preg_match('/^[a-zA-Z0-9._\/-]+$/', $branch)) {
            $errorMsg = 'Ungültiger Branch-Name.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => true, 'message' => $errorMsg]);
                session_write_close();
                exit();
            } else {
                Flash::set('error', $errorMsg);
                header("Location: " . $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') === false ? '?dev' : ''));
                exit();
            }
        }

        if (empty($commitSha) || !preg_match('/^[a-f0-9]{40}$/', $commitSha)) {
            $errorMsg = 'Ungültiger Commit-SHA.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => true, 'message' => $errorMsg]);
                session_write_close();
                exit();
            } else {
                Flash::set('error', $errorMsg);
                header("Location: " . $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') === false ? '?dev' : ''));
                exit();
            }
        }

        $installResult = $updater->downloadAndApplyBranchUpdate($branch, $commitSha);

        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log(
            SessionManager::userId(),
            'system_update_dev_install',
            json_encode(['branch' => $branch, 'commit' => $commitSha, 'result' => $installResult]),
            'System',
            0
        );

        $composerPending = isset($installResult['composer_pending']) && $installResult['composer_pending'];
        if ($isAjax) {
            header('Content-Type: application/json');
            if ($installResult['success']) {
                SessionManager::setComposerPending($composerPending);
                echo json_encode([
                    'success' => true,
                    'message' => $installResult['message'],
                    'composer_pending' => $composerPending
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
            if ($installResult['success']) {
                SessionManager::setComposerPending($composerPending);
                Flash::set('success', $installResult['message']);
            } else {
                Flash::set('error', $installResult['message']);
            }
            header("Location: " . $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') === false ? '?dev' : ''));
            exit();
        }
    }
}

// Dev mode: fetch branches
if ($isDevMode) {
    $devBranches = $updater->fetchBranches();
    if (isset($_GET['branch']) && !empty($_GET['branch'])) {
        $selectedBranch = $_GET['branch'];
        $devBranchInfo = $updater->fetchBranchLatestCommit($selectedBranch);
    }
}
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    $SITE_TITLE = 'System Updates';
    include __DIR__ . '/../../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-6">
                    <div class="mb-6 flex items-center justify-between">
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
                                <div class="ignis-alert ignis-alert--warning mb-3">
                                    <i class="fa-solid fa-flask"></i> <strong>Pre-Release Version:</strong>
                                    Sie verwenden eine Entwickler- oder Vorschau-Version.
                                </div>
                            <?php endif; ?>

                            <?php if ($isUpdateRecommended && !$checking): ?>
                                <div class="ignis-alert ignis-alert--info mb-3">
                                    <strong>Update empfohlen:</strong>
                                    Ihre Version ist <?= $versionAge ?> Tage alt. Es wird empfohlen, auf Updates zu prüfen.
                                </div>
                            <?php endif; ?>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <dl class="mb-0 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                                        <dt>Version:</dt>
                                        <dd>
                                            <strong><?= htmlspecialchars($currentVersion['version']) ?></strong>
                                            <?php if ($isPreRelease): ?>
                                                <span class="ignis-chip ignis-chip--warning text-black ml-1">Pre-Release</span>
                                            <?php endif; ?>
                                        </dd>

                                        <dt>Aktualisiert am:</dt>
                                        <dd>
                                            <?= htmlspecialchars($currentVersion['updated_at']) ?>
                                            <small class="text-gray-400">(vor <?= $versionAge ?> Tagen)</small>
                                        </dd>

                                        <dt>Build-Nummer:</dt>
                                        <dd><?= htmlspecialchars($currentVersion['build_number']) ?></dd>

                                        <dt>Commit-Hash:</dt>
                                        <dd><code><?= htmlspecialchars(substr($currentVersion['commit_hash'], 0, 8)) ?></code></dd>
                                    </dl>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-full">
                                        <form method="post" id="check-updates-form" class="mb-2">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="check_updates" value="1">
                                            <input type="hidden" name="include_prerelease" id="include-prerelease-hidden" value="0">
                                            <button type="submit" class="ignis-btn ignis-btn--soft-primary w-full">
                                                <i class="fa-solid fa-sync"></i> Auf Updates prüfen
                                            </button>
                                        </form>

                                        <?php if (!$isPreRelease): ?>
                                            <label class="ignis-checkbox" for="include-prerelease-check"><input type="checkbox" id="include-prerelease-check" name="include_prerelease_ui" value="1"><span>
                                                    <small><i class="fa-solid fa-flask"></i> Pre-Release-Versionen einschließen</small>
                                                </span></label>
                                        <?php endif; ?>

                                        <div class="flex gap-2">
                                            <form method="post" class="flex-1" id="force-refresh-form">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="check_updates" value="1">
                                                <input type="hidden" name="force_refresh" value="1">
                                                <input type="hidden" name="include_prerelease" id="force-refresh-prerelease" value="0">
                                                <button type="submit" class="ignis-btn ignis-btn--outline-primary ignis-btn--sm w-full">
                                                    <i class="fa-solid fa-sync"></i> Neu laden
                                                </button>
                                            </form>
                                            <form method="post" class="flex-1">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <button type="submit" name="clear_cache" class="ignis-btn ignis-btn--outline-secondary ignis-btn--sm w-full">
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
                                    <div class="ignis-alert ignis-alert--danger">
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
                                                <span class="ignis-chip ignis-chip--warning text-black ml-1"><i class="fa-solid fa-flask"></i> Pre-Release</span>
                                            <?php endif; ?>
                                            <span class="badge bg-<?= $alertClass ?> ml-2"><?= $urgencyLabels[$urgency] ?? 'Update verfügbar' ?></span>
                                            <?php if (isset($updateInfo['cached']) && $updateInfo['cached']): ?>
                                                <span class="ignis-chip ml-1" title="Gecachte Daten"><i class="fa-solid fa-clock"></i> Gecacht</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>

                                    <?php if (isset($updateInfo['is_prerelease']) && $updateInfo['is_prerelease'] && $isPreRelease): ?>
                                        <div class="ignis-alert ignis-alert--warning mb-3">
                                            <i class="fa-solid fa-exclamation-triangle"></i>
                                            <strong>Pre-Release zu Pre-Release Update:</strong>
                                            Sie wechseln von einer Pre-Release zur nächsten Pre-Release Version.
                                            Diese Versionen können instabil sein und unerwartete Fehler enthalten.
                                        </div>
                                    <?php elseif (isset($updateInfo['is_prerelease']) && $updateInfo['is_prerelease'] && !$isPreRelease): ?>
                                        <div class="ignis-alert ignis-alert--warning mb-3">
                                            <i class="fa-solid fa-exclamation-triangle"></i>
                                            <strong>Pre-Release Update:</strong>
                                            Diese Version ist eine Vorabversion und kann instabil sein oder unerwartete Fehler enthalten.
                                        </div>
                                    <?php endif; ?>

                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <h6>Version Details:</h6>
                                            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                                                <dt>Aktuelle Version:</dt>
                                                <dd><?= htmlspecialchars($updateInfo['current_version']) ?></dd>

                                                <dt>Neue Version:</dt>
                                                <dd><strong><?= htmlspecialchars($updateInfo['latest_version']) ?></strong></dd>

                                                <dt>Release-Name:</dt>
                                                <dd><?= htmlspecialchars($updateInfo['release_name']) ?></dd>

                                                <?php if (isset($updateInfo['published_at'])): ?>
                                                    <dt>Veröffentlicht:</dt>
                                                    <dd><?= \App\Helpers\DateTimeHelper::formatShortLocal($updateInfo['published_at']) ?></dd>
                                                <?php endif; ?>
                                            </dl>
                                        </div>
                                        <div>
                                            <h6>Aktionen:</h6>

                                            <!-- Install Update Button -->
                                            <form method="post" id="install-update-form" class="mb-2">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="install_update" value="1">
                                                <input type="hidden" name="download_url" value="<?= htmlspecialchars($updateInfo['download_url']) ?>">
                                                <input type="hidden" name="download_url_fallback" value="<?= htmlspecialchars($updateInfo['download_url_fallback'] ?? '') ?>">
                                                <input type="hidden" name="new_version" value="<?= htmlspecialchars($updateInfo['latest_version']) ?>">
                                                <input type="hidden" name="is_prerelease" value="<?= isset($updateInfo['is_prerelease']) && $updateInfo['is_prerelease'] ? '1' : '0' ?>">
                                                <button type="button" id="install-update-btn" class="ignis-btn ignis-btn--success w-full">
                                                    <i class="fa-solid fa-download"></i> Update jetzt installieren
                                                </button>
                                            </form>

                                            <?php include __DIR__ . '/../../../assets/components/settings/system/_update-progress-modal.php'; ?>

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
                                                                statusText.innerHTML = '<small class="text-gray-400">' + step.text + '</small>';
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
                                                                statusText.innerHTML = '<small class="text-[#6abf76]"><i class="fa-solid fa-check-circle"></i> Update abgeschlossen!</small>';

                                                                // Wait a moment then reload
                                                                setTimeout(() => {
                                                                    window.location.reload();
                                                                }, 1500);
                                                            } else {
                                                                // Show error
                                                                progressBar.classList.remove('progress-bar-animated');
                                                                progressBar.classList.add('bg-danger');
                                                                const errorMsg = result.message || 'Unbekannter Fehler beim Update.';
                                                                statusText.innerHTML = '<small class="text-[#d46b6b]"><i class="fa-solid fa-exclamation-triangle"></i> </small>';
                                                                const errorTextNode = document.createTextNode(errorMsg);
                                                                statusText.querySelector('small').appendChild(errorTextNode);

                                                                // Show close button
                                                                setTimeout(() => {
                                                                    modalElement.querySelector('.modal-header').innerHTML = `
                                                                <h5 class="modal-title text-[#d46b6b]">
                                                                    <i class="fa-solid fa-exclamation-triangle mr-2"></i>
                                                                    Update fehlgeschlagen
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            `;
                                                                    modalElement.querySelector('.modal-body .alert-info').classList.add('hidden');
                                                                }, 1000);
                                                            }

                                                        } catch (error) {
                                                            clearInterval(progressInterval);
                                                            progressBar.classList.remove('progress-bar-animated');
                                                            progressBar.classList.add('bg-danger');
                                                            statusText.innerHTML = '<small class="text-[#d46b6b]"><i class="fa-solid fa-exclamation-triangle"></i> Netzwerkfehler: </small>';
                                                            const errorTextNode = document.createTextNode(error.message);
                                                            statusText.querySelector('small').appendChild(errorTextNode);

                                                            // Show close button
                                                            setTimeout(() => {
                                                                modalElement.querySelector('.modal-header').innerHTML = `
                                                            <h5 class="modal-title text-[#d46b6b]">
                                                                <i class="fa-solid fa-exclamation-triangle mr-2"></i>
                                                                Update fehlgeschlagen
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        `;
                                                                modalElement.querySelector('.modal-body .alert-info').classList.add('hidden');
                                                            }, 1000);
                                                        }
                                                    }
                                                });
                                            </script>

                                            <?php if (isset($updateInfo['html_url'])): ?>
                                                <a href="<?= htmlspecialchars($updateInfo['html_url']) ?>"
                                                    target="_blank"
                                                    class="ignis-btn ignis-btn--outline-primary w-full mb-2">
                                                    <i class="fa-solid fa-external-link-alt"></i> Release auf GitHub ansehen
                                                </a>
                                            <?php endif; ?>

                                            <?php if (isset($updateInfo['download_url'])): ?>
                                                <a href="<?= htmlspecialchars($updateInfo['download_url']) ?>"
                                                    class="ignis-btn ignis-btn--outline-secondary w-full">
                                                    <i class="fa-solid fa-file-zipper"></i> ZIP manuell herunterladen
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($updateInfo['release_notes'])): ?>
                                        <hr>
                                        <h6>Release-Notizen:</h6>
                                        <div class="border rounded p-3 bg-[rgba(0,0,0,0.3)]" style="max-height: 400px; overflow-y: auto;">
                                            <?= $updater->getFormattedReleaseNotes($updateInfo['release_notes']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="ignis-alert ignis-alert--info mt-3">
                                        <strong><i class="fa-solid fa-info-circle"></i> Hinweis:</strong>
                                        Das Update wird automatisch installiert und ein Backup wird im Verzeichnis <code>system/updates/</code> erstellt.
                                        Bei Problemen können Sie das Backup manuell wiederherstellen.
                                        <br><strong>Wichtig:</strong> Erstellen Sie zusätzlich ein manuelles Backup Ihrer Datenbank!
                                    </div>
                                <?php else: ?>
                                    <div class="ignis-alert ignis-alert--info">
                                        <i class="fa-solid fa-check-circle"></i>
                                        Ihr System ist auf dem neuesten Stand. Es sind keine Updates verfügbar.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($isDevMode): ?>
                        <!-- Dev Mode: Branch Update -->
                        <div class="card mb-4 border-warning">
                            <div class="card-header bg-[#c49a2a] bg-opacity-10">
                                <h5 class="mb-0"><i class="fa-solid fa-code-branch mr-2"></i>Entwickler-Modus: Branch-Update</h5>
                            </div>
                            <div class="card-body">
                                <div class="ignis-alert ignis-alert--warning mb-3">
                                    <i class="fa-solid fa-exclamation-triangle"></i>
                                    <strong>Achtung:</strong> Branch-Updates installieren den neuesten Commit eines Branches.
                                    Diese Versionen sind möglicherweise instabil und nicht für den Produktiveinsatz geeignet.
                                </div>

                                <?php if (!empty($devBranches)): ?>
                                    <div class="mb-3">
                                        <label for="dev-branch-select" class="ignis-field__label">Branch auswählen:</label>
                                        <select class="form-select" id="dev-branch-select">
                                            <option value="">-- Branch wählen --</option>
                                            <?php foreach ($devBranches as $branch): ?>
                                                <option value="<?= htmlspecialchars($branch['name']) ?>"
                                                    <?= (isset($selectedBranch) && $selectedBranch === $branch['name']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($branch['name']) ?>
                                                    <?= ($branch['name'] === 'main') ? ' (Standard)' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <script>
                                        document.getElementById('dev-branch-select').addEventListener('change', function() {
                                            if (this.value) {
                                                window.location.href = '?dev&branch=' + encodeURIComponent(this.value);
                                            }
                                        });
                                    </script>

                                    <?php if ($devBranchInfo): ?>
                                        <div class="card bg-[rgba(0,0,0,0.3)] mb-3">
                                            <div class="card-body">
                                                <h6><i class="fa-solid fa-code-commit mr-2"></i>Neuester Commit auf <code><?= htmlspecialchars($selectedBranch) ?></code></h6>
                                                <dl class="mb-0 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                                                    <dt>SHA:</dt>
                                                    <dd><code><?= htmlspecialchars(substr($devBranchInfo['sha'], 0, 8)) ?></code> <small class="text-gray-400">(<?= htmlspecialchars($devBranchInfo['sha']) ?>)</small></dd>

                                                    <dt>Nachricht:</dt>
                                                    <dd><?= htmlspecialchars($devBranchInfo['commit']['message'] ?? '') ?></dd>

                                                    <dt>Autor:</dt>
                                                    <dd><?= htmlspecialchars($devBranchInfo['commit']['author']['name'] ?? '') ?></dd>

                                                    <dt>Datum:</dt>
                                                    <dd>
                                                        <?php
                                                        $commitDate = $devBranchInfo['commit']['author']['date'] ?? null;
                                                        echo $commitDate ? \App\Helpers\DateTimeHelper::formatShortLocal($commitDate) : '-';
                                                        ?>
                                                    </dd>
                                                </dl>

                                                <?php
                                                $currentHash = $currentVersion['commit_hash'] ?? '';
                                                $targetHash = $devBranchInfo['sha'] ?? '';
                                                $isSameCommit = !empty($currentHash) && str_starts_with($targetHash, $currentHash);
                                                ?>

                                                <?php if ($isSameCommit): ?>
                                                    <div class="ignis-alert ignis-alert--info mt-3 mb-0">
                                                        <i class="fa-solid fa-check-circle"></i> Sie sind bereits auf diesem Commit.
                                                    </div>
                                                <?php else: ?>
                                                    <form method="post" id="dev-install-form" class="mt-3">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                        <input type="hidden" name="dev_install_branch" value="1">
                                                        <input type="hidden" name="dev_branch" value="<?= htmlspecialchars($selectedBranch) ?>">
                                                        <input type="hidden" name="dev_commit_sha" value="<?= htmlspecialchars($devBranchInfo['sha']) ?>">
                                                        <button type="button" id="dev-install-btn" class="ignis-btn ignis-btn--warning w-full">
                                                            <i class="fa-solid fa-download"></i> Commit installieren (<?= htmlspecialchars(substr($devBranchInfo['sha'], 0, 8)) ?>)
                                                        </button>
                                                    </form>

                                                    <script>
                                                        document.getElementById('dev-install-btn').addEventListener('click', async function() {
                                                            const branch = <?= json_encode($selectedBranch) ?>;
                                                            const sha = <?= json_encode(substr($devBranchInfo['sha'], 0, 8)) ?>;
                                                            const confirmed = await showConfirm(
                                                                'Branch "' + branch + '" (Commit ' + sha + ') installieren?\n\n' +
                                                                'Ein Backup wird automatisch erstellt.\n' +
                                                                'Dieser Vorgang kann einige Minuten dauern.', {
                                                                    title: 'Branch-Update installieren?',
                                                                    confirmText: 'Installieren',
                                                                    cancelText: 'Abbrechen',
                                                                    confirmClass: 'btn-warning',
                                                                    danger: false
                                                                }
                                                            );

                                                            if (confirmed) {
                                                                const progressModal = new bootstrap.Modal(document.getElementById('update-progress-modal'));
                                                                const modalElement = document.getElementById('update-progress-modal');
                                                                progressModal.show();

                                                                const progressBar = document.getElementById('update-progress-bar');
                                                                const progressText = document.getElementById('update-progress-text');
                                                                const statusText = document.getElementById('update-status-text');

                                                                const steps = [
                                                                    { percent: 10, text: 'Download wird vorbereitet...' },
                                                                    { percent: 25, text: 'Branch-Commit wird heruntergeladen...' },
                                                                    { percent: 40, text: 'Dateien werden extrahiert...' },
                                                                    { percent: 55, text: 'Backup wird erstellt...' },
                                                                    { percent: 70, text: 'Update wird installiert...' },
                                                                    { percent: 85, text: 'Dateien werden kopiert...' },
                                                                    { percent: 95, text: 'Installation wird abgeschlossen...' }
                                                                ];

                                                                let currentStep = 0;
                                                                const updateProgress = () => {
                                                                    if (currentStep < steps.length) {
                                                                        const step = steps[currentStep];
                                                                        progressBar.style.width = step.percent + '%';
                                                                        progressText.textContent = step.percent + '%';
                                                                        statusText.innerHTML = '<small class="text-gray-400">' + step.text + '</small>';
                                                                        currentStep++;
                                                                    }
                                                                };

                                                                const progressInterval = setInterval(updateProgress, 2000);
                                                                updateProgress();

                                                                const formData = new FormData(document.getElementById('dev-install-form'));

                                                                try {
                                                                    const response = await fetch(window.location.href, {
                                                                        method: 'POST',
                                                                        body: formData,
                                                                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                                                                    });

                                                                    clearInterval(progressInterval);

                                                                    if (!response.ok) {
                                                                        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                                                                    }

                                                                    const result = await response.json();

                                                                    if (result.success) {
                                                                        progressBar.style.width = '100%';
                                                                        progressText.textContent = '100%';
                                                                        statusText.innerHTML = '<small class="text-[#6abf76]"><i class="fa-solid fa-check-circle"></i> Update abgeschlossen!</small>';
                                                                        setTimeout(() => { window.location.href = '?dev'; }, 1500);
                                                                    } else {
                                                                        progressBar.classList.remove('progress-bar-animated');
                                                                        progressBar.classList.add('bg-danger');
                                                                        const errorMsg = result.message || 'Unbekannter Fehler.';
                                                                        statusText.innerHTML = '<small class="text-[#d46b6b]"><i class="fa-solid fa-exclamation-triangle"></i> </small>';
                                                                        statusText.querySelector('small').appendChild(document.createTextNode(errorMsg));

                                                                        setTimeout(() => {
                                                                            modalElement.querySelector('.modal-header').innerHTML =
                                                                                '<h5 class="modal-title text-[#d46b6b]"><i class="fa-solid fa-exclamation-triangle mr-2"></i>Update fehlgeschlagen</h5>' +
                                                                                '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
                                                                            modalElement.querySelector('.modal-body .alert-info').classList.add('hidden');
                                                                        }, 1000);
                                                                    }
                                                                } catch (error) {
                                                                    clearInterval(progressInterval);
                                                                    progressBar.classList.remove('progress-bar-animated');
                                                                    progressBar.classList.add('bg-danger');
                                                                    statusText.innerHTML = '<small class="text-[#d46b6b]"><i class="fa-solid fa-exclamation-triangle"></i> Netzwerkfehler: </small>';
                                                                    statusText.querySelector('small').appendChild(document.createTextNode(error.message));

                                                                    setTimeout(() => {
                                                                        modalElement.querySelector('.modal-header').innerHTML =
                                                                            '<h5 class="modal-title text-[#d46b6b]"><i class="fa-solid fa-exclamation-triangle mr-2"></i>Update fehlgeschlagen</h5>' +
                                                                            '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
                                                                        modalElement.querySelector('.modal-body .alert-info').classList.add('hidden');
                                                                    }, 1000);
                                                                }
                                                            }
                                                        });
                                                    </script>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="ignis-alert ignis-alert--danger">
                                        <i class="fa-solid fa-exclamation-triangle"></i>
                                        Konnte Branches nicht von GitHub laden. Bitte Internetverbindung prüfen.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Progress-Modal fürs Dev-Branch-Update — gleiche DOM-Id, aber
                             nur gerendert wenn der Stable-Pfad oben kein Update verfügbar
                             hatte (sonst Duplicate-Id). Beide Pfade nutzen dasselbe Partial. -->
                        <?php if (!$checking || !$updateInfo || !($updateInfo['available'] ?? false)): ?>
                            <?php include __DIR__ . '/../../../assets/components/settings/system/_update-progress-modal.php'; ?>
                        <?php endif; ?>
                    <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../../assets/components/settings/system/_composer-modal.php'; ?>

    <script>
        let composerModal = null;
        let composerCheckInterval = null;

        // Check if we should show the composer modal on page load
        <?php if (SessionManager::consumeComposerPending()): ?>
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
            fetch('<?= BASE_PATH ?>api/system/composer-status?action=check', {
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
            fetch('<?= BASE_PATH ?>api/system/composer-status?action=execute', {
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

    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>