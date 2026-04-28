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
                    <div class="ignis-card mb-4">
                        <div class="ignis-card__header">
                            <h5 class="mb-0">Aktuelle Version</h5>
                        </div>
                        <div class="ignis-card__body">
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
                                            <button type="submit" class="ignis-ignis-btn ignis-btn--soft-primary w-full">
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
                                                <button type="submit" class="ignis-ignis-btn ignis-btn--outline-primary ignis-btn--sm w-full">
                                                    <i class="fa-solid fa-sync"></i> Neu laden
                                                </button>
                                            </form>
                                            <form method="post" class="flex-1">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <button type="submit" name="clear_cache" class="ignis-ignis-btn ignis-btn--outline-secondary ignis-btn--sm w-full">
                                                    <i class="fa-solid fa-trash"></i> Cache leeren
                                                </button>
                                            </form>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($checking && $updateInfo): ?>
                        <!-- Update Information Card -->
                        <div class="ignis-card mb-4">
                            <div class="ignis-card__header">
                                <h5 class="mb-0">
                                    Update-Informationen
                                </h5>
                            </div>
                            <div class="ignis-card__body">
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
                                    <div class="ignis-alert ignis-alert--<?= $alertClass ?>">
                                        <h5 class="ignis-alert__title"><i class="fa-solid fa-check-circle"></i> Neues Update verfügbar!</h5>
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
                                                <button type="button" id="install-update-ignis-btn" class="ignis-ignis-btn ignis-btn--success w-full">
                                                    <i class="fa-solid fa-download"></i> Update jetzt installieren
                                                </button>
                                            </form>

                                            <?php include __DIR__ . '/../../../assets/components/settings/system/_update-progress-modal.php'; ?>

                                            <?php if (isset($updateInfo['html_url'])): ?>
                                                <a href="<?= htmlspecialchars($updateInfo['html_url']) ?>"
                                                    target="_blank"
                                                    class="ignis-ignis-btn ignis-btn--outline-primary w-full mb-2">
                                                    <i class="fa-solid fa-external-link-alt"></i> Release auf GitHub ansehen
                                                </a>
                                            <?php endif; ?>

                                            <?php if (isset($updateInfo['download_url'])): ?>
                                                <a href="<?= htmlspecialchars($updateInfo['download_url']) ?>"
                                                    class="ignis-ignis-btn ignis-btn--outline-secondary w-full">
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
                        <div class="ignis-card mb-4 border-warning">
                            <div class="ignis-card__header bg-[#c49a2a] bg-opacity-10">
                                <h5 class="mb-0"><i class="fa-solid fa-code-branch mr-2"></i>Entwickler-Modus: Branch-Update</h5>
                            </div>
                            <div class="ignis-card__body">
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

                                    <?php if ($devBranchInfo): ?>
                                        <div class="ignis-card bg-[rgba(0,0,0,0.3)] mb-3">
                                            <div class="ignis-card__body">
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
                                                        <button type="button" id="dev-install-ignis-btn" class="ignis-ignis-btn ignis-btn--warning w-full">
                                                            <i class="fa-solid fa-download"></i> Commit installieren (<?= htmlspecialchars(substr($devBranchInfo['sha'], 0, 8)) ?>)
                                                        </button>
                                                    </form>
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

    <?php
    // consumeComposerPending() löscht den Flag atomar — nur einmal aufrufen
    // und das Ergebnis an das JS-Init durchreichen. Sonst wäre das Modal
    // nach einem Refresh nicht mehr aktiv.
    $composerPendingOnLoad = SessionManager::consumeComposerPending();
    ?>

    <script src="<?= BASE_PATH ?>assets/js/modules/system-settings.js"></script>
    <script>
    initSystemSettings({
        basePath: '<?= BASE_PATH ?>',
        showComposerOnLoad: <?= $composerPendingOnLoad ? 'true' : 'false' ?>,
        <?php if (!empty($updateInfo['latest_version'])): ?>
        installButton: {
            buttonId:   'install-update-ignis-btn',
            formId:     'install-update-form',
            newVersion: <?= json_encode($updateInfo['latest_version']) ?>,
        },
        <?php endif; ?>
        <?php if ($isDevMode && !empty($devBranchInfo)):
            $devCurrentHash = $currentVersion['commit_hash'] ?? '';
            $devTargetHash  = $devBranchInfo['sha'] ?? '';
            $devSameCommit  = !empty($devCurrentHash) && str_starts_with($devTargetHash, $devCurrentHash);
        ?>
        <?php if (!$devSameCommit): ?>
        devInstallButton: {
            buttonId:   'dev-install-ignis-btn',
            formId:     'dev-install-form',
            branch:     <?= json_encode($selectedBranch) ?>,
            sha:        <?= json_encode(substr($devBranchInfo['sha'], 0, 8)) ?>,
            successUrl: '?dev',
        },
        <?php endif; ?>
        <?php endif; ?>
    });
    </script>

    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>