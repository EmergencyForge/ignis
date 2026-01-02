/**
 * Force 24-hour format for all time inputs
 * Converts type="time" to type="text" with intelligent time formatting
 * Supports inputs like "0800" → "08:00" or "8:00" → "08:00"
 */
(function () {
  "use strict";

  /**
   * Convert time input to text input with intelligent formatting
   */
  function initTimeInput(input) {
    // Store original attributes
    const originalValue = input.value;
    const originalName = input.name || input.getAttribute("name");
    const originalId = input.id;
    const originalClasses = input.className;
    const originalRequired = input.required;
    const originalDisabled = input.disabled;
    const originalReadonly = input.readOnly;

    // Convert to text input
    input.type = "text";
    input.placeholder = "HH:MM";
    input.maxLength = 5;
    input.setAttribute("inputmode", "numeric");
    input.setAttribute("pattern", "([01]?[0-9]|2[0-3]):[0-5][0-9]");
    input.setAttribute("data-time-input", "true");

    // Restore original value if exists
    if (originalValue) {
      input.value = formatTimeValue(originalValue);
    }

    // Add event listeners
    input.addEventListener("input", handleInput);
    input.addEventListener("blur", handleBlur);
    input.addEventListener("keydown", handleKeydown);
    input.addEventListener("paste", handlePaste);
  }

  /**
   * Handle input event - format as user types
   */
  function handleInput(e) {
    const input = e.target;
    let value = input.value.replace(/[^0-9]/g, ""); // Remove non-digits
    const cursorPos = input.selectionStart;

    // Don't process if empty
    if (value.length === 0) {
      return;
    }

    // Limit to 4 digits
    if (value.length > 4) {
      value = value.substring(0, 4);
    }

    // Auto-format as user types
    let formattedValue = "";

    if (value.length >= 3) {
      // Has at least HHM - format as HH:MM
      const hours = value.substring(0, 2);
      const minutes = value.substring(2, 4);
      formattedValue = hours + ":" + minutes;
    } else if (value.length >= 1) {
      // Just digits, no colon yet
      formattedValue = value;
    }

    input.value = formattedValue;

    // Try to maintain cursor position
    if (cursorPos === 2 && formattedValue.length === 3) {
      input.setSelectionRange(3, 3); // After colon
    }
  }

  /**
   * Handle blur event - finalize formatting
   */
  function handleBlur(e) {
    const input = e.target;
    if (!input.value) return;

    const formatted = formatTimeValue(input.value);
    if (formatted) {
      input.value = formatted;
    } else {
      // Invalid format - clear or show error
      console.warn("Invalid time format:", input.value);
    }
  }

  /**
   * Handle keydown for special keys
   */
  function handleKeydown(e) {
    const input = e.target;
    const key = e.key;

    // Allow: backspace, delete, tab, escape, enter
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

    // Allow colon
    if (key === ":") {
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
    const pastedData = (e.clipboardData || window.clipboardData).getData(
      "text"
    );
    const formatted = formatTimeValue(pastedData);
    if (formatted) {
      e.target.value = formatted;
    }
  }

  /**
   * Format various time inputs to HH:MM
   * Supports: "0800", "800", "8:00", "08:00", etc.
   */
  function formatTimeValue(value) {
    if (!value) return "";

    value = value.trim();

    // Remove any non-digit/colon characters
    let cleaned = value.replace(/[^0-9:]/g, "");

    // Handle different formats
    let hours = "";
    let minutes = "";

    if (cleaned.includes(":")) {
      // Already has colon: "8:00", "08:00"
      const parts = cleaned.split(":");
      hours = parts[0];
      minutes = parts[1] || "00";
    } else {
      // No colon: "0800", "800", "8"
      if (cleaned.length === 4) {
        // "0800" → "08:00"
        hours = cleaned.substring(0, 2);
        minutes = cleaned.substring(2, 4);
      } else if (cleaned.length === 3) {
        // "800" → "08:00"
        hours = "0" + cleaned.substring(0, 1);
        minutes = cleaned.substring(1, 3);
      } else if (cleaned.length === 2) {
        // "08" → "08:00"
        hours = cleaned;
        minutes = "00";
      } else if (cleaned.length === 1) {
        // "8" → "08:00" (on blur)
        hours = "0" + cleaned;
        minutes = "00";
      } else {
        return "";
      }
    }

    // Pad and validate
    hours = hours.padStart(2, "0").substring(0, 2);
    minutes = minutes.padStart(2, "0").substring(0, 2);

    const hoursInt = parseInt(hours, 10);
    const minutesInt = parseInt(minutes, 10);

    // Validate ranges
    if (hoursInt > 23 || minutesInt > 59) {
      return "";
    }

    return hours + ":" + minutes;
  }

  /**
   * Initialize all existing time inputs
   */
  function initAllTimeInputs() {
    const timeInputs = document.querySelectorAll('input[type="time"]');
    timeInputs.forEach(function (input) {
      // Skip if already initialized
      if (input.getAttribute("data-time-input") === "true") {
        return;
      }
      initTimeInput(input);
    });
  }

  /**
   * Watch for dynamically added time inputs
   */
  function observeNewTimeInputs() {
    const observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (node.nodeType === 1) {
            if (node.matches && node.matches('input[type="time"]')) {
              if (node.getAttribute("data-time-input") !== "true") {
                initTimeInput(node);
              }
            }
            const childTimeInputs =
              node.querySelectorAll &&
              node.querySelectorAll('input[type="time"]');
            if (childTimeInputs) {
              childTimeInputs.forEach(function (input) {
                if (input.getAttribute("data-time-input") !== "true") {
                  initTimeInput(input);
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
      initAllTimeInputs();
      observeNewTimeInputs();
    });
  } else {
    initAllTimeInputs();
    observeNewTimeInputs();
  }
})();
