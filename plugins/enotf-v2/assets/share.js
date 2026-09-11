/**
 * eNOTF v2 — Protokoll teilen (Vanilla-Ersatz für v1s share-modals.php).
 *
 * Zwei Dialoge über window.Dialog (assets/js/ui/dialog.js):
 *   - Senden:    EnotfV2Share.open(protocolId, enr) — Fahrzeugauswahl
 *                als <select> mit Ev2Select (Suchfeld ab acht Optionen),
 *                POST auf share/send-request.
 *   - Empfangen: Poll auf share/check-requests (sofort + alle 15s, läuft
 *                auf Protokollseiten und Overview). Bei pending Anfrage
 *                öffnet sich der Übergabe-Dialog: Annehmen als Merge in
 *                ein eigenes offenes Protokoll oder als neues Protokoll,
 *                alternativ Ablehnen.
 *
 * Erwartet window.__ev2Base (BASE_PATH, gesetzt vom Asset-Partial
 * _share-qm-assets.php); Fallback: Ableitung aus location.pathname.
 * Nach "new" wird auf den v2-Editor (/enotf-v2/p/{enr}) weitergeleitet,
 * nach "merge" die Seite neu geladen (v1-Verhalten).
 */
(function () {
    'use strict';

    var POLL_INTERVAL = 15000;

    function basePath() {
        if (typeof window.__ev2Base === 'string' && window.__ev2Base !== '') {
            return window.__ev2Base;
        }
        var m = window.location.pathname.match(/^(.*?)\/enotf-v2\//);
        return m ? m[1] + '/' : '/';
    }

    function api(path) {
        return basePath() + 'api/enotf-v2/share/' + path;
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function notify(message, opts) {
        if (typeof window.showAlert === 'function') {
            window.showAlert(message, opts || {});
        } else {
            window.alert(message);
        }
    }

    // ── Senden-Dialog ─────────────────────────────────────────────────

    function vehicleLabel(vehicle) {
        var label = (vehicle.name || vehicle.identifier) + ' (' + (Number(vehicle.rd_type) === 1 ? 'NA' : 'RD') + ')';
        return vehicle.kennzeichen ? label + ' [' + vehicle.kennzeichen + ']' : label;
    }

    // Der FiveM-CEF zeigt native Select-Popups nicht; Ev2Select ersetzt
    // die Aufklapp-Optik. Der MutationObserver der Komponente greift beim
    // Einhängen des Dialogs, nachgeladene Optionen brauchen den Nachzug.
    function refreshSelect(select) {
        if (!window.Ev2Select) return;
        if (typeof window.Ev2Select.enhance === 'function') window.Ev2Select.enhance(select);
        if (typeof window.Ev2Select.refresh === 'function') window.Ev2Select.refresh(select);
    }

    function openShareDialog(protocolId, enr) {
        if (typeof window.Dialog === 'undefined') return;

        var body = document.createElement('div');
        body.innerHTML =
            '<div class="edivi__box">' +
            '  <label class="ev2-edivi-dialog__label" for="ev2-share-vehicle">Zielfahrzeug</label>' +
            '  <select class="ignis-input" id="ev2-share-vehicle" data-share-vehicle>' +
            '    <option value="">Fahrzeuge werden geladen...</option>' +
            '  </select>' +
            '</div>' +
            '<div class="edivi__box edivi__log-comment">' +
            '  <i class="fa-solid fa-info-circle"></i> ' +
            '  Das ausgewählte Fahrzeug erhält eine Anfrage und kann entscheiden, ob es die Daten in ein bestehendes Protokoll übernehmen oder ein neues Protokoll erstellen möchte.' +
            '</div>' +
            '<div class="ev2-edivi-dialog__error" data-share-error hidden></div>';

        var vehicleSelect = body.querySelector('[data-share-vehicle]');
        var errorBox = body.querySelector('[data-share-error]');
        var confirmBtn = null;

        function showError(message) {
            errorBox.textContent = message;
            errorBox.hidden = false;
        }

        function setConfirmEnabled(enabled) {
            if (confirmBtn) confirmBtn.disabled = !enabled;
        }

        function selectedVehicleId() {
            return vehicleSelect.value;
        }

        function renderVehicles(vehicles) {
            vehicleSelect.innerHTML = '';
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = vehicles.length ? 'Fahrzeug auswählen...' : 'Keine Fahrzeuge gefunden';
            vehicleSelect.appendChild(placeholder);
            vehicles.forEach(function (vehicle) {
                var option = document.createElement('option');
                option.value = vehicle.identifier;
                option.textContent = vehicleLabel(vehicle);
                vehicleSelect.appendChild(option);
            });
            refreshSelect(vehicleSelect);
        }

        vehicleSelect.addEventListener('change', function () {
            setConfirmEnabled(selectedVehicleId() !== '');
        });

        var dlg = new window.Dialog({
            title: 'Protokoll teilen',
            body: body,
            size: 'md',
            actions: [
                { label: 'Abbrechen', variant: 'ghost', onClick: function (d) { d.close(null); } },
                {
                    label: 'Teilen',
                    variant: 'soft-primary',
                    primary: true,
                    onClick: function (d) {
                        if (!selectedVehicleId()) {
                            showError('Bitte wähle ein Fahrzeug aus');
                            return;
                        }
                        errorBox.hidden = true;
                        setConfirmEnabled(false);
                        confirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Wird gesendet...';

                        fetch(api('send-request'), {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                protocol_id: protocolId,
                                enr: enr,
                                target_vehicle: selectedVehicleId(),
                            }),
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (data.success) {
                                    d.close('sent');
                                    notify('Protokoll wurde erfolgreich geteilt. Das Fahrzeug erhält eine Benachrichtigung.', {
                                        type: 'success',
                                        title: 'Erfolgreich geteilt',
                                    });
                                } else {
                                    showError(data.message || 'Fehler beim Teilen des Protokolls');
                                }
                            })
                            .catch(function () {
                                showError('Netzwerkfehler beim Teilen des Protokolls');
                            })
                            .finally(function () {
                                if (dlg.element) {
                                    confirmBtn.textContent = 'Teilen';
                                    setConfirmEnabled(selectedVehicleId() !== '');
                                }
                            });
                    },
                },
            ],
            onOpen: function (instance) {
                instance.element.classList.add('ev2-edivi-dialog');
                confirmBtn = instance.element.querySelector('[data-dialog-primary="true"]');
                setConfirmEnabled(false);
                refreshSelect(vehicleSelect);
                vehicleSelect.focus();
            },
        });

        dlg.open();

        fetch(api('get-available-vehicles'))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.vehicles) {
                    renderVehicles(data.vehicles);
                } else {
                    showError(data.message || 'Fehler beim Laden der verfügbaren Fahrzeuge');
                }
            })
            .catch(function () {
                showError('Fehler beim Laden der verfügbaren Fahrzeuge');
            });
    }

    // ── Empfangen-Dialog + Poll ───────────────────────────────────────

    var receiveDialog = null;
    var lastShownRequestId = null;

    function openReceiveDialog(requestData) {
        if (typeof window.Dialog === 'undefined') return;
        if (receiveDialog || lastShownRequestId === requestData.id) return;

        lastShownRequestId = requestData.id;

        var body = document.createElement('div');
        body.innerHTML =
            '<div class="edivi__box edivi__log-comment">' +
            '  <i class="fa-solid fa-share-nodes"></i> ' +
            '  <strong>' + esc(requestData.source_vehicle) + '</strong> möchte folgendes Protokoll mit dir teilen.' +
            '</div>' +
            '<div class="edivi__box ev2-edivi-dialog__facts">' +
            '  <dl>' +
            '    <dt>Einsatznummer</dt><dd>' + esc(requestData.enr) + '</dd>' +
            '    <dt>Patient</dt><dd>' + esc(requestData.patname || 'Unbekannt') + '</dd>' +
            '    <dt>Protokollart</dt><dd>' + (Number(requestData.prot_by) === 1 ? 'Notarzt-Protokoll' : 'Rettungsdienst-Protokoll') + '</dd>' +
            '    <dt>Einsatzdatum</dt><dd>' + esc(((requestData.edatum || '') + ' ' + (requestData.ezeit || '')).trim() || 'Unbekannt') + '</dd>' +
            '  </dl>' +
            '</div>' +
            '<div class="ev2-edivi-dialog__choice edivi__interactbutton">' +
            '  <input type="radio" class="btn-check" name="ev2ShareAction" id="ev2-share-merge" value="merge" autocomplete="off">' +
            '  <label for="ev2-share-merge">In bestehendes Protokoll übernehmen</label>' +
            '  <input type="radio" class="btn-check" name="ev2ShareAction" id="ev2-share-new" value="new" autocomplete="off">' +
            '  <label for="ev2-share-new">Neues Protokoll erstellen</label>' +
            '</div>' +
            '<div class="edivi__box edivi__log-comment" data-share-hint hidden></div>' +
            '<div class="edivi__box" data-share-protocols hidden>' +
            '  <label class="ev2-edivi-dialog__label" for="ev2-share-protocol">Zielprotokoll</label>' +
            '  <select class="ignis-input" id="ev2-share-protocol" data-share-protocol-select>' +
            '    <option value="">Protokolle werden geladen...</option>' +
            '  </select>' +
            '</div>';

        // Die Erklärung zur Auswahl steht unter den Kacheln statt in
        // ihnen: edivi__interactbutton deckelt die Kachelhöhe bei 74px
        // und schneidet alles darüber ab.
        var ACTION_HINTS = {
            merge: 'Die Daten werden in das gewählte Protokoll übernommen, ohne deine Fahrzeugzuweisungen zu überschreiben.',
            'new': 'Es entsteht ein neues Protokoll mit den geteilten Daten und deinen Fahrzeugdaten.',
        };

        var protocolsBox = body.querySelector('[data-share-protocols]');
        var protocolSelect = body.querySelector('[data-share-protocol-select]');
        var hintBox = body.querySelector('[data-share-hint]');
        var protocolsLoaded = false;
        var acceptBtn = null;
        var busy = false;

        function selectedAction() {
            var checked = body.querySelector('input[name="ev2ShareAction"]:checked');
            return checked ? checked.value : null;
        }

        function updateAcceptState() {
            if (!acceptBtn) return;
            var action = selectedAction();
            var ok = action === 'new' || (action === 'merge' && protocolSelect.value !== '');
            acceptBtn.disabled = busy || !ok;
        }

        function loadOwnProtocols() {
            if (protocolsLoaded) return;
            protocolsLoaded = true;
            fetch(api('get-own-protocols'))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    protocolSelect.innerHTML = '';
                    var placeholder = document.createElement('option');
                    placeholder.value = '';
                    if (data.success && data.protocols && data.protocols.length) {
                        placeholder.textContent = 'Protokoll auswählen...';
                        protocolSelect.appendChild(placeholder);
                        data.protocols.forEach(function (protocol) {
                            var option = document.createElement('option');
                            option.value = protocol.enr;
                            option.textContent = protocol.enr + ' - ' + (protocol.patname || 'Unbekannt') + ' (' + (protocol.edatum || 'N/A') + ')';
                            protocolSelect.appendChild(option);
                        });
                    } else {
                        placeholder.textContent = 'Keine Protokolle gefunden';
                        protocolSelect.appendChild(placeholder);
                    }
                    refreshSelect(protocolSelect);
                    updateAcceptState();
                })
                .catch(function () {
                    protocolSelect.innerHTML = '<option value="">Fehler beim Laden der Protokolle</option>';
                    refreshSelect(protocolSelect);
                });
        }

        body.addEventListener('change', function (e) {
            if (e.target.name === 'ev2ShareAction') {
                var isMerge = e.target.value === 'merge';
                protocolsBox.hidden = !isMerge;
                hintBox.textContent = ACTION_HINTS[e.target.value] || '';
                hintBox.hidden = hintBox.textContent === '';
                if (isMerge) loadOwnProtocols();
            }
            updateAcceptState();
        });

        function respond(url, payload, onSuccess) {
            busy = true;
            updateAcceptState();
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        onSuccess(data);
                    } else {
                        notify(data.message || 'Fehler beim Verarbeiten der Anfrage', { type: 'error', title: 'Fehler' });
                    }
                })
                .catch(function () {
                    notify('Netzwerkfehler beim Verarbeiten der Anfrage', { type: 'error', title: 'Fehler' });
                })
                .finally(function () {
                    busy = false;
                    updateAcceptState();
                });
        }

        receiveDialog = new window.Dialog({
            title: 'Protokoll-Freigabe erhalten',
            body: body,
            size: 'lg',
            closeOnBackdrop: false,
            closeOnEscape: false,
            actions: [
                {
                    label: 'Ablehnen',
                    variant: 'ghost-danger',
                    onClick: function (d) {
                        respond(api('reject-request'), { request_id: requestData.id }, function () {
                            d.close('rejected');
                            notify('Anfrage wurde abgelehnt', { type: 'info', title: 'Abgelehnt' });
                        });
                    },
                },
                {
                    label: 'Annehmen',
                    variant: 'soft-primary',
                    primary: true,
                    onClick: function (d) {
                        var action = selectedAction();
                        if (!action) return;
                        var targetEnr = action === 'merge' ? protocolSelect.value : null;
                        respond(api('accept-request'), {
                            request_id: requestData.id,
                            action: action,
                            target_enr: targetEnr,
                        }, function (data) {
                            d.close('accepted');
                            notify(data.message || 'Protokoll wurde erfolgreich übernommen', { type: 'success', title: 'Erfolgreich' });
                            window.setTimeout(function () {
                                if (action === 'new' && data.new_enr) {
                                    window.location.href = basePath() + 'enotf-v2/p/' + encodeURIComponent(data.new_enr);
                                } else {
                                    window.location.reload();
                                }
                            }, 1500);
                        });
                    },
                },
            ],
            onOpen: function (instance) {
                instance.element.classList.add('ev2-edivi-dialog');
                acceptBtn = instance.element.querySelector('[data-dialog-primary="true"]');
                updateAcceptState();
            },
            onClose: function () {
                receiveDialog = null;
                lastShownRequestId = null;
            },
        });

        receiveDialog.open();
    }

    function checkForShareRequests() {
        if (receiveDialog) return;
        fetch(api('check-requests'))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.has_requests && data.request) {
                    openReceiveDialog(data.request);
                }
            })
            .catch(function () { /* Netzfehler beim Poll: einfach nächster Versuch */ });
    }

    var pollStarted = false;

    function initPoll() {
        if (pollStarted) return;
        pollStarted = true;
        checkForShareRequests();
        window.setInterval(checkForShareRequests, POLL_INTERVAL);
    }

    window.EnotfV2Share = {
        open: openShareDialog,
        initPoll: initPoll,
        checkNow: checkForShareRequests,
    };

    // Das Asset-Partial wird nur auf Crew-Seiten (Protokoll + Overview)
    // eingebunden — der Poll kann daher direkt starten.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPoll);
    } else {
        initPoll();
    }
})();
