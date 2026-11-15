/**
 * assets/js/pages/design-settings.js: style rows on
 * templates/settings/system/design.php (add, remove, preview).
 *
 * Not an ES module: Vite copies `assets/js/pages/` as is. Without the
 * script the form still saves the rows that are there.
 */
(function () {
    'use strict';

    function init() {
        var form = document.querySelector('[data-design-styles]');
        if (!form || form.dataset.ready) return;
        form.dataset.ready = '1';

        var list = form.querySelector('[data-style-list]');
        var template = form.querySelector('[data-style-template]');
        var next = list.querySelectorAll('[data-style-row]').length;

        // Each row's CSS applies to its own sample only.
        function preview(row) {
            var block = row.querySelector('select').value === 'block';
            row.querySelector('[data-style-sample]').setAttribute('style', (block ? 'display:block;' : '') + row.querySelector('[data-style-css]').value);
        }
        list.querySelectorAll('[data-style-row]').forEach(preview);
        // The dropdown component only fires change, the textarea fires input.
        ['input', 'change'].forEach(function (type) {
            list.addEventListener(type, function (event) {
                var row = event.target.closest('[data-style-row]');
                if (row && event.target.matches('[data-style-css], select')) preview(row);
            });
        });
        list.addEventListener('click', function (event) {
            var remove = event.target.closest('[data-style-remove]');
            if (remove) remove.closest('[data-style-row]').remove();
        });
        form.querySelector('[data-style-add]').addEventListener('click', function () {
            list.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__i__/g, String(next++)));
            list.lastElementChild.querySelector('input').focus();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
