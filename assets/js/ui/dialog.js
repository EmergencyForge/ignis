/**
 * ıgnıs Dialog — hauseigener Ersatz für native confirm/alert/prompt und
 * Bootstrap-Modals. Vanilla JS, plain DOM, keine Third-Party-Dependencies.
 *
 * Usage (statische Factory-Methoden):
 *     await Dialog.confirm('Wirklich?', { danger: true });
 *     await Dialog.alert('Gespeichert');
 *     const name = await Dialog.prompt('Name?', { default: 'Max' });
 *
 * Usage (Custom-Dialog):
 *     const d = new Dialog({
 *       title: 'Etwas bearbeiten',
 *       body: '<form>…</form>',
 *       actions: [
 *         { label: 'Abbrechen', variant: 'ghost', close: true },
 *         { label: 'Speichern', variant: 'primary', onClick: (d) => d.close('saved') },
 *       ],
 *       size: 'md',
 *     });
 *     const result = await d.open();
 *
 * Features: Focus-Trap, ESC-Close, Backdrop-Click, Stack-fähig, ARIA.
 */

let activeStack = [];
let nextId = 1;

const FOCUSABLE_SELECTOR =
    'a[href], button:not([disabled]), textarea:not([disabled]), ' +
    'input:not([disabled]):not([type="hidden"]), select:not([disabled]), ' +
    '[tabindex]:not([tabindex="-1"])';

export class Dialog {
    constructor(options = {}) {
        this.id = nextId++;
        this.options = {
            title: '',
            body: '',
            actions: [],
            size: 'md',
            closeOnBackdrop: true,
            closeOnEscape: true,
            onOpen: null,
            onClose: null,
            ariaLabel: null,
            ...options,
        };
        this.element = null;
        this.backdrop = null;
        this.previousFocus = null;
        this._resolve = null;
        this._result = null;
        this._boundKeyHandler = this._handleKey.bind(this);
    }

    open() {
        if (this.element) return Promise.resolve(this._result);

        this.previousFocus = document.activeElement;
        this._render();
        document.body.appendChild(this.backdrop);
        document.body.appendChild(this.element);
        activeStack.push(this);

        // reflow damit Animation greift
        this.element.offsetHeight;
        requestAnimationFrame(() => {
            this.element.classList.add('is-open');
            this.backdrop.classList.add('is-open');
        });

        document.addEventListener('keydown', this._boundKeyHandler, true);
        this._focusFirst();

        if (typeof this.options.onOpen === 'function') {
            this.options.onOpen(this);
        }

        return new Promise((resolve) => {
            this._resolve = resolve;
        });
    }

    close(result = null) {
        if (!this.element) return;
        this._result = result;

        this.element.classList.remove('is-open');
        this.backdrop.classList.remove('is-open');

        document.removeEventListener('keydown', this._boundKeyHandler, true);

        const el = this.element;
        const bd = this.backdrop;

        setTimeout(() => {
            el.remove();
            bd.remove();
        }, 220);

        this.element = null;
        this.backdrop = null;
        activeStack = activeStack.filter((d) => d !== this);

        if (this.previousFocus && document.contains(this.previousFocus)) {
            this.previousFocus.focus();
        }

        if (typeof this.options.onClose === 'function') {
            this.options.onClose(result);
        }

        if (this._resolve) {
            this._resolve(result);
            this._resolve = null;
        }
    }

    // ───────────────────────────────────────────────────────────────────
    // Static factory methods
    // ───────────────────────────────────────────────────────────────────

    /**
     * Boolean-Confirm-Dialog. Resolved mit true bei Bestätigung, sonst false.
     * Opts: title, confirmText, cancelText, danger, body (überschreibt text)
     */
    static confirm(text, opts = {}) {
        const d = new Dialog({
            size: 'sm',
            title: opts.title ?? 'Bestätigen',
            body: opts.body ?? `<p class="ignis-dialog__text">${escape(text)}</p>`,
            ariaLabel: opts.title ?? 'Bestätigen',
            actions: [
                {
                    label: opts.cancelText ?? 'Abbrechen',
                    variant: 'ghost',
                    close: false,
                    onClick: (dlg) => dlg.close(false),
                },
                {
                    label: opts.confirmText ?? 'Bestätigen',
                    variant: opts.danger ? 'danger' : 'primary',
                    primary: true,
                    onClick: (dlg) => dlg.close(true),
                },
            ],
        });
        return d.open().then((r) => r === true);
    }

