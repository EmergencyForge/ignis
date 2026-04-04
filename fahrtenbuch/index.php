<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin', 'fahrtenbuch.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit;
}

$canManage = Permissions::check(['admin', 'fahrtenbuch.manage']);

// Fahrttypen
$fahrttypen = [
    'einsatzfahrt'   => 'Einsatzfahrt',
    'bewegungsfahrt' => 'Bewegungsfahrt',
    'werkstattfahrt' => 'Werkstattfahrt',
    'uebungsfahrt'   => 'Übungsfahrt',
    'dienstfahrt'    => 'Dienstfahrt',
    'sonstige'       => 'Sonstige',
];

$fahrttypBadges = [
    'einsatzfahrt'   => 'danger',
    'bewegungsfahrt' => 'info',
    'werkstattfahrt' => 'warning',
    'uebungsfahrt'   => 'success',
    'dienstfahrt'    => 'primary',
    'sonstige'       => 'secondary',
];

// Load vehicles for filter and create form
$vehicles = $pdo->query("SELECT id, name, identifier, veh_type FROM intra_fahrzeuge WHERE active = 1 ORDER BY priority ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Filters
$filterVehicle = isset($_GET['vehicle']) ? (int)$_GET['vehicle'] : 0;
$filterFahrttyp = $_GET['fahrttyp'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

// Load entries
$tableExists = true;
$entries = [];
$stats = ['total' => 0, 'total_km' => 0];

try {
    // Stats
    $stats = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(kilometer), 0) AS total_km FROM intra_fahrtenbuch")->fetch(PDO::FETCH_ASSOC);

    // Filtered query
    $sql = "SELECT fb.*, f.name AS vehicle_name, f.veh_type
            FROM intra_fahrtenbuch fb
            LEFT JOIN intra_fahrzeuge f ON fb.vehicle_id = f.id
            WHERE 1=1";
    $params = [];

    if ($filterVehicle) {
        $sql .= " AND fb.vehicle_id = :vid";
        $params['vid'] = $filterVehicle;
    }
    if ($filterFahrttyp && isset($fahrttypen[$filterFahrttyp])) {
        $sql .= " AND fb.fahrttyp = :ftyp";
        $params['ftyp'] = $filterFahrttyp;
    }
    if ($filterDateFrom) {
        $sql .= " AND fb.datum >= :dfrom";
        $params['dfrom'] = $filterDateFrom;
    }
    if ($filterDateTo) {
        $sql .= " AND fb.datum <= :dto";
        $params['dto'] = $filterDateTo;
    }

    $sql .= " ORDER BY fb.datum DESC, fb.abfahrt DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tableExists = false;
}
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php $SITE_TITLE = 'Fahrtenbuch'; include __DIR__ . '/../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="fahrzeuge">
    <?php include __DIR__ . '/../assets/components/navbar.php'; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <nav class="admin-breadcrumb">
                        <a href="<?= BASE_PATH ?>index.php">Dashboard</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <a href="<?= BASE_PATH ?>settings/fahrzeuge/fahrzeuge/index.php">Fahrzeuge</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span class="current">Fahrtenbuch</span>
                    </nav>

                    <div class="page-header mb-4">
                        <h1>Fahrtenbuch</h1>
                        <?php if ($canManage): ?>
                            <div class="header-actions">
                                <button type="button" class="btn btn-success" id="toggleCreateForm">
                                    <i class="fa-solid fa-plus"></i> Neuer Eintrag
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php Flash::render(); ?>

                    <?php if (!$tableExists): ?>
                        <div class="alert alert-warning">
                            <i class="fa-solid fa-database"></i> Die Tabelle <code>intra_fahrtenbuch</code> existiert noch nicht.
                            Bitte führe den <a href="<?= BASE_PATH ?>setup/database-init.php">Datenbank-Updater</a> aus.
                        </div>
                    <?php else: ?>

                    <!-- Stats -->
                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md">
                            <div class="intra__tile p-3 text-center">
                                <div class="fs-2 fw-bold"><?= (int)$stats['total'] ?></div>
                                <small class="text-muted">Einträge gesamt</small>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="intra__tile p-3 text-center">
                                <div class="fs-2 fw-bold"><?= number_format((float)$stats['total_km'], 1, ',', '.') ?></div>
                                <small class="text-muted">Kilometer gesamt</small>
                            </div>
                        </div>
                    </div>

                    <!-- Create Form -->
                    <?php if ($canManage): ?>
                    <div id="createFormWrap" style="display:none;" class="intra__tile p-4 mb-4">
                        <h5 class="mb-3">Neuer Eintrag</h5>
                        <form method="POST" action="<?= BASE_PATH ?>fahrtenbuch/actions.php">
                            <input type="hidden" name="action" value="create">
                            <input type="hidden" name="return_to" value="admin">
                            <input type="hidden" name="source" value="admin">

                            <?php
                            $context = 'admin';
                            $entry = null;
                            $vehicleName = '';
                            $vehicleIdentifier = '';
                            $vehicleId = null;
                            $fahrerName = '';
                            include __DIR__ . '/../assets/components/fahrtenbuch/_form-fields.php';
                            ?>

                            <div class="d-flex gap-2 mt-3">
                                <button type="submit" class="btn btn-sm btn-success"><i class="fa-solid fa-save me-1"></i>Speichern</button>
                                <button type="button" class="btn btn-sm btn-ghost" id="cancelCreateForm">Abbrechen</button>
                            </div>
                        </form>
                    </div>

                    <!-- Edit Form -->
                    <div id="editFormWrap" style="display:none;" class="intra__tile p-4 mb-4">
                        <h5 class="mb-3">Eintrag bearbeiten</h5>
                        <form method="POST" action="<?= BASE_PATH ?>fahrtenbuch/actions.php" id="editForm">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" id="edit_id" value="">
                            <input type="hidden" name="return_to" value="admin">

                            <?php
                            $context = 'admin';
                            $entry = null;
                            include __DIR__ . '/../assets/components/fahrtenbuch/_form-fields.php';
                            ?>

                            <div class="d-flex gap-2 mt-3">
                                <button type="submit" class="btn btn-sm btn-success"><i class="fa-solid fa-save me-1"></i>Aktualisieren</button>
                                <button type="button" class="btn btn-sm btn-ghost" id="cancelEditForm">Abbrechen</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Filter -->
                    <div class="intra__tile p-3 mb-4">
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-auto">
                                <label class="form-label mb-1">Fahrzeug</label>
                                <select name="vehicle" class="form-select form-select-sm">
                                    <option value="">Alle</option>
                                    <?php foreach ($vehicles as $v): ?>
                                        <option value="<?= $v['id'] ?>" <?= $filterVehicle == $v['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['identifier']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <label class="form-label mb-1">Fahrttyp</label>
                                <select name="fahrttyp" class="form-select form-select-sm">
                                    <option value="">Alle</option>
                                    <?php foreach ($fahrttypen as $slug => $label): ?>
                                        <option value="<?= $slug ?>" <?= $filterFahrttyp === $slug ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <label class="form-label mb-1">Von</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filterDateFrom) ?>">
                            </div>
                            <div class="col-auto">
                                <label class="form-label mb-1">Bis</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filterDateTo) ?>">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-sm btn-soft-primary"><i class="fa-solid fa-filter"></i> Filtern</button>
                                <a href="?" class="btn btn-sm btn-ghost">Zurücksetzen</a>
                            </div>
                        </form>
                    </div>

                    <!-- Entries Table -->
                    <div class="intra__tile">
                        <?php if (!empty($entries)): ?>
                            <div class="p-3" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                                    <input type="text" id="fbLocalSearch" class="form-control" placeholder="Einträge durchsuchen...">
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="p-3">
                            <?php if (empty($entries)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fa-solid fa-book fa-2x mb-2" style="opacity:0.4;"></i>
                                    <div>Keine Einträge gefunden</div>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table intra__table table-sm mb-0" id="fahrtenbuchAdminTable">
                                        <thead>
                                            <tr>
                                                <th>Datum</th>
                                                <th>Abfahrt</th>
                                                <th>Ankunft</th>
                                                <th>Fahrzeug</th>
                                                <th>Fahrer</th>
                                                <th>Fahrttyp</th>
                                                <th>km</th>
                                                <th>Stationierungsort</th>
                                                <th>Grund</th>
                                                <th>Quelle</th>
                                                <?php if ($canManage): ?><th></th><?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($entries as $e):
                                                $typSlug = $e['fahrttyp'] ?? '';
                                                $typLabel = $fahrttypen[$typSlug] ?? $typSlug;
                                                $typBadge = $fahrttypBadges[$typSlug] ?? 'secondary';
                                                $sourceLabels = ['enotf' => 'eNOTF', 'firetab' => 'FireTab', 'admin' => 'Admin'];
                                            ?>
                                                <tr data-search="<?= htmlspecialchars(mb_strtolower(
                                                    ($e['vehicle_name'] ?? $e['vehicle_identifier']) . ' ' .
                                                    $e['fahrer_name'] . ' ' . $typLabel . ' ' .
                                                    ($e['stationierungsort'] ?? '') . ' ' . ($e['grund'] ?? '')
                                                )) ?>">
                                                    <td><?= date('d.m.Y', strtotime($e['datum'])) ?></td>
                                                    <td><?= date('H:i', strtotime($e['abfahrt'])) ?></td>
                                                    <td><?= $e['ankunft'] ? date('H:i', strtotime($e['ankunft'])) : '<span class="text-muted">—</span>' ?></td>
                                                    <td><?= htmlspecialchars($e['vehicle_name'] ?? $e['vehicle_identifier']) ?></td>
                                                    <td><?= htmlspecialchars($e['fahrer_name']) ?></td>
                                                    <td><span class="badge text-bg-<?= $typBadge ?>"><?= htmlspecialchars($typLabel) ?></span></td>
                                                    <td><?= $e['kilometer'] !== null ? number_format((float)$e['kilometer'], 1, ',', '.') : '—' ?></td>
                                                    <td class="text-truncate" style="max-width:150px;" title="<?= htmlspecialchars($e['stationierungsort'] ?? '') ?>">
                                                        <?= htmlspecialchars($e['stationierungsort'] ?? '') ?: '—' ?>
                                                    </td>
                                                    <td class="text-truncate" style="max-width:150px;" title="<?= htmlspecialchars($e['grund'] ?? '') ?>">
                                                        <?= htmlspecialchars($e['grund'] ?? '') ?: '—' ?>
                                                    </td>
                                                    <td><span class="badge text-bg-secondary"><?= $sourceLabels[$e['source']] ?? $e['source'] ?></span></td>
                                                    <?php if ($canManage): ?>
                                                        <td class="text-end text-nowrap">
                                                            <button type="button" class="btn btn-sm btn-ghost fb-edit-btn"
                                                                    data-id="<?= $e['id'] ?>"
                                                                    data-datum="<?= htmlspecialchars($e['datum']) ?>"
                                                                    data-abfahrt="<?= date('H:i', strtotime($e['abfahrt'])) ?>"
                                                                    data-ankunft="<?= $e['ankunft'] ? date('H:i', strtotime($e['ankunft'])) : '' ?>"
                                                                    data-vehicle-id="<?= (int)($e['vehicle_id'] ?? 0) ?>"
                                                                    data-vehicle-identifier="<?= htmlspecialchars($e['vehicle_identifier']) ?>"
                                                                    data-fahrer-name="<?= htmlspecialchars($e['fahrer_name']) ?>"
                                                                    data-fahrttyp="<?= htmlspecialchars($e['fahrttyp']) ?>"
                                                                    data-kilometer="<?= htmlspecialchars($e['kilometer'] ?? '') ?>"
                                                                    data-stationierungsort="<?= htmlspecialchars($e['stationierungsort'] ?? '') ?>"
                                                                    data-grund="<?= htmlspecialchars($e['grund'] ?? '') ?>"
                                                                    title="Bearbeiten">
                                                                <i class="fa-solid fa-pen"></i>
                                                            </button>
                                                            <form method="POST" action="<?= BASE_PATH ?>fahrtenbuch/actions.php" class="d-inline"
                                                                  onsubmit="return confirm('Eintrag wirklich löschen?');">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                                                <input type="hidden" name="return_to" value="admin">
                                                                <button type="submit" class="btn btn-sm btn-ghost text-danger" title="Löschen">
                                                                    <i class="fa-solid fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php endif; // tableExists ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var createWrap = document.getElementById('createFormWrap');
        var editWrap = document.getElementById('editFormWrap');
        var toggleBtn = document.getElementById('toggleCreateForm');
        var cancelCreate = document.getElementById('cancelCreateForm');
        var cancelEdit = document.getElementById('cancelEditForm');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                if (editWrap) editWrap.style.display = 'none';
                createWrap.style.display = createWrap.style.display === 'none' ? 'block' : 'none';
            });
        }
        if (cancelCreate) {
            cancelCreate.addEventListener('click', function() {
                createWrap.style.display = 'none';
            });
        }
        if (cancelEdit) {
            cancelEdit.addEventListener('click', function() {
                editWrap.style.display = 'none';
            });
        }

        // Edit buttons
        document.querySelectorAll('.fb-edit-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (createWrap) createWrap.style.display = 'none';
                editWrap.style.display = 'block';

                document.getElementById('edit_id').value = btn.dataset.id;

                var form = document.getElementById('editForm');
                var fields = {
                    'datum': btn.dataset.datum,
                    'abfahrt': btn.dataset.abfahrt,
                    'ankunft': btn.dataset.ankunft || '',
                    'fahrttyp': btn.dataset.fahrttyp,
                    'kilometer': btn.dataset.kilometer || '',
                    'stationierungsort': btn.dataset.stationierungsort || '',
                    'grund': btn.dataset.grund || '',
                    'fahrer_name': btn.dataset.fahrerName || ''
                };

                // Set vehicle dropdown
                var vehicleSelect = form.querySelector('[name="vehicle_id"]');
                if (vehicleSelect && vehicleSelect.tagName === 'SELECT') {
                    vehicleSelect.value = btn.dataset.vehicleId || '';
                    // Trigger change to update hidden identifier
                    vehicleSelect.dispatchEvent(new Event('change'));
                }

                for (var key in fields) {
                    var input = form.querySelector('[name="' + key + '"]');
                    if (input) {
                        input.value = fields[key];
                    }
                }

                editWrap.scrollIntoView({ behavior: 'smooth' });
            });
        });

        // Vehicle select → update hidden identifier (admin context)
        document.querySelectorAll('select[name="vehicle_id"]').forEach(function(sel) {
            sel.addEventListener('change', function() {
                var opt = sel.options[sel.selectedIndex];
                var identInput = sel.closest('form').querySelector('[name="vehicle_identifier"]');
                if (identInput) {
                    identInput.value = opt ? (opt.dataset.identifier || '') : '';
                }
            });
        });

        // Local search
        var searchInput = document.getElementById('fbLocalSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                var term = this.value.toLowerCase();
                var rows = document.querySelectorAll('#fahrtenbuchAdminTable tbody tr');
                var visibleCount = 0;
                rows.forEach(function(row) {
                    var searchData = row.dataset.search || '';
                    var match = !term || searchData.indexOf(term) !== -1;
                    row.style.display = match ? '' : 'none';
                    if (match) visibleCount++;
                });
            });
        }
    });
    </script>
</body>

</html>
