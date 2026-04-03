/**
 * Shared Utilities für den Template-Editor
 * Wird VOR allen anderen Editor-Modulen geladen
 */
(function () {
    'use strict';

    const CONFIG = window.TEMPLATE_EDITOR_CONFIG;
    const PX_PER_MM = CONFIG.mmToPx;

    /** Konvertierungsfaktor pt → px (bei 96dpi: 1pt = 1.333px) */
    const PT_TO_PX = 96 / 72; // = 1.3333...

    window.EditorUtils = {
        PX_PER_MM,
        PT_TO_PX,

        pxToMm(px) {
            return Math.round((px / PX_PER_MM) * 10) / 10;
        },

        mmToPx(mm) {
            return mm * PX_PER_MM;
        },

        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },

        escapeAttr(str) {
            return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        },
    };

    /** Leichtgewichtiger Event-Bus für Modul-Entkopplung */
    class EditorEventBus {
        constructor() { this._listeners = new Map(); }

        on(event, callback) {
            if (!this._listeners.has(event)) this._listeners.set(event, []);
            this._listeners.get(event).push(callback);
            return () => this.off(event, callback); // Unsubscribe-Funktion
        }

        off(event, callback) {
            const list = this._listeners.get(event);
            if (list) this._listeners.set(event, list.filter(cb => cb !== callback));
        }

        emit(event, ...args) {
            const list = this._listeners.get(event);
            if (list) list.forEach(cb => cb(...args));
        }
    }

    window.EditorEvents = new EditorEventBus();

    /**
     * CSRF-Token-Management für alle AJAX-Requests.
     * Token wird nach erfolgreichen POST-Requests rotiert.
     */
    window.EditorCsrf = {
        getToken() {
            return CONFIG.csrfToken;
        },

        /** Aktualisiert den Token (z.B. nach Server-Rotation) */
        updateToken(newToken) {
            if (newToken) CONFIG.csrfToken = newToken;
        },

        /** Fügt den Token einem JSON-Body-Objekt hinzu */
        addToBody(bodyObj) {
            bodyObj.csrf_token = this.getToken();
            return bodyObj;
        },

        /** Fügt den Token einem FormData-Objekt hinzu */
        addToFormData(formData) {
            formData.append('csrf_token', this.getToken());
            return formData;
        },

        /** Aktualisiert den Token aus einer Server-Response */
        handleResponse(result) {
            if (result && result.csrf_token) {
                this.updateToken(result.csrf_token);
            }
        },
    };
})();