    /**
     * Alert-Dialog mit einem OK-Button. Resolved mit void.
     * Opts: title, okText, type ('info'|'success'|'warning'|'error')
     */
    static alert(text, opts = {}) {
        const type = opts.type ?? 'info';
        const icon = {
            info:    'fa-circle-info',
            success: 'fa-circle-check',
            warning: 'fa-triangle-exclamation',
            error:   'fa-circle-xmark',
        }[type] ?? 'fa-circle-info';

        const d = new Dialog({
            size: 'sm',
            title: opts.title ?? ({ info: 'Hinweis', success: 'Erfolg', warning: 'Achtung', error: 'Fehler' }[type]),
            body: `<div class="ignis-dialog__alert ignis-dialog__alert--${type}">
                <i class="fa-solid ${icon}"></i>
                <p class="ignis-dialog__text">${escape(text)}</p>
            </div>`,
            actions: [
                {
                    label: opts.okText ?? 'OK',
                    variant: type === 'error' ? 'danger' : 'primary',
                    primary: true,
                    close: true,
                },
            ],
        });
        return d.open().then(() => undefined);
    }

    /**
     * Prompt-Dialog mit Text-Input. Resolved mit String oder null (Cancel).
     * Opts: title, default, placeholder, confirmText, cancelText
     */
    static prompt(text, opts = {}) {
        const inputId = `ignis-dialog-prompt-${Math.random().toString(36).slice(2, 8)}`;
        const def = escape(String(opts.default ?? ''));
        const ph = escape(String(opts.placeholder ?? ''));

        const d = new Dialog({
            size: 'sm',
            title: opts.title ?? 'Eingabe',
            body: `<label for="${inputId}" class="ignis-dialog__label">${escape(text)}</label>
                <input id="${inputId}" type="text" class="ignis-dialog__input" value="${def}" placeholder="${ph}" />`,
            actions: [
                {
                    label: opts.cancelText ?? 'Abbrechen',
                    variant: 'ghost',
                    onClick: (dlg) => dlg.close(null),
                },
                {
                    label: opts.confirmText ?? 'OK',
                    variant: 'primary',
                    primary: true,
                    onClick: (dlg) => {
                        const input = dlg.element.querySelector(`#${inputId}`);
                        dlg.close(input ? input.value : null);
                    },
                },
            ],
            onOpen: (dlg) => {
                const input = dlg.element.querySelector(`#${inputId}`);
                if (input) {
                    input.focus();
                    input.select();
                }
            },
        });

        return d.open();
    }

    /**
     * Form-Dialog: ein Dialog, der den Body-Inhalt aus einem inerten
     * <template>-Element klont und Cancel/Submit-Actions anbietet.
     *
     * Zwei Modi:
     *   - Server-Submit (formAction gesetzt): Body ist ein <form>, Submit
     *     ruft form.requestSubmit() — HTML5-Validation greift.
     *   - AJAX (onSubmit gesetzt): Body ist ein <div>, Submit ruft den
     *     Callback mit (bodyElement, dialogInstance). Caller schließt
     *     den Dialog selbst nach erfolgreichem AJAX.
     *
     * Opts:
     *   title:          string       Dialog-Titel
     *   template:       string|HTMLTemplateElement   ID oder Element
     *   size:           'sm'|'md'|'lg'|'xl'  (default: 'md')
     *   formAction:     string?      URL für POST (server-form-modus)
     *   formMethod:     string?      Default 'POST'
     *   hiddenFields:   {[name]: value}   Hidden-Inputs vor dem Template-Inhalt
     *   submitLabel:    string?      Default 'Speichern'
     *   submitIcon:     string?      Font-Awesome-Klassenname, z.B. 'fa-solid fa-link'
     *   submitVariant:  'primary'|'success'|'danger'|'soft-primary'|...
     *   cancelLabel:    string?      Default 'Abbrechen'
     *   onSubmit:       (body, dlg) => void   Custom-Submit-Handler (AJAX)
     *   onOpen:         (dlg) => void   Wird nach dem Open gerufen,
     *                                    z.B. für Field-Prefill
     *   onClose:        (result) => void
     *   closeOnBackdrop / closeOnEscape: wie bei Dialog (default true)
     */
    static form(opts = {}) {
        const tpl = typeof opts.template === 'string'
            ? document.getElementById(opts.template)
            : opts.template;
        if (!tpl || !(tpl instanceof HTMLTemplateElement)) {
            console.error('Dialog.form: template not found or not a <template>:', opts.template);
            return null;
        }

        // Body: <form> wenn formAction gesetzt, sonst <div>. Beim AJAX-Pfad
        // brauchen wir kein <form>, weil der Submit-Handler manuell läuft.
        const isFormSubmit = typeof opts.formAction === 'string';
        const body = document.createElement(isFormSubmit ? 'form' : 'div');
        if (isFormSubmit) {
            body.method = opts.formMethod || 'POST';
            body.action = opts.formAction;
        }

        if (opts.hiddenFields) {
            Object.keys(opts.hiddenFields).forEach((name) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = opts.hiddenFields[name];
                body.appendChild(input);
            });
        }

