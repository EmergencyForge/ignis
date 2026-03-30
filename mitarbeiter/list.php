<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];

    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin', 'personnel.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
}

$stmtg = $pdo->prepare("SELECT * FROM intra_mitarbeiter_dienstgrade ORDER BY priority ASC");
$stmtg->execute();
$dginfo = $stmtg->fetchAll(PDO::FETCH_UNIQUE);

$stmtr = $pdo->prepare("SELECT * FROM intra_mitarbeiter_rdquali ORDER BY priority ASC");
$stmtr->execute();
$rdginfo = $stmtr->fetchAll(PDO::FETCH_UNIQUE);

$stmtf = $pdo->prepare("SELECT * FROM intra_mitarbeiter_fwquali ORDER BY priority ASC");
$stmtf->execute();
$fwginfo = $stmtf->fetchAll(PDO::FETCH_UNIQUE);

?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . "/../assets/components/_base/admin/head.php";
    ?>
</head>

<body data-bs-theme="dark" data-page="mitarbeiter">
    <?php include "../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <nav class="admin-breadcrumb">
                        <a href="<?= BASE_PATH ?>index.php">Dashboard</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span class="current">Mitarbeiter</span>
                    </nav>
                    <div class="page-header mb-4">
                        <h1>Mitarbeiterübersicht</h1>
                        <div class="header-actions">
                            <?php if (Permissions::check(['admin', 'personnel.edit']) && !isset($_GET['archiv'])): ?>
                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateMitarbeiter">
                                    <i class="fa-solid fa-plus me-1"></i>Neuer Mitarbeiter
                                </button>
                            <?php endif; ?>
                            <?php if (isset($_GET['archiv'])) { ?>
                                <a href="<?= BASE_PATH ?>mitarbeiter/list.php" class="btn btn-outline-success">Aktive Mitarbeiter</a>
                            <?php } else { ?>
                                <a href="<?= BASE_PATH ?>mitarbeiter/list.php?archiv" class="btn btn-outline-secondary">Archiv</a>
                            <?php } ?>
                        </div>
                    </div>
                    <?php
                    Flash::render();
                    ?>

                    <!-- Filter-Leiste -->
                    <div class="intra__tile py-2 px-3 mb-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-auto">
                                <label for="filterDienstgrad" class="form-label small mb-1">Dienstgrad</label>
                                <select class="form-select form-select-sm" id="filterDienstgrad" style="min-width: 180px;">
                                    <option value="">Alle</option>
                                    <?php foreach ($dginfo as $dgId => $dg): ?>
                                        <?php if (!$dg['archive']): ?>
                                            <option value="<?= htmlspecialchars($dg['name']) ?>"><?= htmlspecialchars($dg['name']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <label for="filterRDQuali" class="form-label small mb-1">RD-Qualifikation</label>
                                <select class="form-select form-select-sm" id="filterRDQuali" style="min-width: 200px;">
                                    <option value="">Alle</option>
                                    <?php foreach ($rdginfo as $rdId => $rd): ?>
                                        <?php if (!$rd['none']): ?>
                                            <option value="<?= htmlspecialchars($rd['name']) ?>"><?= htmlspecialchars($rd['name']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <label for="filterFWQuali" class="form-label small mb-1">FW-Qualifikation</label>
                                <select class="form-select form-select-sm" id="filterFWQuali" style="min-width: 180px;">
                                    <option value="">Alle</option>
                                    <?php foreach ($fwginfo as $fwId => $fw): ?>
                                        <?php if (!$fw['none']): ?>
                                            <option value="<?= htmlspecialchars($fw['shortname']) ?>"><?= htmlspecialchars($fw['name']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="resetFilters">
                                    <i class="fa-solid fa-rotate-left"></i> Zurücksetzen
                                </button>
                            </div>
                            <div class="col-auto ms-auto">
                                <button type="button" class="btn btn-sm btn-outline-success" id="exportCSV" data-tooltip="Gefilterte Liste als CSV exportieren">
                                    <i class="fa-solid fa-file-csv"></i> CSV-Export
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="mitarbeiterTable">
                            <thead>
                                <th scope="col">Dienstnummer</th>
                                <th scope="col">Name</th>
                                <th scope="col">Dienstgrad</th>
                                <th scope="col">RD-Quali</th>
                                <th scope="col">FW-Quali</th>
                                <th scope="col">Einstellungsdatum</th>
                                <th scope="col"></th>
                            </thead>
                            <tbody>
                                <?php
                                require __DIR__ . '/../assets/config/database.php';

                                $stmta = $pdo->prepare("SELECT id,archive FROM intra_mitarbeiter_dienstgrade WHERE archive = 1");
                                $stmta->execute();
                                $stdata = $stmta->fetch();

                                if ($stdata !== false) {
                                    // Archivierter Dienstgrad gefunden
                                    if (isset($_GET['archiv'])) {
                                        $listQuery = "SELECT * FROM intra_mitarbeiter WHERE dienstgrad = :dienstgrad ORDER BY einstdatum ASC";
                                        $params = [$stdata['id']];
                                    } else {
                                        $listQuery = "SELECT * FROM intra_mitarbeiter WHERE dienstgrad <> :dienstgrad ORDER BY einstdatum ASC";
                                        $params = [$stdata['id']];
                                    }
                                    $stmt = $pdo->prepare($listQuery);
                                    $stmt->execute($params);
                                } else {
                                    // Kein archivierter Dienstgrad gefunden - alle Mitarbeiter anzeigen
                                    $stmt = $pdo->prepare("SELECT * FROM intra_mitarbeiter ORDER BY einstdatum ASC");
                                    $stmt->execute();
                                }
                                $result = $stmt->fetchAll();

                                foreach ($result as $row) {
                                    $einstellungsdatum = (new DateTime($row['einstdatum']))->format('d.m.Y');

                                    $dginfo2 = $dginfo[$row['dienstgrad'] ?? ''] ?? [];
                                    $rdginfo2 = $rdginfo[$row['qualird'] ?? ''] ?? [];
                                    $fwginfo2 = $fwginfo[$row['qualifw2'] ?? ''] ?? [];

                                    if ($row['geschlecht'] == 0) {
                                        $dienstgrad = $dginfo2['name_m'];
                                        $rdqualtext = $rdginfo2['name_m'];
                                    } elseif ($row['geschlecht'] == 1) {
                                        $dienstgrad = $dginfo2['name_w'];
                                        $rdqualtext = $rdginfo2['name_w'];
                                    } else {
                                        $dienstgrad = $dginfo2['name'];
                                        $rdqualtext = $rdginfo2['name'];
                                    }

                                    // Gender-neutral name for filtering (consistent across genders)
                                    $dgNeutral = $dginfo2['name'] ?? '';
                                    $rdNeutral = $rdginfo2['name'] ?? '';
                                    $fwShort = $fwginfo2['shortname'] ?? '-';
                                    $fwName = $fwginfo2['name'] ?? '';
                                    $isRdNone = $rdginfo2['none'] ?? 1;
                                    $isFwNone = $fwginfo2['none'] ?? 1;

                                    echo "<tr data-dg='" . htmlspecialchars($dgNeutral) . "' data-rd='" . htmlspecialchars($rdNeutral) . "' data-fw='" . htmlspecialchars($fwShort) . "'>";
                                    echo "<td>" . htmlspecialchars($row['dienstnr']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
                                    echo "<td>";
                                    if (!empty($dginfo2['badge'])) {
                                        echo "<img src='" . $dginfo2['badge'] . "' height='16px' width='auto' style='padding-right:5px' alt='Dienstgrad' loading='lazy' />";
                                    }
                                    echo htmlspecialchars($dienstgrad);
                                    echo "</td>";
                                    echo "<td>";
                                    if (!$isRdNone) {
                                        echo "<span class='badge text-bg-warning' style='color:var(--black)'>" . htmlspecialchars($rdqualtext) . "</span>";
                                    } else {
                                        echo "<span class='text-muted'>-</span>";
                                    }
                                    echo "</td>";
                                    echo "<td>";
                                    if (!$isFwNone) {
                                        echo "<span class='badge text-bg-danger'>" . htmlspecialchars($fwShort) . "</span> <small>" . htmlspecialchars($fwName) . "</small>";
                                    } else {
                                        echo "<span class='text-muted'>-</span>";
                                    }
                                    echo "</td>";
                                    echo "<td><span style='display:none'>" . $row['einstdatum'] . "</span>" . $einstellungsdatum . "</td>";
                                    echo "<td><div class='col-actions'><a href='" . BASE_PATH . "mitarbeiter/profile.php?id=" . $row['id'] . "' class='btn btn-sm btn-soft-primary btn-icon' data-tooltip='Profil ansehen'><i class='fa-solid fa-eye'></i></a></div></td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <?php if (Permissions::check(['admin', 'personnel.edit'])): ?>
    <!-- Modal: Neuer Mitarbeiter -->
    <div class="modal fade" id="modalCreateMitarbeiter" tabindex="-1" aria-labelledby="modalCreateMitarbeiterLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateMitarbeiterLabel"><i class="fa-solid fa-user-plus me-2"></i>Neuer Mitarbeiter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <form id="createMitarbeiterForm" novalidate>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input class="form-control" type="text" name="fullname" id="cm_fullname" placeholder="Vor- und Zuname" required>
                                    <label for="cm_fullname">Vor- und Zuname</label>
                                    <div class="invalid-feedback">Pflichtfeld</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input class="form-control" type="date" name="gebdatum" id="cm_gebdatum" min="1900-01-01" placeholder="Geburtsdatum" required>
                                    <label for="cm_gebdatum">Geburtsdatum</label>
                                    <div class="invalid-feedback">Pflichtfeld</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" name="dienstgrad" id="cm_dienstgrad" required>
                                        <option value="" selected hidden>Bitte wählen</option>
                                        <?php foreach ($dginfo as $dgId => $dg): ?>
                                            <?php if (!$dg['archive']): ?>
                                                <option value="<?= $dgId ?>"><?= htmlspecialchars($dg['name']) ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="cm_dienstgrad">Dienstgrad</label>
                                    <div class="invalid-feedback">Pflichtfeld</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select name="geschlecht" id="cm_geschlecht" class="form-select" required>
                                        <option value="" selected hidden>Bitte wählen</option>
                                        <option value="0">Männlich</option>
                                        <option value="1">Weiblich</option>
                                        <option value="2">Divers</option>
                                    </select>
                                    <label for="cm_geschlecht">Geschlecht</label>
                                    <div class="invalid-feedback">Pflichtfeld</div>
                                </div>
                            </div>
                            <?php /** @phpstan-ignore if.alwaysTrue (CHAR_ID is runtime-configured) */ if (CHAR_ID) : ?>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input class="form-control" type="text" name="charakterid" id="cm_charakterid" placeholder="ABC12345" pattern="[a-zA-Z]{3}[0-9]{5}" required>
                                        <label for="cm_charakterid">Charakter-ID</label>
                                        <div class="invalid-feedback">Format: ABC12345</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input class="form-control" type="text" inputmode="numeric" name="discordtag" id="cm_discordtag" pattern="[0-9]{17,18}" maxlength="18" placeholder="Discord-ID" required>
                                    <label for="cm_discordtag">Discord-ID</label>
                                    <div class="invalid-feedback">17-18 Ziffern</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input class="form-control" type="text" name="telefonnr" id="cm_telefonnr" placeholder="Telefonnummer" value="0176 00 00 00 0">
                                    <label for="cm_telefonnr">Telefonnummer</label>
                                </div>
                            </div>
                            <div class="col-md-6 dienstnr-container">
                                <div class="form-floating">
                                    <input class="form-control" type="text" name="dienstnr" id="dienstnr"
                                        pattern="^(?=.*[0-9])[A-Za-z0-9\-]+$" title="z.B. RD-001, BF01" placeholder="Dienstnummer" required>
                                    <label for="dienstnr">Dienstnummer</label>
                                    <div id="dienstnr-status" class="dienstnr-status"></div>
                                    <div class="invalid-feedback">Mindestens eine Zahl (z.B. RD-001)</div>
                                    <div id="dienstnr-feedback" class="text-danger small" style="display: none;"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input class="form-control" type="date" name="einstdatum" id="cm_einstdatum" min="2022-01-01" placeholder="Einstellungsdatum" required>
                                    <label for="cm_einstdatum">Einstellungsdatum</label>
                                    <div class="invalid-feedback">Pflichtfeld</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost btn-sm" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-success btn-sm" id="cm_submit">
                            <i class="fa-solid fa-plus me-1"></i>Mitarbeiter erstellen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net/js/dataTables.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Custom filter functions for dropdowns
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                if (settings.nTable.id !== 'mitarbeiterTable') return true;

                var row = settings.aoData[dataIndex].nTr;
                var dgFilter = $('#filterDienstgrad').val();
                var rdFilter = $('#filterRDQuali').val();
                var fwFilter = $('#filterFWQuali').val();

                if (dgFilter && $(row).data('dg') !== dgFilter) return false;
                if (rdFilter && $(row).data('rd') !== rdFilter) return false;
                if (fwFilter && $(row).data('fw') !== fwFilter) return false;

                return true;
            });

            var table = $('#mitarbeiterTable').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [10, 20, 50, 100],
                pageLength: 20,
                order: [
                    [5, 'asc']
                ],
                columnDefs: [{
                    orderable: false,
                    targets: -1
                }],
                language: {
                    "decimal": "",
                    "emptyTable": "Keine Daten vorhanden",
                    "info": "Zeige _START_ bis _END_  | Gesamt: _TOTAL_",
                    "infoEmpty": "Keine Daten verfügbar",
                    "infoFiltered": "| Gefiltert von _MAX_ Mitarbeitern",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ Mitarbeiter pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Mitarbeiter suchen:",
                    "zeroRecords": "Keine Einträge gefunden",
                    "paginate": {
                        "first": "Erste",
                        "last": "Letzte",
                        "next": "Nächste",
                        "previous": "Vorherige"
                    },
                    "aria": {
                        "sortAscending": ": aktivieren, um Spalte aufsteigend zu sortieren",
                        "sortDescending": ": aktivieren, um Spalte absteigend zu sortieren"
                    }
                }
            });

            // Filter change handlers
            $('#filterDienstgrad, #filterRDQuali, #filterFWQuali').on('change', function() {
                table.draw();
            });

            // Reset filters
            $('#resetFilters').on('click', function() {
                $('#filterDienstgrad, #filterRDQuali, #filterFWQuali').val('');
                table.search('').draw();
            });

            // CSV Export
            $('#exportCSV').on('click', function() {
                var csvContent = "Dienstnummer;Name;Dienstgrad;RD-Qualifikation;FW-Qualifikation;Einstellungsdatum\n";
                var rows = table.rows({filter: 'applied'}).nodes();

                $(rows).each(function() {
                    var cols = $(this).find('td');
                    var dienstnr = $(cols[0]).text().trim();
                    var name = $(cols[1]).text().trim();
                    var dg = $(cols[2]).text().trim();
                    var rd = $(cols[3]).text().trim();
                    var fw = $(cols[4]).text().trim();
                    var datum = $(cols[5]).text().trim();
                    csvContent += '"' + dienstnr + '";"' + name + '";"' + dg + '";"' + rd + '";"' + fw + '";"' + datum + '"\n';
                });

                var blob = new Blob(["\uFEFF" + csvContent], {type: 'text/csv;charset=utf-8;'});
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'mitarbeiter_export_' + new Date().toISOString().slice(0,10) + '.csv';
                link.click();
                showToast('CSV-Export wurde heruntergeladen.', 'success');
            });
        });
    </script>
    <?php if (Permissions::check(['admin', 'personnel.edit'])): ?>
    <script src="<?= BASE_PATH ?>assets/js/dienstnr-check.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Init dienstnr check for the modal (uses id="cm_dienstnr" but shared JS expects id="dienstnr")
        // We need to re-map: the shared JS looks for #dienstnr, but our modal uses #cm_dienstnr
        // So we init it when the modal opens
        var modalEl = document.getElementById('modalCreateMitarbeiter');
        if (!modalEl) return;

        initDienstnrCheck({ basePath: '<?= BASE_PATH ?>' });

        // Form submission
        var form = document.getElementById('createMitarbeiterForm');
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }

            var submitBtn = document.getElementById('cm_submit');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Wird erstellt...';

            var formData = new FormData(form);

            fetch('<?= BASE_PATH ?>mitarbeiter/create.php', {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(function() {
                        window.location.href = data.redirect;
                    }, 500);
                } else {
                    showToast(data.message || 'Fehler beim Erstellen', 'danger');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fa-solid fa-plus me-1"></i>Mitarbeiter erstellen';
                }
            })
            .catch(function() {
                showToast('Verbindungsfehler', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-plus me-1"></i>Mitarbeiter erstellen';
            });
        });

        // Reset form when modal is closed
        modalEl.addEventListener('hide.bs.modal', function() {
            // Move focus away before modal hides to prevent aria-hidden conflict
            if (modalEl.contains(document.activeElement)) {
                document.activeElement.blur();
            }
        });

        modalEl.addEventListener('hidden.bs.modal', function() {
            form.reset();
            form.classList.remove('was-validated');
            // Reset dienstnr check state
            var dnInput = document.getElementById('dienstnr');
            var dnStatus = document.getElementById('dienstnr-status');
            var dnFeedback = document.getElementById('dienstnr-feedback');
            if (dnInput) { dnInput.classList.remove('valid', 'invalid'); }
            if (dnStatus) { dnStatus.innerHTML = ''; dnStatus.className = 'dienstnr-status'; }
            if (dnFeedback) { dnFeedback.style.display = 'none'; }
            // Reset submit button
            var submitBtn = document.getElementById('cm_submit');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-plus me-1"></i>Mitarbeiter erstellen';
            }
        });
    });
    </script>
    <?php endif; ?>
    <?php include __DIR__ . "/../assets/components/footer.php"; ?>
</body>

</html>
