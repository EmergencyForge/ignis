<?php
/**
 * View: Fahrtenbuch-Übersicht (Admin)
 *
 * Seitenkopf wie die übrigen Listen, Kennzahlen, Filter als Werkzeugleiste
 * (Fahrzeug und Fahrttyp als Auswahl, Zeitraum, Suche lokal im Browser),
 * die Fahrten als ignis-Tabelle. Anlegen und Bearbeiten laufen in einem
 * Seitenpanel (twplus-slide-over) über dieselben Felder
 * (assets/components/logbook/_form-fields.php).
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
 */


$SITE_TITLE = 'Fahrtenbuch';

$layout = 'admin';
$bodyId = 'fahrzeuge';

// Fahrttyp => Chip-Semantik (FAHRTTYP_BADGES nennt die alten Namen).
$chipFor = ['danger' => 'danger', 'info' => 'info', 'warning' => 'warn', 'success' => 'ok', 'primary' => 'primary', 'secondary' => 'secondary'];
$sourceLabels = ['enotf' => 'eNOTF', 'firetab' => 'fireTab', 'admin' => 'Verwaltung'];
$hasFilter = $filterVehicle > 0 || $filterFahrttyp !== '' || $filterDateFrom !== '' || $filterDateTo !== '';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="mb-6">
                <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/vehicles/vehicles/index">Fahrzeuge</a></span> <span class="ignis-breadcrumb__item is-active">Fahrtenbuch</span></nav>

                <div class="page-header twplus-page-header mb-4">
                    <div class="twplus-page-header__copy">
                        <p class="twplus-page-header__eyebrow">Fuhrpark</p>
                        <h1>Fahrtenbuch</h1>
                        <p class="twplus-page-header__description">Fahrten, Kilometerstände und Fahrzeugnutzung zentral nachvollziehen.</p>
                    </div>
                    <?php if ($canManage): ?>
                        <div class="header-actions twplus-page-header__actions">
                            <button type="button" class="ignis-btn ignis-btn--primary" id="toggleCreateForm">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i> Neuer Eintrag
                            </button>
                        </div>
                    <?php endif; ?>
                </div>


                <?php if (!$tableExists): ?>
                    <div class="ignis-alert ignis-alert--warn">
                        <i class="fa-solid fa-database ignis-alert__icon" aria-hidden="true"></i>
                        <div class="ignis-alert__body">Die Tabelle <code>intra_fahrtenbuch</code> existiert noch nicht.
                        Bitte führe <code>composer db:migrate</code> aus oder lade die Seite neu — die Datenbank wird automatisch migriert.</div>
                    </div>
                <?php else: ?>

                <!-- Stats -->
                <dl class="twplus-stats" aria-label="Fahrtenbuchstatistik">
                    <div class="twplus-stats__item">
                        <dt class="twplus-stats__label">Einträge gesamt</dt>
                        <dd class="twplus-stats__value"><?= (int) $stats['total'] ?></dd>
                    </div>
                    <div class="twplus-stats__item">
                        <dt class="twplus-stats__label">Kilometer gesamt</dt>
                        <dd class="twplus-stats__value"><?= number_format((float) $stats['total_km'], 1, ',', '.') ?></dd>
                    </div>
                </dl>

                <!-- Create Form -->
                <?php if ($canManage): ?>
                <div id="createFormWrap" hidden class="twplus-section-card twplus-slide-over mb-4 p-4">
                    <h2 class="mb-4 text-lg">Neuer Eintrag</h2>
                    <form method="POST" action="<?= BASE_PATH ?>logbook/actions">
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
                        include __DIR__ . '/../../assets/components/logbook/_form-fields.php';
                        ?>

                        <div class="mt-4 flex gap-2 justify-end">
                            <button type="button" class="ignis-btn ignis-btn--ghost" id="cancelCreateForm">Abbrechen</button>
                            <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-save" aria-hidden="true"></i> Speichern</button>
                        </div>
                    </form>
                </div>

                <!-- Edit Form -->
                <div id="editFormWrap" hidden class="twplus-section-card twplus-slide-over mb-4 p-4">
                    <h2 class="mb-4 text-lg">Eintrag bearbeiten</h2>
                    <form method="POST" action="<?= BASE_PATH ?>logbook/actions" id="editForm">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id" value="">
                        <input type="hidden" name="return_to" value="admin">

                        <?php
                        $context = 'admin';
                        $entry = null;
                        include __DIR__ . '/../../assets/components/logbook/_form-fields.php';
                        ?>

                        <div class="mt-4 flex gap-2 justify-end">
                            <button type="button" class="ignis-btn ignis-btn--ghost" id="cancelEditForm">Abbrechen</button>
                            <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-save" aria-hidden="true"></i> Aktualisieren</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Filter: Fahrzeug, Fahrttyp und Zeitraum per GET, Suche lokal im Browser -->
                <form method="GET" action="<?= BASE_PATH ?>logbook/index" class="ignis-list-toolbar" role="search">
                    <label class="ignis-list-toolbar__search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" id="fbLocalSearch" class="ignis-input" placeholder="Fahrzeug, Fahrer, Grund" aria-label="Einträge durchsuchen"<?= empty($entries) ? ' disabled' : '' ?>>
                    </label>
                    <label class="ignis-list-toolbar__field">
                        <span class="ignis-field__label">Fahrzeug</span>
                        <select name="vehicle" class="ignis-input ignis-input--sm" data-custom-dropdown="true">
                            <option value="">Alle</option>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?= (int) $v['id'] ?>" <?= $filterVehicle === (int) $v['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['identifier']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="ignis-list-toolbar__field">
                        <span class="ignis-field__label">Fahrttyp</span>
                        <select name="fahrttyp" class="ignis-input ignis-input--sm" data-custom-dropdown="true">
                            <option value="">Alle</option>
                            <?php foreach ($fahrttypen as $slug => $label): ?>
                                <option value="<?= htmlspecialchars($slug) ?>" <?= $filterFahrttyp === $slug ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="ignis-list-toolbar__field">
                        <span class="ignis-field__label">Von</span>
                        <input type="date" name="date_from" class="ignis-input ignis-input--sm" value="<?= htmlspecialchars($filterDateFrom) ?>">
                    </label>
                    <label class="ignis-list-toolbar__field">
                        <span class="ignis-field__label">Bis</span>
                        <input type="date" name="date_to" class="ignis-input ignis-input--sm" value="<?= htmlspecialchars($filterDateTo) ?>">
                    </label>
                    <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--secondary">Filtern</button>
                    <?php if ($hasFilter): ?>
                        <a href="<?= BASE_PATH ?>logbook/index" class="ignis-btn ignis-btn--sm ignis-btn--ghost">Zurücksetzen</a>
                    <?php endif; ?>
                </form>

                <!-- Entries Table -->
                <div class="twplus-table-card">
                    <div id="fbNoResults" class="ignis-table-empty" hidden>
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Keine Treffer
                    </div>
                    <?php if (empty($entries)): ?>
                        <div class="twplus-empty">
                            <i class="fa-solid fa-book twplus-empty__icon" aria-hidden="true"></i>
                            <h2 class="twplus-empty__title">Keine Fahrten gefunden</h2>
                            <p class="twplus-empty__description">Passe die Filter an oder erfasse den ersten Fahrtenbucheintrag.</p>
                        </div>
                    <?php else: ?>
                        <div class="twplus-table-card__scroll">
                            <table class="ignis-table" id="fahrtenbuchAdminTable">
                                <thead>
                                    <tr>
                                        <th scope="col">Datum</th>
                                        <th scope="col">Abfahrt</th>
                                        <th scope="col">Ankunft</th>
                                        <th scope="col">Fahrzeug</th>
                                        <th scope="col">Fahrer</th>
                                        <th scope="col">Fahrttyp</th>
                                        <th scope="col" class="ignis-table__num">km</th>
                                        <th scope="col">Stationierungsort</th>
                                        <th scope="col">Grund</th>
                                        <th scope="col">Quelle</th>
                                        <?php if ($canManage): ?><th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($entries as $e):
                                        $typSlug  = $e['fahrttyp'] ?? '';
                                        $typLabel = $fahrttypen[$typSlug] ?? $typSlug;
                                        $typChip  = $chipFor[$fahrttypBadges[$typSlug] ?? 'secondary'] ?? 'secondary';
                                    ?>
                                        <tr data-search="<?= htmlspecialchars(mb_strtolower(
                                            ($e['vehicle_name'] ?? $e['vehicle_identifier']) . ' ' .
                                            $e['fahrer_name'] . ' ' . $typLabel . ' ' .
                                            ($e['stationierungsort'] ?? '') . ' ' . ($e['grund'] ?? '')
                                        )) ?>">
                                            <td><?= \App\Helpers\DateTimeHelper::formatDateLocal($e['datum']) ?></td>
                                            <td><?= \App\Helpers\DateTimeHelper::formatTimeLocal($e['abfahrt']) ?></td>
                                            <td><?= $e['ankunft'] ? \App\Helpers\DateTimeHelper::formatTimeLocal($e['ankunft']) : '<span class="text-[var(--text-3)]">—</span>' ?></td>
                                            <td><?= htmlspecialchars($e['vehicle_name'] ?? $e['vehicle_identifier']) ?></td>
                                            <td><?= htmlspecialchars($e['fahrer_name']) ?></td>
                                            <td><span class="ignis-chip ignis-chip--<?= $typChip ?>"><?= htmlspecialchars($typLabel) ?></span></td>
                                            <td class="ignis-table__num"><?= $e['kilometer'] !== null ? number_format((float) $e['kilometer'], 1, ',', '.') : '—' ?></td>
                                            <td class="max-w-[150px] truncate" title="<?= htmlspecialchars($e['stationierungsort'] ?? '') ?>">
                                                <?= htmlspecialchars($e['stationierungsort'] ?? '') ?: '—' ?>
                                            </td>
                                            <td class="max-w-[150px] truncate" title="<?= htmlspecialchars($e['grund'] ?? '') ?>">
                                                <?= htmlspecialchars($e['grund'] ?? '') ?: '—' ?>
                                            </td>
                                            <td><span class="ignis-chip ignis-chip--secondary"><?= htmlspecialchars($sourceLabels[$e['source']] ?? $e['source']) ?></span></td>
                                            <?php if ($canManage): ?>
                                                <td class="ignis-table__actions">
                                                    <div class="ignis-row-actions">
                                                        <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon fb-edit-btn"
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
                                                                data-ignis-tooltip="Eintrag bearbeiten" aria-label="Eintrag bearbeiten">
                                                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                                        </button>
                                                        <form method="POST" action="<?= BASE_PATH ?>logbook/actions" class="inline"
                                                              onsubmit="<?= confirm_attr('Eintrag wirklich löschen?') ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
                                                            <input type="hidden" name="return_to" value="admin">
                                                            <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger ignis-btn--icon" data-ignis-tooltip="Eintrag löschen" aria-label="Eintrag löschen">
                                                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
                if (editWrap) editWrap.hidden = true;
                createWrap.hidden = !createWrap.hidden;
            });
        }
        if (cancelCreate) {
            cancelCreate.addEventListener('click', function() {
                createWrap.hidden = true;
            });
        }
        if (cancelEdit) {
            cancelEdit.addEventListener('click', function() {
                editWrap.hidden = true;
            });
        }

        // Edit buttons
        document.querySelectorAll('.fb-edit-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (createWrap) createWrap.hidden = true;
                editWrap.hidden = false;

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
        var noResults = document.getElementById('fbNoResults');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                var term = this.value.toLowerCase();
                var rows = document.querySelectorAll('#fahrtenbuchAdminTable tbody tr');
                var visible = 0;
                rows.forEach(function(row) {
                    var searchData = row.dataset.search || '';
                    var match = !term || searchData.indexOf(term) !== -1;
                    row.hidden = !match;
                    if (match) visible++;
                });
                if (noResults) noResults.hidden = visible > 0 || rows.length === 0;
            });
        }
    });
    </script>