        body.appendChild(tpl.content.cloneNode(true));

        const submitAction = {
            variant: opts.submitVariant || 'primary',
            primary: true,
        };
        if (opts.submitIcon) {
            submitAction.labelHtml =
                '<i class="' + opts.submitIcon + '"></i> ' + escape(opts.submitLabel || 'Speichern');
        } else {
            submitAction.label = opts.submitLabel || 'Speichern';
        }
        submitAction.onClick = (dlg) => {
            if (typeof opts.onSubmit === 'function') {
                opts.onSubmit(body, dlg);
            } else if (isFormSubmit) {
                body.requestSubmit();
            } else {
                dlg.close('submit');
            }
        };

        const actions = [];
        if (opts.dangerAction) {
            actions.push({
                label: opts.dangerAction.label,
                variant: opts.dangerAction.variant || 'ghost-danger',
                pullLeft: true,
                onClick: opts.dangerAction.onClick,
            });
        }
        actions.push({
            label: opts.cancelLabel || 'Abbrechen',
            variant: 'ghost',
            onClick: (d) => d.close(null),
        });
        actions.push(submitAction);

        const dlg = new Dialog({
            title: opts.title,
            size: opts.size || 'md',
            body: body,
            actions: actions,
            closeOnBackdrop: opts.closeOnBackdrop !== false,
            closeOnEscape: opts.closeOnEscape !== false,
            onOpen: opts.onOpen,
            onClose: opts.onClose,
        });

