<?php
// This file should be included in view.php
// Expects: $asuProtocols, fmt_dt(), fmt_elapsed() functions to be available

?>

<!-- Einsatzgeschehen -->
<div class="intra__tile p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Einsatzgeschehen</h4>
    </div>

    <?php if ($incident['finalized']): ?>
        <!-- Read-only view wenn abgeschlossen -->
        <div class="alert alert-secondary mb-0">
            <?php if (!empty($incident['notes'])): ?>
                <?= nl2br(htmlspecialchars($incident['notes'])) ?>
            <?php else: ?>
                <em class="text-muted">Keine Beschreibung vorhanden</em>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Editable form -->
        <form method="post" action="<?= BASE_PATH ?>einsatz/actions.php">
            <input type="hidden" name="action" value="update_notes">
            <input type="hidden" name="incident_id" value="<?= $id ?>">
            <input type="hidden" name="return_tab" value="bericht">
            <textarea name="notes" class="form-control mb-2" rows="5" placeholder="Beschreiben Sie hier das Einsatzgeschehen..."><?= htmlspecialchars($incident['notes'] ?? '') ?></textarea>
            <div class="text-end">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-save me-1"></i>Speichern
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<!-- ASU-Protokolle -->
<div class="intra__tile p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Atemschutzüberwachung (ASU)</h4>
        <?php if (!$incident['finalized']): ?>
            <a href="<?= BASE_PATH ?>einsatz/asu.php?incident_id=<?= $id ?>&incident_number=<?= urlencode($incident['incident_number']) ?>&location=<?= urlencode($incident['location']) ?>" class="btn btn-danger btn-sm">
                ASU starten
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($asuProtocols)): ?>
        <div class="alert alert-secondary">
            <i class="fa-solid fa-info-circle me-2"></i>
            Keine ASU-Protokolle vorhanden
        </div>
    <?php else: ?>
        <div class="row g-2">
            <?php foreach ($asuProtocols as $asu): ?>
                <?php $protocolData = json_decode($asu['data'], true) ?? []; ?>
                <div class="col-auto">
                    <button type="button" class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#asuModal<?= (int)$asu['id'] ?>">
                        <i class="fa-solid fa-shield"></i>
                        <?= htmlspecialchars($asu['supervisor']) ?>
                        <br>
                        <small><?= fmt_dt(!empty($asu['updated_at']) && strtotime($asu['updated_at']) > strtotime($asu['created_at']) ? $asu['updated_at'] : $asu['created_at']) ?></small>
                    </button>
                </div>

                <!-- Modal für ASU Protokoll -->
                <div class="modal fade" id="asuModal<?= (int)$asu['id'] ?>" tabindex="-1" aria-labelledby="asuModalLabel<?= (int)$asu['id'] ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="asuModalLabel<?= (int)$asu['id'] ?>">
                                    ASU-Protokoll: <?= htmlspecialchars($asu['supervisor']) ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                            </div>
                            <div class="modal-body">
                                <!-- Grundinformationen -->
                                <div class="mb-4">
                                    <h6 class="mb-3">Einsatzinformationen</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <small class="text-muted d-block mb-1">Überwacher</small>
                                            <p class="mb-0"><strong><?= htmlspecialchars($asu['supervisor']) ?></strong></p>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block mb-1">Erfasst am</small>
                                            <p class="mb-0"><?= fmt_dt($asu['created_at']) ?></p>
                                        </div>
                                        <?php if (!empty($asu['mission_location'])): ?>
                                            <div class="col-md-6">
                                                <small class="text-muted d-block mb-1">Einsatzort</small>
                                                <p class="mb-0"><?= htmlspecialchars($asu['mission_location']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($asu['mission_date'])): ?>
                                            <div class="col-md-6">
                                                <small class="text-muted d-block mb-1">Einsatzdatum</small>
                                                <p class="mb-0">
                                                    <?php
                                                    if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $asu['mission_date'], $matches)) {
                                                        echo htmlspecialchars($matches[3] . '.' . $matches[2] . '.' . $matches[1]);
                                                    } else {
                                                        echo htmlspecialchars($asu['mission_date']);
                                                    }
                                                    ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Trupps -->
                                <?php
                                $trupps = [];
                                for ($i = 1; $i <= 10; $i++) {
                                    $truppKey = 'trupp' . $i;
                                    if (isset($protocolData[$truppKey]) && !empty($protocolData[$truppKey])) {
                                        $trupp = $protocolData[$truppKey];
                                        if (
                                            !empty($trupp['tf']) || !empty($trupp['tm1']) || !empty($trupp['tm2']) ||
                                            !empty($trupp['startTime']) || !empty($trupp['retreat']) || !empty($trupp['end']) ||
                                            !empty($trupp['mission']) || !empty($trupp['objective']) || !empty($trupp['startPressure']) ||
                                            !empty($trupp['elapsedTime']) || !empty($trupp['check1']) || !empty($trupp['check2']) ||
                                            !empty($trupp['remarks'])
                                        ) {
                                            $trupps[] = $trupp;
                                        }
                                    }
                                }
                                ?>
                                <?php if (!empty($trupps)): ?>
                                    <div class="mb-4">
                                        <h6 class="mb-3">Trupps</h6>
                                        <div class="row g-3">
                                            <?php
                                            $truppCount = count($trupps);
                                            $colClass = match ($truppCount) {
                                                1 => 'col-12',
                                                2 => 'col-md-6',
                                                default => 'col-lg-4 col-md-6'
                                            };
                                            ?>
                                            <?php foreach ($trupps as $trupp): ?>
                                                <div class="<?= $colClass ?>">
                                                    <div class="border rounded p-3" style="background-color: rgba(255,255,255,0.02); border-color: rgba(255,255,255,0.1) !important;">
                                                        <?php
                                                        $num = isset($trupp['truppNumber']) ? (int)$trupp['truppNumber'] : 0;
                                                        $label = match ($num) {
                                                            1 => '1. Trupp',
                                                            2 => '2. Trupp',
                                                            3 => 'Sicherheitstrupp',
                                                            default => $num > 0 ? ('Trupp ' . $num) : 'Trupp'
                                                        };
                                                        ?>
                                                        <h6 class="mb-3 pb-2 border-bottom">
                                                            <?= htmlspecialchars($label) ?>
                                                        </h6>

                                                        <?php if (!empty($trupp['tf']) || !empty($trupp['tm1']) || !empty($trupp['tm2'])): ?>
                                                            <div class="mb-3">
                                                                <small class="text-muted d-block mb-2"><strong>Personal</strong></small>
                                                                <?php if (!empty($trupp['tf'])): ?>
                                                                    <small class="d-block"><strong>TF:</strong> <?= htmlspecialchars($trupp['tf']) ?></small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($trupp['tm1'])): ?>
                                                                    <small class="d-block"><strong>TM1:</strong> <?= htmlspecialchars($trupp['tm1']) ?></small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($trupp['tm2'])): ?>
                                                                    <small class="d-block"><strong>TM2:</strong> <?= htmlspecialchars($trupp['tm2']) ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($trupp['mission']) || !empty($trupp['objective'])): ?>
                                                            <div class="mb-3">
                                                                <small class="text-muted d-block mb-2"><strong>Einsatz</strong></small>
                                                                <?php if (!empty($trupp['mission'])): ?>
                                                                    <small class="d-block"><strong>Art:</strong> <?= htmlspecialchars($trupp['mission']) ?></small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($trupp['objective'])): ?>
                                                                    <small class="d-block"><strong>Ziel:</strong> <?= htmlspecialchars($trupp['objective']) ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($trupp['startTime']) || !empty($trupp['retreat']) || !empty($trupp['end'])): ?>
                                                            <div class="mb-3">
                                                                <small class="text-muted d-block mb-2"><strong>Zeiten</strong></small>
                                                                <?php if (!empty($trupp['startTime'])): ?>
                                                                    <small class="d-block"><strong>Start:</strong> <?= htmlspecialchars($trupp['startTime']) ?> Uhr</small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($trupp['retreat'])): ?>
                                                                    <small class="d-block"><strong>Rückzug:</strong> <?= htmlspecialchars($trupp['retreat']) ?> Uhr</small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($trupp['end'])): ?>
                                                                    <small class="d-block"><strong>Ende:</strong> <?= htmlspecialchars($trupp['end']) ?> Uhr</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($trupp['startPressure']) || !empty($trupp['elapsedTime'])): ?>
                                                            <div class="mb-3">
                                                                <small class="text-muted d-block mb-2"><strong>Ausrüstung</strong></small>
                                                                <?php if (!empty($trupp['startPressure'])): ?>
                                                                    <small class="d-block"><strong>Startdruck:</strong> <?= htmlspecialchars($trupp['startPressure']) ?></small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($trupp['elapsedTime'])): ?>
                                                                    <small class="d-block"><strong>Einsatzzeit:</strong> <?= fmt_elapsed($trupp['elapsedTime']) ?> Min.</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($trupp['check1']) || !empty($trupp['check2'])): ?>
                                                            <div class="mb-3">
                                                                <small class="text-muted d-block mb-2"><strong>Druckkontrollen</strong></small>
                                                                <?php if (!empty($trupp['check1'])): ?>
                                                                    <small class="d-block">1. Kontrolle: <?= htmlspecialchars($trupp['check1']) ?></small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($trupp['check2'])): ?>
                                                                    <small class="d-block">2. Kontrolle: <?= htmlspecialchars($trupp['check2']) ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($trupp['remarks'])): ?>
                                                            <div>
                                                                <small class="text-muted d-block mb-2"><strong>Bemerkungen</strong></small>
                                                                <small class="d-block"><?= nl2br(htmlspecialchars($trupp['remarks'])) ?></small>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer">
                                <?php if (!$incident['finalized']): ?>
                                    <a href="<?= BASE_PATH ?>einsatz/asu.php?incident_id=<?= $id ?>&incident_number=<?= urlencode($incident['incident_number']) ?>&location=<?= urlencode($incident['location']) ?>&asu_id=<?= (int)$asu['id'] ?>" class="btn btn-primary">
                                        <i class="fa-solid fa-edit me-1"></i>Protokoll fortführen
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>