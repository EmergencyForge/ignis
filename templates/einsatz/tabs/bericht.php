<?php
// This file should be included in view.php
// Expects: $asuProtocols, fmt_dt(), fmt_elapsed() functions to be available

?>

<!-- Einsatzgeschehen -->
<div class="intra__tile mb-3 p-3">
    <div class="mb-3 flex items-center justify-between">
        <h4 class="mb-0">Einsatzgeschehen</h4>
    </div>

    <?php if ($incident['finalized']): ?>
        <!-- Read-only view wenn abgeschlossen -->
        <div class="ignis-alert mb-0">
            <?php if (!empty($incident['notes'])): ?>
                <?= nl2br(htmlspecialchars($incident['notes'])) ?>
            <?php else: ?>
                <em class="text-gray-400">Keine Beschreibung vorhanden</em>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Editable form -->
        <form method="post" action="<?= BASE_PATH ?>einsatz/actions.php">
            <input type="hidden" name="action" value="update_notes">
            <input type="hidden" name="incident_id" value="<?= $id ?>">
            <input type="hidden" name="return_tab" value="bericht">
            <textarea name="notes" class="form-control mb-2" rows="5" placeholder="Beschreiben Sie hier das Einsatzgeschehen..."><?= htmlspecialchars($incident['notes'] ?? '') ?></textarea>
            <div class="text-right">
                <button type="submit" class="ignis-btn ignis-btn--primary ignis-btn--sm">
                    <i class="fa-solid fa-save me-1"></i>Speichern
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<!-- ASU-Protokolle -->
<div class="intra__tile mb-3 p-3">
    <div class="mb-3 flex items-center justify-between">
        <h4 class="mb-0">Atemschutzüberwachung (ASU)</h4>
        <?php if (!$incident['finalized']): ?>
            <a href="<?= BASE_PATH ?>einsatz/asu.php?incident_id=<?= $id ?>&incident_number=<?= urlencode($incident['incident_number']) ?>&location=<?= urlencode($incident['location']) ?>" class="ignis-btn ignis-btn--danger ignis-btn--sm">
                ASU starten
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($asuProtocols)): ?>
        <div class="ignis-alert">
            <i class="fa-solid fa-info-circle me-2"></i>
            Keine ASU-Protokolle vorhanden
        </div>
    <?php else: ?>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($asuProtocols as $asu): ?>
                <?php $protocolData = json_decode($asu['data'], true) ?? []; ?>
                <div>
                    <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm" data-bs-toggle="modal" data-bs-target="#asuModal<?= (int)$asu['id'] ?>">
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
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <div>
                                            <small class="mb-1 block text-gray-400">Überwacher</small>
                                            <p class="mb-0"><strong><?= htmlspecialchars($asu['supervisor']) ?></strong></p>
                                        </div>
                                        <div>
                                            <small class="mb-1 block text-gray-400">Erfasst am</small>
                                            <p class="mb-0"><?= fmt_dt($asu['created_at']) ?></p>
                                        </div>
                                        <?php if (!empty($asu['mission_location'])): ?>
                                            <div>
                                                <small class="mb-1 block text-gray-400">Einsatzort</small>
                                                <p class="mb-0"><?= htmlspecialchars($asu['mission_location']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($asu['mission_date'])): ?>
                                            <div>
                                                <small class="mb-1 block text-gray-400">Einsatzdatum</small>
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
                                        <?php
                                        $truppCount = count($trupps);
                                        // Grid-Layout passend zur Trupp-Anzahl wählen — bei 1 Trupp volle Breite,
                                        // 2 nebeneinander, ansonsten 3er-Reihen auf lg, 2 auf md, 1 auf mobil.
                                        $gridClass = match ($truppCount) {
                                            1 => 'grid grid-cols-1 gap-3',
                                            2 => 'grid grid-cols-1 gap-3 md:grid-cols-2',
                                            default => 'grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3'
                                        };
                                        ?>
                                        <div class="<?= $gridClass ?>">
                                            <?php foreach ($trupps as $trupp): ?>
                                                <div>
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
                                                                <small class="mb-2 block text-gray-400"><strong>Personal</strong></small>
                                                                <?php if (!empty($trupp['tf'])): ?>
                                                                    <small class="block"><strong>TF:</strong> <?= htmlspecialchars($trupp['tf']) ?></small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($trupp['tm1'])): ?>
                                                                    <small class="block"><strong>TM1:</strong> <?= htmlspecialchars($trupp['tm1']) ?></small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($trupp['tm2'])): ?>
                                                                    <small class="block"><strong>TM2:</strong> <?= htmlspecialchars($trupp['tm2']) ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($trupp['mission']) || !empty($trupp['objective'])): ?>
                                                            <div class="mb-3">
                                                                <small class="mb-2 block text-gray-400"><strong>Einsatz</strong></small>
                                                                <?php if (!empty($trupp['mission'])): ?>
                                                                    <small class="block"><strong>Art:</strong> <?= htmlspecialchars($trupp['mission']) ?></small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($trupp['objective'])): ?>
                                                                    <small class="block"><strong>Ziel:</strong> <?= htmlspecialchars($trupp['objective']) ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($trupp['startTime']) || !empty($trupp['retreat']) || !empty($trupp['end'])): ?>
                                                            <div class="mb-3">
                                                                <small class="mb-2 block text-gray-400"><strong>Zeiten</strong></small>
                                                                <?php if (!empty($trupp['startTime'])): ?>
                                                                    <small class="block"><strong>Start:</strong> <?= htmlspecialchars($trupp['startTime']) ?> Uhr</small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($trupp['retreat'])): ?>
                                                                    <small class="block"><strong>Rückzug:</strong> <?= htmlspecialchars($trupp['retreat']) ?> Uhr</small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($trupp['end'])): ?>
                                                                    <small class="block"><strong>Ende:</strong> <?= htmlspecialchars($trupp['end']) ?> Uhr</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($trupp['startPressure']) || !empty($trupp['elapsedTime'])): ?>
                                                            <div class="mb-3">
                                                                <small class="mb-2 block text-gray-400"><strong>Ausrüstung</strong></small>
                                                                <?php if (!empty($trupp['startPressure'])): ?>
                                                                    <small class="block"><strong>Startdruck:</strong> <?= htmlspecialchars($trupp['startPressure']) ?></small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($trupp['elapsedTime'])): ?>
                                                                    <small class="block"><strong>Einsatzzeit:</strong> <?= fmt_elapsed($trupp['elapsedTime']) ?> Min.</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($trupp['check1']) || !empty($trupp['check2'])): ?>
                                                            <div class="mb-3">
                                                                <small class="mb-2 block text-gray-400"><strong>Druckkontrollen</strong></small>
                                                                <?php if (!empty($trupp['check1'])): ?>
                                                                    <small class="block">1. Kontrolle: <?= htmlspecialchars($trupp['check1']) ?></small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($trupp['check2'])): ?>
                                                                    <small class="block">2. Kontrolle: <?= htmlspecialchars($trupp['check2']) ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($trupp['remarks'])): ?>
                                                            <div>
                                                                <small class="mb-2 block text-gray-400"><strong>Bemerkungen</strong></small>
                                                                <small class="block"><?= nl2br(htmlspecialchars($trupp['remarks'])) ?></small>
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
                                    <a href="<?= BASE_PATH ?>einsatz/asu.php?incident_id=<?= $id ?>&incident_number=<?= urlencode($incident['incident_number']) ?>&location=<?= urlencode($incident['location']) ?>&asu_id=<?= (int)$asu['id'] ?>" class="ignis-btn ignis-btn--primary">
                                        <i class="fa-solid fa-edit me-1"></i>Protokoll fortführen
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>