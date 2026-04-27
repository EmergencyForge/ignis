<?php

use App\Auth\Permissions;
use App\Helpers\EnotfUrl;
?>

<!-- QM Modals - Include this component where topbar.php is used -->
<?php if (Permissions::check(['admin', 'edivi.edit'])) : ?>
    <!-- QM Actions Modal -->
    <div class="modal fade" id="qmActionsModal" tabindex="-1" aria-labelledby="qmActionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qmActionsModalLabel">QM-Funktionen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="qmActionsContent">
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Laden...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QM Log Modal -->
    <div class="modal fade" id="qmLogModal" tabindex="-1" aria-labelledby="qmLogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qmLogModalLabel">QM-Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="qmLogContent">
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Laden...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // QM Actions Modal Functions
        if (typeof openQMActions === 'undefined') {
            window.openQMActions = function(id, enr, patname) {
                const modal = new bootstrap.Modal(document.getElementById('qmActionsModal'));
                document.getElementById('qmActionsModalLabel').textContent = `QM-Funktionen [#${enr}] ${patname}`;

                // Reset content
                document.getElementById('qmActionsContent').innerHTML = `
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Laden...</span>
                        </div>
                    </div>
                `;

                modal.show();

                // Load content via AJAX
                fetch(`<?= BASE_PATH ?>enotf/admin/qm-actions-modal?id=${id}`)
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('qmActionsContent').innerHTML = data;
                    })
                    .catch(error => {
                        document.getElementById('qmActionsContent').innerHTML = `
                            <div class="ignis-alert ignis-alert--danger">
                                Fehler beim Laden der QM-Aktionen: ${error.message}
                            </div>
                        `;
                    });
            };
        }

        // QM Log Modal Functions
        if (typeof openQMLog === 'undefined') {
            window.openQMLog = function(id, enr, patname) {
                const modal = new bootstrap.Modal(document.getElementById('qmLogModal'));
                document.getElementById('qmLogModalLabel').textContent = `QM-Log [#${enr}] ${patname}`;

                // Reset content
                document.getElementById('qmLogContent').innerHTML = `
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Laden...</span>
                        </div>
                    </div>
                `;

                modal.show();

                // Load content via AJAX
                fetch(`<?= BASE_PATH ?>enotf/admin/qm-log-modal?id=${id}`)
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('qmLogContent').innerHTML = data;
                    })
                    .catch(error => {
                        document.getElementById('qmLogContent').innerHTML = `
                            <div class="ignis-alert ignis-alert--danger">
                                Fehler beim Laden des QM-Logs: ${error.message}
                            </div>
                        `;
                    });
            };
        }

        // Handle QM Actions form submission
        $(document).on('submit', '#qmActionsForm', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = this.querySelector('input[type="submit"]');
            const originalText = submitBtn.value;

            submitBtn.value = 'Speichere...';
            submitBtn.disabled = true;

            fetch(this.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('qmActionsModal')).hide();
                        // Show success message or reload if needed
                        if (typeof location !== 'undefined') {
                            location.reload();
                        }
                    } else {
                        showAlert('Fehler beim Speichern: ' + (data.message || 'Unbekannter Fehler'), {type: 'error', title: 'Fehler'});
                    }
                })
                .catch(error => {
                    showAlert('Fehler beim Speichern: ' + error.message, {type: 'error', title: 'Fehler'});
                })
                .finally(() => {
                    submitBtn.value = originalText;
                    submitBtn.disabled = false;
                });
        });
    </script>

    <style>
        /* QM Modals Styling for eNOTF */
        #qmActionsModal .modal-content,
        #qmLogModal .modal-content {
            background-color: #2b2b2b;
            border: 1px solid #4a4a4a;
            border-radius: 0;
        }

        #qmActionsModal .modal-header,
        #qmLogModal .modal-header {
            border-bottom: 1px solid #4a4a4a;
            background-color: #1f1f1f;
            border-radius: 0;
        }

        #qmActionsModal .modal-body,
        #qmLogModal .modal-body {
            background-color: #2b2b2b;
        }

        /* eNOTF Box Styling */
        #qmActionsModal .edivi__box,
        #qmLogModal .edivi__box {
            background: #333333;
            margin: 10px 0;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.5);
            border-radius: 0;
            padding: 1rem;
        }

        #qmActionsModal .edivi__box.edivi__log-comment,
        #qmLogModal .edivi__box.edivi__log-comment {
            background: #333333;
            color: #a2a2a2;
            padding: 12px;
            margin: 0;
            font-size: 0.8rem;
            margin-bottom: 10px !important;
        }

        #qmActionsModal .edivi__box.edivi__log-comment i,
        #qmLogModal .edivi__box.edivi__log-comment i {
            padding: 6px 9px;
            border-radius: 2px;
            background: #a2a2a2;
            color: #333333;
            opacity: 0.6;
            font-size: 1rem !important;
        }

        /* eNOTF Admin Form Controls */
        #qmActionsModal .edivi__admin,
        #qmLogModal .edivi__admin {
            background: transparent;
            border-radius: 0;
            color: #fff;
            border: 0;
            font-size: 1.2rem;
        }

        #qmActionsModal .edivi__admin:focus,
        #qmLogModal .edivi__admin:focus {
            box-shadow: 0 0 0 1px #5783cf !important;
            background: transparent;
            color: #fff;
            border-color: #5783cf;
        }

        #qmActionsModal .edivi__admin[readonly],
        #qmLogModal .edivi__admin[readonly] {
            box-shadow: none !important;
            caret-color: transparent;
            user-select: none;
            pointer-events: none;
            cursor: default;
        }

        #qmActionsModal .edivi__admin option,
        #qmLogModal .edivi__admin option {
            background: #333333;
            color: #fff;
        }

        /* Button styling for topbar */
        .edivi__iconlink-button {
            background: none;
            border: none;
            color: inherit;
            text-decoration: none;
            cursor: pointer;
            padding: 0;
            font: inherit;
            text-align: center;
        }

        .edivi__iconlink-button:hover {
            color: inherit;
            text-decoration: none;
        }

        .edivi__iconlink-button:focus {
            outline: none;
        }
    </style>
<?php endif; ?>