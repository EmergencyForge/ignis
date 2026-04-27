/**
 * Shared Dienstnummer availability check
 * Used on both create and profile edit pages.
 *
 * Usage:
 *   initDienstnrCheck({ basePath: '/intra/', excludeId: 0 });
 *
 * Requires:
 *   - Input#dienstnr
 *   - #dienstnr-status (icon container)
 *   - #dienstnr-feedback (text feedback)
 *   - jQuery (for AJAX)
 */
(function(window) {
    'use strict';

    var delayTimer;
    var isDienstnrAvailable = false;

    window.isDienstnrAvailable = function() { return isDienstnrAvailable; };

    window.initDienstnrCheck = function(opts) {
        opts = opts || {};
        var basePath = opts.basePath || '/';
        var excludeId = opts.excludeId || 0;

        var dienstnrInput = document.getElementById('dienstnr');
        if (!dienstnrInput) return;

        // Check on input
        dienstnrInput.addEventListener('input', function() {
            checkDienstnrAvailability(basePath, excludeId);
        });

        // Initial check if field has value
        if (dienstnrInput.value.trim()) {
            checkDienstnrAvailability(basePath, excludeId);
        }
    };

    function checkDienstnrAvailability(basePath, excludeId) {
        clearTimeout(delayTimer);

        var dienstnrInput = document.getElementById('dienstnr');
        var statusElement = document.getElementById('dienstnr-status');
        var feedbackElement = document.getElementById('dienstnr-feedback');
        var dienstnr = dienstnrInput.value.trim();

        dienstnrInput.classList.remove('valid', 'invalid');
        if (feedbackElement) feedbackElement.style.display = 'none';
        if (statusElement) {
            statusElement.innerHTML = '';
            statusElement.className = 'dienstnr-status';
        }

        if (!dienstnr) {
            isDienstnrAvailable = false;
            return;
        }

        var hasNumber = /[0-9]/.test(dienstnr);
        var validFormat = /^[A-Za-z0-9\-]+$/.test(dienstnr);

        if (!validFormat || !hasNumber) {
            if (statusElement) {
                statusElement.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                statusElement.classList.add('unavailable');
            }
            dienstnrInput.classList.add('invalid');
            if (feedbackElement) {
                feedbackElement.textContent = !hasNumber
                    ? 'Dienstnummer muss mindestens eine Zahl enthalten (z.B. RD-001, BF01).'
                    : 'Nur Buchstaben, Zahlen und Bindestriche erlaubt.';
                feedbackElement.style.display = 'block';
            }
            isDienstnrAvailable = false;
            return;
        }

        if (statusElement) {
            statusElement.innerHTML = '<div class="spinner"></div>';
            statusElement.classList.add('loading');
        }

        delayTimer = setTimeout(function() {
            var postData = { dienstnr: dienstnr };
            if (excludeId) postData.exclude_id = excludeId;

            $.ajax({
                url: basePath + 'api/personnel/check-dienstnr-legacy',
                method: 'POST',
                data: postData,
                dataType: 'text',
                success: function(response) {
                    if (statusElement) statusElement.classList.remove('loading');
                    response = response.trim();

                    if (response === 'exists') {
                        if (statusElement) {
                            statusElement.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                            statusElement.classList.add('unavailable');
                        }
                        dienstnrInput.classList.add('invalid');
                        if (feedbackElement) {
                            feedbackElement.textContent = 'Diese Dienstnummer ist bereits vergeben.';
                            feedbackElement.style.display = 'block';
                        }
                        isDienstnrAvailable = false;
                    } else if (response === 'not_exists') {
                        if (statusElement) {
                            statusElement.innerHTML = '<i class="fa-solid fa-check"></i>';
                            statusElement.classList.add('available');
                        }
                        dienstnrInput.classList.add('valid');
                        isDienstnrAvailable = true;
                    } else {
                        if (statusElement) {
                            statusElement.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                            statusElement.classList.add('unavailable');
                        }
                        dienstnrInput.classList.add('invalid');
                        if (feedbackElement) {
                            feedbackElement.textContent = 'Ungültiges Format.';
                            feedbackElement.style.display = 'block';
                        }
                        isDienstnrAvailable = false;
                    }
                },
                error: function(xhr, status, error) {
                    if (statusElement) {
                        statusElement.classList.remove('loading');
                        statusElement.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
                        statusElement.classList.add('unavailable');
                    }
                    if (feedbackElement) {
                        feedbackElement.textContent = 'Verbindungsfehler: ' + xhr.status;
                        feedbackElement.style.display = 'block';
                    }
                    isDienstnrAvailable = false;
                }
            });
        }, 500);
    }

})(window);
