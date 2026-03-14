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

        // Header immer aktualisieren (auch beim ersten Poll,
        // falls PHP-Session veraltet war)
        if (data.crew) {
          updateHeaderDisplay(data.crew);
        }

        // PHP-Session aktualisieren wenn sich Daten geändert haben
        if (cachedCrew && hasCrewChanged(cachedCrew, data.crew)) {
          syncPhpSession();
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
   * Rebuilds the crew display inside #topbar-crew-display
   */
  function updateHeaderDisplay(crew) {
    var container = document.getElementById("topbar-crew-display");
    if (!container) return;

    // Finde oder erstelle den Crew-Content-Bereich (erstes div Kind)
    var contentDiv = container.querySelector(".crew-live-content");
    if (!contentDiv) {
      // Erstmaliger Aufbau: vorhandenen Inhalt ersetzen
      // "Anmelden" Text am Ende beibehalten
      var anmeldenEl = container.querySelector("small");
      var anmeldenText = anmeldenEl ? anmeldenEl.outerHTML : '<small style="font-size: 0.65rem;">Anmelden</small>';

      container.innerHTML = '<div class="d-flex align-items-start crew-live-content"></div>' + anmeldenText;
      contentDiv = container.querySelector(".crew-live-content");
    }

    // Crew-HTML aufbauen
    var html = '<div class="d-flex flex-column align-items-end justify-content-start">';
    html += "<span>" + escapeHtml(crew.fahrername || "") + "</span>";
    html += "</div>";

    if (crew.beifahrername || crew.praktikantname) {
      html += '<div class="d-flex flex-column align-items-start ms-3">';
      if (crew.beifahrername) {
        html += "<span>" + escapeHtml(crew.beifahrername) + "</span>";
      }
      if (crew.praktikantname) {
        html += "<span>" + escapeHtml(crew.praktikantname) + "</span>";
      }
      html += "</div>";
    }

    contentDiv.innerHTML = html;
  }

  function escapeHtml(str) {
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
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
