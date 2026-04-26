<?php
// Modal zum Auswählen des Zielfarzeugs
?>
<div class="modal fade" id="shareProtocolModal" tabindex="-1" aria-labelledby="shareProtocolModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shareProtocolModalLabel">Protokoll teilen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Wähle ein Fahrzeug aus, mit dem du dieses Protokoll teilen möchtest:</p>
                <div class="mb-3 relative">
                    <label for="targetVehicleSearch" class="ignis-field__label">Zielfahrzeug</label>
                    <input type="text"
                        class="ignis-input"
                        id="targetVehicleSearch"
                        placeholder="Rufname, Kennzeichen oder ID eingeben..."
                        autocomplete="off">
                    <input type="hidden" id="selectedVehicleId">
                    <div class="dropdown-menu w-full" id="vehicleDropdown" style="max-height: 300px; overflow-y: auto;">
                        <!-- Dropdown items werden hier dynamisch eingefügt -->
                    </div>
                </div>
                <div class="ignis-alert ignis-alert--info">
                    <i class="fa-solid fa-info-circle"></i>
                    Das ausgewählte Fahrzeug erhält eine Anfrage und kann entscheiden, ob es die Daten in ein bestehendes Protokoll übernehmen oder ein neues Protokoll erstellen möchte.
                </div>
                <div id="shareErrorMessage" class="ignis-alert ignis-alert--danger hidden"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="ignis-btn ignis-btn--soft-primary" id="confirmShareBtn" disabled>Teilen</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal zur Benachrichtigung über empfangene Share-Anfrage -->
<div class="modal fade" id="shareRequestReceivedModal" tabindex="-1" aria-labelledby="shareRequestReceivedModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shareRequestReceivedModalLabel">
                    <i class="fa-solid fa-share-nodes"></i> Protokoll-Freigabe erhalten
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="ignis-alert ignis-alert--info">
                    <strong id="shareSourceVehicle"></strong> möchte folgendes Protokoll mit dir teilen:
                </div>
                <div class="card mb-3">
                    <div class="card-header">
                        <strong>Protokoll-Details</strong>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tbody>
                                <tr>
                                    <th style="width: 200px;">Einsatznummer:</th>
                                    <td id="shareProtocolEnr"></td>
                                </tr>
                                <tr>
                                    <th>Patient:</th>
                                    <td id="shareProtocolPatient"></td>
                                </tr>
                                <tr>
                                    <th>Protokollart:</th>
                                    <td id="shareProtocolType"></td>
                                </tr>
                                <tr>
                                    <th>Einsatzdatum/-zeit:</th>
                                    <td id="shareProtocolDateTime"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <p><strong>Was möchtest du tun?</strong></p>
                <label class="ignis-radio" for="shareActionMerge"><input type="radio" name="shareAction" id="shareActionMerge" value="merge"><span>
                        <strong>In bestehendes Protokoll übernehmen</strong><br>
                        <small class="text-[var(--text-dimmed,#818189)]">Wähle ein vorhandenes Protokoll aus. Die Daten werden übernommen, ohne deine Fahrzeugzuweisungen zu überschreiben.</small>
                    </span></label>
                <div id="existingProtocolsContainer" class="ml-4 mb-3 hidden">
                    <select class="form-select" id="existingProtocolSelect" data-custom-dropdown="true" data-search-threshold="8">
                        <option value="">Protokoll auswählen...</option>
                    </select>
                </div>
                <label class="ignis-radio" for="shareActionNew"><input type="radio" name="shareAction" id="shareActionNew" value="new"><span>
                        <strong>Neues Protokoll erstellen</strong><br>
                        <small class="text-[var(--text-dimmed,#818189)]">Erstellt ein neues Protokoll mit den geteilten Daten und deinen Fahrzeugdaten.</small>
                    </span></label>
            </div>
            <div class="modal-footer">
                <button type="button" class="ignis-btn ignis-btn--ghost-danger" id="rejectShareBtn">Ablehnen</button>
                <button type="button" class="ignis-btn ignis-btn--soft-primary" id="acceptShareBtn" disabled>Annehmen</button>
            </div>
        </div>
    </div>
</div>

