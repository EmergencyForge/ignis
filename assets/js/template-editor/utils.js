/**
 * Shared Utilities für den Template-Editor
 * Wird VOR allen anderen Editor-Modulen geladen
 */
(function () {
    'use strict';

    const CONFIG = window.TEMPLATE_EDITOR_CONFIG;
    const PX_PER_MM = CONFIG.mmToPx;

    window.EditorUtils = {
        PX_PER_MM,

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
})();
