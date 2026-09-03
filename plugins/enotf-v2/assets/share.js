/**
 * eNOTF v2 — Protokoll teilen (Vanilla-Ersatz für v1s share-modals.php).
 *
 * Zwei Dialoge über window.Dialog (assets/js/ui/dialog.js):
 *   - Senden:    EnotfV2Share.open(protocolId, enr) — Fahrzeugsuche mit
 *                Dropdown (Rufname/Kennzeichen/Identifier), POST auf
 *                share/send-request.
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
        return (vehicle.name || vehicle.identifier) + ' (' + (Number(vehicle.rd_type) === 1 ? 'NA' : 'RD') + ')';
    }

    function openShareDialog(protocolId, enr) {
        if (typeof window.Dialog === 'undefined') return;

        var allVehicles = [];
        var selectedVehicle = null;

        var body = document.createElement('div');
        body.innerHTML =
            '<p>Wähle ein Fahrzeug aus, mit dem du dieses Protokoll teilen möchtest:</p>' +
            '<div class="mb-3 relative">' +
            '  <label class="ignis-field__label" data-share-label>Zielfahrzeug</label>' +
            '  <input type="text" class="ignis-input w-100" data-share-search placeholder="Rufname, Kennzeichen oder ID eingeben..." autocomplete="off">' +
            '  <div class="ev2-share-dropdown" data-share-dropdown hidden></div>' +
            '</div>' +
            '<div class="ignis-alert ignis-alert--info">' +
            '  <i class="fa-solid fa-info-circle"></i> ' +
            '  Das ausgewählte Fahrzeug erhält eine Anfrage und kann entscheiden, ob es die Daten in ein bestehendes Protokoll übernehmen oder ein neues Protokoll erstellen möchte.' +
            '</div>' +
            '<div class="ignis-alert ignis-alert--danger mt-2" data-share-error hidden></div>';

        var searchInput = body.querySelector('[data-share-search]');
        var dropdown = body.querySelector('[data-share-dropdown]');
        var errorBox = body.querySelector('[data-share-error]');
        var confirmBtn = null;

        function showError(message) {
            errorBox.textContent = message;
            errorBox.hidden = false;
        }

        function setConfirmEnabled(enabled) {
            if (confirmBtn) confirmBtn.disabled = !enabled;
        }

        function renderDropdown(vehicles) {
            dropdown.innerHTML = '';
            if (!vehicles.length) {
                var empty = document.createElement('div');
                empty.className = 'ev2-share-dropdown__item ev2-share-dropdown__item--disabled';
                empty.textContent = 'Keine Fahrzeuge gefunden';
                dropdown.appendChild(empty);
                return;
            }
            vehicles.forEach(function (vehicle) {
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'ev2-share-dropdown__item';
                var typeLabel = Number(vehicle.rd_type) === 1 ? 'NA' : 'RD';
                item.innerHTML = esc(vehicle.name || vehicle.identifier) +
                    ' <span class="ignis-chip">' + typeLabel + '</span>' +
                    (vehicle.kennzeichen ? ' <small class="ev2-share-dropdown__plate">[' + esc(vehicle.kennzeichen) + ']</small>' : '');
                item.addEventListener('click', function () {
                    selectedVehicle = vehicle;
                    searchInput.value = vehicleLabel(vehicle);
                    dropdown.hidden = true;
                    setConfirmEnabled(true);
                });
                dropdown.appendChild(item);
            });
        }

        searchInput.addEventListener('input', function () {
            var term = searchInput.value.toLowerCase().trim();
            dropdown.hidden = false;

            // Auswahl zurücksetzen, sobald der Text nicht mehr zur Auswahl passt
            if (selectedVehicle && searchInput.value !== vehicleLabel(selectedVehicle)) {
                selectedVehicle = null;
                setConfirmEnabled(false);
            }

            if (!term) {
                renderDropdown(allVehicles);
                return;
            }
            renderDropdown(allVehicles.filter(function (vehicle) {
                return (vehicle.name || '').toLowerCase().indexOf(term) !== -1 ||
                    (vehicle.kennzeichen || '').toLowerCase().indexOf(term) !== -1 ||
                    vehicle.identifier.toLowerCase().indexOf(term) !== -1;
            }));
        });

        searchInput.addEventListener('focus', function () {
            if (allVehicles.length) {
                dropdown.hidden = false;
                if (!searchInput.value) renderDropdown(allVehicles);
            }
        });

        body.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.hidden = true;
            }
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
                        if (!selectedVehicle) {
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
                                target_vehicle: selectedVehicle.identifier,
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
                                    setConfirmEnabled(!!selectedVehicle);
                                }
                            });
                    },
                },
            ],
            onOpen: function (instance) {
                confirmBtn = instance.element.querySelector('[data-dialog-primary="true"]');
                setConfirmEnabled(false);
                searchInput.focus();
            },
        });

        dlg.open();

        fetch(api('get-available-vehicles'))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.vehicles) {
                    allVehicles = data.vehicles;
                    renderDropdown(allVehicles);
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
            '<div class="ignis-alert ignis-alert--info mb-3">' +
            '  <strong>' + esc(requestData.source_vehicle) + '</strong> möchte folgendes Protokoll mit dir teilen:' +
            '</div>' +
            '<table class="twplus-description-table mb-3">' +
            '  <tbody>' +
            '    <tr><th style="width:200px;">Einsatznummer:</th><td>' + esc(requestData.enr) + '</td></tr>' +
            '    <tr><th>Patient:</th><td>' + esc(requestData.patname || 'Unbekannt') + '</td></tr>' +
            '    <tr><th>Protokollart:</th><td>' + (Number(requestData.prot_by) === 1 ? 'Notarzt-Protokoll' : 'Rettungsdienst-Protokoll') + '</td></tr>' +
            '    <tr><th>Einsatzdatum/-zeit:</th><td>' + esc((requestData.edatum || '') + ' ' + (requestData.ezeit || '')) + '</td></tr>' +
            '  </tbody>' +
            '</table>' +
            '<p><strong>Was möchtest du tun?</strong></p>' +
            '<label class="ignis-radio"><input type="radio" name="ev2ShareAction" value="merge"><span>' +
            '  <strong>In bestehendes Protokoll übernehmen</strong><br>' +
            '  <small class="text-[var(--text-dimmed,#818189)]">Wähle ein vorhandenes Protokoll aus. Die Daten werden übernommen, ohne deine Fahrzeugzuweisungen zu überschreiben.</small>' +
            '</span></label>' +
            '<div class="ml-4 mb-3" data-share-protocols hidden>' +
            '  <select class="form-select" data-share-protocol-select>' +
            '    <option value="">Protokolle werden geladen...</option>' +
            '  </select>' +
            '</div>' +
            '<label class="ignis-radio"><input type="radio" name="ev2ShareAction" value="new"><span>' +
            '  <strong>Neues Protokoll erstellen</strong><br>' +
            '  <small class="text-[var(--text-dimmed,#818189)]">Erstellt ein neues Protokoll mit den geteilten Daten und deinen Fahrzeugdaten.</small>' +
            '</span></label>';

        var protocolsBox = body.querySelector('[data-share-protocols]');
        var protocolSelect = body.querySelector('[data-share-protocol-select]');
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
                    updateAcceptState();
                })
                .catch(function () {
                    protocolSelect.innerHTML = '<option value="">Fehler beim Laden der Protokolle</option>';
                });
        }

        body.addEventListener('change', function (e) {
            if (e.target.name === 'ev2ShareAction') {
                var isMerge = e.target.value === 'merge';
                protocolsBox.hidden = !isMerge;
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
