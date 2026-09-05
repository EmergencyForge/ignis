/**
 * eNOTF v2 — QM-Dialoge (Vanilla-Ersatz für v1s qm-modals.php).
 *
 * Lädt die serverseitig gerenderten v1-QM-Fragmente über die v2-Wrapper-
 * Routen (/enotf-v2/qm/actions/{id} bzw. /enotf-v2/qm/log/{id}, siehe
 * routes.web.php) und zeigt sie in einem Dialog (assets/js/ui/dialog.js)
 * im dunklen eDIVI-Look (Styles im Partial _share-qm-assets.php):
 *
 *   EnotfV2QM.open(protocolId, enr, patname)     — QM-Funktionen
 *                                                  (Status + Bemerkung)
 *   EnotfV2QM.openLog(protocolId, enr, patname)  — QM-Log (read-only)
 *
 * Der Speichern-Submit des Actions-Formulars wird abgefangen und als
 * FormData-POST auf die Wrapper-Route geschickt (JSON-Antwort wie v1,
 * bei Erfolg Reload). Berechtigungen prüft durchgehend der Server —
 * ohne Panel-Login/edivi.view liefert die Route eine Fehlermeldung,
 * die hier als Hinweis im Dialog landet.
 */
(function () {
    'use strict';

    function basePath() {
        if (typeof window.__ev2Base === 'string' && window.__ev2Base !== '') {
            return window.__ev2Base;
        }
        var m = window.location.pathname.match(/^(.*?)\/enotf-v2\//);
        return m ? m[1] + '/' : '/';
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function spinnerHtml() {
        return '<div class="flex justify-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div>';
    }

    function errorHtml(message) {
        return '<div class="ev2-edivi-dialog__error">' + esc(message) + '</div>';
    }

    /**
     * Lädt ein Fragment und schreibt es in den Container. Fehlerfälle
     * (Login-Redirect, 403-JSON, Netzwerkfehler) enden als Meldung im
     * Container statt als JS-Error.
     */
    function loadFragment(url, container) {
        fetch(url)
            .then(function (response) {
                var contentType = response.headers.get('Content-Type') || '';
                if (contentType.indexOf('application/json') !== -1) {
                    // Route antwortet bei fehlender Berechtigung mit JSON
                    return response.json().then(function (data) {
                        container.innerHTML = errorHtml(data.message || 'Keine Berechtigung');
                    });
                }
                if (!response.ok || (response.redirected && response.url.indexOf('/login') !== -1)) {
                    container.innerHTML = errorHtml('Keine Berechtigung — QM-Funktionen erfordern einen Panel-Login.');
                    return undefined;
                }
                return response.text().then(function (html) {
                    container.innerHTML = html;
                });
            })
            .catch(function (error) {
                container.innerHTML = errorHtml('Fehler beim Laden: ' + error.message);
            });
    }

    function openDialog(title, url, withSubmit) {
        if (typeof window.Dialog === 'undefined') return;

        var body = document.createElement('div');
        body.innerHTML = spinnerHtml();

        if (withSubmit) {
            // Submit des v1-Fragments abfangen: die Form-Action zeigt auf die
            // GET-only v1-Route — gePOSTet wird stattdessen auf die Wrapper-URL
            body.addEventListener('submit', function (e) {
                var form = e.target.closest('#qmActionsForm');
                if (!form) return;
                e.preventDefault();

                var submitBtn = form.querySelector('input[type="submit"]');
                var originalValue = submitBtn ? submitBtn.value : '';
                if (submitBtn) {
                    submitBtn.value = 'Speichere...';
                    submitBtn.disabled = true;
                }

                // CSRF-Token als Header — das v1-Fragment kennt das
                // v2-Hidden-Field nicht (Prüfung: CsrfMiddleware)
                var csrfHeaders = {};
                if (typeof window.__ev2Csrf === 'string' && window.__ev2Csrf !== '') {
                    csrfHeaders['X-Csrf-Token'] = window.__ev2Csrf;
                }

                fetch(url, { method: 'POST', body: new FormData(form), headers: csrfHeaders })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data.success) {
                            if (body._ev2Dialog) body._ev2Dialog.close('saved');
                            window.location.reload();
                        } else if (typeof window.showAlert === 'function') {
                            window.showAlert('Fehler beim Speichern: ' + (data.message || 'Unbekannter Fehler'), { type: 'error', title: 'Fehler' });
                        }
                    })
                    .catch(function (error) {
                        if (typeof window.showAlert === 'function') {
                            window.showAlert('Fehler beim Speichern: ' + error.message, { type: 'error', title: 'Fehler' });
                        }
                    })
                    .finally(function () {
                        if (submitBtn) {
                            submitBtn.value = originalValue;
                            submitBtn.disabled = false;
                        }
                    });
            });
        }

        var dlg = new window.Dialog({
            title: title,
            body: body,
            size: 'lg',
            actions: [
                { label: 'Schließen', variant: 'ghost', onClick: function (d) { d.close(null); } },
            ],
            onOpen: function (instance) {
                instance.element.classList.add('ev2-edivi-dialog', 'ev2-qm-dialog');
            },
        });

        dlg.open();
        // Instanz am Body verankern — der Submit-Handler schließt darüber
        body._ev2Dialog = dlg;

        loadFragment(url, body);
        return dlg;
    }

    window.EnotfV2QM = {
        open: function (protocolId, enr, patname) {
            var url = basePath() + 'enotf-v2/qm/actions/' + encodeURIComponent(protocolId);
            return openDialog('QM-Funktionen [#' + enr + '] ' + (patname || 'Unbekannt'), url, true);
        },
        openLog: function (protocolId, enr, patname) {
            var url = basePath() + 'enotf-v2/qm/log/' + encodeURIComponent(protocolId);
            return openDialog('QM-Log [#' + enr + '] ' + (patname || 'Unbekannt'), url, false);
        },
    };
})();
