/**
 * Toast Notification System
 * Non-blocking, auto-dismissing notifications
 */

(function(window) {
    'use strict';

    let container = null;

    function ensureContainer() {
        if (!container) {
            container = document.createElement('div');
            container.id = 'intra-toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    const icons = {
        success: 'fa-solid fa-circle-check',
        danger: 'fa-solid fa-circle-xmark',
        warning: 'fa-solid fa-triangle-exclamation',
        info: 'fa-solid fa-circle-info'
    };

    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} type - 'success', 'danger', 'warning', 'info' (default: 'info')
     * @param {Object} options - Additional options
     * @param {number} options.duration - Duration in ms (default: 4000, 0 = sticky)
     */
    window.showToast = function(message, type, options) {
        if (typeof type === 'object') {
            options = type;
            type = options.type || 'info';
        }
        type = type || 'info';
        options = options || {};

        const duration = options.duration !== undefined ? options.duration : 4000;
        const c = ensureContainer();

        const toast = document.createElement('div');
        toast.className = 'toast-item toast-' + type;

        const icon = icons[type] || icons.info;

        toast.innerHTML =
            '<i class="' + icon + ' toast-icon"></i>' +
            '<span class="toast-msg">' + message + '</span>' +
            '<button class="toast-close" aria-label="Schließen">&times;</button>' +
            (duration > 0 ? '<div class="toast-progress" style="animation-duration:' + duration + 'ms"></div>' : '');

        c.appendChild(toast);

        // Force reflow then add visible class
        toast.offsetHeight;
        toast.classList.add('toast-visible');

        var closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', function() {
            dismiss(toast);
        });

        if (duration > 0) {
            setTimeout(function() {
                dismiss(toast);
            }, duration);
        }

        return toast;
    };

    function dismiss(toast) {
        if (toast.classList.contains('toast-dismissing')) return;
        toast.classList.add('toast-dismissing');
        toast.addEventListener('animationend', function() {
            toast.remove();
        }, { once: true });
    }

})(window);
