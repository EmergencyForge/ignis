/**
 * ıgnıs UI — Snackbar
 *
 * Bottom-left Toast-Stack mit max. 3 sichtbaren Snackbars (FIFO-
 * Eviction beim 4.), Slide-in von links, Hover-Pause, ESC schließt
 * den obersten. Variants info | success | warning | error | progress.
 *
 *   ignis.snack.info('Gespeichert');
 *   ignis.snack.success('Fertig', { duration: 2000 });
 *   ignis.snack.error('Fehlgeschlagen', { duration: 0 }); // sticky
 *   ignis.snack.warning('Vorsicht', {
 *       action: { label: 'Rückgängig', onClick: () => undo() }
 *   });
 *
 *   const p = ignis.snack.progress('Lade Datei…', { title: 'Upload' });
 *   p.setProgress(0.45);            // 45%
 *   p.setMessage('Bild 3 von 7');
 *   p.complete('Hochgeladen');      // wandelt zu success + auto-close
 *   p.error('Verbindung verloren'); // wandelt zu error + sticky
 *
 * Legacy `window.showToast(message, type, opts)` ist als Shim am
 * Ende dieser Datei eingerichtet, sodass alte Call-Sites ohne
 * Migration weiterlaufen.
 */

const MAX_VISIBLE = 3;
const DEFAULT_DURATION = 5000;
const Z_INDEX = 1100;

const ICONS = {
    info:     'fa-circle-info',
    success:  'fa-circle-check',
    warning:  'fa-triangle-exclamation',
    error:    'fa-circle-xmark',
    progress: 'fa-spinner',
};

let stackEl = null;
const active = [];

function ensureStack() {
    if (stackEl && document.body.contains(stackEl)) return stackEl;
    stackEl = document.createElement('div');
    stackEl.className = 'ignis-snack-stack';
    stackEl.style.zIndex = String(Z_INDEX);
    document.body.appendChild(stackEl);
    return stackEl;
}

function enforceMax() {
    while (active.length > MAX_VISIBLE) {
        const oldest = active[0];
        oldest._dismiss(true);
    }
}

class Snack {
    constructor(variant, message, opts = {}) {
        this.variant = variant;
        this.message = message;
        this.title = opts.title ?? '';
        this.duration = opts.duration ?? (variant === 'progress' ? 0 : (variant === 'error' ? 0 : DEFAULT_DURATION));
        this.action = opts.action ?? null;
        this.timer = null;
        this.paused = false;
        this.dismissed = false;

        this._build();
        this._mount();
        this._scheduleAutoClose();
    }

    _build() {
        const el = document.createElement('div');
        el.className = `ignis-snack ignis-snack--${this.variant}`;
        el.setAttribute('role', this.variant === 'error' ? 'alert' : 'status');
        el.setAttribute('aria-live', 'polite');
        el.tabIndex = 0;

        // Icon
        const icon = document.createElement('i');
        icon.className = `ignis-snack__icon fa-solid ${ICONS[this.variant] || ICONS.info}`;
        if (this.variant === 'progress') icon.classList.add('fa-spin');
        el.appendChild(icon);

        // Body
        const body = document.createElement('div');
        body.className = 'ignis-snack__body';

        if (this.title) {
            const t = document.createElement('div');
            t.className = 'ignis-snack__title';
            t.textContent = this.title;
            body.appendChild(t);
        }

        const msgEl = document.createElement('div');
        msgEl.className = 'ignis-snack__msg';
        msgEl.textContent = this.message;
        body.appendChild(msgEl);
        this._msgEl = msgEl;

        if (this.variant === 'progress') {
            const track = document.createElement('div');
            track.className = 'ignis-snack__progress';
            const bar = document.createElement('div');
            bar.className = 'ignis-snack__bar';
            bar.style.width = '0%';
            track.appendChild(bar);
            body.appendChild(track);
            this._barEl = bar;
        }

        el.appendChild(body);

        // Optional Action-Button
        if (this.action && this.action.label && typeof this.action.onClick === 'function') {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ignis-snack__action';
            btn.textContent = this.action.label;
            btn.addEventListener('click', () => {
                try { this.action.onClick(); } catch (e) { console.error(e); }
                this.dismiss();
            });
            el.appendChild(btn);
        }

        // Close-Button
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'ignis-snack__close';
        close.setAttribute('aria-label', 'Schließen');
        close.innerHTML = '&times;';
        close.addEventListener('click', () => this.dismiss());
        el.appendChild(close);

        // Hover-Pause
        el.addEventListener('mouseenter', () => this._pause());
        el.addEventListener('mouseleave', () => this._resume());

        this.el = el;
    }

