<?php
/**
 * View: Atemschutzüberwachung (ASU-Protokoll-Formular)
 *
 * @var string      $prefillNumber
 * @var string      $prefillLocation
 * @var string|null $prefillIncidentId
 * @var string|null $asuId
 * @var array|null  $existingProtocol
 * @var \PDO        $pdo
 */
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
    <style>
        .progress {
            background-color: var(--body-bg-darker);
            border-radius: 8px;
        }
        hr {
            border-color: var(--darkgray);
            opacity: 0.3;
        }
    </style>
    <script>
        const basePath = '<?= BASE_PATH ?>';
    </script>
</head>

<body data-bs-theme="dark" data-page="asu">
    <div class="flex">
        <?php
        $einsatzActivePage = 'asu';
        $einsatzExtraNav = '';
        if ($prefillIncidentId) {
            $einsatzExtraNav = '<a href="' . BASE_PATH . 'einsatz/view?id=' . (int)$prefillIncidentId . '&tab=bericht" class="sidebar-link"><i class="fa-solid fa-arrow-left"></i><span>Zurück zum Einsatz</span></a>';
        }
        include __DIR__ . '/../../assets/components/einsatz-sidebar.php';
        ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <div class="container mx-auto my-4">
                <div class="mb-3 flex items-center justify-between">
                    <h1><i class="fa-solid fa-lungs mr-2"></i>Atemschutzüberwachung (ASU)</h1>
                    <div class="current-time-display">
                        <i class="fa-solid fa-clock mr-2"></i>
                        <span id="currentTime" style="font-family: monospace; font-size: 1.1rem; font-weight: bold;">00:00</span>
                    </div>
                </div>

                <!-- ASU-Überwachung -->
                <div class="intra__tile p-3">
                    <form id="asuForm">
                        <input type="hidden" id="incidentId" value="<?= htmlspecialchars($prefillIncidentId ?? '') ?>">
                        <input type="hidden" id="asuId" value="<?= htmlspecialchars($asuId ?? '') ?>">

                        <!-- Einsatzinformationen -->
                        <div class="ignis-card bg-[rgba(0,0,0,0.3)] mb-3">
                            <div class="ignis-card__header">
                                <h5 class="mb-0">Einsatzinformationen</h5>
                            </div>
                            <div class="ignis-card__body">
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <label class="ignis-field__label">Einsatznummer *</label>
                                        <input type="text" class="ignis-input" id="missionNumber" value="<?= htmlspecialchars($prefillNumber) ?>" required>
                                    </div>
                                    <div>
                                        <label class="ignis-field__label">Einsatzort *</label>
                                        <input type="text" class="ignis-input" id="missionLocation" value="<?= htmlspecialchars($prefillLocation) ?>" required>
                                    </div>
                                    <div>
                                        <label class="ignis-field__label">Einsatzdatum *</label>
                                        <input type="text" class="ignis-input" id="missionDate" placeholder="TT.MM.JJJJ" required>
                                    </div>
                                    <div>
                                        <label class="ignis-field__label">Überwacher *</label>
                                        <input type="text" class="ignis-input" id="supervisor" placeholder="Name des Überwachenden" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php require __DIR__ . '/tabs/asu_trupps.php'; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View ASU Protocol Modal -->
    <div class="modal fade" id="viewASUModal" tabindex="-1" aria-labelledby="viewASUModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content bg-[rgba(0,0,0,0.3)]">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="viewASUModalLabel">ASU-Protokoll Ansicht</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body" id="asuProtocolView">
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= BASE_PATH ?>assets/js/asu.js"></script>

    <?php if ($existingProtocol): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const protocolData = <?= json_encode(json_decode($existingProtocol['data'], true)) ?>;

                if (protocolData.missionDate) document.getElementById('missionDate').value = protocolData.missionDate;
                if (protocolData.supervisor) document.getElementById('supervisor').value = protocolData.supervisor;

                for (let i = 1; i <= 3; i++) {
                    const truppKey = 'trupp' + i;
                    const truppData = protocolData[truppKey];

                    if (truppData) {
                        if (truppData.tf) document.getElementById(truppKey + 'TF').value = truppData.tf;
                        if (truppData.tm1) document.getElementById(truppKey + 'TM1').value = truppData.tm1;
                        if (truppData.tm2) document.getElementById(truppKey + 'TM2').value = truppData.tm2;

                        if (truppData.startPressure) document.getElementById(truppKey + 'StartPressure').value = truppData.startPressure;
                        if (truppData.startTime) document.getElementById(truppKey + 'StartTime').value = truppData.startTime;
                        if (truppData.mission) document.getElementById(truppKey + 'Mission').value = truppData.mission;

                        if (truppData.check1) document.getElementById(truppKey + 'Check1').value = truppData.check1;
                        if (truppData.check2) document.getElementById(truppKey + 'Check2').value = truppData.check2;

                        if (truppData.objective) document.getElementById(truppKey + 'Objective').value = truppData.objective;
                        if (truppData.retreat) document.getElementById(truppKey + 'Retreat').value = truppData.retreat;
                        if (truppData.end) document.getElementById(truppKey + 'End').value = truppData.end;
                        if (truppData.remarks) document.getElementById(truppKey + 'Remarks').value = truppData.remarks;

                        if (truppData.elapsedTime && truppData.elapsedTime > 0) {
                            truppTimers[i].elapsedSeconds = truppData.elapsedTime;
                            updateTruppDisplay(i);

                            if (!truppData.end || truppData.end === '') {
                                console.log(`Auto-starting Trupp ${i} from saved state`);
                                startTrupp(i);
                            }
                        }
                    }
                }
            });
        </script>
    <?php endif; ?>
</body>

</html>
