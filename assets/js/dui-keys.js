/**
 * Tastatur für ignis im FiveM-Tablet als DUI. Eine DUI bekommt vom Spiel
 * nur Mauseingaben, ef_bridge schickt Tasten per SendDuiMessage als
 * {type: "efKey", key, ctrl, shift}. execCommand, damit input-Events
 * feuern wie beim echten Tippen.
 *
 * Nur im obersten Fenster (im NUI-iframe gibt es echte Tasten) und nur für
 * Nachrichten aus dem eigenen Fenster: ein Opener oder Frame darf hier
 * keine Tasten einschleusen.
 */
(() => {
    if (window.top !== window) return;

    const focusables = () =>
        [...document.querySelectorAll('input, select, textarea, button, a[href], [tabindex]:not([tabindex="-1"])')]
            .filter((el) => !el.disabled && el.offsetParent !== null);

    window.addEventListener('message', (e) => {
        const m = e.data;
        if (!m || m.type !== 'efKey' || typeof m.key !== 'string') return;
        if (e.source !== null && e.source !== window) return;

        const el = document.activeElement;

        if (m.key === 'Tab') {
            const all = focusables();
            const next = all[(all.indexOf(el) + (m.shift ? -1 : 1) + all.length) % all.length];
            if (next) next.focus();
            return;
        }
        if (el && el.tagName === 'SELECT' && (m.key === 'ArrowDown' || m.key === 'ArrowUp')) {
            el.selectedIndex = Math.max(0, Math.min(el.options.length - 1, el.selectedIndex + (m.key === 'ArrowDown' ? 1 : -1)));
            el.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }

        const editable = el && (el.isContentEditable || el.tagName === 'TEXTAREA' || el.tagName === 'INPUT');
        if (!editable) {
            // Enter auf Buttons und Links löst sie aus wie mit echter Tastatur
            if (m.key === 'Enter' && el && (el.tagName === 'BUTTON' || el.tagName === 'A')) el.click();
            return;
        }

        if (m.ctrl && m.key.toLowerCase() === 'a') {
            if (el.select) el.select();
            else document.execCommand('selectAll');
            return;
        }
        if (m.ctrl) return;
        if (m.key.length === 1) {
            document.execCommand('insertText', false, m.key);
        } else if (m.key === 'Backspace') {
            document.execCommand('delete');
        } else if (m.key === 'Delete') {
            document.execCommand('forwardDelete');
        } else if (m.key === 'Enter') {
            if (el.tagName === 'TEXTAREA' || el.isContentEditable) document.execCommand('insertText', false, '\n');
            else if (el.form) el.form.requestSubmit();
        } else if (m.key === 'ArrowLeft' || m.key === 'ArrowRight') {
            try {
                const p = Math.max(0, el.selectionStart + (m.key === 'ArrowLeft' ? -1 : 1));
                el.setSelectionRange(p, p);
            } catch {
                // Datum, Zeit und Regler haben keinen Cursor
            }
        }
    });
})();
