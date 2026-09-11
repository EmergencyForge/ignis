"use strict";

/**
 * eNOTF Custom Dropdown Component
 * Converts standard select elements into custom styled dropdowns with optional search functionality
 *
 * Usage:
 * 1. Add data-custom-dropdown="true" to select elements
 * 2. Set data-search-threshold="10" to enable search for dropdowns with more than 10 options (default: 10)
 * 3. Call eNOTFCustomDropdown.init() when DOM is ready
 */
var eNOTFCustomDropdown = {
  // Configuration
  config: {
    defaultSearchThreshold: 10,
    // Auto-enable search for dropdowns with more than X options
    dropdownClass: "enotf-custom-dropdown",
    containerClass: "enotf-dropdown-container",
    wrapperClass: "enotf-dropdown-wrapper",
    searchClass: "enotf-dropdown-search",
    optionsClass: "enotf-dropdown-options",
    optionClass: "enotf-dropdown-option",
    selectedClass: "selected",
    openClass: "open",
    disabledClass: "disabled"
  },
  // Store all custom dropdown instances
  instances: [],

  /**
   * Initialize all custom dropdowns on the page
   */
  init: function init() {
    var _this = this;

    var selects = document.querySelectorAll('select[data-custom-dropdown="true"]');
    selects.forEach(function (select) {
      // Skip if already initialized
      if (select.dataset.dropdownInitialized) {
        return;
      } // Skip GCS selects (they have display:none and are for system calculation only)


      if (select.classList.contains("gcs-select")) {
        return;
      } // Skip selects that are already hidden


      var computedStyle = window.getComputedStyle(select);

      if (computedStyle.display === "none" || select.style.display === "none") {
        return;
      }

      _this.createCustomDropdown(select);
    });
  },

  /**
   * Create a custom dropdown from a select element
   */
  createCustomDropdown: function createCustomDropdown(originalSelect) {
    var _this2 = this;

    // Mark as initialized
    originalSelect.dataset.dropdownInitialized = "true"; // Get configuration

    var searchThreshold = parseInt(originalSelect.dataset.searchThreshold) || this.config.defaultSearchThreshold;
    var optionsCount = originalSelect.options.length;
    var enableSearch = optionsCount > searchThreshold; // Create wrapper container

    var wrapper = document.createElement("div");
    wrapper.className = this.config.wrapperClass;
    wrapper.style.position = "relative";
    wrapper.style.display = "block"; // Insert wrapper before original select

    originalSelect.parentNode.insertBefore(wrapper, originalSelect); // Hide original select but keep it in the DOM for form submission

    originalSelect.style.display = "none";
    originalSelect.style.visibility = "hidden";
    wrapper.appendChild(originalSelect); // Create custom dropdown container

    var container = document.createElement("div");
    container.className = "".concat(this.config.containerClass, " form-select"); // Copy classes from original select (excluding form-select which we already added)

    var selectClasses = Array.from(originalSelect.classList).filter(function (cls) {
      return cls !== "form-select";
    });
    selectClasses.forEach(function (cls) {
      return container.classList.add(cls);
    }); // Copy specific inline styles but NOT display/visibility

    var computedStyle = window.getComputedStyle(originalSelect);

    if (originalSelect.style.backgroundColor) {
      container.style.backgroundColor = originalSelect.style.backgroundColor;
    }

    if (originalSelect.style.color) {
      container.style.color = originalSelect.style.color;
    } // Ensure container is always visible and properly displayed


    container.style.display = "flex";
    container.style.visibility = "visible";
    container.tabIndex = 0; // Setup observer to sync classes (especially edivi__input-check and edivi__input-checked)

    var classObserver = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        if (mutation.attributeName === "class") {
          // Sync edivi__input-check and edivi__input-checked classes
          if (originalSelect.classList.contains("edivi__input-checked")) {
            container.classList.add("edivi__input-checked");
            container.classList.remove("edivi__input-check");
          } else if (originalSelect.classList.contains("edivi__input-check")) {
            container.classList.add("edivi__input-check");
            container.classList.remove("edivi__input-checked");
          } else {
            // Remove both if neither class is present on select
            container.classList.remove("edivi__input-check", "edivi__input-checked");
          }
        }
      });
    });
    classObserver.observe(originalSelect, {
      attributes: true,
      attributeFilter: ["class"]
    }); // Initial sync of edivi__input-check/checked classes

    if (originalSelect.classList.contains("edivi__input-checked")) {
      container.classList.add("edivi__input-checked");
    } else if (originalSelect.classList.contains("edivi__input-check")) {
      container.classList.add("edivi__input-check");
    } // Create display value element


    var displayValue = document.createElement("div");
    displayValue.className = "enotf-dropdown-display";
    displayValue.textContent = this.getSelectedText(originalSelect) || originalSelect.dataset.placeholder || "Bitte wählen...";
    container.appendChild(displayValue); // Create dropdown arrow

    var arrow = document.createElement("i");
    arrow.className = "enotf-dropdown-arrow fa-solid fa-chevron-down";
    container.appendChild(arrow); // Create dropdown options container

    var optionsContainer = document.createElement("div");
    optionsContainer.className = this.config.dropdownClass;
    optionsContainer.style.display = "none"; // Add search input if needed

    if (enableSearch) {
      var searchInput = document.createElement("input");
      searchInput.type = "text";
      searchInput.className = "".concat(this.config.searchClass, " form-control");
      searchInput.placeholder = "Suchen...";
      searchInput.style.cssText = "margin-bottom: 8px; background-color: #333; color: white; border: 1px solid #555;";
      searchInput.autocomplete = "off";
      optionsContainer.appendChild(searchInput); // Add search event listener

      searchInput.addEventListener("input", function (e) {
        _this2.filterOptions(optionsContainer, e.target.value);
      }); // Prevent dropdown from closing when clicking search input

      searchInput.addEventListener("click", function (e) {
        e.stopPropagation();
      }); // Focus search input when dropdown opens

      container.addEventListener("click", function () {
        if (optionsContainer.style.display === "block") {
          setTimeout(function () {
            return searchInput.focus();
          }, 50);
        }
      });
    } // Create options list


    var optionsList = document.createElement("div");
    optionsList.className = this.config.optionsClass;
    this.populateOptions(originalSelect, optionsList);
    optionsContainer.appendChild(optionsList);
    wrapper.appendChild(container);
    wrapper.appendChild(optionsContainer); // Add event listeners

    this.addEventListeners(container, optionsContainer, originalSelect, displayValue, optionsList); // Handle disabled state

    if (originalSelect.disabled || originalSelect.hasAttribute("readonly")) {
      container.classList.add(this.config.disabledClass);
      container.style.pointerEvents = "none";
      container.style.opacity = "0.6";
    } // Setup validation reflection


    this.setupValidation(originalSelect, container); // Store instance

    this.instances.push({
      select: originalSelect,
      wrapper: wrapper,
      container: container,
      optionsContainer: optionsContainer,
      displayValue: displayValue,
      optionsList: optionsList
    });
    return wrapper;
  },

  /**
   * Setup validation state reflection
   */
  setupValidation: function setupValidation(select, container) {
    // Function to update validation state
    var updateValidationState = function updateValidationState() {
      // Check if select is invalid
      if (select.validity && !select.validity.valid) {
        container.classList.add("is-invalid");
        container.classList.remove("is-valid");
      } else if (select.validity && select.validity.valid && select.value !== "") {
        container.classList.add("is-valid");
        container.classList.remove("is-invalid");
      } else {
        container.classList.remove("is-invalid", "is-valid");
      }
    }; // Initial validation state


    updateValidationState(); // Update on change

    select.addEventListener("change", updateValidationState); // Update on invalid event (when HTML5 validation fails)

    select.addEventListener("invalid", function (e) {
      container.classList.add("is-invalid");
      container.classList.remove("is-valid");
    }); // Update on form submission attempt

    var form = select.closest("form");

    if (form) {
      form.addEventListener("submit", function (e) {
        updateValidationState();
      });
    } // Watch for required attribute changes


    var observer = new MutationObserver(function () {
      updateValidationState();
    });
    observer.observe(select, {
      attributes: true,
      attributeFilter: ["required", "disabled"]
    });
  },

  /**
   * Populate options from select element
   */
  populateOptions: function populateOptions(select, optionsList) {
    var _this3 = this;

    Array.from(select.options).forEach(function (option, index) {
      // Skip placeholder options
      if (option.disabled && option.value === "") return;
      var optionDiv = document.createElement("div");
      optionDiv.className = _this3.config.optionClass;
      optionDiv.textContent = option.text;
      optionDiv.dataset.value = option.value;
      optionDiv.dataset.index = index;

      if (option.selected) {
        optionDiv.classList.add(_this3.config.selectedClass);
      } // Copy data attributes


      Array.from(option.attributes).forEach(function (attr) {
        if (attr.name.startsWith("data-") && attr.name !== "data-value" && attr.name !== "data-index") {
          optionDiv.setAttribute(attr.name, attr.value);
        }
      });
      optionsList.appendChild(optionDiv);
    });
  },

  /**
   * Get selected option text
   */
  getSelectedText: function getSelectedText(select) {
    var selectedOption = select.options[select.selectedIndex];

    if (selectedOption && selectedOption.value !== "") {
      return selectedOption.text;
    }

    return "";
  },

  /**
   * Add event listeners to custom dropdown
   */
  addEventListeners: function addEventListeners(container, optionsContainer, originalSelect, displayValue, optionsList) {
    var _this4 = this;

    // Toggle dropdown
    container.addEventListener("click", function (e) {
      e.stopPropagation();
      var isOpen = optionsContainer.style.display === "block"; // Close all other dropdowns

      _this4.closeAllDropdowns();

      if (!isOpen) {
        optionsContainer.style.display = "block";
        container.classList.add(_this4.config.openClass); // Change arrow icon

        var arrow = container.querySelector(".enotf-dropdown-arrow");

        if (arrow) {
          arrow.classList.remove("fa-chevron-down");
          arrow.classList.add("fa-chevron-up");
        }
      }
    }); // Handle option selection

    optionsList.addEventListener("click", function (e) {
      if (e.target.classList.contains(_this4.config.optionClass)) {
        var value = e.target.dataset.value;
        var index = e.target.dataset.index; // Update original select

        originalSelect.selectedIndex = index; // Trigger change event on original select

        var changeEvent = new Event("change", {
          bubbles: true
        });
        originalSelect.dispatchEvent(changeEvent); // Update display

        displayValue.textContent = e.target.textContent; // Update selected class

        optionsList.querySelectorAll(".".concat(_this4.config.optionClass)).forEach(function (opt) {
          opt.classList.remove(_this4.config.selectedClass);
        });
        e.target.classList.add(_this4.config.selectedClass); // Close dropdown

        optionsContainer.style.display = "none";
        container.classList.remove(_this4.config.openClass); // Clear search if exists

        var searchInput = optionsContainer.querySelector(".".concat(_this4.config.searchClass));

        if (searchInput) {
          searchInput.value = "";

          _this4.filterOptions(optionsContainer, "");
        }
      }
    }); // Close dropdown when clicking outside

    document.addEventListener("click", function (e) {
      if (!container.contains(e.target) && !optionsContainer.contains(e.target)) {
        optionsContainer.style.display = "none";
        container.classList.remove(_this4.config.openClass);
      }
    }); // Keyboard navigation

    container.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        container.click();
      }
    });
  },

  /**
   * Filter options based on search query
   */
  filterOptions: function filterOptions(optionsContainer, query) {
    var optionsList = optionsContainer.querySelector(".".concat(this.config.optionsClass));
    var options = optionsList.querySelectorAll(".".concat(this.config.optionClass));
    var searchQuery = query.toLowerCase();
    var hasVisibleOption = false;
    options.forEach(function (option) {
      var text = option.textContent.toLowerCase();

      if (text.includes(searchQuery)) {
        option.style.display = "block";
        hasVisibleOption = true;
      } else {
        option.style.display = "none";
      }
    }); // Show "no results" message if no options match

    var noResultsMsg = optionsList.querySelector(".no-results-message");

    if (!hasVisibleOption && query !== "") {
      if (!noResultsMsg) {
        noResultsMsg = document.createElement("div");
        noResultsMsg.className = "no-results-message";
        noResultsMsg.textContent = "Keine Ergebnisse gefunden";
        noResultsMsg.style.cssText = "padding: 8px 12px; color: #999; text-align: center;";
        optionsList.appendChild(noResultsMsg);
      }

      noResultsMsg.style.display = "block";
    } else if (noResultsMsg) {
      noResultsMsg.style.display = "none";
    }
  },

  /**
   * Close all open dropdowns
   */
  closeAllDropdowns: function closeAllDropdowns() {
    var _this5 = this;

    this.instances.forEach(function (instance) {
      instance.optionsContainer.style.display = "none";
      instance.container.classList.remove(_this5.config.openClass); // Reset arrow icon

      var arrow = instance.container.querySelector(".enotf-dropdown-arrow");

      if (arrow) {
        arrow.classList.remove("fa-chevron-up");
        arrow.classList.add("fa-chevron-down");
      } // Clear search


      var searchInput = instance.optionsContainer.querySelector(".".concat(_this5.config.searchClass));

      if (searchInput) {
        searchInput.value = "";

        _this5.filterOptions(instance.optionsContainer, "");
      }
    });
  },

  /**
   * Refresh a specific dropdown (e.g., after options change)
   */
  refresh: function refresh(selectElement) {
    var instance = this.instances.find(function (inst) {
      return inst.select === selectElement;
    });

    if (instance) {
      // Clear and repopulate options
      instance.optionsList.innerHTML = "";
      this.populateOptions(selectElement, instance.optionsList); // Update display value

      instance.displayValue.textContent = this.getSelectedText(selectElement) || selectElement.dataset.placeholder || "Bitte wählen...";
    }
  },

  /**
   * Auto-convert all selects in eNOTF without data-custom-dropdown attribute
   */
  autoConvertAll: function autoConvertAll() {
    var _this6 = this;

    var selector = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : ".enotf select:not([data-custom-dropdown])";
    var selects = document.querySelectorAll(selector);
    selects.forEach(function (select) {
      // Add the data attribute
      select.setAttribute("data-custom-dropdown", "true"); // Create custom dropdown

      _this6.createCustomDropdown(select);
    });
  }
}; // Auto-initialize when DOM is ready

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", function () {
    eNOTFCustomDropdown.init();
  });
} else {
  eNOTFCustomDropdown.init();
}