/**
 * Unified Toast Notification System
 * Compact, non-blocking, auto-dismissing notifications with batching support.
 *
 * Usage:
 *   showToast('Message', 'success')
 *   showToast('Message', 'danger', { duration: 8000 })
 *   showToast('Error', 'danger', { retry: function() { ... } })
 *   ToastQueue.add('Saved', 'success')  // batches rapid calls
 */

(function(window) {
    'use strict';

    var MAX_TOASTS = 5;
    var container = null;

    var defaults = {
        success: { duration: 1500 },
        danger:  { duration: 8000 },
        warning: { duration: 5000 },
        info:    { duration: 4000 }
    };

    var icons = {
        success: 'fa-solid fa-circle-check',
        danger: 'fa-solid fa-circle-xmark',
        warning: 'fa-solid fa-triangle-exclamation',
        info: 'fa-solid fa-circle-info'
    };

    function ensureContainer() {
        if (!container) {
            container = document.getElementById('intra-toast-container');
        }
        if (!container) {
            container = document.createElement('div');
            container.id = 'intra-toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    function enforceMaxToasts(c) {
        while (c.children.length >= MAX_TOASTS) {
            var oldest = c.children[0];
            oldest.remove();
        }
    }

    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} [type='info'] - 'success', 'danger', 'warning', 'info'
     * @param {Object} [options] - Additional options
     * @param {number} [options.duration] - Duration in ms (0 = sticky)
     * @param {Function} [options.retry] - Retry callback for error toasts
     */
    window.showToast = function(message, type, options) {
        if (typeof type === 'object') {
            options = type;
            type = options.type || 'info';
        }
        type = type || 'info';
        // Map 'error' to 'danger' for consistency
        if (type === 'error') type = 'danger';
        options = options || {};

        var duration = options.duration !== undefined ? options.duration : (defaults[type] || defaults.info).duration;
        var c = ensureContainer();
        enforceMaxToasts(c);

        var toast = document.createElement('div');
        toast.className = 'toast-item toast-' + type;

        var icon = icons[type] || icons.info;

        var html =
            '<i class="' + icon + ' toast-icon"></i>' +
            '<span class="toast-msg">' + message + '</span>';

        // Retry button for errors
        if (options.retry && typeof options.retry === 'function') {
            html += '<button class="toast-retry" title="Erneut versuchen"><i class="fa-solid fa-rotate-right"></i></button>';
        }

        html += '<button class="toast-close" aria-label="Schließen">&times;</button>';

        // Progress bar for timed toasts
        if (duration > 0) {
            html += '<div class="toast-progress" style="animation-duration:' + duration + 'ms"></div>';
        }

        toast.innerHTML = html;
        c.appendChild(toast);

        // Force reflow then animate in
        toast.offsetHeight;
        toast.classList.add('toast-visible');

        // Close button
        toast.querySelector('.toast-close').addEventListener('click', function() {
            dismiss(toast);
        });

        // Retry button
        if (options.retry) {
            var retryBtn = toast.querySelector('.toast-retry');
            if (retryBtn) {
                retryBtn.addEventListener('click', function() {
                    dismiss(toast);
                    options.retry();
                });
            }
        }

        // Auto-dismiss with hover-pause
        if (duration > 0) {
            var timerId = setTimeout(function() { dismiss(toast); }, duration);

            toast.addEventListener('mouseenter', function() {
                clearTimeout(timerId);
                var progress = toast.querySelector('.toast-progress');
                if (progress) progress.style.animationPlayState = 'paused';
            });

            toast.addEventListener('mouseleave', function() {
                var progress = toast.querySelector('.toast-progress');
                if (progress) progress.style.animationPlayState = 'running';
                timerId = setTimeout(function() { dismiss(toast); }, duration);
            });
        }

        return toast;
    };

    function dismiss(toast) {
        if (toast.classList.contains('toast-dismissing')) return;
        toast.classList.add('toast-dismissing');
        toast.addEventListener('animationend', function() {
            toast.remove();
        }, { once: true });
        // Fallback if animationend doesn't fire
        setTimeout(function() { if (toast.parentNode) toast.remove(); }, 300);
    }

    /**
     * Toast Queue for batching rapid notifications
     * Usage: ToastQueue.add('Feld gespeichert', 'success')
     */
    var ToastQueue = {
        pending: [],
        timer: null,
        batchDelay: 500,

        add: function(message, type) {
            type = type || 'success';
            this.pending.push({ message: message, type: type });
            if (this.timer) clearTimeout(this.timer);
            this.timer = setTimeout(function() { ToastQueue.flush(); }, this.batchDelay);
        },

        flush: function() {
            var grouped = {};
            this.pending.forEach(function(item) {
                if (!grouped[item.type]) grouped[item.type] = [];
                grouped[item.type].push(item.message);
            });

            Object.keys(grouped).forEach(function(type) {
                var messages = grouped[type];
                if (messages.length === 1) {
                    window.showToast(messages[0], type);
                } else {
                    var summary = type === 'success' || type === 'info'
                        ? messages.length + ' Felder gespeichert'
                        : messages.length + ' Fehler aufgetreten';
                    window.showToast(summary, type);
                }
            });

            this.pending = [];
            this.timer = null;
        }
    };

    window.ToastQueue = ToastQueue;

    /**
     * Add loading state to a button, run async action, then remove loading state.
     * @param {HTMLElement} btn - The button element
     * @param {Function} asyncFn - Async function to execute
     */
    window.btnLoading = function(btn, asyncFn) {
        if (btn.classList.contains('btn-loading')) return Promise.resolve();
        btn.classList.add('btn-loading');
        return Promise.resolve(asyncFn()).finally(function() {
            btn.classList.remove('btn-loading');
        });
    };

})(window);
