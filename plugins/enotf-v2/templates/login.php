<?php

/**
 * View: Crew-Login (eNOTF v2) — v1-Optik.
 *
 * Markup/Optik = plugins/enotf/templates/enotf/login.php (Karten, Buttons,
 * Session-Info-Box, Join-Panel). Technik = v2: POST an den v2-Login
 * (login_mode new/join), Session-Check über /api/enotf-v2/check-vehicle-
 * session, Namensvorschläge über Ev2Suggest und Selects über Ev2Select
 * (beide im v1-Dropdown-Look, siehe _v1head.php) — der FiveM-CEF zeigt
 * native select-/datalist-Popups nicht an.
 *
 * @var bool                             $charLocked
 * @var string                           $charName
 * @var list<string>                     $fullnames
 * @var array<int,array<string,mixed>>   $qualifikationen
 * @var array<int,array<string,mixed>>   $vehicles
 * @var array<string,string>             $prefill
 * @var string                           $loginError
 */

use Plugin\EnotfV2\Helpers\EnotfV2Url;
use Plugin\EnotfV2\Policies\EnotfV2Policy;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

$pinEnabled = EnotfV2Policy::pinEnabled() ? 'true' : 'false';
$hasPrefill = !empty($prefill);

// Char-Lock + Prefill: eigene Position bestimmen (v1-Logik)
$charLockOwnPosition = null;
if ($charLocked && $hasPrefill) {
    if (($prefill['fahrername'] ?? '') === $charName) $charLockOwnPosition = 'fahrer';
    elseif (($prefill['beifahrername'] ?? '') === $charName) $charLockOwnPosition = 'beifahrer';
    elseif (($prefill['praktikantname'] ?? '') === $charName) $charLockOwnPosition = 'praktikant';
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $__v1Title = 'eNOTF';
    require __DIR__ . '/_v1head.php';
    ?>
    <style>
        /* Session-Info-Box (v1 login.php) */
        .session-info-box {
            background-color: #2a2a2a;
            border: 1px solid #444;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 12px;
        }

        .session-info-box .session-crew-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
        }

        .session-info-box .session-crew-item .position-label {
            color: #888;
            font-size: 0.85rem;
        }

        .session-info-box .session-crew-item .crew-name {
            color: #fff;
        }

        .session-info-box .position-free {
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" id="edivi__login" data-page="enotf-v2" data-pin-enabled="<?= $pinEnabled ?>">
    <!-- Normales Anmeldeformular (POST /enotf-v2/login, login_mode=new) -->
    <form name="form" method="post" action="<?= $e(EnotfV2Url::page('login')) ?>" id="login-form-new">
        <input type="hidden" name="login_mode" value="new" />
        <input type="hidden" name="_csrf" value="<?= $e(\Plugin\EnotfV2\Http\Csrf::token()) ?>" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-full">
                <div class="col" id="edivi__content">
                    <div class="row my-2 border-bottom border-light" id="edivi__login-title">
                        <div class="col">
                            <h5 class="fw-bold">Anmeldung</h5>
                        </div>
                    </div>
                    <?php if ($loginError === 'char_mismatch'): ?>
                        <div class="row mb-2">
                            <div class="col" style="color:#d91425;">
                                <i class="fa-solid fa-user-lock" style="margin-right:6px;"></i>
                                Charakter-Sperre aktiv: Dein Charaktername muss Teil der Besatzung sein.
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col">
                            <?php if ($charLocked && !$hasPrefill): ?>
                                <!-- Char-Lock ohne Prefill: Positions-Wähler (v1) -->
                                <div class="row mb-3">
                                    <div class="col">
                                        <select class="form-select ignis-input my-2" id="charlock-position" data-placeholder="Position wählen">
                                            <option value="fahrer" selected>Fahrer</option>
                                            <option value="beifahrer">Beifahrer</option>
                                            <option value="praktikant">Praktikant</option>
                                        </select>
                                        <label>Meine Position</label>
                                    </div>
                                    <div class="col-3">
                                        <select class="form-select ignis-input my-2" id="charlock-quali" data-placeholder="Qualifikation">
                                            <option value=""></option>
                                            <?php foreach ($qualifikationen as $quali): ?>
                                                <option value="<?= $e($quali['abkuerzung']) ?>"><?= $e($quali['abkuerzung']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label>Qualifikation</label>
                                    </div>
                                </div>
                                <div class="text-[var(--text-dimmed,#818189)] mb-2" style="font-size:0.85rem;">
                                    <i class="fa-solid fa-lock mr-1"></i>Anmeldung als: <strong><?= $e($charName) ?></strong>
                                </div>
                                <!-- Hidden fields: werden per JS befüllt -->
                                <input type="hidden" name="fahrername" id="fahrername" value="" />
                                <input type="hidden" name="fahrerquali" id="fahrerquali" value="" />
                                <input type="hidden" name="beifahrername" id="beifahrername" value="" />
                                <input type="hidden" name="beifahrerquali" id="beifahrerquali" value="" />
                                <input type="hidden" name="praktikantname" id="praktikantname" value="" />
                                <input type="hidden" name="praktikantquali" id="praktikantquali" value="" />
                            <?php else: ?>
                                <?php
                                // Bei Char-Lock + Prefill: eigene Position readonly, fremde
                                // Positionen (außer Praktikant) readonly (v1-Logik)
                                $fahrerRo = ''; $beifahrerRo = '';
                                if ($charLocked && $charLockOwnPosition) {
                                    $fahrerRo = ($charLockOwnPosition === 'fahrer' || ($charLockOwnPosition !== 'fahrer' && !empty($prefill['fahrername'] ?? ''))) ? 'readonly' : '';
                                    $beifahrerRo = ($charLockOwnPosition === 'beifahrer' || ($charLockOwnPosition !== 'beifahrer' && !empty($prefill['beifahrername'] ?? ''))) ? 'readonly' : '';
                                    // Praktikant ist IMMER frei editierbar
                                }
                                ?>
                                <div class="row mb-2">
                                    <div class="col">
                                        <input type="text" class="ignis-input my-2" name="fahrername" id="fahrername" data-ev2-suggest="ev2-personnel" autocomplete="off" required value="<?= $e($prefill['fahrername'] ?? '') ?>" <?= $fahrerRo ?> />
                                        <label for="fahrername">Fahrer-Name</label>
                                    </div>
                                    <div class="col-3">
                                        <select class="form-select ignis-input my-2" name="fahrerquali" id="fahrerquali" required data-placeholder="Qualifikation">
                                            <option value="" <?= empty($prefill['fahrerquali'] ?? '') ? 'selected' : '' ?>></option>
                                            <?php foreach ($qualifikationen as $quali): ?>
                                                <option value="<?= $e($quali['abkuerzung']) ?>" <?= ($prefill['fahrerquali'] ?? '') === $quali['abkuerzung'] ? 'selected' : '' ?>><?= $e($quali['abkuerzung']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="fahrerquali">Qualifikation</label>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col">
                                        <input type="text" class="ignis-input my-2" name="beifahrername" id="beifahrername" data-ev2-suggest="ev2-personnel" autocomplete="off" value="<?= $e($prefill['beifahrername'] ?? '') ?>" <?= $beifahrerRo ?> />
                                        <label for="beifahrername">Beifahrer-Name</label>
                                    </div>
                                    <div class="col-3">
                                        <select class="form-select ignis-input my-2" name="beifahrerquali" id="beifahrerquali" data-placeholder="Qualifikation">
                                            <option value="" <?= empty($prefill['beifahrerquali'] ?? '') ? 'selected' : '' ?>></option>
                                            <?php foreach ($qualifikationen as $quali): ?>
                                                <option value="<?= $e($quali['abkuerzung']) ?>" <?= ($prefill['beifahrerquali'] ?? '') === $quali['abkuerzung'] ? 'selected' : '' ?>><?= $e($quali['abkuerzung']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="beifahrerquali">Qualifikation</label>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col">
                                        <input type="text" class="ignis-input my-2" name="praktikantname" id="praktikantname" data-ev2-suggest="ev2-personnel" autocomplete="off" value="<?= $e($prefill['praktikantname'] ?? '') ?>" />
                                        <label for="praktikantname">Praktikant-Name</label>
                                    </div>
                                    <div class="col-3">
                                        <select class="form-select ignis-input my-2" name="praktikantquali" id="praktikantquali" data-placeholder="Qualifikation">
                                            <option value="" <?= empty($prefill['praktikantquali'] ?? '') ? 'selected' : '' ?>></option>
                                            <?php foreach ($qualifikationen as $quali): ?>
                                                <option value="<?= $e($quali['abkuerzung']) ?>" <?= ($prefill['praktikantquali'] ?? '') === $quali['abkuerzung'] ? 'selected' : '' ?>><?= $e($quali['abkuerzung']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="praktikantquali">Qualifikation</label>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col"><button type="button" class="edivi__nidabutton w-100" id="crew__delete" name="crew__delete">Besatzung löschen</button></div>
                                    <div class="col"><button type="button" class="edivi__nidabutton w-100" id="crew__switch" name="crew__switch">Fahrer / Beifahrer tauschen</button></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col">
                            <div class="row">
                                <div class="col">
                                    <select name="protfzg" id="protfzg" class="form-select ignis-input my-2" required data-placeholder="Fahrzeug wählen">
                                        <option value="" disabled <?= empty($prefill['protfzg'] ?? '') ? 'selected' : '' ?>>Fahrzeug wählen</option>
                                        <?php foreach ($vehicles as $row): ?>
                                            <option value="<?= $e($row['identifier']) ?>" <?= ($prefill['protfzg'] ?? '') === $row['identifier'] ? 'selected' : '' ?>><?= $e($row['name']) ?> (<?= $e($row['veh_type']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <!-- Session-Info: wird per JS eingeblendet wenn Fahrzeug aktive Session hat -->
                            <div id="session-info-container" style="display:none;">
                                <div class="session-info-box">
                                    <div class="flex justify-content-between align-items-center mb-2">
                                        <strong style="color:#e0a800;">Es ist bereits eine Besatzung auf diesem Fahrzeug angemeldet</strong>
                                    </div>
                                    <div id="session-crew-list"></div>
                                    <div class="mt-3 flex gap-2">
                                        <button type="button" class="edivi__nidabutton grow" id="btn-join-session">Beitreten</button>
                                        <button type="button" class="edivi__nidabutton grow" id="btn-new-session">Neue Besatzung</button>
                                        <button type="button" class="edivi__nidabutton" id="btn-delete-session" style="background-color:#dc3545;border-color:#dc3545;aspect-ratio:1;padding:0;width:42px;min-width:42px;" title="Session löschen"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                            <!-- Join-Formular: wird eingeblendet wenn "Beitreten" geklickt -->
                            <div id="join-form-container" style="display:none;">
                                <div class="session-info-box">
                                    <strong class="block mb-2">Position wählen:</strong>
                                    <select id="join-position-select" class="form-select ignis-input mb-2" data-placeholder="Position wählen">
                                    </select>
                                    <div class="row mb-2">
                                        <div class="col">
                                            <input type="text" class="ignis-input" id="join-name" placeholder="Name" data-ev2-suggest="ev2-personnel" autocomplete="off" value="<?= $charLocked ? $e($charName) : '' ?>" <?= $charLocked ? 'readonly' : '' ?> />
                                        </div>
                                        <div class="col-4">
                                            <select id="join-quali" class="form-select ignis-input" data-placeholder="Qualifikation">
                                                <option value=""></option>
                                                <?php foreach ($qualifikationen as $quali): ?>
                                                    <option value="<?= $e($quali['abkuerzung']) ?>"><?= $e($quali['abkuerzung']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="button" class="edivi__nidabutton w-100" id="btn-submit-join">Beitreten</button>
                                </div>
                            </div>
                            <div id="spacer-area">
                                <hr class="my-5" style="color: transparent">
                                <hr class="my-5" style="color: transparent">
                            </div>
                            <div class="row">
                                <div class="col text-end">
                                    <button type="submit" class="edivi__nidabutton" style="padding: 20px 40px" id="data__set" name="data__set">OK</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </form>

    <!-- Verstecktes Beitritts-Formular (POST /enotf-v2/login, login_mode=join) -->
    <form id="login-form-join" method="post" action="<?= $e(EnotfV2Url::page('login')) ?>" style="display:none;">
        <input type="hidden" name="login_mode" value="join" />
        <input type="hidden" name="_csrf" value="<?= $e(\Plugin\EnotfV2\Http\Csrf::token()) ?>" />
        <input type="hidden" name="protfzg" id="join-protfzg" value="" />
        <input type="hidden" name="join_position" id="join-position-hidden" value="" />
        <input type="hidden" name="join_name" id="join-name-hidden" value="" />
        <input type="hidden" name="join_quali" id="join-quali-hidden" value="" />
    </form>

    <!-- Namensliste für die Ev2Suggest-Vorschläge (datalist-Ersatz, CEF).
         JSON_HEX_TAG hält das JSON script-sicher. -->
    <script type="application/json" data-ev2-suggest-source="ev2-personnel"><?= json_encode($fullnames, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>

    <script>
        // Select-Werte programmatisch ändern: change-Event dispatchen,
        // damit Ev2Select den Trigger-Text nachzieht (statt v1s
        // eNOTFCustomDropdown.refresh)
        function refreshSelect(el) {
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }

        document.getElementById('crew__delete')?.addEventListener('click', function() {
            // Text-Inputs leeren
            ['fahrername', 'beifahrername', 'praktikantname'].forEach(function(id) {
                document.getElementById(id).value = '';
            });
            // Quali-Selects zurücksetzen
            ['fahrerquali', 'beifahrerquali', 'praktikantquali'].forEach(function(id) {
                const el = document.getElementById(id);
                el.selectedIndex = 0;
                refreshSelect(el);
            });
        });

        document.getElementById('crew__switch')?.addEventListener('click', function() {
            const fName = document.getElementById('fahrername');
            const bName = document.getElementById('beifahrername');
            const fQuali = document.getElementById('fahrerquali');
            const bQuali = document.getElementById('beifahrerquali');

            // Namen tauschen
            [fName.value, bName.value] = [bName.value, fName.value];

            // Qualifikationen tauschen
            const tmpIndex = fQuali.selectedIndex;
            fQuali.selectedIndex = bQuali.selectedIndex;
            bQuali.selectedIndex = tmpIndex;
            refreshSelect(fQuali);
            refreshSelect(bQuali);
        });

        // Session-Erkennung bei Fahrzeugwechsel (v2-Endpoint)
        let currentSessionData = null;

        function checkVehicleSession(vehicleId) {
            if (!vehicleId) {
                document.getElementById('session-info-container').style.display = 'none';
                document.getElementById('join-form-container').style.display = 'none';
                document.getElementById('spacer-area').style.display = '';
                return;
            }

            fetch('<?= $e(EnotfV2Url::api('check-vehicle-session')) ?>?vehicle=' + encodeURIComponent(vehicleId), {
                    headers: { 'Accept': 'application/json' }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.active) {
                        currentSessionData = data;
                        showSessionInfo(data);
                    } else {
                        currentSessionData = null;
                        document.getElementById('session-info-container').style.display = 'none';
                        document.getElementById('join-form-container').style.display = 'none';
                        document.getElementById('spacer-area').style.display = '';
                    }
                })
                .catch(() => {
                    currentSessionData = null;
                    document.getElementById('session-info-container').style.display = 'none';
                });
        }

        function showSessionInfo(data) {
            const container = document.getElementById('session-info-container');
            const crewList = document.getElementById('session-crew-list');
            crewList.innerHTML = '';

            const positions = [{
                    key: 'fahrer',
                    label: 'Fahrer',
                    nameKey: 'fahrername',
                    qualiKey: 'fahrerquali'
                },
                {
                    key: 'beifahrer',
                    label: 'Beifahrer',
                    nameKey: 'beifahrername',
                    qualiKey: 'beifahrerquali'
                },
                {
                    key: 'praktikant',
                    label: 'Praktikant',
                    nameKey: 'praktikantname',
                    qualiKey: 'praktikantquali'
                }
            ];

            positions.forEach(pos => {
                const name = data.crew[pos.nameKey];
                const quali = data.crew[pos.qualiKey];
                const div = document.createElement('div');
                div.className = 'session-crew-item';

                if (name) {
                    div.innerHTML = '<span class="position-label">' + pos.label + ':</span><span class="crew-name">' +
                        escapeHtml(name) + (quali ? ' (' + escapeHtml(quali) + ')' : '') + '</span>';
                } else {
                    div.innerHTML = '<span class="position-label">' + pos.label + ':</span><span class="position-free">frei</span>';
                }
                crewList.appendChild(div);
            });

            container.style.display = '';
            document.getElementById('join-form-container').style.display = 'none';
            document.getElementById('spacer-area').style.display = 'none';

            // Beitreten-Button nur anzeigen wenn freie Positionen vorhanden
            const btnJoin = document.getElementById('btn-join-session');
            btnJoin.style.display = data.free_positions.length > 0 ? '' : 'none';

            // Löschen-Button nur, wenn der Server die Löschung zulassen
            // würde (eigene Fahrzeug-Bindung, PIN-verifiziertes Gerät oder
            // Panel-Login) — sonst antwortet der Endpoint ohnehin mit 403
            document.getElementById('btn-delete-session').style.display = data.can_delete ? '' : 'none';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Fahrzeug-Auswahl: onChange prüft ob aktive Session existiert
        // (Ev2Select dispatcht ein natives change-Event)
        const protfzgSelect = document.getElementById('protfzg');
        protfzgSelect.addEventListener('change', function() {
            checkVehicleSession(this.value);
        });

        // Beitreten-Button: Zeigt Join-Formular
        document.getElementById('btn-join-session').addEventListener('click', function() {
            if (!currentSessionData || currentSessionData.free_positions.length === 0) return;

            const posSelect = document.getElementById('join-position-select');
            posSelect.innerHTML = '';

            const posLabels = {
                fahrer: 'Fahrer',
                beifahrer: 'Beifahrer',
                praktikant: 'Praktikant'
            };
            currentSessionData.free_positions.forEach(pos => {
                const opt = document.createElement('option');
                opt.value = pos;
                opt.textContent = posLabels[pos] || pos;
                posSelect.appendChild(opt);
            });
            // Ev2Select beobachtet die Options per MutationObserver und
            // zieht den Trigger-Text selbst nach

            document.getElementById('session-info-container').style.display = 'none';
            document.getElementById('join-form-container').style.display = '';
        });

        // Neue Besatzung: Standard-Formular verwenden, Session-Info ausblenden
        document.getElementById('btn-new-session').addEventListener('click', function() {
            currentSessionData = null;
            document.getElementById('session-info-container').style.display = 'none';
            document.getElementById('join-form-container').style.display = 'none';
            document.getElementById('spacer-area').style.display = '';
        });

        // Session löschen: aktive Session deaktivieren (v2-Endpoint —
        // das v1-Pendant hängt hinter hartem User-Auth → 401 für Crews
        // ohne Panel-Login)
        document.getElementById('btn-delete-session').addEventListener('click', async function() {
            if (!currentSessionData) return;

            const confirmed = (typeof showConfirm === 'function') ?
                await showConfirm('Möchten Sie die aktive Session auf diesem Fahrzeug wirklich beenden?', {
                    title: 'Session beenden',
                    confirmText: 'Beenden',
                    cancelText: 'Abbrechen',
                    danger: true
                }) :
                confirm('Möchten Sie die aktive Session auf diesem Fahrzeug wirklich beenden?');

            if (!confirmed) return;

            fetch('<?= $e(EnotfV2Url::api('delete-vehicle-session')) ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        vehicle: document.getElementById('protfzg').value
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        currentSessionData = null;
                        document.getElementById('session-info-container').style.display = 'none';
                        document.getElementById('join-form-container').style.display = 'none';
                        document.getElementById('spacer-area').style.display = '';
                    }
                })
                .catch(() => {});
        });

        // Beitreten absenden
        document.getElementById('btn-submit-join').addEventListener('click', function() {
            const position = document.getElementById('join-position-select').value;
            const name = document.getElementById('join-name').value.trim();
            const quali = document.getElementById('join-quali').value;
            const vehicle = document.getElementById('protfzg').value;

            if (!name) {
                document.getElementById('join-name').classList.add('is-invalid');
                return;
            }
            document.getElementById('join-name').classList.remove('is-invalid');

            document.getElementById('join-protfzg').value = vehicle;
            document.getElementById('join-position-hidden').value = position;
            document.getElementById('join-name-hidden').value = name;
            document.getElementById('join-quali-hidden').value = quali;
            document.getElementById('login-form-join').submit();
        });

        // Prefill: Session-Check für vorausgewähltes Fahrzeug auslösen
        <?php if ($hasPrefill): ?>
            document.addEventListener('DOMContentLoaded', function() {
                if (protfzgSelect.value) {
                    checkVehicleSession(protfzgSelect.value);
                }
            });
        <?php endif; ?>

        // Char-Lock: Positions-Wähler → Hidden-Fields befüllen beim Submit
        <?php if ($charLocked && !$hasPrefill): ?>
        (function() {
            var charName = <?= json_encode($charName) ?>;
            var form = document.getElementById('login-form-new');
            if (!form) return;

            form.addEventListener('submit', function() {
                var pos = document.getElementById('charlock-position').value;
                var quali = document.getElementById('charlock-quali').value;

                // Alle zurücksetzen
                document.getElementById('fahrername').value = '';
                document.getElementById('fahrerquali').value = '';
                document.getElementById('beifahrername').value = '';
                document.getElementById('beifahrerquali').value = '';
                document.getElementById('praktikantname').value = '';
                document.getElementById('praktikantquali').value = '';

                // Gewählte Position befüllen
                document.getElementById(pos + 'name').value = charName;
                document.getElementById(pos + 'quali').value = quali;
            });
        })();
        <?php endif; ?>
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>
