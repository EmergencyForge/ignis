/**
 * Force German date format for all date inputs
 * Converts type="date" to type="text" with intelligent date formatting
 * Supports inputs like "01012000" → "01.01.2000" or "010190" → "01.01.1990"
 * 2-digit year cutoff: dynamic based on current year (e.g. 2026: 00-26 → 20XX, 27-99 → 19XX)
 */
(function () {
  "use strict";

  var currentYearShort = new Date().getFullYear() % 100;

  /**
   * Convert date input to text input with intelligent formatting
   */
  function initDateInput(input) {
    var originalValue = input.value;

    // Convert to text input
    input.type = "text";
    input.placeholder = "TT.MM.JJJJ";
    input.maxLength = 10;
    input.setAttribute("inputmode", "numeric");
    input.setAttribute(
      "pattern",
      "(0[1-9]|[12][0-9]|3[01])\\.(0[1-9]|1[0-2])\\.[0-9]{4}"
    );
    input.setAttribute("data-date-input", "true");

    // Convert existing ISO value (YYYY-MM-DD) to German format
    if (originalValue && originalValue.match(/^\d{4}-\d{2}-\d{2}$/)) {
      var parts = originalValue.split("-");
      input.value = parts[2] + "." + parts[1] + "." + parts[0];
    }

    // Add event listeners
    input.addEventListener("input", handleInput);
    input.addEventListener("blur", handleBlur);
    input.addEventListener("keydown", handleKeydown);
    input.addEventListener("paste", handlePaste);
  }

  /**
   * Handle input event - only filter invalid characters, no auto-formatting
   * Formatting happens on blur
   */
  function handleInput(e) {
    var input = e.target;
    var cursorPos = input.selectionStart;
    var cleaned = input.value.replace(/[^0-9.]/g, "");
    if (cleaned !== input.value) {
      input.value = cleaned;
      input.setSelectionRange(cursorPos, cursorPos);
    }
  }

  /**
   * Handle blur event - finalize formatting.
   *
   * Wichtig: nach dem Formatieren MÜSSEN input UND change Events dispatched werden:
   *   - change → notify.php Autosave speichert den formatierten Wert (statt Roh-Input)
   *   - input  → page-spezifische Listener (z.B. updateAge in rettdaten/index.php),
   *              die am input-Event hängen, können ihre Berechnungen aktualisieren
   *
   * Ohne diese Events sieht notify.php nur den Roh-Input "01012000", den der
   * Server mit 400 ablehnt, und der gleichzeitig den activeRequests-Lock setzt,
   * der das zweite (formatierte) Save blockiert.
   */
  function handleBlur(e) {
    var input = e.target;
    if (!input.value) return;

    var formatted = formatDateValue(input.value);
    if (formatted && formatted !== input.value) {
      input.value = formatted;
      input.dispatchEvent(new Event("input", { bubbles: true }));
      input.dispatchEvent(new Event("change", { bubbles: true }));
    } else if (!formatted) {
      // Invalid format - clear
      console.warn("Invalid date format:", input.value);
    }
  }

  /**
   * Handle keydown for special keys
   */
  function handleKeydown(e) {
    var key = e.key;

    // Allow: backspace, delete, tab, escape, enter, arrows, home, end
    if (
      [
        "Backspace",
        "Delete",
        "Tab",
        "Escape",
        "Enter",
        "ArrowLeft",
        "ArrowRight",
        "Home",
        "End",
      ].includes(key)
    ) {
      return;
    }

    // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
    if (
      (e.ctrlKey || e.metaKey) &&
      ["a", "c", "v", "x"].includes(key.toLowerCase())
    ) {
      return;
    }

    // Allow dot
    if (key === ".") {
      return;
    }

    // Only allow numbers
    if (!/^[0-9]$/.test(key)) {
      e.preventDefault();
    }
  }

  /**
   * Handle paste event
   */
  function handlePaste(e) {
    e.preventDefault();
    var pastedData = (e.clipboardData || window.clipboardData).getData("text");
    var formatted = formatDateValue(pastedData);
    if (formatted) {
      e.target.value = formatted;
      // Trigger input event for autosave
      e.target.dispatchEvent(new Event("input", { bubbles: true }));
    }
  }

  /**
   * Format various date inputs to DD.MM.YYYY
   * Supports: "01012000", "010190", "01.01.2000", "2000-01-01" (ISO), etc.
   */
  function formatDateValue(value) {
    if (!value) return "";

    value = value.trim();

    // Handle ISO format (YYYY-MM-DD) - convert directly
    if (value.match(/^\d{4}-\d{2}-\d{2}$/)) {
      var isoParts = value.split("-");
      return isoParts[2] + "." + isoParts[1] + "." + isoParts[0];
    }

    // Remove any non-digit/dot characters
    var cleaned = value.replace(/[^0-9.]/g, "");

    var day, month, year;

    if (cleaned.includes(".")) {
      // Already has dots: "01.01.2000" or "01.01.90"
      var dotParts = cleaned.split(".");
      if (dotParts.length < 3) return "";
      day = dotParts[0];
      month = dotParts[1];
      year = dotParts[2];
    } else {
      // No dots - pure digits
      if (cleaned.length === 8) {
        // "01012000" → DD MM YYYY
        day = cleaned.substring(0, 2);
        month = cleaned.substring(2, 4);
        year = cleaned.substring(4, 8);
      } else if (cleaned.length === 6) {
        // "010190" → DD MM YY
        day = cleaned.substring(0, 2);
        month = cleaned.substring(2, 4);
        year = cleaned.substring(4, 6);
      } else if (cleaned.length === 4) {
        // "0101" → DD MM (assume current year)
        day = cleaned.substring(0, 2);
        month = cleaned.substring(2, 4);
        year = String(new Date().getFullYear());
      } else {
        return "";
      }
    }

    // Pad day and month
    day = day.padStart(2, "0").substring(0, 2);
    month = month.padStart(2, "0").substring(0, 2);

    // Handle 2-digit year with dynamic cutoff
    if (year.length === 2) {
      var yearNum = parseInt(year, 10);
      if (yearNum <= currentYearShort) {
        year = "20" + year;
      } else {
        year = "19" + year;
      }
    } else if (year.length !== 4) {
      return "";
    }

    // Validate
    var dayInt = parseInt(day, 10);
    var monthInt = parseInt(month, 10);
    var yearInt = parseInt(year, 10);

    if (dayInt < 1 || dayInt > 31) return "";
    if (monthInt < 1 || monthInt > 12) return "";
    if (yearInt < 1900 || yearInt > 2099) return "";

    return day + "." + month + "." + year;
  }

  /**
   * Initialize all existing date inputs
   */
  function initAllDateInputs() {
    var dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(function (input) {
      if (input.getAttribute("data-date-input") === "true") {
        return;
      }
      initDateInput(input);
    });
  }

  /**
   * Watch for dynamically added date inputs
   */
  function observeNewDateInputs() {
    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (node.nodeType === 1) {
            if (node.matches && node.matches('input[type="date"]')) {
              if (node.getAttribute("data-date-input") !== "true") {
                initDateInput(node);
              }
            }
            var childDateInputs =
              node.querySelectorAll &&
              node.querySelectorAll('input[type="date"]');
            if (childDateInputs) {
              childDateInputs.forEach(function (input) {
                if (input.getAttribute("data-date-input") !== "true") {
                  initDateInput(input);
                }
              });
            }
          }
        });
      });
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });
  }

  // Initialize when DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initAllDateInputs();
      observeNewDateInputs();
    });
  } else {
    initAllDateInputs();
    observeNewDateInputs();
  }
})();