    _mount() {
        const stack = ensureStack();
        stack.appendChild(this.el);
        active.push(this);
        enforceMax();

        // Force reflow für Transition
        void this.el.offsetWidth;
        requestAnimationFrame(() => this.el.classList.add('is-visible'));
    }

    _scheduleAutoClose() {
        if (this.duration > 0 && !this.timer) {
            this.timer = setTimeout(() => this.dismiss(), this.duration);
        }
    }

    _pause() {
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
            this.paused = true;
        }
    }

    _resume() {
        if (this.paused && this.duration > 0) {
            this.paused = false;
            this._scheduleAutoClose();
        }
    }

    _dismiss(immediate = false) {
        if (this.dismissed) return;
        this.dismissed = true;
        if (this.timer) { clearTimeout(this.timer); this.timer = null; }

        const idx = active.indexOf(this);
        if (idx !== -1) active.splice(idx, 1);

        if (immediate) {
            this.el.remove();
        } else {
            this.el.classList.remove('is-visible');
            this.el.classList.add('is-leaving');
            setTimeout(() => this.el.remove(), 250);
        }
    }

    dismiss() { this._dismiss(false); }

    setMessage(msg) {
        this.message = msg;
        if (this._msgEl) this._msgEl.textContent = msg;
    }

    setProgress(value) {
        if (!this._barEl) return;
        const pct = Math.max(0, Math.min(1, value)) * 100;
        this._barEl.style.width = pct + '%';
    }

    complete(msg) {
        this._morph('success', msg);
    }

    error(msg) {
        this._morph('error', msg, { sticky: true });
    }

    _morph(newVariant, msg, opts = {}) {
        this.variant = newVariant;
        this.el.className = `ignis-snack ignis-snack--${newVariant} is-visible`;
        const icon = this.el.querySelector('.ignis-snack__icon');
        if (icon) {
            icon.className = `ignis-snack__icon fa-solid ${ICONS[newVariant] || ICONS.info}`;
            icon.classList.remove('fa-spin');
        }
        const bar = this.el.querySelector('.ignis-snack__progress');
        if (bar) bar.remove();
        this._barEl = null;

        if (msg !== undefined) this.setMessage(msg);

        if (opts.sticky) {
            this.duration = 0;
        } else {
            this.duration = 2000;
            this._scheduleAutoClose();
        }
    }
}

function spawn(variant, message, opts) {
    return new Snack(variant, message, opts);
}

const snack = {
    info:     (m, o) => spawn('info', m, o),
    success:  (m, o) => spawn('success', m, o),
    warning:  (m, o) => spawn('warning', m, o),
    error:    (m, o) => spawn('error', m, o),
    progress: (m, o) => spawn('progress', m, o),
    dismissAll: () => { while (active.length) active[0].dismiss(); },
};

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && active.length > 0) {
        active[active.length - 1].dismiss();
    }
});

if (typeof window !== 'undefined') {
    window.ignis = window.ignis || {};
    window.ignis.snack = snack;

    // Legacy `showToast(message, type, opts)` Shim — alle bestehenden
    // Aufruf-Sites laufen ohne Markup-Änderung weiter, geroutet auf
    // die neue Snackbar.
    window.showToast = function (message, type, options) {
        if (typeof type === 'object') {
            options = type;
            type = options.type;
        }
        const variant = ({
            danger: 'error',
            error:  'error',
            success: 'success',
            warning: 'warning',
            info:   'info',
        })[type] || 'info';
        const opts = options ? { duration: options.duration } : undefined;
        if (options && options.retry && typeof options.retry === 'function') {
            opts && (opts.action = { label: 'Wiederholen', onClick: options.retry });
        }
        return spawn(variant, message, opts);
    };
}

export { snack, Snack };