        dlg.open();
        return dlg;
    }

    // ───────────────────────────────────────────────────────────────────
    // Internal
    // ───────────────────────────────────────────────────────────────────

    _render() {
        const z = 2000 + activeStack.length * 10;

        this.backdrop = document.createElement('div');
        this.backdrop.className = 'ignis-dialog-backdrop';
        this.backdrop.style.zIndex = String(z);
        if (this.options.closeOnBackdrop) {
            this.backdrop.addEventListener('click', () => this.close(null));
        }

        this.element = document.createElement('div');
        this.element.className = `ignis-dialog ignis-dialog--${this.options.size}`;
        this.element.setAttribute('role', 'dialog');
        this.element.setAttribute('aria-modal', 'true');
        this.element.style.zIndex = String(z + 1);

        const titleId = `ignis-dialog-title-${this.id}`;
        if (this.options.title) {
            this.element.setAttribute('aria-labelledby', titleId);
        } else if (this.options.ariaLabel) {
            this.element.setAttribute('aria-label', this.options.ariaLabel);
        }

        const headerHtml = this.options.title
            ? `<header class="ignis-dialog__header">
                <h2 class="ignis-dialog__title" id="${titleId}">${escape(this.options.title)}</h2>
                <button type="button" class="ignis-dialog__close" aria-label="Schließen">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </header>`
            : '';

        const bodyHtml = `<div class="ignis-dialog__body"></div>`;

        const footerHtml = this.options.actions.length
            ? `<footer class="ignis-dialog__footer">
                ${this.options.actions.map((a, i) => this._renderAction(a, i)).join('')}
            </footer>`
            : '';

        this.element.innerHTML = headerHtml + bodyHtml + footerHtml;

        // Body-Content injizieren (kann String oder HTMLElement sein)
        const bodyEl = this.element.querySelector('.ignis-dialog__body');
        const body = this.options.body;
        if (body instanceof HTMLElement) {
            bodyEl.appendChild(body);
        } else if (typeof body === 'string') {
            bodyEl.innerHTML = body;
        }

        // Event-Listeners
        const closeBtn = this.element.querySelector('.ignis-dialog__close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.close(null));
        }

        this.options.actions.forEach((action, i) => {
            const btn = this.element.querySelector(`[data-dialog-action="${i}"]`);
            if (!btn) return;
            btn.addEventListener('click', (ev) => {
                ev.preventDefault();
                if (typeof action.onClick === 'function') {
                    action.onClick(this);
                }
                if (action.close !== false && typeof action.onClick !== 'function') {
                    this.close(action.value ?? null);
                }
            });
        });
    }

    _renderAction(action, index) {
        const variant = action.variant ?? 'primary';
        const primaryAttr = action.primary ? 'data-dialog-primary="true"' : '';
        // pullLeft schiebt den Button an den linken Rand des Footers — fuer
        // CRUD-Modals, in denen die Loesch-Action visuell vom Save-Pair
        // getrennt sein soll.
        const pullAttr = action.pullLeft ? 'data-dialog-pull-left="true"' : '';
        // labelHtml ist ein opt-in für Icons o.ä. — Caller verantwortlich für
        // sichere HTML-Quelle. Default-Pfad escaped wie zuvor.
        const inner = action.labelHtml ?? escape(action.label);
        return `<button type="button"
                    class="ignis-dialog__action ignis-dialog__action--${variant}"
                    data-dialog-action="${index}" ${primaryAttr} ${pullAttr}>
                ${inner}
            </button>`;
    }

    _focusFirst() {
        // Bevorzugt Primary-Action, sonst erstes fokusfähiges Element
        const primary = this.element.querySelector('[data-dialog-primary="true"]');
        if (primary) {
            primary.focus();
            return;
        }
        const first = this.element.querySelector(FOCUSABLE_SELECTOR);
        if (first) first.focus();
    }

    _handleKey(ev) {
        // Nur den obersten Dialog im Stack behandeln
        if (activeStack[activeStack.length - 1] !== this) return;

        if (ev.key === 'Escape' && this.options.closeOnEscape) {
            ev.preventDefault();
            this.close(null);
            return;
        }

        if (ev.key === 'Enter') {
            // Enter triggert Primary-Action, aber nur wenn nicht in Textarea/multi-line
            const target = ev.target;
            if (target && target.tagName === 'TEXTAREA') return;

            const primary = this.element.querySelector('[data-dialog-primary="true"]');
            if (primary && !target.matches('button, a')) {
                ev.preventDefault();
                primary.click();
            }
            return;
        }

        if (ev.key === 'Tab') {
            this._trapFocus(ev);
        }
    }

    _trapFocus(ev) {
        const focusable = this.element.querySelectorAll(FOCUSABLE_SELECTOR);
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const active = document.activeElement;

        if (ev.shiftKey && active === first) {
            ev.preventDefault();
            last.focus();
        } else if (!ev.shiftKey && active === last) {
            ev.preventDefault();
            first.focus();
        }
    }
}

function escape(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Default-Export für bequeme Imports
export default Dialog;

// Globale Auto-Registrierung — ermöglicht `window.Dialog.confirm(...)` aus
// Inline-Scripts in Legacy-Templates, ohne Import.
if (typeof window !== 'undefined') {
    window.Dialog = Dialog;

    // Backwards-Compat mit dem alten assets/js/dialogs.js.
    // Alte Call-Sites: showConfirm(msg, {title, confirmText, cancelText, danger})
    // Alte Call-Sites: showAlert(msg, {title, okText, type})
    // Alte Call-Sites: showPrompt(msg, defaultValue, {title, placeholder, confirmText, cancelText})
    window.showConfirm = (message, options = {}) =>
        Dialog.confirm(message, {
            title: options.title,
            confirmText: options.confirmText,
            cancelText: options.cancelText,
            danger: options.danger,
        });

    window.showAlert = (message, options = {}) =>
        Dialog.alert(message, {
            title: options.title,
            okText: options.okText,
            type: options.type,
        });

    window.showPrompt = (message, defaultValue = '', options = {}) =>
        Dialog.prompt(message, {
            title: options.title,
            default: defaultValue,
            placeholder: options.placeholder,
            confirmText: options.confirmText,
            cancelText: options.cancelText,
        });

    // Aliase, die im alten System existierten
    window.intraConfirm = window.showConfirm;
    window.intraAlert   = window.showAlert;
    window.intraPrompt  = window.showPrompt;
}
