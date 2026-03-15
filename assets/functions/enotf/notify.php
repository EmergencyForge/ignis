<div id="toast-container"></div>
<script>
    $(document).ready(function() {
        const enr = <?= json_encode($enr) ?>;

        $('input[name="psych[]"]').addClass('medikament-field-ignore');
        $('input[name="ebesonderheiten[]"]').addClass('medikament-field-ignore');
        $('input[name="rettungstechnik[]"]').addClass('medikament-field-ignore');
        $('input[type="checkbox"][data-quickfill], input[type="checkbox"][data-quickclear]').addClass('medikament-field-ignore');
        $('input[id$="_datum"]').addClass('medikament-field-ignore');

        const inputElements = $(
            "form[name='form'] input:not([readonly]):not([disabled]):not(.medikament-field-ignore):not([data-ignore-autosave]), " +
            "form[name='form'] select:not([readonly]):not([disabled]):not(.medikament-field-ignore):not([data-ignore-autosave]), " +
            "form[name='form'] textarea:not([readonly]):not([disabled]):not(.medikament-field-ignore):not([data-ignore-autosave])"
        );
        const activeRequests = {};

        const zeroIsValid = [
            'patsex', 'awsicherung_neu', 'b_symptome',
            'b_auskult', 'b_beatmung', 'c_kreislauf', 'c_ekg', 'c_zugang',
            'd_bewusstsein', 'd_ex_1', 'd_pupillenw_1', 'd_pupillenw_2',
            'd_lichtreakt_1', 'd_lichtreakt_2', 'd_gcs_1', 'd_gcs_2', 'd_gcs_3', 'v_muster_k', 'v_muster_t',
            'v_muster_a', 'v_muster_al', 'v_muster_bl', 'v_muster_w', 'transportziel', 'medis',
            'naca_initial', 'naca_uebergabe'
        ];

        inputElements.each(function() {
            $(this).data('original-value', $(this).val());
        });

        // Nav data-requires dynamisch aus CONDITIONS aktualisieren (global für field_checks.php)
        window.updateNavRequires = updateNavRequires;
        function updateNavRequires(tz) {
            if (typeof CONDITIONS === 'undefined') return;
            var sectionMap = {1: 'stammdaten', 2: 'erstbefund', 3: 'anamnese', 4: 'diagnose', 6: 'massnahmen', 7: 'abschluss'};
            var overrides = CONDITIONS.overrides[String(tz)] || [];
            var additions = CONDITIONS.additions[String(tz)] || {};

            // Pro Section die aktiven DB-Spalten sammeln
            var sectionDbCols = {};
            for (var key in CONDITIONS.base) {
                if (overrides.indexOf(key) !== -1) continue;
                var rule = CONDITIONS.base[key];
                var sec = rule.section;
                if (!sectionDbCols[sec]) sectionDbCols[sec] = [];
                var dbCols = rule.db || [];
                for (var i = 0; i < dbCols.length; i++) {
                    if (sectionDbCols[sec].indexOf(dbCols[i]) === -1) {
                        sectionDbCols[sec].push(dbCols[i]);
                    }
                }
            }
            for (var addKey in additions) {
                var addRule = additions[addKey];
                var addSec = addRule.section;
                if (!sectionDbCols[addSec]) sectionDbCols[addSec] = [];
                var addDb = addRule.db || [];
                for (var j = 0; j < addDb.length; j++) {
                    if (sectionDbCols[addSec].indexOf(addDb[j]) === -1) {
                        sectionDbCols[addSec].push(addDb[j]);
                    }
                }
            }

            // data-requires auf Nav-Links setzen
            for (var section in sectionMap) {
                var page = sectionMap[section];
                var $link = $('#edivi__nidanav a[data-page="' + page + '"]');
                if ($link.length) {
                    var cols = sectionDbCols[section] || [];
                    if (cols.length > 0) {
                        $link.attr('data-requires', cols.join(','));
                        $link.removeClass('edivi__nidanav-nocheck');
                    } else {
                        $link.removeAttr('data-requires');
                        $link.removeClass('edivi__nidanav-unfilled edivi__nidanav-partfilled edivi__nidanav-filled');
                        $link.addClass('edivi__nidanav-nocheck');
                    }
                }
            }
        }

        window.updateNavFillStates = updateNavFillStates;
        function updateNavFillStates(data) {
            $("#edivi__nidanav a[data-requires]").each(function() {
                const $link = $(this);
                const requiresRaw = $link[0].dataset.requires;
                if (!requiresRaw) return;

                const groups = requiresRaw.split(",");
                let filledGroups = 0;

                groups.forEach(group => {
                    const options = group.split("|").map(key => key.trim());
                    const isGroupFilled = options.some(field => {
                        const val = data[field];
                        return (
                            val !== null &&
                            typeof val !== "undefined" &&
                            (val !== "" && (val !== 0 || zeroIsValid.includes(field)))
                        );
                    });
                    if (isGroupFilled) filledGroups++;
                });

                const totalGroups = groups.length;
                const isFullyFilled = filledGroups === totalGroups;
                const isPartiallyFilled = filledGroups > 0 && filledGroups < totalGroups;

                $link
                    .toggleClass("edivi__nidanav-filled", isFullyFilled)
                    .toggleClass("edivi__nidanav-partfilled", isPartiallyFilled)
                    .toggleClass("edivi__nidanav-unfilled", filledGroups === 0);
            });

            // Nav-Links ohne data-requires → nocheck
            $("#edivi__nidanav a:not([data-requires])").each(function() {
                const $link = $(this);
                $link.removeClass('edivi__nidanav-unfilled edivi__nidanav-partfilled edivi__nidanav-filled');
            });
        }

        window.validateLinks = validateLinks;
        function validateLinks() {
            // Aktive DB-Spalten aus Conditions berechnen
            var activeDbCols = null;
            if (typeof _enotfGetActiveDbCols === 'function' && typeof _enotfCurrentTransportziel !== 'undefined') {
                activeDbCols = _enotfGetActiveDbCols(_enotfCurrentTransportziel);
            }

            $("[class*='edivi__interactbutton'] a[data-requires]").each(function() {
                const $link = $(this);
                const requirements = $link.data("requires") || $link.attr("data-requires");

                if (requirements && requirements !== "" && !$link.hasClass("edivi__validation-ignore")) {
                    // Nur aktive DB-Spalten validieren (wenn Conditions verfügbar)
                    var filteredReqs = requirements;
                    if (activeDbCols) {
                        var groups = requirements.split(',');
                        var activeGroups = [];
                        for (var i = 0; i < groups.length; i++) {
                            var fields = groups[i].split('|');
                            var anyActive = false;
                            for (var j = 0; j < fields.length; j++) {
                                if (activeDbCols[fields[j].trim()]) {
                                    anyActive = true;
                                    break;
                                }
                            }
                            if (anyActive) activeGroups.push(groups[i]);
                        }
                        filteredReqs = activeGroups.join(',');
                    }

                    $link.removeClass("edivi__validation-green edivi__validation-red edivi__validation-yellow");

                    if (!filteredReqs) {
                        // Alle Requirements sind durch Conditions deaktiviert → keine Farbe
                        return;
                    } else {
                        const validationResult = validateRequirements(filteredReqs);
                        if (validationResult === true) {
                            $link.addClass("edivi__validation-green");
                        } else if (validationResult === 'partial') {
                            $link.addClass("edivi__validation-yellow");
                        } else {
                            $link.addClass("edivi__validation-red");
                        }
                    }
                }
            });
        }

        function validateRequirements(requirements) {
            if (!requirements || requirements === "") return true;

            const groups = requirements.split(',');
            let allGroupsValid = true;
            let anyGroupValid = false;

            for (let group of groups) {
                const fields = group.split('|');
                let groupValid = false;

                for (let field of fields) {
                    const fieldName = field.trim();
                    const fieldValue = getFieldValue(fieldName);
                    console.log("Checking field:", fieldName, "Value:", fieldValue);

                    if (fieldValue !== null &&
                        fieldValue !== undefined &&
                        fieldValue !== '' &&
                        (fieldValue !== 0 || zeroIsValid.includes(fieldName)) &&
                        (fieldValue !== '0' || zeroIsValid.includes(fieldName))) {
                        groupValid = true;
                        break;
                    }
                }

                if (groupValid) {
                    anyGroupValid = true;
                } else {
                    allGroupsValid = false;
                }

                console.log("Group:", group, "Valid:", groupValid);
            }

            if (allGroupsValid) {
                return true;
            } else if (anyGroupValid) {
                return 'partial';
            } else {
                return false;
            }
        }

        function getFieldValue(fieldName) {
            try {
                return window.__dynamicDaten[fieldName];
            } catch (e) {
                console.error("Error getting field value:", e);
                return null;
            }
        }

        window.__dynamicDaten = <?= json_encode($daten) ?>;
        updateNavFillStates(window.__dynamicDaten);
        validateLinks();

        function showToast(message, type = 'success') {
            var timeouts = { success: 1500, error: 8000, warning: 5000, info: 4000 };
            var timeout = timeouts[type] || 4000;

            var $container = $('#toast-container');
            while ($container.children().length >= 5) {
                var $oldest = $container.children().first();
                $oldest.remove();
            }

            var $toast = $('<div class="enotf-toast enotf-toast--' + type + '">' +
                '<span class="enotf-toast__dot"></span>' +
                '<span class="enotf-toast__text"></span>' +
                '<span class="enotf-toast__close"><i class="fa-solid fa-xmark"></i></span>' +
            '</div>');

            $toast.find('.enotf-toast__text').text(message);

            $toast.find('.enotf-toast__close').on('click', function() {
                dismissToast($toast);
            });

            $container.append($toast);

            if (timeout > 0) {
                var timerId = setTimeout(function() { dismissToast($toast); }, timeout);
                $toast.data('timer', timerId);

                $toast.on('mouseenter', function() {
                    clearTimeout($toast.data('timer'));
                });
                $toast.on('mouseleave', function() {
                    $toast.data('timer', setTimeout(function() { dismissToast($toast); }, timeout));
                });
            }
        }

        function dismissToast($toast) {
            if ($toast.hasClass('enotf-toast--leaving')) return;
            $toast.addClass('enotf-toast--leaving');
            setTimeout(function() { $toast.remove(); }, 200);
        }

        window.showToast = showToast;

        // Toast-Queue fuer Batching (Quick-Fill etc.)
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
                        showToast(messages[0], type);
                    } else {
                        var summary = type === 'success'
                            ? messages.length + ' Felder gespeichert'
                            : messages.length + ' Fehler aufgetreten';
                        showToast(summary, type);
                    }
                });

                this.pending = [];
                this.timer = null;
            }
        };

        window.ToastQueue = ToastQueue;

        const exclusiveValues = [1, 98, 99];

        $('input[name="psych[]"]').on('change', function() {
            const $clicked = $(this);
            const clickedValue = parseInt($clicked.val());

            if (exclusiveValues.includes(clickedValue)) {
                if ($clicked.is(':checked')) {
                    $('input[name="psych[]"]').not($clicked).prop('checked', false);
                }
            } else {
                if ($clicked.is(':checked')) {
                    exclusiveValues.forEach(val => {
                        $('input[name="psych[]"][value="' + val + '"]').prop('checked', false);
                    });
                }
            }

            const selectedValues = [];
            $('input[name="psych[]"]:checked').each(function() {
                selectedValues.push(parseInt($(this).val()));
            });

            const jsonValue = selectedValues.length > 0 ? JSON.stringify(selectedValues) : null;

            console.log('Saving psych field with values:', selectedValues, 'as JSON:', jsonValue);

            $.ajax({
                url: '<?= BASE_PATH ?>api/enotf/save-fields.php',
                type: 'POST',
                data: {
                    enr: enr,
                    field: 'psych',
                    value: jsonValue
                },
                success: function(response) {
                    showToast("Feld gespeichert", 'success');

                    window.__dynamicDaten['psych'] = jsonValue;
                    updateNavFillStates(window.__dynamicDaten);
                    validateLinks();
                    updateQuickFillCheckboxes();
                },
                error: function(xhr, status, error) {
                    console.error('Error saving psych field:', xhr.responseText);
                    showToast("Fehler beim Speichern", 'error');
                }
            });
        });

        $('input[name="ebesonderheiten[]"]').on('change', function() {
            const $clicked = $(this);
            const clickedValue = parseInt($clicked.val());

            if (clickedValue === 1) {
                if ($clicked.is(':checked')) {
                    $('input[name="ebesonderheiten[]"]').not($clicked).prop('checked', false);
                }
            } else {
                if ($clicked.is(':checked')) {
                    $('input[name="ebesonderheiten[]"][value="1"]').prop('checked', false);
                }
            }

            const selectedValues = [];
            $('input[name="ebesonderheiten[]"]:checked').each(function() {
                selectedValues.push(parseInt($(this).val()));
            });

            const jsonValue = selectedValues.length > 0 ? JSON.stringify(selectedValues) : null;

            console.log('Saving field: ebesonderheiten[] value:', jsonValue);

            $.ajax({
                url: '<?= BASE_PATH ?>api/enotf/save-fields.php',
                type: 'POST',
                data: {
                    enr: enr,
                    field: 'ebesonderheiten',
                    value: jsonValue
                },
                success: function(response) {
                    showToast("Feld gespeichert", 'success');

                    window.__dynamicDaten['ebesonderheiten'] = jsonValue;
                    updateNavFillStates(window.__dynamicDaten);
                    validateLinks();
                    updateQuickFillCheckboxes();
                },
                error: function(xhr, status, error) {
                    console.error('Error saving ebesonderheiten field:', xhr.responseText);
                    showToast("Fehler beim Speichern", 'error');
                }
            });
        });

        $('input[name="rettungstechnik[]"]').on('change', function() {
            const $clicked = $(this);
            const clickedValue = parseInt($clicked.val());

            if (clickedValue === 1) {
                if ($clicked.is(':checked')) {
                    $('input[name="rettungstechnik[]"]').not($clicked).prop('checked', false);
                }
            } else {
                if ($clicked.is(':checked')) {
                    $('input[name="rettungstechnik[]"][value="1"]').prop('checked', false);
                }
            }

            const selectedValues = [];
            $('input[name="rettungstechnik[]"]:checked').each(function() {
                selectedValues.push(parseInt($(this).val()));
            });

            const jsonValue = selectedValues.length > 0 ? JSON.stringify(selectedValues) : null;

            console.log('Saving field: rettungstechnik[] value:', jsonValue);

            $.ajax({
                url: '<?= BASE_PATH ?>api/enotf/save-fields.php',
                type: 'POST',
                data: {
                    enr: enr,
                    field: 'rettungstechnik',
                    value: jsonValue
                },
                success: function(response) {
                    showToast("Feld gespeichert", 'success');

                    window.__dynamicDaten['rettungstechnik'] = jsonValue;
                    updateNavFillStates(window.__dynamicDaten);
                    validateLinks();
                    updateQuickFillCheckboxes();
                },
                error: function(xhr, status, error) {
                    console.error('Error saving rettungstechnik field:', xhr.responseText);
                    showToast("Fehler beim Speichern", 'error');
                }
            });
        });

        $('input.btn-check[type="checkbox"]').on('change', function() {
            const clicked = $(this);
            const clickedId = clicked.attr('id');
            const base = clickedId.split('_')[0];

            if (clicked.attr('name') === 'psych[]') {
                return;
            }

            if (clicked.attr('name') === 'ebesonderheiten[]') {
                return;
            }

            if (clicked.attr('name') === 'rettungstechnik[]') {
                return;
            }

            const group = $('input.btn-check[type="checkbox"]').filter(function() {
                return $(this).attr('id')?.startsWith(base + '_');
            });

            group.each(function() {
                const $box = $(this);
                if ($box[0] !== clicked[0]) {
                    if ($box.is(':checked')) {
                        $box.prop('checked', false).trigger('change');
                    }
                }
            });

            clicked.trigger('blur');
        });

        inputElements.off('change blur').on('change blur', function(e) {
            const $this = $(this);
            const fieldName = $this.attr('name');
            const elementId = $this.attr('id');

            if (fieldName === 'psych[]') {
                console.log('Skipping auto-save for psych[] - handled by custom handler');
                return;
            }

            if (fieldName === 'ebesonderheiten[]') {
                console.log('Skipping auto-save for ebesonderheiten[] - handled by custom handler');
                return;
            }

            if (fieldName === 'rettungstechnik[]') {
                console.log('Skipping auto-save for rettungstechnik[] - handled by custom handler');
                return;
            }

            if (fieldName === 'diagnose_weitere[]') {
                console.log('Skipping auto-save for diagnose_weitere[] - handled by custom handler');
                return;
            }

            if (elementId === 'c_zugang-0') {
                return;
            }

            if ($this.hasClass('zugang-checkbox')) {
                return;
            }

            let currentValue;

            if ($this.is(':radio')) {
                currentValue = $('input[name="' + fieldName + '"]:checked').val();
            } else if ($this.is(':checkbox')) {
                currentValue = $this.is(':checked') ? 1 : 0;
            } else {
                currentValue = $this.val();
            }

            if ($this.is(':radio')) {
                const savedValue = window.__dynamicDaten[fieldName];
                console.log('Radio check:', fieldName, 'currentValue:', currentValue, 'savedValue:', savedValue, 'types:', typeof currentValue, typeof savedValue);

                if (String(currentValue) === String(savedValue)) {
                    console.log('Radio value unchanged, skipping save');
                    return;
                }
            } else {
                const originalValue = $this.data('original-value');
                if (!$this.hasClass('btn-check') && currentValue == originalValue) return;
            }

            if (!activeRequests[fieldName]) {
                activeRequests[fieldName] = true;

                console.log('Saving field:', fieldName, 'value:', currentValue);

                $.ajax({
                    url: '<?= BASE_PATH ?>api/enotf/save-fields.php',
                    type: 'POST',
                    data: {
                        enr: enr,
                        field: fieldName,
                        value: currentValue
                    },
                    success: function(response) {
                        showToast("Feld gespeichert", 'success');

                        $('input[name="' + fieldName + '"]').data('original-value', currentValue);

                        window.__dynamicDaten[fieldName] = currentValue;
                        console.log('Updated __dynamicDaten[' + fieldName + ']:', currentValue);

                        // Bei Versorgung-Änderung: komplettes Re-Apply (Conditions + Nav + Groups)
                        if (fieldName === 'transportziel') {
                            if (typeof enotfReapplyAll === 'function') {
                                enotfReapplyAll(currentValue);
                            }
                        }

                        updateNavFillStates(window.__dynamicDaten);
                        validateLinks();
                        updateQuickFillCheckboxes();
                    },
                    error: function() {
                        showToast("Fehler beim Speichern", 'error');
                    },
                    complete: function() {
                        activeRequests[fieldName] = false;
                    }
                });
            }
        });

        $(document).on('change', 'input, select, textarea', function() {
            setTimeout(function() {
                validateLinks();
            }, 50);
        });

        $('#final').on('click', function(e) {
            e.preventDefault();

            const plausibilityContent = document.getElementById('plausibility');
            if (plausibilityContent && plausibilityContent.innerText.trim().length > 0) {
                showToast("Abschluss nicht möglich: Plausibilitätsprüfung nicht bestanden", 'error');
                return;
            }

            const pfname = <?= json_encode($daten['pfname']) ?>;
            if (!pfname || pfname.trim() === "") {
                showToast("Kein Protokollant angegeben", 'error');
                return;
            }

            $(this).prop('disabled', true);

            $.ajax({
                url: '<?= BASE_PATH ?>api/enotf/save-fields.php',
                type: 'POST',
                data: {
                    enr: enr,
                    field: 'freigeber',
                    value: pfname
                },
                success: function(response) {
                    if (response.includes("erfolgreich")) {
                        window.location.href = "<?= BASE_PATH ?>enotf/protokoll/index.php?enr=" + enr;
                    } else {
                        showToast(response, 'error');
                        $('#final').prop('disabled', false);
                    }
                },
                error: function() {
                    showToast("Fehler beim Abschließen", 'error');
                    $('#final').prop('disabled', false);
                }
            });
        });

        function checkQuickFillStatus($checkbox) {
            try {
                const quickFillData = JSON.parse($checkbox.attr('data-quickfill'));

                if (!quickFillData || typeof quickFillData !== 'object') {
                    return false;
                }

                let allMatch = true;

                Object.entries(quickFillData).forEach(([fieldName, expectedValue]) => {
                    const savedValue = window.__dynamicDaten[fieldName];

                    if (String(savedValue) !== String(expectedValue)) {
                        allMatch = false;
                    }
                });

                return allMatch;
            } catch (e) {
                console.error('Error checking quickfill status:', e);
                return false;
            }
        }

        function updateQuickFillCheckboxes() {
            $('input[type="checkbox"][data-quickfill]').each(function() {
                const $checkbox = $(this);
                const shouldBeChecked = checkQuickFillStatus($checkbox);

                if (shouldBeChecked !== $checkbox.is(':checked')) {
                    $checkbox.prop('checked', shouldBeChecked);
                }
            });
        }

        updateQuickFillCheckboxes();

        $('input[type="checkbox"][data-quickfill]').on('change', function(e) {
            e.stopPropagation();

            const $checkbox = $(this);
            const isChecked = $checkbox.is(':checked');

            try {
                const quickFillData = JSON.parse($checkbox.attr('data-quickfill'));

                if (!quickFillData || typeof quickFillData !== 'object') {
                    console.error('Invalid quickfill data format');
                    return;
                }

                const labelText = $('label[for="' + $checkbox.attr('id') + '"]').text().trim() || 'Quick-Fill';

                if (isChecked) {
                    const fieldsToSave = [];

                    Object.entries(quickFillData).forEach(([fieldName, fieldValue]) => {
                        const $field = $('[name="' + fieldName + '"]').first();

                        if ($field.length === 0) {
                            const savedValue = window.__dynamicDaten[fieldName];

                            console.log('Field:', fieldName, 'Saved:', savedValue, 'Target:', fieldValue);

                            if (String(savedValue) !== String(fieldValue)) {
                                fieldsToSave.push({
                                    name: fieldName,
                                    value: fieldValue,
                                    element: null
                                });
                            }
                            return;
                        }

                        let currentValue;
                        if ($field.is(':radio')) {
                            const $checked = $('[name="' + fieldName + '"]:checked');
                            currentValue = $checked.length > 0 ? $checked.val() : null;
                        } else if ($field.is(':checkbox')) {
                            currentValue = $field.is(':checked') ? 1 : 0;
                        } else {
                            currentValue = $field.val();
                            if (currentValue === '') {
                                currentValue = null;
                            }
                        }

                        const savedValue = window.__dynamicDaten[fieldName];
                        const valueToCompare = currentValue !== null ? currentValue : savedValue;

                        console.log('Field:', fieldName, 'Current:', currentValue, 'Saved:', savedValue, 'Target:', fieldValue);

                        const currentIsEmpty = valueToCompare === null || valueToCompare === undefined || valueToCompare === '';
                        const targetIsEmpty = fieldValue === null || fieldValue === undefined || fieldValue === '';

                        if (currentIsEmpty && targetIsEmpty) {
                            return;
                        }

                        if (String(valueToCompare) !== String(fieldValue)) {
                            fieldsToSave.push({
                                name: fieldName,
                                value: fieldValue,
                                element: $field
                            });
                        }
                    });

                    if (fieldsToSave.length === 0) {
                        showToast("Alle Felder bereits korrekt gesetzt", 'success');
                        return;
                    }

                    let savePromises = [];

                    fieldsToSave.forEach(field => {
                        const promise = $.ajax({
                            url: '<?= BASE_PATH ?>api/enotf/save-fields.php',
                            type: 'POST',
                            data: {
                                enr: enr,
                                field: field.name,
                                value: field.value
                            }
                        }).done(function() {
                            if (field.element && field.element.length > 0) {
                                if (field.element.is(':radio')) {
                                    $('[name="' + field.name + '"][value="' + field.value + '"]').prop('checked', true).trigger('change');
                                } else if (field.element.is(':checkbox')) {
                                    field.element.prop('checked', field.value == 1).trigger('change');
                                } else {
                                    field.element.val(field.value).trigger('change');
                                }

                                $('[name="' + field.name + '"]').data('original-value', field.value);
                            }

                            window.__dynamicDaten[field.name] = field.value;
                        });

                        savePromises.push(promise);
                    });

                    $.when.apply($, savePromises)
                        .done(function() {
                            showToast(fieldsToSave.length + " Felder gespeichert", 'success');

                            updateNavFillStates(window.__dynamicDaten);
                            validateLinks();

                            fieldsToSave.forEach(field => {
                                if (field.element && field.element.length > 0) {
                                    field.element.trigger('input');
                                }
                            });

                            updateQuickFillCheckboxes();
                        })
                        .fail(function(xhr) {
                            showToast("Fehler beim Speichern", 'error');
                            $checkbox.prop('checked', false);
                        });

                } else {

                }

            } catch (e) {
                console.error('Error in quickfill handler:', e);
                showToast("Fehler beim Verarbeiten der Quick-Fill Daten", 'error');
                $checkbox.prop('checked', false);
            }
        });

        $(document).on('change', 'input, select, textarea', function() {
            setTimeout(function() {
                updateQuickFillCheckboxes();
            }, 100);
        });
    });
</script>