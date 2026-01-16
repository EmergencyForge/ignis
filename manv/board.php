<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\MANV\MANVLage;
use App\MANV\MANVPatient;
use App\MANV\MANVRessource;
use App\Auth\Permissions;

$lageId = $_GET['id'] ?? null;
if (!$lageId) {
    header('Location: index.php');
    exit;
}

if (!Permissions::check(['admin', 'manv.manage'])) {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$manvLage = new MANVLage($pdo);
$manvPatient = new MANVPatient($pdo);
$manvRessource = new MANVRessource($pdo);

$lage = $manvLage->getById((int)$lageId);
if (!$lage) {
    header('Location: index.php');
    exit;
}

$stats = $manvLage->getStatistics((int)$lageId);
$patienten = $manvPatient->getByLage((int)$lageId);
$ressourcen = $manvRessource->getByLage((int)$lageId, 'fahrzeug');

// Reichere Patienten-Daten mit Fahrzeug rd_type an
foreach ($patienten as &$patient) {
    if (!empty($patient['transportmittel_rufname'])) {
        $fahrzeugStmt = $pdo->prepare("
            SELECT f.rd_type, f.name as rufname
            FROM intra_manv_ressourcen r
            LEFT JOIN intra_fahrzeuge f ON r.bezeichnung = f.name
            WHERE r.manv_lage_id = ? 
            AND r.bezeichnung = ?
            LIMIT 1
        ");
        $fahrzeugStmt->execute([(int)$lageId, $patient['transportmittel_rufname']]);
        $fahrzeugData = $fahrzeugStmt->fetch(PDO::FETCH_ASSOC);
        $patient['fahrzeug_rd_type'] = $fahrzeugData['rd_type'] ?? null;
        $patient['fahrzeug_rufname'] = $fahrzeugData['rufname'] ?? $patient['transportmittel_rufname'];
    } else {
        $patient['fahrzeug_rd_type'] = null;
        $patient['fahrzeug_rufname'] = null;
    }
}
unset($patient);

// Patienten nach Sichtungskategorie gruppieren
$patientenBySK = [
    'SK1' => [],
    'SK2' => [],
    'SK3' => [],
    'SK4' => [],
    'SK5' => [],
    'SK6' => [],
    'tot' => []
];

foreach ($patienten as $patient) {
    $kategorie = $patient['sichtungskategorie'] ?? null;
    if ($kategorie && isset($patientenBySK[$kategorie])) {
        $patientenBySK[$kategorie][] = $patient;
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = 'MANV-Board - ' . htmlspecialchars($lage['einsatznummer']);
    include __DIR__ . '/../assets/components/_base/admin/head.php';
    ?>
    <style>
        .stats-bar {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .badge-sk1 {
            background-color: #dc3545 !important;
        }

        .badge-sk2 {
            background-color: #ffc107 !important;
            color: #000 !important;
        }

        .badge-sk3 {
            background-color: #28a745 !important;
        }

        .badge-sk4 {
            background-color: #17a2b8 !important;
        }

        .badge-sk5 {
            background-color: #000 !important;
            color: #fff !important;
        }

        .badge-sk6 {
            background-color: #9b59b6 !important;
            color: #fff !important;
        }

        .badge-tot {
            background-color: #000 !important;
            color: #fff !important;
        }
    </style>
</head>

<body data-bs-theme="dark" id="manv-board" data-page="edivi">
    <?php include __DIR__ . '/../assets/components/navbar.php'; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <!-- Header -->
            <hr class="text-light my-3">
            <div class="row mb-5">
                <div class="col-md-8">
                    <h1><?= htmlspecialchars($lage['einsatznummer']) ?></h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($lage['einsatzort']) ?>
                    </p>
                    <small class="text-muted">
                        Beginn: <?= $lage['einsatzbeginn'] ? date('d.m.Y H:i', strtotime($lage['einsatzbeginn'])) : 'Nicht angegeben' ?>
                    </small>
                </div>
                <div class="col-md-4 text-end">
                    <a href="<?= BASE_PATH ?>manv/edit.php?id=<?= $lageId ?>" class="btn btn-secondary">
                        <i class="fas fa-edit me-2"></i>Bearbeiten
                    </a>
                </div>
            </div>

            <!-- Einsatzleitung -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <strong>LNA:</strong> <?= htmlspecialchars($lage['lna_name'] ?? 'Nicht zugewiesen') ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <strong>OrgL:</strong> <?= htmlspecialchars($lage['orgl_name'] ?? 'Nicht zugewiesen') ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistiken -->
            <div class="stats-bar">
                <div class="row text-center">
                    <div class="col-md-2">
                        <h3 class="mb-0"><?= $stats['total_patienten'] ?></h3>
                        <small class="text-muted">Gesamt</small>
                    </div>
                    <div class="col-md-2">
                        <h3 class="mb-0 text-danger"><?= $stats['sk1'] ?></h3>
                        <small class="text-muted">SK1</small>
                    </div>
                    <div class="col-md-2">
                        <h3 class="mb-0 text-warning"><?= $stats['sk2'] ?></h3>
                        <small class="text-muted">SK2</small>
                    </div>
                    <div class="col-md-1">
                        <h3 class="mb-0 text-success"><?= $stats['sk3'] ?></h3>
                        <small class="text-muted">SK3</small>
                    </div>
                    <div class="col-md-1">
                        <h3 class="mb-0 text-info"><?= $stats['sk4'] ?></h3>
                        <small class="text-muted">SK4</small>
                    </div>
                    <div class="col-md-1">
                        <h3 class="mb-0" style="color: #fff;"><?= $stats['sk5'] ?? 0 ?></h3>
                        <small class="text-muted">SK5</small>
                    </div>
                    <div class="col-md-1">
                        <h3 class="mb-0" style="color: #9b59b6;"><?= $stats['sk6'] ?? 0 ?></h3>
                        <small class="text-muted">SK6</small>
                    </div>
                    <div class="col-md-2">
                        <h3 class="mb-0"><?= $stats['transportiert'] ?></h3>
                        <small class="text-muted">Transportiert</small>
                    </div>
                </div>
            </div>

            <!-- Patienten-Tabelle -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>Patienten</h5>
                    <div>
                        <a href="ressourcen.php?lage_id=<?= $lageId ?>" class="btn btn-sm btn-secondary me-2">
                            <i class="fas fa-truck me-2"></i>Fahrzeugverwaltung (<?= count($ressourcen) ?>)
                        </a>
                        <a href="<?= BASE_PATH ?>manv/patient-create.php?lage_id=<?= $lageId ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-user-plus me-2"></i>Neuer Patient
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="patientenTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Pat.-Nr.</th>
                                    <th>SK</th>
                                    <th>Name</th>
                                    <th>Verletzung</th>
                                    <th>Transportmittel</th>
                                    <th>Transportziel</th>
                                    <th>Status</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($patienten as $patient): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($patient['patienten_nummer']) ?></strong></td>
                                        <td>
                                            <?php
                                            $skBadgeClass = 'badge-' . strtolower($patient['sichtungskategorie']);
                                            ?>
                                            <span class="badge <?= $skBadgeClass ?>">
                                                <?= htmlspecialchars($patient['sichtungskategorie']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($patient['name'] ?? 'Unbekannt') ?>
                                            <?php if ($patient['vorname']): ?>
                                                <?= htmlspecialchars($patient['vorname']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($patient['verletzungen']): ?>
                                                <?= htmlspecialchars(mb_substr($patient['verletzungen'], 0, 50)) ?><?= mb_strlen($patient['verletzungen']) > 50 ? '...' : '' ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($patient['transportmittel_rufname']): ?>
                                                <i class="fas fa-ambulance me-1"></i><?= htmlspecialchars($patient['fahrzeug_rufname'] ?? $patient['transportmittel_rufname']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Nicht zugewiesen</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $patient['transportziel'] ? htmlspecialchars($patient['transportziel']) : '-' ?></td>
                                        <td>
                                            <?php if ($patient['transport_abfahrt']): ?>
                                                <span class="badge bg-secondary"><i class="fas fa-check-double me-1"></i>Abgefahren</span>
                                            <?php elseif ($patient['transportziel']): ?>
                                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Bereit</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning"><i class="fas fa-clock me-1"></i>Wartend</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?= BASE_PATH ?>manv/patient-view.php?id=<?= $patient['id'] ?>" class="btn btn-sm btn-primary me-1">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php
                                            // SK4, SK5, SK6 können nicht transportiert werden
                                            // Nur Rettungsdienstfahrzeuge (rd_type >= 1) können transportieren, keine Feuerwehr (rd_type = 0)
                                            $canTransport = !in_array($patient['sichtungskategorie'], ['SK4', 'SK5', 'SK6', 'tot']);
                                            $isTransportVehicle = isset($patient['fahrzeug_rd_type']) && (int)$patient['fahrzeug_rd_type'] >= 1;
                                            if ($canTransport && $isTransportVehicle && !$patient['transport_abfahrt'] && $patient['transportziel'] && $patient['transportziel'] !== 'Kein Transport'):
                                            ?>
                                                <button class="btn btn-sm btn-success transport-btn"
                                                    data-patient-id="<?= $patient['id'] ?>"
                                                    data-patient-nr="<?= htmlspecialchars($patient['patienten_nummer']) ?>">
                                                    <i class="fas fa-truck-loading me-1"></i>Abfahrt
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Schnellaktionen -->
            <div class="row mt-4 mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Zurück zur Übersicht
                            </a>
                            <a href="log.php?id=<?= $lageId ?>" class="btn btn-secondary ms-2">
                                <i class="fas fa-history me-2"></i>Aktionslog
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            </hr>
        </div>
    </div>

    <!-- Transport Abfahrt Modal -->
    <div class="modal fade" id="transportModal" tabindex="-1" aria-labelledby="transportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title" id="transportModalLabel">
                        <i class="fas fa-truck-loading me-2"></i>Patient als abgefahren markieren
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body">
                    <p>Möchten Sie Patient <strong id="modal-patient-nr"></strong> wirklich als abgefahren markieren?</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Der Patient wird nicht mehr an der Einsatzstelle angezeigt.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Abbrechen
                    </button>
                    <button type="button" class="btn btn-success" id="confirmTransportBtn">
                        <i class="fas fa-check me-2"></i>Bestätigen
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../assets/components/footer.php'; ?>

    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net/js/dataTables.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#patientenTable').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [10, 25, 50, 100],
                pageLength: 25,
                order: [
                    [1, 'asc'],
                    [0, 'asc']
                ],
                columnDefs: [{
                    orderable: false,
                    targets: -1 // Aktionen-Spalte nicht sortierbar
                }],
                language: {
                    "decimal": "",
                    "emptyTable": "Keine Patienten vorhanden",
                    "info": "Zeige _START_ bis _END_ | Gesamt: _TOTAL_",
                    "infoEmpty": "Keine Daten verfügbar",
                    "infoFiltered": "| Gefiltert von _MAX_ Patienten",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ Einträge anzeigen",
                    "loadingRecords": "Wird geladen...",
                    "processing": "Bitte warten...",
                    "search": "Suchen:",
                    "zeroRecords": "Keine übereinstimmenden Einträge gefunden",
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

            // Transport/Abfahrt Button Handler
            let currentPatientId = null;
            let currentPatientNr = null;

            $('.transport-btn').on('click', function() {
                currentPatientId = $(this).data('patient-id');
                currentPatientNr = $(this).data('patient-nr');

                $('#modal-patient-nr').text(currentPatientNr);
                $('#transportModal').modal('show');
            });

            $('#confirmTransportBtn').on('click', function() {
                if (currentPatientId) {
                    $.ajax({
                        url: '<?= BASE_PATH ?>api/manv-api.php',
                        method: 'POST',
                        data: {
                            action: 'transport_abfahrt',
                            patient_id: currentPatientId
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#transportModal').modal('hide');
                                location.reload();
                            } else {
                                alert('Fehler: ' + (response.message || 'Unbekannter Fehler'));
                            }
                        },
                        error: function() {
                            alert('Fehler bei der Kommunikation mit dem Server');
                        }
                    });
                }
            });
        });
    </script>
</body>

</html>