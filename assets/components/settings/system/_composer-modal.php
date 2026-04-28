<?php
/**
 * Composer-Installation-Modal — wird gezeigt, wenn nach dem Update
 * ein `composer install` ausstehend ist (composer_pending.json), und
 * der User die Installation aus der UI heraus auslöst.
 */
?>
<div class="modal fade" id="composer-modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-box-open mr-2"></i>
                    Composer-Abhängigkeiten werden installiert
                </h5>
            </div>
            <div class="modal-body">
                <div id="composer-status-content">
                    <div class="text-center mb-3">
                        <div class="spinner-border text-[#7ba3d4]" role="status">
                            <span class="sr-only">Wird geladen...</span>
                        </div>
                    </div>
                    <div id="composer-status-text" class="text-center">
                        <p class="mb-2">Composer-Abhängigkeiten werden installiert...</p>
                        <small class="text-gray-400">Dies kann einige Minuten dauern. Bitte warten Sie.</small>
                    </div>
                </div>

                <div id="composer-success-content" style="display: none;">
                    <div class="ignis-alert ignis-alert--success mb-3">
                        <i class="fa-solid fa-check-circle mr-2"></i>
                        <strong>Erfolg!</strong> Composer-Abhängigkeiten wurden erfolgreich installiert.
                    </div>
                    <p class="mb-3">Das Update ist vollständig abgeschlossen. Bitte laden Sie die Seite neu, um die Änderungen zu übernehmen.</p>
                    <button type="button" id="reload-page-ignis-btn" class="ignis-ignis-btn ignis-btn--soft-primary w-full">
                        <i class="fa-solid fa-sync mr-2"></i>Seite neu laden
                    </button>
                </div>

                <div id="composer-error-content" style="display: none;">
                    <div class="ignis-alert ignis-alert--danger mb-3">
                        <i class="fa-solid fa-exclamation-triangle mr-2"></i>
                        <strong>Fehler!</strong> Composer-Installation fehlgeschlagen.
                    </div>
                    <p id="composer-error-message" class="mb-3"></p>
                    <p class="mb-3">
                        <strong>Manuelle Installation erforderlich:</strong><br>
                        Bitte führen Sie im Terminal im Anwendungsverzeichnis aus:<br>
                        <code>composer install --no-dev --optimize-autoloader</code>
                    </p>
                    <button type="button" id="retry-composer-ignis-btn" class="ignis-ignis-btn ignis-btn--outline-secondary w-full mb-2">
                        <i class="fa-solid fa-rotate-right mr-2"></i>Erneut versuchen
                    </button>
                    <button type="button" id="dismiss-composer-ignis-btn" class="ignis-ignis-btn ignis-btn--outline-secondary w-full">
                        <i class="fa-solid fa-times mr-2"></i>Schließen (Update manuell abschließen)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
