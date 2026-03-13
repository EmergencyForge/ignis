/**
 * eNOTF Session Sync
 * Pollt den Session-Status und synchronisiert Crew-Daten zwischen Clients.
 * - Bei Session-Deaktivierung: Redirect zu loggedout.php
 * - Bei Crew-Änderung: PHP-Session aktualisieren + Header live updaten
 * - Session-Icon in der Topbar aktualisieren (grün/rot)
 */
(function () {
  "use strict";

  const POLL_INTERVAL = 10000; // 10 Sekunden
  const LOGGEDOUT_PATH = "enotf/loggedout.php";

  let sessionToken = null;
  let basePath = "";
  let cachedCrew = null;
  let pollTimer = null;
  let consecutiveErrors = 0;

  /**
   * Initialize session sync
   */
  function init() {
    const body = document.body;
    sessionToken = body.getAttribute("data-session-token");
    basePath = body.getAttribute("data-base-path") || "/";

    if (!sessionToken) {
      updateSessionIcon("unknown");
      return;
    }

    // Initial poll
    pollSessionStatus();

    // Start polling
    pollTimer = setInterval(pollSessionStatus, POLL_INTERVAL);
  }

  /**
   * Poll session status from server
   */
  function pollSessionStatus() {
    if (!sessionToken) return;

    fetch(
      basePath +
        "api/enotf/session-status.php?token=" +
        encodeURIComponent(sessionToken)
    )
      .then(function (r) {
        if (!r.ok) {
          // Server-Fehler (500, 404, etc.) — nicht redirecten
          throw new Error("HTTP " + r.status);
        }
        return r.json();
      })
      .then(function (data) {
        consecutiveErrors = 0;

        if (data.active === false) {
          // Session wurde explizit deaktiviert → Redirect
          updateSessionIcon("disconnected");
          clearInterval(pollTimer);
          window.location.href = basePath + LOGGEDOUT_PATH;
          return;
        }

        updateSessionIcon("connected");

        // Crew-Daten vergleichen
        if (cachedCrew && hasCrewChanged(cachedCrew, data.crew)) {
          // PHP-Session aktualisieren
          syncPhpSession();
          // Header live updaten
          updateHeaderDisplay(data.crew);
        }

        cachedCrew = data.crew;
      })
      .catch(function () {
        consecutiveErrors++;
        if (consecutiveErrors >= 3) {
          updateSessionIcon("disconnected");
        }
      });
  }

  /**
   * Check if crew data has changed
   */
  function hasCrewChanged(oldCrew, newCrew) {
    var fields = [
      "fahrername",
      "fahrerquali",
      "beifahrername",
      "beifahrerquali",
      "praktikantname",
      "praktikantquali",
    ];
    for (var i = 0; i < fields.length; i++) {
      var f = fields[i];
      if ((oldCrew[f] || "") !== (newCrew[f] || "")) {
        return true;
      }
    }
    return false;
  }

  /**
   * Sync PHP session with current DB state
   */
  function syncPhpSession() {
    if (!sessionToken) return;

    var formData = new FormData();
    formData.append("token", sessionToken);

    fetch(basePath + "api/enotf/session-update.php", {
      method: "POST",
      body: formData,
    }).catch(function () {
      // Silently fail - next poll will retry
    });
  }

  /**
   * Update header crew display (live, no page reload)
   */
  function updateHeaderDisplay(crew) {
    var crewCols = document.querySelectorAll("[data-crew-name]");
    crewCols.forEach(function (el) {
      var field = el.getAttribute("data-crew-name");
      if (crew[field] !== undefined) {
        el.textContent = crew[field] || "";
        // Optionale Felder ein-/ausblenden
        if (field !== "fahrername") {
          if (crew[field]) {
            el.classList.remove("d-none");
          } else {
            el.classList.add("d-none");
          }
        }
      }
    });
  }

  /**
   * Update session connection icon in topbar
   */
  function updateSessionIcon(status) {
    var iconEl = document.querySelector("#session-conn-icon i");
    if (!iconEl) return;

    var parentEl = iconEl.parentElement;

    switch (status) {
      case "connected":
        iconEl.style.color = "#28a745";
        parentEl.title = "Session aktiv";
        break;
      case "disconnected":
        iconEl.style.color = "#dc3545";
        parentEl.title = "Session-Verbindung unterbrochen";
        break;
      default:
        iconEl.style.color = "#ffffff";
        parentEl.title = "Session-Status unbekannt";
        break;
    }
  }

  // Initialize when DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
