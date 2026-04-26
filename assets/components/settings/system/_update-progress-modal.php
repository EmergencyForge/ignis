<?php
/**
 * Update-Progress-Modal — vom System-Updater während eines laufenden
 * Updates getriggert. Wird sowohl im Stable- als auch im Dev-Branch-
 * Update-Pfad eingeblendet, daher als Partial extrahiert (vorher
 * waren das zwei identische Block-Kopien mit demselben DOM-Id, was
 * zu Duplicate-Id-Markup führte, sobald beide Cards gleichzeitig
 * gerendert wurden).
 */
?>
<div class="modal fade" id="update-progress-modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-download mr-2"></i>
                    Update wird installiert
                </h5>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="spinner-border text-[#7ba3d4]" role="status">
                        <span class="sr-only">Wird geladen...</span>
                    </div>
                </div>
                <div class="progress mb-3" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated"
                         role="progressbar"
                         id="update-progress-bar"
                         style="width: 0%">
                        <span id="update-progress-text">0%</span>
                    </div>
                </div>
                <div id="update-status-text" class="text-center">
                    <small class="text-gray-400">Update wird vorbereitet...</small>
                </div>
                <div class="ignis-alert ignis-alert--info mt-3 mb-0">
                    <small>
                        <i class="fa-solid fa-info-circle mr-1"></i>
                        <strong>Hinweis:</strong> Bitte schließen Sie dieses Fenster nicht.
                        Der Vorgang kann mehrere Minuten dauern.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
