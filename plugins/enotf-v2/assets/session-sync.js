/**
 * eNOTF v2 — Crew-Session-Live-Sync.
 *
 * Vanilla-Pendant zu assets/js/enotf-session-sync.js (v1), aber gegen die
 * v2-Endpoints (die v1-Routen hängen hinter hartem User-Auth und liefern
 * Crews ohne Panel-Login nur 401er):
 *
 *   GET  api/enotf-v2/session-status           (10s-Poll, Token im
 *                                               Header X-Enotf-Session-Token —
 *                                               nicht im Query-String, damit es
 *                                               nicht in Access-Logs landet)
 *   POST api/enotf-v2/session-update           (PHP-Session nachziehen,
 *                                               Token im POST-Body)
 *
 * Verhalten wie v1:
 *   - Session deaktiviert (active:false) → Redirect auf die Abmelde-Seite
 *   - Crew-Änderung → Topbar-Spans live aktualisieren + PHP-Session updaten
 *   - Session-Icon (#session-conn-icon) grün/rot/weiß färben
 *
 * Anders als v1 baut das Update die Crew-Anzeige nicht neu auf, sondern
 * schreibt in die vorhandenen [data-crew-name]-Spans der v2-Topbar.
 *
 * Erwartet auf <body>:
 *   data-base-path       BASE_PATH (für API- und Redirect-URLs)
 *   data-session-token   Token der eigenen Crew-Session (leer = kein Poll)
 */
(function () {
  "use strict";

  const POLL_INTERVAL = 10000; // 10 Sekunden
  const LOGGEDOUT_PATH = "enotf-v2/loggedout";

  let sessionToken = null;
  let basePath = "";
  let cachedCrew = null;
  let pollTimer = null;
  let consecutiveErrors = 0;

  function init() {
    const body = document.body;
    sessionToken = body.getAttribute("data-session-token");
    basePath = body.getAttribute("data-base-path") || "/";

    if (!sessionToken) {
      updateSessionIcon("unknown");
      return;
    }

    pollSessionStatus();
    pollTimer = setInterval(pollSessionStatus, POLL_INTERVAL);
  }

  function pollSessionStatus() {
    if (!sessionToken) return;

    fetch(basePath + "api/enotf-v2/session-status", {
      headers: {
        Accept: "application/json",
        "X-Enotf-Session-Token": sessionToken,
      },
    })
      .then(function (r) {
        if (!r.ok) {
          // Server-Fehler (500, 404, …) — nicht redirecten
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

        // Anzeige immer nachziehen (auch beim ersten Poll, falls die
        // PHP-Session veraltet war)
        if (data.crew) {
          updateHeaderDisplay(data.crew);
        }

        // PHP-Session aktualisieren, wenn sich die Crew geändert hat
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

  function hasCrewChanged(oldCrew, newCrew) {
    const fields = [
      "fahrername",
      "fahrerquali",
      "beifahrername",
      "beifahrerquali",
      "praktikantname",
      "praktikantquali",
    ];
    for (let i = 0; i < fields.length; i++) {
      const f = fields[i];
      if ((oldCrew[f] || "") !== ((newCrew && newCrew[f]) || "")) {
        return true;
      }
    }
    return false;
  }

  function syncPhpSession() {
    if (!sessionToken) return;

    const formData = new FormData();
    formData.append("token", sessionToken);

    fetch(basePath + "api/enotf-v2/session-update", {
      method: "POST",
      body: formData,
    }).catch(function () {
      // Still scheitern lassen — der nächste Poll versucht es erneut
    });
  }

  /**
   * Crew-Anzeige in der Topbar live aktualisieren: die v2-Topbar rendert
   * feste [data-crew-name]-Spans (Fahrer links, Beifahrer/Praktikant
   * rechts), hier werden nur Text und Sichtbarkeit nachgezogen.
   */
  function updateHeaderDisplay(crew) {
    const mapping = {
      fahrername: crew.fahrername || "",
      beifahrername: crew.beifahrername || "",
      praktikantname: crew.praktikantname || "",
    };

    Object.keys(mapping).forEach(function (key) {
      const el = document.querySelector('[data-crew-name="' + key + '"]');
      if (!el) return;
      if (el.textContent !== mapping[key]) {
        el.textContent = mapping[key];
      }
      // Fahrer-Span bleibt immer sichtbar (v1-Layout), die anderen
      // blenden bei leerem Namen aus
      if (key !== "fahrername") {
        el.classList.toggle("hidden", mapping[key] === "");
      }
    });
  }

  function updateSessionIcon(status) {
    const iconEl = document.querySelector("#session-conn-icon i");
    if (!iconEl) return;

    const parentEl = iconEl.parentElement;

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

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
