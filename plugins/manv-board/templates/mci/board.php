<?php
/**
 * View: MANV-Board (Live-Dashboard einer Lage)
 *
 * Oben die Patienten an der Einsatzstelle als Board (.ignis-board) mit
 * einer Spalte je Sichtungskategorie im Ton der Kategorie, darunter
 * dieselben Patienten als sortierbare Tabelle.
 *
 * Die Patiententabelle sortiert auf dem Server (App\Support\ListQuery,
 * MciController::board, ?sort=&dir=), die Kopfzellen sind Links; Standard
 * ist Sichtungskategorie, dann Patientennummer.
 *
 * @var array<string,mixed>            $lage
 * @var int                            $lageId
 * @var array<string,int>              $stats
 * @var array<int,array<string,mixed>> $patienten   (mit fahrzeug_rd_type + fahrzeug_rufname angereichert)
 * @var array<int,array<string,mixed>> $ressourcen
 * @var \App\Support\ListQuery         $list
 */

$SITE_TITLE = 'MANV-Board - ' . htmlspecialchars($lage['einsatznummer']);

$layout = 'admin';
$bodyId = 'manv-board';
$bodyPage = 'edivi';

$pgPath = 'mci/board';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>mci/index">MANV-Board</a></span> <span class="ignis-breadcrumb__item" aria-current="page"><?= htmlspecialchars($lage['einsatznummer']) ?></span></nav>
            <header class="twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Aktive MANV-Lage</p>
                    <h1><?= htmlspecialchars($lage['einsatznummer']) ?></h1>
                    <p class="twplus-page-header__description">
                        <i class="fas fa-map-marker-alt mr-2"></i><?= htmlspecialchars($lage['einsatzort']) ?>
                        · Beginn: <?= !empty($lage['einsatzbeginn']) ? \App\Helpers\DateTimeHelper::formatShortLocal($lage['einsatzbeginn']) : 'Nicht angegeben' ?>
                    </p>
                </div>
                <div class="twplus-page-header__actions">
                    <a href="<?= BASE_PATH ?>mci/log?id=<?= $lageId ?>" class="ignis-btn ignis-btn--ghost">
                        <i class="fas fa-history mr-2"></i>Aktionslog
                    </a>
                    <a href="<?= BASE_PATH ?>mci/edit?id=<?= $lageId ?>" class="ignis-btn ignis-btn--secondary">
                        <i class="fas fa-edit mr-2"></i>Bearbeiten
                    </a>
                    <a href="<?= BASE_PATH ?>mci/patient-create?lage_id=<?= $lageId ?>" class="ignis-btn ignis-btn--primary">
                        <i class="fas fa-user-plus mr-2"></i>Neuer Patient
                    </a>
                </div>
            </header>

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

            <div class="ignis-kpis mb-4">
                <div class="ignis-kpi">
                    <div class="ignis-kpi__top"><span class="ignis-kpi__label"><i class="fa-solid fa-user-injured" aria-hidden="true"></i>An der Einsatzstelle</span></div>
                    <div class="ignis-kpi__value"><?= count($patienten) ?></div>
                    <div class="ignis-kpi__sub"><?= (int) $stats['total_patienten'] ?> gesichtet insgesamt</div>
                </div>
                <div class="ignis-kpi">
                    <div class="ignis-kpi__top"><span class="ignis-kpi__label"><i class="fa-solid fa-truck-medical" aria-hidden="true"></i>Transportiert</span></div>
                    <div class="ignis-kpi__value"><?= (int) $stats['transportiert'] ?></div>
                </div>
            </div>

            <?php
            // Board nach Sichtung: jede Spalte trägt den Ton ihrer Kategorie,
            // keine im Akzent. SK1 bis SK3 stehen immer, die übrigen nur,
            // wenn dort jemand liegt.
            $boardLanes = [
                'SK1' => ['SK1 Rot', 'danger'],
                'SK2' => ['SK2 Gelb', 'warn'],
                'SK3' => ['SK3 Grün', 'ok'],
                'SK4' => ['SK4 Blau', 'info'],
                'SK5' => ['SK5 Tot', ''],
                'tot' => ['Tot', ''],
                'SK6' => ['SK6 Unverletzt', ''],
                ''    => ['Ohne Sichtung', ''],
            ];
            $boardPatients = array_fill_keys(array_keys($boardLanes), []);
            foreach ($patienten as $boardPatient) {
                $boardKey = (string) ($boardPatient['sichtungskategorie'] ?? '');
                $boardPatients[isset($boardLanes[$boardKey]) ? $boardKey : ''][] = $boardPatient;
            }
            ?>
            <div class="ignis-board mb-4" aria-label="Patienten nach Sichtung">
                <?php foreach ($boardLanes as $laneKey => [$laneTitle, $laneTone]):
                    $lanePatients = $boardPatients[$laneKey];
                    if ($lanePatients === [] && !in_array($laneKey, ['SK1', 'SK2', 'SK3'], true)) {
                        continue;
                    }
                    $laneId = 'lane-' . ($laneKey === '' ? 'ohne' : strtolower($laneKey));
                ?>
                    <section class="ignis-lane" aria-labelledby="<?= $laneId ?>">
                        <header class="ignis-lane__head"<?= $laneTone !== '' ? ' data-tone="' . $laneTone . '"' : '' ?>>
                            <i class="fa-solid fa-user-injured" aria-hidden="true"></i>
                            <h2 class="ignis-lane__title" id="<?= $laneId ?>"><?= $laneTitle ?></h2>
                            <span class="ignis-lane__count" aria-label="<?= count($lanePatients) ?> Patienten"><?= count($lanePatients) ?></span>
                        </header>
                        <?php if ($lanePatients !== []): ?>
                            <ul class="ignis-lane__list">
                                <?php foreach ($lanePatients as $patient):
                                    $canTransport       = !in_array($patient['sichtungskategorie'] ?? '', ['SK4', 'SK5', 'SK6', 'tot'], true);
                                    $isTransportVehicle = isset($patient['fahrzeug_rd_type']) && (int) $patient['fahrzeug_rd_type'] >= 1;
                                ?>
                                    <li>
                                        <article class="ignis-task">
                                            <h3 class="ignis-task__title"><a href="<?= BASE_PATH ?>mci/patient-view?id=<?= (int) $patient['id'] ?>"><?= htmlspecialchars(trim(($patient['name'] ?? '') . ' ' . ($patient['vorname'] ?? '')) ?: 'Unbekannt') ?></a></h3>
                                            <?php if (!empty($patient['verletzungen'])): ?>
                                                <p class="ignis-task__text"><?= htmlspecialchars($patient['verletzungen']) ?></p>
                                            <?php endif; ?>
                                            <div class="ignis-task__foot">
                                                <span class="ignis-mono"><?= htmlspecialchars($patient['patienten_nummer']) ?></span>
                                                <?php if (!empty($patient['transportmittel_rufname'])): ?>
                                                    <span class="ignis-task__meta"><i class="fa-solid fa-truck-medical" aria-hidden="true"></i><?= htmlspecialchars($patient['fahrzeug_rufname'] ?? $patient['transportmittel_rufname']) ?></span>
                                                <?php endif; ?>
                                                <?php if ($canTransport && $isTransportVehicle && !empty($patient['transportziel']) && $patient['transportziel'] !== 'Kein Transport'): ?>
                                                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--secondary transport-btn" data-patient-id="<?= (int) $patient['id'] ?>" data-patient-nr="<?= htmlspecialchars($patient['patienten_nummer']) ?>"><i class="fa-solid fa-truck-ramp-box" aria-hidden="true"></i>Abfahrt</button>
                                                <?php elseif (!empty($patient['transportziel'])): ?>
                                                    <span class="ignis-chip ignis-chip--sm" data-tone="ok"><span class="ignis-dot" aria-hidden="true"></span>Bereit</span>
                                                <?php else: ?>
                                                    <span class="ignis-chip ignis-chip--sm" data-tone="warn"><span class="ignis-dot" aria-hidden="true"></span>Wartend</span>
                                                <?php endif; ?>
                                            </div>
                                        </article>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>

            <div class="twplus-table-card mb-4">
                <div class="ignis-card__header flex flex-wrap items-center justify-between gap-2">
                    <h2 class="ignis-card__title mb-0"><i class="fas fa-users mr-2"></i>Patienten an der Einsatzstelle</h2>
                    <a href="<?= BASE_PATH ?>mci/resources?lage_id=<?= $lageId ?>" class="ignis-btn ignis-btn--sm ignis-btn--secondary">
                        <i class="fas fa-truck mr-2"></i>Fahrzeugverwaltung (<?= count($ressourcen) ?>)
                    </a>
                </div>
                <div class="twplus-table-card__scroll">
                        <table id="patientenTable" class="ignis-table">
                            <thead>
                                <tr>
                                    <?= $list->th('nr', 'Pat.-Nr.', $pgPath) ?>
                                    <?= $list->th('sk', 'SK', $pgPath) ?>
                                    <?= $list->th('name', 'Name', $pgPath) ?>
                                    <?= $list->th('verletzung', 'Verletzung', $pgPath) ?>
                                    <?= $list->th('transport', 'Transportmittel', $pgPath) ?>
                                    <?= $list->th('ziel', 'Transportziel', $pgPath) ?>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($patienten === []): ?>
                                    <?php
                                    $empty = [
                                        'variant' => 'sm',
                                        'icon'    => 'fa-user-injured',
                                        'title'   => 'Noch keine Patienten',
                                        'text'    => 'Patienten erscheinen hier, sobald sie an der Einsatzstelle gesichtet sind.',
                                    ];
                                    ?>
                                    <tr><td colspan="8"><?php require dirname(__DIR__, 4) . '/templates/partials/empty.php'; ?></td></tr>
                                <?php endif; ?>
                                <?php foreach ($patienten as $patient):
                                    $skChipClass = 'ignis-chip--' . strtolower((string) $patient['sichtungskategorie']);
                                    $canTransport       = !in_array($patient['sichtungskategorie'] ?? '', ['SK4', 'SK5', 'SK6', 'tot'], true);
                                    $isTransportVehicle = isset($patient['fahrzeug_rd_type']) && (int) $patient['fahrzeug_rd_type'] >= 1;
                                ?>
                                    <tr>
                                        <td><strong class="ignis-mono"><?= htmlspecialchars($patient['patienten_nummer']) ?></strong></td>
                                        <td>
                                            <span class="ignis-chip <?= $skChipClass ?>">
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
                                                <span class="ignis-list-meta">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($patient['transportmittel_rufname'])): ?>
                                                <i class="fas fa-ambulance mr-1"></i><?= htmlspecialchars($patient['fahrzeug_rufname'] ?? $patient['transportmittel_rufname']) ?>
                                            <?php else: ?>
                                                <span class="ignis-list-meta">Nicht zugewiesen</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= !empty($patient['transportziel']) ? htmlspecialchars($patient['transportziel']) : '-' ?></td>
                                        <td>
                                            <?php if (!empty($patient['transport_abfahrt'])): ?>
                                                <span class="ignis-chip ignis-chip--dot ignis-chip--secondary">Abgefahren</span>
                                            <?php elseif (!empty($patient['transportziel'])): ?>
                                                <span class="ignis-chip ignis-chip--dot ignis-chip--ok">Bereit</span>
                                            <?php else: ?>
                                                <span class="ignis-chip ignis-chip--dot ignis-chip--warn">Wartend</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="ignis-table__actions">
                                            <?php if ($canTransport && $isTransportVehicle && empty($patient['transport_abfahrt']) && !empty($patient['transportziel']) && $patient['transportziel'] !== 'Kein Transport'): ?>
                                                <button class="ignis-btn ignis-btn--sm ignis-btn--secondary transport-btn"
                                                    data-patient-id="<?= (int) $patient['id'] ?>"
                                                    data-patient-nr="<?= htmlspecialchars($patient['patienten_nummer']) ?>">
                                                    <i class="fas fa-truck-loading mr-1"></i>Abfahrt
                                                </button>
                                            <?php endif; ?>
                                            <a href="<?= BASE_PATH ?>mci/patient-view?id=<?= (int) $patient['id'] ?>" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="Patient öffnen" aria-label="Patient öffnen">
                                                <i class="fas fa-arrow-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                </div>
            </div>

            <p class="ignis-list-meta"><a href="<?= BASE_PATH ?>mci/index"><i class="fas fa-arrow-left mr-1"></i>Zurück zur Übersicht</a></p>
        </div>
    </div>


    <script>
        $(document).ready(function() {
            // Transport-Abfahrt-Bestaetigung war frueher ein eigenes Modal mit
            // einem einzigen Confirm-Button. showConfirm + Inline-AJAX ersetzt
            // das vollstaendig — kein Modal-Markup, kein currentPatientId-State.
            $('.transport-btn').on('click', function() {
                const patientId = $(this).data('patient-id');
                const patientNr = $(this).data('patient-nr');
                if (!patientId) return;

                showConfirm(
                    'Möchten Sie Patient ' + patientNr + ' wirklich als abgefahren markieren? Der Patient wird nicht mehr an der Einsatzstelle angezeigt.',
                    {
                        title:       'Patient als abgefahren markieren',
                        confirmText: 'Bestätigen',
                        cancelText:  'Abbrechen',
                    }
                ).then(function (ok) {
                    if (!ok) return;
                    $.ajax({
                        url:    '<?= BASE_PATH ?>api/manv/api',
                        method: 'POST',
                        data:   { action: 'transport_abfahrt', patient_id: patientId },
                        success: function (response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                showToast('Fehler: ' + (response.message || 'Unbekannter Fehler'), 'danger');
                            }
                        },
                        error: function () {
                            showToast('Fehler bei der Kommunikation mit dem Server', 'danger');
                        },
                    });
                });
            });
        });
    </script>
