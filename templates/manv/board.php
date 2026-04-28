<?php
/**
 * View: MANV-Board (Live-Dashboard einer Lage)
 *
 * @var array<string,mixed>            $lage
 * @var int                            $lageId
 * @var array<string,int>              $stats
 * @var array<int,array<string,mixed>> $patienten   (mit fahrzeug_rd_type + fahrzeug_rufname angereichert)
 * @var array<int,array<string,mixed>> $ressourcen
 * @var \PDO                           $pdo
 */

$SITE_TITLE = 'MANV-Board - ' . htmlspecialchars($lage['einsatznummer']);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
    <style>
        .stats-bar {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .badge-sk1 { background-color: #dc3545 !important; }
        .badge-sk2 { background-color: #ffc107 !important; color: #000 !important; }
        .badge-sk3 { background-color: #28a745 !important; }
        .badge-sk4 { background-color: #17a2b8 !important; }
        .badge-sk5 { background-color: #000 !important; color: #fff !important; }
        .badge-sk6 { background-color: #9b59b6 !important; color: #fff !important; }
        .badge-tot { background-color: #000 !important; color: #fff !important; }
    </style>
</head>

<body data-bs-theme="dark" id="manv-board" data-page="edivi">
    <?php include __DIR__ . '/../../assets/components/navbar.php'; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1><?= htmlspecialchars($lage['einsatznummer']) ?></h1>
                    <p class="mb-0 text-gray-400">
                        <i class="fas fa-map-marker-alt mr-2"></i><?= htmlspecialchars($lage['einsatzort']) ?>
                    </p>
                    <small class="text-gray-400">
                        Beginn: <?= !empty($lage['einsatzbeginn']) ? \App\Helpers\DateTimeHelper::formatShortLocal($lage['einsatzbeginn']) : 'Nicht angegeben' ?>
                    </small>
                </div>
                <div class="md:text-right">
                    <a href="<?= BASE_PATH ?>manv/edit?id=<?= $lageId ?>" class="ignis-btn ignis-btn--soft-primary no-underline hover:no-underline">
                        <i class="fas fa-edit mr-2"></i>Bearbeiten
                    </a>
                </div>
            </div>

            <div class="mb-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                <div class="ignis-card">
                    <div class="ignis-card__body">
                        <strong>LNA:</strong> <?= htmlspecialchars($lage['lna_name'] ?? 'Nicht zugewiesen') ?>
                    </div>
                </div>
                <div class="ignis-card">
                    <div class="ignis-card__body">
                        <strong>OrgL:</strong> <?= htmlspecialchars($lage['orgl_name'] ?? 'Nicht zugewiesen') ?>
                    </div>
                </div>
            </div>

            <div class="stats-bar">
                <div class="grid grid-cols-4 gap-2 text-center md:grid-cols-8">
                    <div>
                        <h3 class="mb-0"><?= (int) $stats['total_patienten'] ?></h3>
                        <small class="text-gray-400">Gesamt</small>
                    </div>
                    <div>
                        <h3 class="mb-0 text-[#d46b6b]"><?= (int) $stats['sk1'] ?></h3>
                        <small class="text-gray-400">SK1</small>
                    </div>
                    <div>
                        <h3 class="mb-0 text-[#ddb84a]"><?= (int) $stats['sk2'] ?></h3>
                        <small class="text-gray-400">SK2</small>
                    </div>
                    <div>
                        <h3 class="mb-0 text-[#6abf76]"><?= (int) $stats['sk3'] ?></h3>
                        <small class="text-gray-400">SK3</small>
                    </div>
                    <div>
                        <h3 class="mb-0 text-[#5bb8cc]"><?= (int) $stats['sk4'] ?></h3>
                        <small class="text-gray-400">SK4</small>
                    </div>
                    <div>
                        <h3 class="mb-0" style="color: #fff;"><?= (int) ($stats['sk5'] ?? 0) ?></h3>
                        <small class="text-gray-400">SK5</small>
                    </div>
                    <div>
                        <h3 class="mb-0" style="color: #9b59b6;"><?= (int) ($stats['sk6'] ?? 0) ?></h3>
                        <small class="text-gray-400">SK6</small>
                    </div>
                    <div>
                        <h3 class="mb-0"><?= (int) $stats['transportiert'] ?></h3>
                        <small class="text-gray-400">Transportiert</small>
                    </div>
                </div>
            </div>

            <div class="ignis-card mb-4">
                <div class="ignis-card__header flex flex-wrap items-center justify-between gap-2">
                    <h5 class="mb-0"><i class="fas fa-users mr-2"></i>Patienten</h5>
                    <div class="flex flex-wrap gap-2">
                        <a href="ressourcen?lage_id=<?= $lageId ?>" class="ignis-btn ignis-btn--sm ignis-btn--outline-secondary no-underline hover:no-underline">
                            <i class="fas fa-truck mr-2"></i>Fahrzeugverwaltung (<?= count($ressourcen) ?>)
                        </a>
                        <a href="<?= BASE_PATH ?>manv/patient-create?lage_id=<?= $lageId ?>" class="ignis-btn ignis-btn--sm ignis-btn--soft-primary no-underline hover:no-underline">
                            <i class="fas fa-user-plus mr-2"></i>Neuer Patient
                        </a>
                    </div>
                </div>
                <div class="ignis-card__body">
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
                                <?php foreach ($patienten as $patient):
                                    $skBadgeClass = 'badge-' . strtolower((string) $patient['sichtungskategorie']);
                                    $canTransport       = !in_array($patient['sichtungskategorie'] ?? '', ['SK4', 'SK5', 'SK6', 'tot'], true);
                                    $isTransportVehicle = isset($patient['fahrzeug_rd_type']) && (int) $patient['fahrzeug_rd_type'] >= 1;
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($patient['patienten_nummer']) ?></strong></td>
                                        <td>
                                            <span class="badge <?= $skBadgeClass ?>">
                                                <?= htmlspecialchars($patient['sichtungskategorie'] ?? '') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($patient['name'] ?? 'Unbekannt') ?>
                                            <?php if (!empty($patient['vorname'])): ?>
                                                <?= htmlspecialchars($patient['vorname']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($patient['verletzungen'])): ?>
                                                <?= htmlspecialchars(mb_substr($patient['verletzungen'], 0, 50)) ?><?= mb_strlen($patient['verletzungen']) > 50 ? '...' : '' ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($patient['transportmittel_rufname'])): ?>
                                                <i class="fas fa-ambulance mr-1"></i><?= htmlspecialchars($patient['fahrzeug_rufname'] ?? $patient['transportmittel_rufname']) ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">Nicht zugewiesen</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= !empty($patient['transportziel']) ? htmlspecialchars($patient['transportziel']) : '-' ?></td>
                                        <td>
                                            <?php if (!empty($patient['transport_abfahrt'])): ?>
                                                <span class="ignis-chip ignis-chip--status ignis-chip--dark">Abgefahren</span>
                                            <?php elseif (!empty($patient['transportziel'])): ?>
                                                <span class="ignis-chip ignis-chip--status ignis-chip--success">Bereit</span>
                                            <?php else: ?>
                                                <span class="ignis-chip ignis-chip--status ignis-chip--warning">Wartend</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?= BASE_PATH ?>manv/patient-view?id=<?= (int) $patient['id'] ?>" class="ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon mr-1">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($canTransport && $isTransportVehicle && empty($patient['transport_abfahrt']) && !empty($patient['transportziel']) && $patient['transportziel'] !== 'Kein Transport'): ?>
                                                <button class="ignis-btn ignis-btn--sm ignis-btn--success transport-ignis-btn"
                                                    data-patient-id="<?= (int) $patient['id'] ?>"
                                                    data-patient-nr="<?= htmlspecialchars($patient['patienten_nummer']) ?>">
                                                    <i class="fas fa-truck-loading mr-1"></i>Abfahrt
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

            <div class="mb-4 mt-4">
                <div class="ignis-card">
                    <div class="ignis-card__body flex flex-wrap gap-2">
                        <a href="index" class="ignis-btn ignis-btn--ghost no-underline hover:no-underline">
                            <i class="fas fa-arrow-left mr-2"></i>Zurück zur Übersicht
                        </a>
                        <a href="log?id=<?= $lageId ?>" class="ignis-btn ignis-btn--outline-secondary no-underline hover:no-underline">
                            <i class="fas fa-history mr-2"></i>Aktionslog
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transport Abfahrt Modal -->
    <div class="modal fade" id="transportModal" tabindex="-1" aria-labelledby="transportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-[rgba(0,0,0,0.3)]">
                <div class="modal-header">
                    <h5 class="modal-title" id="transportModalLabel">
                        <i class="fas fa-truck-loading mr-2"></i>Patient als abgefahren markieren
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body">
                    <p>Möchten Sie Patient <strong id="modal-patient-nr"></strong> wirklich als abgefahren markieren?</p>
                    <div class="ignis-alert ignis-alert--info">
                        <i class="fas fa-info-circle mr-2"></i>
                        Der Patient wird nicht mehr an der Einsatzstelle angezeigt.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">
                        <i class="fas fa-times mr-2"></i>Abbrechen
                    </button>
                    <button type="button" class="ignis-btn ignis-btn--success" id="confirmTransportBtn">
                        <i class="fas fa-check mr-2"></i>Bestätigen
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../assets/components/footer.php'; ?>

    <script>
        $(document).ready(function() {
            $('#patientenTable').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [10, 25, 50, 100],
                pageLength: 25,
                order: [[1, 'asc'], [0, 'asc']],
                columnDefs: [{ orderable: false, targets: -1 }],
                language: window.IgnisDataTableLang('Einträge')
            });

            let currentPatientId = null;
            let currentPatientNr = null;

            $('.transport-ignis-btn').on('click', function() {
                currentPatientId = $(this).data('patient-id');
                currentPatientNr = $(this).data('patient-nr');
                $('#modal-patient-nr').text(currentPatientNr);
                $('#transportModal').modal('show');
            });

            $('#confirmTransportBtn').on('click', function() {
                if (currentPatientId) {
                    $.ajax({
                        url: '<?= BASE_PATH ?>api/manv/api',
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
                                showToast('Fehler: ' + (response.message || 'Unbekannter Fehler'), 'danger');
                            }
                        },
                        error: function() {
                            showToast('Fehler bei der Kommunikation mit dem Server', 'danger');
                        }
                    });
                }
            });
        });
    </script>
</body>

</html>
