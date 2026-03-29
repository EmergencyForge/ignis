<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Enotf\PresetService;
use App\Enotf\ProtocolTypeService;

if (!Permissions::check(['admin', 'edivi.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

$presetService = new PresetService($pdo);
$typeService = new ProtocolTypeService($pdo);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'export') {
        $typeId = (int)($_POST['type_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($typeId && $name) {
            $exportData = $presetService->export($typeId);
            if ($exportData) {
                $presetService->save($name, trim($_POST['description'] ?? ''), $exportData, $_SESSION['userid'] ?? null);
                Flash::set('success', 'Preset "' . htmlspecialchars($name) . '" wurde erstellt.');
            }
        }
    } elseif ($action === 'apply') {
        $presetId = (int)($_POST['preset_id'] ?? 0);
        $targetTypeId = (int)($_POST['target_type_id'] ?? 0);
        if ($presetId && $targetTypeId) {
            $result = $presetService->apply($presetId, $targetTypeId);
            if ($result) {
                Flash::set('success', 'Preset wurde angewendet.');
            } else {
                Flash::set('error', 'Preset konnte nicht angewendet werden.');
            }
        }
    } elseif ($action === 'import') {
        if (isset($_FILES['preset_file']) && $_FILES['preset_file']['error'] === 0) {
            $json = file_get_contents($_FILES['preset_file']['tmp_name']);
            $name = trim($_POST['name'] ?? 'Importiert');
            $id = $presetService->importFromJson($json, $name, '', $_SESSION['userid'] ?? null);
            if ($id) {
                Flash::set('success', 'Preset "' . htmlspecialchars($name) . '" wurde importiert.');
            } else {
                Flash::set('error', 'Ungültiges JSON-Format.');
            }
        }
    } elseif ($action === 'delete') {
        $presetId = (int)($_POST['preset_id'] ?? 0);
        $presetService->delete($presetId);
        Flash::set('success', 'Preset wurde gelöscht.');
    }

    header("Location: " . BASE_PATH . "settings/enotf/presets/index.php");
    exit();
}

// Handle JSON download
if (isset($_GET['download'])) {
    $presetId = (int)$_GET['download'];
    $preset = $presetService->get($presetId);
    if ($preset) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="preset_' . $presetId . '.json"');
        echo $preset['preset_json'];
        exit();
    }
}

$presets = $presetService->getAll();
$types = $typeService->getAllTypes(false);
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . "/../../../assets/components/_base/admin/head.php"; ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <nav class="admin-breadcrumb">
                        <a href="<?= BASE_PATH ?>index.php">Dashboard</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <a href="<?= BASE_PATH ?>settings/enotf/index.php">eNOTF</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <a href="<?= BASE_PATH ?>settings/enotf/protokolltypen/index.php">Protokolltypen</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span class="current">Presets</span>
                    </nav>

                    <div class="page-header mb-4">
                        <h1>Konfigurationspresets</h1>
                        <div class="header-actions">
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal">
                                    <i class="fa-solid fa-file-import"></i> Importieren
                                </button>
                                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportModal">
                                    <i class="fa-solid fa-file-export"></i> Aus Typ exportieren
                                </button>
                            </div>
                        </div>
                    </div>

                    <?php Flash::render(); ?>

                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped">
                            <thead>
                                <th scope="col">Name</th>
                                <th scope="col">Beschreibung</th>
                                <th scope="col">Version</th>
                                <th scope="col">Typ</th>
                                <th scope="col">Erstellt</th>
                                <th scope="col" style="width:200px"></th>
                            </thead>
                            <tbody>
                                <?php foreach ($presets as $preset): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($preset['name']) ?></strong></td>
                                        <td class="text-muted"><?= htmlspecialchars($preset['description'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($preset['version']) ?></td>
                                        <td>
                                            <?php if ($preset['is_builtin']): ?>
                                                <span class="badge bg-secondary">System</span>
                                            <?php else: ?>
                                                <span class="badge bg-dark">Custom</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted"><?= date('d.m.Y', strtotime($preset['created_at'])) ?></td>
                                        <td class="text-end">
                                            <div class="d-flex gap-1 justify-content-end">
                                                <a href="?download=<?= $preset['id'] ?>" class="btn btn-sm btn-soft-primary btn-icon" title="JSON herunterladen">
                                                    <i class="fa-solid fa-download"></i>
                                                </a>
                                                <button class="btn btn-sm btn-soft-success apply-preset-btn" data-id="<?= $preset['id'] ?>" data-name="<?= htmlspecialchars($preset['name']) ?>" title="Auf Typ anwenden">
                                                    <i class="fa-solid fa-play me-1"></i> Anwenden
                                                </button>
                                                <?php if (!$preset['is_builtin']): ?>
                                                    <form method="POST" style="display:inline" onsubmit="return confirm('Preset löschen?')">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="preset_id" value="<?= $preset['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" title="Löschen">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="export">
                    <div class="modal-header">
                        <h5 class="modal-title">Konfiguration als Preset speichern</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="export-type" class="form-label">Protokolltyp</label>
                            <select class="form-select" name="type_id" id="export-type" required>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= $t['short_name'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="export-name" class="form-label">Preset-Name</label>
                            <input type="text" class="form-control" name="name" id="export-name" required>
                        </div>
                        <div class="mb-3">
                            <label for="export-desc" class="form-label">Beschreibung</label>
                            <textarea class="form-control" name="description" id="export-desc" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-success">Exportieren</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="import">
                    <div class="modal-header">
                        <h5 class="modal-title">Preset importieren</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="import-name" class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" id="import-name" required>
                        </div>
                        <div class="mb-3">
                            <label for="import-file" class="form-label">JSON-Datei</label>
                            <input type="file" class="form-control" name="preset_file" id="import-file" accept=".json" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-success">Importieren</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Apply Modal -->
    <div class="modal fade" id="applyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="apply">
                    <input type="hidden" name="preset_id" id="apply-preset-id">
                    <div class="modal-header">
                        <h5 class="modal-title">Preset anwenden</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Preset <strong id="apply-preset-name"></strong> auf folgenden Protokolltyp anwenden:</p>
                        <div class="alert alert-warning" style="font-size:0.82rem">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            Dies überschreibt die bestehende Sektions- und Feldkonfiguration des Zieltyps.
                        </div>
                        <div class="mb-3">
                            <label for="apply-target" class="form-label">Ziel-Protokolltyp</label>
                            <select class="form-select" name="target_type_id" id="apply-target" required>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= $t['short_name'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-soft-warning">Anwenden</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>

    <script>
        document.querySelectorAll('.apply-preset-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('apply-preset-id').value = this.dataset.id;
                document.getElementById('apply-preset-name').textContent = this.dataset.name;
                new bootstrap.Modal(document.getElementById('applyModal')).show();
            });
        });
    </script>
</body>

</html>
