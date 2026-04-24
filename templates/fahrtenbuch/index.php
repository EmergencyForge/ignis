<?php
/**
 * View: Fahrtenbuch-Übersicht (Admin)
 *
 * @var array<int,array<string,mixed>> $entries
 * @var array<int,array<string,mixed>> $vehicles
 * @var array{total:int,total_km:float} $stats
 * @var bool                            $tableExists
 * @var bool                            $canManage
 * @var int                             $filterVehicle
 * @var string                          $filterFahrttyp
 * @var string                          $filterDateFrom
 * @var string                          $filterDateTo
 * @var array<string,string>            $fahrttypen
 * @var array<string,string>            $fahrttypBadges
 * @var \PDO                            $pdo
 */

use App\Helpers\Flash;

$SITE_TITLE = 'Fahrtenbuch';
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="fahrzeuge">
    <?php include __DIR__ . '/../../assets/components/navbar.php'; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-6">
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
                        Bitte führe <code>composer db:migrate</code> aus oder lade die Seite neu — die Datenbank wird automatisch migriert.
                    </div>
                <?php else: ?>

                <!-- Stats -->
                <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div class="intra__tile p-3 text-center">
                        <div class="text-3xl font-bold"><?= (int) $stats['total'] ?></div>
                        <small class="text-gray-400">Einträge gesamt</small>
                    </div>
                    <div class="intra__tile p-3 text-center">
                        <div class="text-3xl font-bold"><?= number_format((float) $stats['total_km'], 1, ',', '.') ?></div>
                        <small class="text-gray-400">Kilometer gesamt</small>
                    </div>
                </div>

                <!-- Create Form -->
                <?php if ($canManage): ?>
                <div id="createFormWrap" style="display:none;" class="intra__tile mb-4 p-4">
                    <h5 class="mb-4">Neuer Eintrag</h5>
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
                        include __DIR__ . '/../../assets/components/fahrtenbuch/_form-fields.php';
                        ?>

                        <div class="mt-4 flex gap-2">
                            <button type="submit" class="btn btn-sm btn-success"><i class="fa-solid fa-save mr-1"></i>Speichern</button>
                            <button type="button" class="btn btn-sm btn-ghost" id="cancelCreateForm">Abbrechen</button>
                        </div>
                    </form>
                </div>

                <!-- Edit Form -->
                <div id="editFormWrap" style="display:none;" class="intra__tile mb-4 p-4">
                    <h5 class="mb-4">Eintrag bearbeiten</h5>
                    <form method="POST" action="<?= BASE_PATH ?>fahrtenbuch/actions.php" id="editForm">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id" value="">
                        <input type="hidden" name="return_to" value="admin">

                        <?php
                        $context = 'admin';
                        $entry = null;
                        include __DIR__ . '/../../assets/components/fahrtenbuch/_form-fields.php';
                        ?>

                        <div class="mt-4 flex gap-2">
                            <button type="submit" class="btn btn-sm btn-success"><i class="fa-solid fa-save mr-1"></i>Aktualisieren</button>
                            <button type="button" class="btn btn-sm btn-ghost" id="cancelEditForm">Abbrechen</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Filter -->
                <div class="intra__tile mb-4 p-3">
                    <form method="GET" class="flex flex-wrap items-end gap-2">
                        <div>
                            <label class="form-label mb-1">Fahrzeug</label>
                            <select name="vehicle" class="form-select form-select-sm">
                                <option value="">Alle</option>
                                <?php foreach ($vehicles as $v): ?>
                                    <option value="<?= (int) $v['id'] ?>" <?= $filterVehicle === (int) $v['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['identifier']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1">Fahrttyp</label>
                            <select name="fahrttyp" class="form-select form-select-sm">
                                <option value="">Alle</option>
                                <?php foreach ($fahrttypen as $slug => $label): ?>
                                    <option value="<?= htmlspecialchars($slug) ?>" <?= $filterFahrttyp === $slug ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1">Von</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filterDateFrom) ?>">
                        </div>
                        <div>
                            <label class="form-label mb-1">Bis</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filterDateTo) ?>">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-sm btn-soft-primary"><i class="fa-solid fa-filter"></i> Filtern</button>
                            <a href="?" class="btn btn-sm btn-ghost no-underline hover:no-underline">Zurücksetzen</a>
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
                            <div class="py-4 text-center text-gray-400">
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
                                            $typSlug  = $e['fahrttyp'] ?? '';
                                            $typLabel = $fahrttypen[$typSlug] ?? $typSlug;
                                            $typBadge = $fahrttypBadges[$typSlug] ?? 'secondary';
                                            $sourceLabels = ['enotf' => 'eNOTF', 'firetab' => 'FireTab', 'admin' => 'Admin'];
                                        ?>
                                            <tr data-search="<?= htmlspecialchars(mb_strtolower(
                                                ($e['vehicle_name'] ?? $e['vehicle_identifier']) . ' ' .
                                                $e['fahrer_name'] . ' ' . $typLabel . ' ' .
                                                ($e['stationierungsort'] ?? '') . ' ' . ($e['grund'] ?? '')
                                            )) ?>">
                                                <td><?= \App\Helpers\DateTimeHelper::formatDateLocal($e['datum']) ?></td>
                                                <td><?= \App\Helpers\DateTimeHelper::formatTimeLocal($e['abfahrt']) ?></td>
                                                <td><?= $e['ankunft'] ? \App\Helpers\DateTimeHelper::formatTimeLocal($e['ankunft']) : '<span class="text-gray-400">—</span>' ?></td>
                                                <td><?= htmlspecialchars($e['vehicle_name'] ?? $e['vehicle_identifier']) ?></td>
                                                <td><?= htmlspecialchars($e['fahrer_name']) ?></td>
                                                <td><span class="badge text-bg-<?= htmlspecialchars($typBadge) ?>"><?= htmlspecialchars($typLabel) ?></span></td>
                                                <td><?= $e['kilometer'] !== null ? number_format((float) $e['kilometer'], 1, ',', '.') : '—' ?></td>
                                                <td class="truncate" style="max-width:150px;" title="<?= htmlspecialchars($e['stationierungsort'] ?? '') ?>">
                                                    <?= htmlspecialchars($e['stationierungsort'] ?? '') ?: '—' ?>
                                                </td>
                                                <td class="truncate" style="max-width:150px;" title="<?= htmlspecialchars($e['grund'] ?? '') ?>">
                                                    <?= htmlspecialchars($e['grund'] ?? '') ?: '—' ?>
                                                </td>
                                                <td><span class="badge text-bg-secondary"><?= htmlspecialchars($sourceLabels[$e['source']] ?? $e['source']) ?></span></td>
                                                <?php if ($canManage): ?>
                                                    <td class="whitespace-nowrap text-right">
                                                        <button type="button" class="btn btn-sm btn-ghost fb-edit-btn"
                                                                data-id="<?= (int) $e['id'] ?>"
                                                                data-datum="<?= htmlspecialchars($e['datum']) ?>"
                                                                data-abfahrt="<?= \App\Helpers\DateTimeHelper::formatTimeLocal($e['abfahrt']) ?>"
                                                                data-ankunft="<?= $e['ankunft'] ? \App\Helpers\DateTimeHelper::formatTimeLocal($e['ankunft']) : '' ?>"
                                                                data-vehicle-id="<?= (int) ($e['vehicle_id'] ?? 0) ?>"
                                                                data-vehicle-identifier="<?= htmlspecialchars($e['vehicle_identifier']) ?>"
                                                                data-fahrer-name="<?= htmlspecialchars($e['fahrer_name']) ?>"
                                                                data-fahrttyp="<?= htmlspecialchars($e['fahrttyp']) ?>"
                                                                data-kilometer="<?= htmlspecialchars((string) ($e['kilometer'] ?? '')) ?>"
                                                                data-stationierungsort="<?= htmlspecialchars($e['stationierungsort'] ?? '') ?>"
                                                                data-grund="<?= htmlspecialchars($e['grund'] ?? '') ?>"
                                                                title="Bearbeiten">
                                                            <i class="fa-solid fa-pen"></i>
                                                        </button>
                                                        <form method="POST" action="<?= BASE_PATH ?>fahrtenbuch/actions.php" class="inline"
                                                              onsubmit="return confirm('Eintrag wirklich löschen?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
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
                rows.forEach(function(row) {
                    var searchData = row.dataset.search || '';
                    var match = !term || searchData.indexOf(term) !== -1;
                    row.style.display = match ? '' : 'none';
                });
            });
        }
    });
    </script>

    <?php include __DIR__ . '/../../assets/components/footer.php'; ?>
</body>

</html>
