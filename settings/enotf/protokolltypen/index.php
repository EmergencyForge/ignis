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
use App\Enotf\ProtocolTypeService;

if (!Permissions::check(['admin', 'edivi.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

$typeService = new ProtocolTypeService($pdo);
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
                        <span class="current">Protokolltypen</span>
                    </nav>

                    <div class="page-header mb-4">
                        <h1>Protokolltypen</h1>
                        <div class="header-actions">
                            <div class="d-flex gap-2">
                                <a href="<?= BASE_PATH ?>settings/enotf/presets/index.php" class="btn btn-outline-secondary">
                                    <i class="fa-solid fa-box-archive"></i> Presets
                                </a>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createTypeModal">
                                    <i class="fa-solid fa-plus"></i> Neuer Typ
                                </button>
                            </div>
                        </div>
                    </div>

                    <?php Flash::render(); ?>

                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-protocol-types">
                            <thead>
                                <th scope="col" style="width: 60px">Sort.</th>
                                <th scope="col" style="width: 60px">Farbe</th>
                                <th scope="col">Kurzname</th>
                                <th scope="col">Name</th>
                                <th scope="col">Beschreibung</th>
                                <th scope="col" style="width: 80px">Aktiv?</th>
                                <th scope="col" style="width: 80px">Typ</th>
                                <th scope="col" style="width: 120px"></th>
                            </thead>
                            <tbody>
                                <?php foreach ($types as $type): ?>
                                    <tr>
                                        <td><?= $type['sort_order'] ?></td>
                                        <td>
                                            <span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:<?= htmlspecialchars($type['color']) ?>"></span>
                                        </td>
                                        <td><strong><?= htmlspecialchars($type['short_name']) ?></strong></td>
                                        <td>
                                            <?php if ($type['icon']): ?>
                                                <i class="<?= htmlspecialchars($type['icon']) ?> me-1"></i>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($type['name']) ?>
                                        </td>
                                        <td class="text-muted"><?= htmlspecialchars($type['description'] ?? '') ?></td>
                                        <td>
                                            <?php if ($type['active']): ?>
                                                <span class="badge-status status-success"><span class="status-dot"></span>Ja</span>
                                            <?php else: ?>
                                                <span class="badge-status status-danger"><span class="status-dot"></span>Nein</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($type['is_builtin']): ?>
                                                <span class="badge bg-secondary">System</span>
                                            <?php else: ?>
                                                <span class="badge bg-dark">Custom</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= BASE_PATH ?>settings/enotf/protokolltypen/edit.php?id=<?= $type['id'] ?>" class="btn btn-sm btn-soft-primary btn-icon" title="Konfigurieren">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                                <?php if (!$type['is_builtin']): ?>
                                                    <button class="btn btn-sm btn-outline-danger btn-icon delete-type-btn" data-id="<?= $type['id'] ?>" data-name="<?= htmlspecialchars($type['name']) ?>" title="Löschen">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
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

    <!-- Create Type Modal -->
    <div class="modal fade" id="createTypeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?= BASE_PATH ?>api/enotf/admin/save-type.php" method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">Neuen Protokolltyp erstellen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="create-name" class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" id="create-name" placeholder="z.B. Krankentransport" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4">
                                <label for="create-short-name" class="form-label">Kurzname</label>
                                <input type="text" class="form-control" name="short_name" id="create-short-name" placeholder="z.B. KTP" maxlength="10" required>
                            </div>
                            <div class="col-4">
                                <label for="create-color" class="form-label">Farbe</label>
                                <input type="color" class="form-control form-control-color" name="color" id="create-color" value="#dc3545">
                            </div>
                            <div class="col-4">
                                <label for="create-sort-order" class="form-label">Sortierung</label>
                                <input type="number" class="form-control" name="sort_order" id="create-sort-order" value="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="create-icon" class="form-label">Icon <small class="form-hint">(Font Awesome Klasse)</small></label>
                            <input type="text" class="form-control" name="icon" id="create-icon" placeholder="fa-solid fa-file-medical">
                        </div>
                        <div class="mb-3">
                            <label for="create-description" class="form-label">Beschreibung</label>
                            <textarea class="form-control" name="description" id="create-description" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="create-preset" class="form-label">Preset als Basis <small class="form-hint">(optional)</small></label>
                            <select class="form-select" name="preset_id" id="create-preset">
                                <option value="">Leer starten</option>
                                <?php
                                $presets = $pdo->query("SELECT id, name FROM intra_edivi_presets ORDER BY is_builtin DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($presets as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="active" id="create-active" checked>
                            <label class="form-check-label" for="create-active">Aktiv</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-success">Erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>

    <script>
        $(document).ready(function() {
            $('#table-protocol-types').DataTable({
                paging: false,
                order: [[0, 'asc']],
                columnDefs: [{ orderable: false, targets: -1 }],
                language: {
                    emptyTable: "Keine Protokolltypen vorhanden",
                    info: "_TOTAL_ Protokolltypen",
                    search: "Suchen:",
                    zeroRecords: "Keine Einträge gefunden"
                }
            });

            document.querySelectorAll('.delete-type-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id = this.dataset.id;
                    var name = this.dataset.name;
                    showConfirm('Möchtest du den Protokolltyp "' + name + '" wirklich löschen?', {
                        danger: true,
                        confirmText: 'Löschen',
                        title: 'Protokolltyp löschen'
                    }).then(function(result) {
                        if (result) {
                            window.location.href = '<?= BASE_PATH ?>api/enotf/admin/delete-type.php?id=' + id;
                        }
                    });
                });
            });
        });
    </script>
</body>

</html>
