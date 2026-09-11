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
<div data-dialog-source class="modal" id="update-progress-modal" data-dialog-static tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-download mr-2"></i>
                    Update wird installiert
                </h5>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="ignis-spinner ignis-spinner--lg ignis-spinner--info" role="status">
                        <span class="sr-only">Wird geladen...</span>
                    </div>
                </div>
                <div class="ignis-progress ignis-progress--labeled ignis-progress--striped ignis-progress--info mb-3">
                    <div class="ignis-progress__label"><strong id="update-progress-text">0%</strong></div>
                    <div class="ignis-progress__track">
                        <div class="ignis-progress__bar"
                             role="progressbar"
                             id="update-progress-bar"
                             style="width: 0%"></div>
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
