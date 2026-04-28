<?php
// This file should be included in view.php
// Expects: $incident, $id to be available

// Check if incident can be finalized
$canFinalize = false;
$missingRequired = [];
if ($incident) {
    if (empty($incident['incident_number'])) $missingRequired[] = 'Einsatznummer';
    if (empty($incident['location'])) $missingRequired[] = 'Einsatzort';
    if (empty($incident['keyword'])) $missingRequired[] = 'Einsatzstichwort';
    if (empty($incident['started_at'])) $missingRequired[] = 'Beginn (Datum & Uhrzeit)';
    if (empty($incident['leader_id'])) $missingRequired[] = 'Einsatzleiter';
    $canFinalize = empty($missingRequired) && !$incident['finalized'];
}
?>

<div class="intra__tile mb-3 p-3">
    <h4 class="mb-4">Einsatz abschließen</h4>

    <?php if ($incident['finalized']): ?>
        <div class="ignis-alert ignis-alert--success">
            <i class="fa-solid fa-check-circle mr-2"></i>
            <strong>Dieser Einsatz wurde bereits abgeschlossen</strong>
        </div>

        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="ignis-card bg-[rgba(0,0,0,0.3)]">
                <div class="ignis-card__body">
                    <h6 class="ignis-card__title">Abgeschlossen am</h6>
                    <p class="ignis-card__text">
                        <?php
                        if ($incident['finalized_at']) {
                            $dt = new DateTime($incident['finalized_at'], new DateTimeZone('UTC'));
                            $dt->setTimezone(new DateTimeZone('Europe/Berlin'));
                            echo $dt->format('d.m.Y H:i');
                        } else {
                            echo '-';
                        }
                        ?>
                    </p>
                </div>
            </div>
            <div class="ignis-card bg-[rgba(0,0,0,0.3)]">
                <div class="ignis-card__body">
                    <h6 class="ignis-card__title">QM-Status</h6>
                    <p class="ignis-card__text">
                        <?php
                        $statusMap = [
                            0 => ['bg-secondary', 'Ungesehen'],
                            1 => ['bg-warning', 'In Prüfung'],
                            2 => ['bg-success', 'Freigegeben'],
                            3 => ['bg-danger', 'Ungenügend'],
                            4 => ['bg-dark', 'Ausgeblendet'],
                        ];
                        $s = (int)$incident['status'];
                        [$badge, $statusText] = $statusMap[$s] ?? ['bg-secondary', 'Unbekannt'];
                        ?>
                        <span class="badge <?= $badge ?>"><?= htmlspecialchars($statusText) ?></span>
                    </p>
                </div>
            </div>
        </div>

        <div class="ignis-alert ignis-alert--info mt-4">
            <i class="fa-solid fa-info-circle mr-2"></i>
            Das Protokoll ist zur QM-Sichtung markiert und kann nicht mehr bearbeitet werden.
        </div>

    <?php else: ?>
        <!-- Not finalized yet -->

        <?php if (!$canFinalize): ?>
            <div class="ignis-alert ignis-alert--warning">
                <h5 class="ignis-alert__title">
                    <i class="fa-solid fa-exclamation-triangle mr-2"></i>
                    Abschluss nicht möglich
                </h5>
                <p class="mb-2">Bitte ergänzen Sie folgende Pflichtangaben in den Stammdaten:</p>
                <ul class="mb-0">
                    <?php foreach ($missingRequired as $mr): ?>
                        <li><?= htmlspecialchars($mr) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="ignis-alert ignis-alert--info">
                <i class="fa-solid fa-info-circle mr-2"></i>
                Sie können diesen Einsatz nun abschließen.
            </div>
        <?php endif; ?>

        <div class="ignis-card bg-[rgba(0,0,0,0.3)] mt-4">
            <div class="ignis-card__body">
                <h5 class="ignis-card__title mb-3">Was passiert beim Abschluss?</h5>
                <ul class="mb-0">
                    <li>Das Protokoll wird zur <strong>QM-Sichtung</strong> markiert</li>
                    <li>Alle Daten werden <strong>gesperrt</strong> und können nicht mehr bearbeitet werden</li>
                    <li>Der Status wird auf "Ungesichtet" gesetzt</li>
                    <li>QM-Berechtigte können anschließend den Status ändern</li>
                </ul>
            </div>
        </div>

        <div class="mt-4 flex justify-center">
            <button type="button" class="ignis-btn ignis-btn--success ignis-btn--lg" data-bs-toggle="modal" data-bs-target="#finalizeConfirmModal" <?= $canFinalize ? '' : 'disabled' ?>>
                <i class="fa-solid fa-check-circle mr-2"></i>
                Einsatz jetzt abschließen
            </button>
        </div>

    <?php endif; ?>
</div>