<script>
    let currentShareRequestId = null;
    let currentProtocolId = null;
    let currentProtocolEnr = null;

    // Share-Button wurde geklickt
    function openShareProtocol(protocolId, enr) {
        currentProtocolId = protocolId;
        currentProtocolEnr = enr;

        // Lade verfügbare Fahrzeuge
        loadAvailableVehicles();

        const modal = new bootstrap.Modal(document.getElementById('shareProtocolModal'));
        modal.show();
    }

    // Speichere alle Fahrzeuge für die Suche
    let allVehicles = [];
    let selectedVehicle = null;

    // Lade verfügbare Fahrzeuge (außer dem eigenen)
    function loadAvailableVehicles() {
        fetch('<?= BASE_PATH ?>api/enotf/share/get-available-vehicles.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.vehicles) {
                    allVehicles = data.vehicles;
                    updateVehicleDropdown(allVehicles);
                }
            })
            .catch(error => {
                console.error('Fehler beim Laden der Fahrzeuge:', error);
                showShareError('Fehler beim Laden der verfügbaren Fahrzeuge');
            });
    }

    // Aktualisiere Dropdown mit Fahrzeugen
    function updateVehicleDropdown(vehicles) {
        const dropdown = document.getElementById('vehicleDropdown');
        dropdown.innerHTML = '';

        if (vehicles.length === 0) {
            const item = document.createElement('div');
            item.className = 'dropdown-item disabled';
            item.textContent = 'Keine Fahrzeuge gefunden';
            dropdown.appendChild(item);
            return;
        }

        vehicles.forEach(vehicle => {
            const item = document.createElement('a');
            item.className = 'dropdown-item';
            item.href = '#';

            const typeLabel = vehicle.rd_type == 1 ? 'NA' : 'RD';
            const kennzeichenText = vehicle.kennzeichen ? ` <small class="text-[var(--text-dimmed,#818189)]">[${vehicle.kennzeichen}]</small>` : '';
            item.innerHTML = `${vehicle.name || vehicle.identifier} <span class="ignis-chip">${typeLabel}</span>${kennzeichenText}`;

            item.dataset.identifier = vehicle.identifier;
            item.dataset.name = vehicle.name || vehicle.identifier;
            item.dataset.kennzeichen = vehicle.kennzeichen || '';

            item.addEventListener('click', function(e) {
                e.preventDefault();
                selectVehicle(vehicle);
            });

            dropdown.appendChild(item);
        });
    }

    // Wähle ein Fahrzeug aus
    function selectVehicle(vehicle) {
        selectedVehicle = vehicle;
        const typeLabel = vehicle.rd_type == 1 ? 'NA' : 'RD';
        const displayText = `${vehicle.name || vehicle.identifier} (${typeLabel})`;

        document.getElementById('targetVehicleSearch').value = displayText;
        document.getElementById('selectedVehicleId').value = vehicle.identifier;
        document.getElementById('vehicleDropdown').classList.remove('show');
        document.getElementById('confirmShareBtn').disabled = false;
    }

    // Suchfunktion mit Dropdown
    document.getElementById('targetVehicleSearch')?.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        const dropdown = document.getElementById('vehicleDropdown');

        // Zeige Dropdown wenn getippt wird
        if (searchTerm.length > 0) {
            dropdown.classList.add('show');
        }

        // Reset selection wenn Benutzer Text ändert
        if (selectedVehicle && this.value !== `${selectedVehicle.name || selectedVehicle.identifier} (${selectedVehicle.rd_type == 1 ? 'NA' : 'RD'})`) {
            selectedVehicle = null;
            document.getElementById('selectedVehicleId').value = '';
            document.getElementById('confirmShareBtn').disabled = true;
        }

        if (!searchTerm) {
            updateVehicleDropdown(allVehicles);
            return;
        }

        const filtered = allVehicles.filter(vehicle => {
            const name = (vehicle.name || '').toLowerCase();
            const kennzeichen = (vehicle.kennzeichen || '').toLowerCase();
            const identifier = vehicle.identifier.toLowerCase();

            return name.includes(searchTerm) ||
                kennzeichen.includes(searchTerm) ||
                identifier.includes(searchTerm);
        });

        updateVehicleDropdown(filtered);
    });

    // Zeige Dropdown beim Fokus
    document.getElementById('targetVehicleSearch')?.addEventListener('focus', function() {
        const dropdown = document.getElementById('vehicleDropdown');
        dropdown.classList.add('show');
        if (allVehicles.length > 0 && !this.value) {
            updateVehicleDropdown(allVehicles);
        }
    });

    // Schließe Dropdown beim Klick außerhalb
    document.addEventListener('click', function(e) {
        const search = document.getElementById('targetVehicleSearch');
        const dropdown = document.getElementById('vehicleDropdown');
        if (search && dropdown && !search.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });

    // Share bestätigen
    document.getElementById('confirmShareBtn')?.addEventListener('click', function() {
        const targetVehicle = document.getElementById('selectedVehicleId').value;

        if (!targetVehicle) {
            showShareError('Bitte wähle ein Fahrzeug aus');
            return;
        }

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Wird gesendet...';

        // Sende Share-Request
        fetch('<?= BASE_PATH ?>api/enotf/share/send-request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    protocol_id: currentProtocolId,
                    enr: currentProtocolEnr,
                    target_vehicle: targetVehicle
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Protokoll wurde erfolgreich geteilt. Das Fahrzeug erhält eine Benachrichtigung.', {
                        type: 'success',
                        title: 'Erfolgreich geteilt'
                    });
                    bootstrap.Modal.getInstance(document.getElementById('shareProtocolModal')).hide();
                } else {
                    showShareError(data.message || 'Fehler beim Teilen des Protokolls');
                }
            })
            .catch(error => {
                console.error('Fehler:', error);
                showShareError('Netzwerkfehler beim Teilen des Protokolls');
            })
            .finally(() => {
                this.disabled = false;
                this.textContent = 'Teilen';
            });
    });

    function showShareError(message) {
        const errorDiv = document.getElementById('shareErrorMessage');
        errorDiv.textContent = message;
        errorDiv.classList.remove('hidden');
    }

    // Radio button handlers für Share-Anfrage
    document.getElementById('shareActionMerge')?.addEventListener('change', function() {
        if (this.checked) {
            document.getElementById('existingProtocolsContainer').classList.remove('hidden');
            loadExistingProtocols();
        }
        updateAcceptButton();
    });

    document.getElementById('shareActionNew')?.addEventListener('change', function() {
        if (this.checked) {
            document.getElementById('existingProtocolsContainer').classList.add('hidden');
        }
        updateAcceptButton();
    });

    document.getElementById('existingProtocolSelect')?.addEventListener('change', function() {
        updateAcceptButton();
    });

    function updateAcceptButton() {
        const mergeChecked = document.getElementById('shareActionMerge')?.checked;
        const newChecked = document.getElementById('shareActionNew')?.checked;
        const protocolSelected = document.getElementById('existingProtocolSelect')?.value;

        let enabled = false;
        if (newChecked) {
            enabled = true;
        } else if (mergeChecked && protocolSelected) {
            enabled = true;
        }

        document.getElementById('acceptShareBtn').disabled = !enabled;
    }

    function loadExistingProtocols() {
        fetch('<?= BASE_PATH ?>api/enotf/share/get-own-protocols.php')
            .then(response => response.json())
            .then(data => {
                console.log('Geladene Protokolle:', data);
                const select = document.getElementById('existingProtocolSelect');
                select.innerHTML = '<option value="">Öffne die Konsole falls keine Protokolle sichtbar sind...</option>';

                if (data.success && data.protocols && data.protocols.length > 0) {
                    select.innerHTML = '<option value="">Protokoll auswählen...</option>';
                    data.protocols.forEach(protocol => {
                        const option = document.createElement('option');
                        option.value = protocol.enr;
                        option.textContent = `${protocol.enr} - ${protocol.patname || 'Unbekannt'} (${protocol.edatum || 'N/A'})`;
                        select.appendChild(option);
                    });
                } else {
                    select.innerHTML = '<option value="">Keine Protokolle gefunden</option>';
                    console.warn('Keine Protokolle verfügbar:', data);
                }
            })
            .catch(error => {
                console.error('Fehler beim Laden der Protokolle:', error);
            });
    }

    // Accept Share Request
    document.getElementById('acceptShareBtn')?.addEventListener('click', function() {
        const action = document.querySelector('input[name="shareAction"]:checked')?.value;
        const targetEnr = action === 'merge' ? document.getElementById('existingProtocolSelect').value : null;

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Wird verarbeitet...';

        fetch('<?= BASE_PATH ?>api/enotf/share/accept-request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_id: currentShareRequestId,
                    action: action,
                    target_enr: targetEnr
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message || 'Protokoll wurde erfolgreich übernommen', {
                        type: 'success',
                        title: 'Erfolgreich'
                    });
                    bootstrap.Modal.getInstance(document.getElementById('shareRequestReceivedModal')).hide();

                    // Wenn neues Protokoll erstellt wurde, ggf. dorthin weiterleiten
                    if (action === 'new' && data.new_enr) {
                        setTimeout(() => {
                            window.location.href = '<?= BASE_PATH ?>enotf/protokoll/index.php?enr=' + encodeURIComponent(data.new_enr);
                        }, 1500);
                    } else if (action === 'merge') {
                        // Bei Merge zur Overview oder Reload
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                } else {
                    showAlert(data.message || 'Fehler beim Verarbeiten der Anfrage', {
                        type: 'error',
                        title: 'Fehler'
                    });
                }
            })
            .catch(error => {
                console.error('Fehler:', error);
                showAlert('Netzwerkfehler beim Verarbeiten der Anfrage', {
                    type: 'error',
                    title: 'Fehler'
                });
            })
            .finally(() => {
                this.disabled = false;
                this.textContent = 'Annehmen';
            });
    });

    // Reject Share Request
    document.getElementById('rejectShareBtn')?.addEventListener('click', function() {
        this.disabled = true;

        fetch('<?= BASE_PATH ?>api/enotf/share/reject-request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_id: currentShareRequestId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Anfrage wurde abgelehnt', {
                        type: 'info',
                        title: 'Abgelehnt'
                    });
                    bootstrap.Modal.getInstance(document.getElementById('shareRequestReceivedModal')).hide();
                } else {
                    showAlert(data.message || 'Fehler beim Ablehnen der Anfrage', {
                        type: 'error',
                        title: 'Fehler'
                    });
                }
            })
            .catch(error => {
                console.error('Fehler:', error);
                showAlert('Netzwerkfehler', {
                    type: 'error',
                    title: 'Fehler'
                });
            })
            .finally(() => {
                this.disabled = false;
            });
    });

    // Zeige Share-Request-Modal
    let lastShownRequestId = null;

    function showShareRequestModal(requestData) {
        // Verhindere mehrfaches Öffnen desselben Requests
        if (lastShownRequestId === requestData.id) {
            return;
        }

        // Prüfe ob das Modal bereits offen ist
        const existingModal = bootstrap.Modal.getInstance(document.getElementById('shareRequestReceivedModal'));
        if (existingModal) {
            return;
        }

        lastShownRequestId = requestData.id;
        currentShareRequestId = requestData.id;

        document.getElementById('shareSourceVehicle').textContent = requestData.source_vehicle;
        document.getElementById('shareProtocolEnr').textContent = requestData.enr;
        document.getElementById('shareProtocolPatient').textContent = requestData.patname || 'Unbekannt';
        document.getElementById('shareProtocolType').textContent = requestData.prot_by == 1 ? 'Notarzt-Protokoll' : 'Rettungsdienst-Protokoll';
        document.getElementById('shareProtocolDateTime').textContent = requestData.edatum + ' ' + (requestData.ezeit || '');

        const modal = new bootstrap.Modal(document.getElementById('shareRequestReceivedModal'));

        // Reset lastShownRequestId wenn Modal geschlossen wird
        document.getElementById('shareRequestReceivedModal').addEventListener('hidden.bs.modal', function() {
            lastShownRequestId = null;
        }, {
            once: true
        });

        modal.show();
    }

    // Polling für Share-Requests (wird in der Hauptseite aufgerufen)
    function checkForShareRequests() {
        fetch('<?= BASE_PATH ?>api/enotf/share/check-requests.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.has_requests && data.request) {
                    showShareRequestModal(data.request);
                }
            })
            .catch(error => {
                console.error('Fehler beim Prüfen auf Share-Requests:', error);
            });
    }

    // Starte Polling für Share-Requests
    if (window.location.pathname.includes('overview.php') || window.location.pathname.includes('enotf/index.php')) {
        // Check sofort und dann alle 10 Sekunden
        checkForShareRequests();
        setInterval(checkForShareRequests, 10000);
    }
</script>