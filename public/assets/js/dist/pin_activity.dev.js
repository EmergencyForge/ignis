"use strict";

(function () {
  var pinEnabled = document.body.dataset.pinEnabled === "true";
  if (!pinEnabled) return;
  var activityTimer = null;
  var updateInterval = 30000; // Alle 30 Sekunden Session aktualisieren

  var checkInterval = 10000; // Alle 10 Sekunden Timeout prüfen

  var timeout = 300000; // 5 Minuten in Millisekunden

  var lastActivity = Date.now();

  var getBasePath = function getBasePath() {
    var baseElement = document.querySelector("base");

    if (baseElement) {
      return baseElement.href;
    } // Fallback: ROOT-Pfad ermitteln


    var path = window.location.pathname;
    var rootPath = path.substring(0, path.indexOf("/", 1) + 1);
    return window.location.origin + rootPath;
  };

  function registerActivity() {
    lastActivity = Date.now();

    if (activityTimer) {
      clearTimeout(activityTimer);
    }

    activityTimer = setTimeout(updateInterval);
  }

  function checkTimeout() {
    var now = Date.now();
    var timeSinceActivity = now - lastActivity;

    if (timeSinceActivity >= timeout) {
      console.log("🔒 User inactive for 5 minutes, redirecting to lockscreen");
      var basePath = getBasePath();
      window.location.href = basePath + "lockscreen.php";
    }
  }

  var events = ["mousedown", "mousemove", "keypress", "scroll", "touchstart", "click", "focus", "input"];
  var throttleTimer = null;

  function throttledActivity() {
    if (!throttleTimer) {
      throttleTimer = setTimeout(function () {
        registerActivity();
        throttleTimer = null;
      }, 1000);
    }
  }

  events.forEach(function (event) {
    document.addEventListener(event, throttledActivity, true);
  });
  registerActivity();
  var timeoutChecker = setInterval(checkTimeout, checkInterval);
  var warningShown = false;
  var warningTime = 240000; // 4 Minuten - 1 Minute vor Timeout
  // FÜR TESTS: Warnung nach 10 Sekunden statt 4 Minuten

  var testMode = window.location.search.includes("timer=show");
  var effectiveWarningTime = testMode ? 10000 : warningTime; // 10 Sekunden im Test-Modus

  var warningChecker = setInterval(function () {
    var now = Date.now();
    var timeSinceActivity = now - lastActivity;

    if (testMode && timeSinceActivity > 5000 && timeSinceActivity < effectiveWarningTime + 1000) {
      console.log("Warning check:", Math.floor(timeSinceActivity / 1000) + "s / " + Math.floor(effectiveWarningTime / 1000) + "s");
    }

    if (timeSinceActivity >= effectiveWarningTime && !warningShown) {
      console.log("🔔 Showing inactivity warning");
      console.log("Warning already exists?", document.getElementById("pin-inactivity-warning"));
      console.log("Document body:", document.body);
      warningShown = true;
      showInactivityWarning();
    } else if (timeSinceActivity < effectiveWarningTime && warningShown) {
      console.log("✅ Hiding warning - user active again");
      warningShown = false;
      hideInactivityWarning();
    }
  }, 5000);

  function showInactivityWarning() {
    if (document.getElementById("pin-inactivity-warning")) return;
    var warning = document.createElement("div");
    warning.id = "pin-inactivity-warning";
    warning.style.cssText = "\n            position: fixed;\n            top: 20px;\n            right: 20px;\n            background: #ff6b6b;\n            color: white;\n            padding: 15px 20px;\n            border-radius: 0;\n            box-shadow: 0 4px 8px rgba(0,0,0,0.3);\n            z-index: 10000;\n            font-size: 14px;\n            max-width: 300px;\n            animation: slideIn 0.3s ease-out;\n        ";
    warning.innerHTML = "\n            <strong><i class=\"fa-solid fa-triangle-exclamation\"></i> Inaktivit\xE4tswarnung</strong><br>\n            System wird in 1 Minute gesperrt\n        ";
    var style = document.createElement("style");
    style.textContent = "\n            @keyframes slideIn {\n                from {\n                    transform: translateX(400px);\n                    opacity: 0;\n                }\n                to {\n                    transform: translateX(0);\n                    opacity: 1;\n                }\n            }\n            @keyframes slideOut {\n                from {\n                    transform: translateX(0);\n                    opacity: 1;\n                }\n                to {\n                    transform: translateX(400px);\n                    opacity: 0;\n                }\n            }\n        ";
    document.head.appendChild(style);
    document.body.appendChild(warning);
  }

  function hideInactivityWarning() {
    var warning = document.getElementById("pin-inactivity-warning");

    if (warning) {
      warning.style.animation = "slideOut 0.3s ease-out";
      setTimeout(function () {
        return warning.remove();
      }, 300);
    }
  }

  window.addEventListener("beforeunload", function () {
    if (activityTimer) {
      clearTimeout(activityTimer);
    }

    if (timeoutChecker) {
      clearInterval(timeoutChecker);
    }

    if (warningChecker) {
      clearInterval(warningChecker);
    }
  });

  function createTimerDisplay() {
    var timerDiv = document.createElement("div");
    timerDiv.id = "pin-timer-display";
    timerDiv.style.cssText = "\n            position: fixed;\n            top: 10px;\n            left: 10px;\n            background: rgba(0, 0, 0, 0.8);\n            color: #0f0;\n            padding: 10px 15px;\n            border-radius: 0;\n            font-family: monospace;\n            font-size: 14px;\n            z-index: 10001;\n            border: 2px solid #0f0;\n            min-width: 200px;\n        ";
    timerDiv.innerHTML = "\n            <div><strong>\uD83D\uDD12 PIN Timer (TEST)</strong></div>\n            <div>Verbleibend: <span id=\"pin-timer-remaining\">--:--</span></div>\n            <div>Letzter Reset: <span id=\"pin-timer-last\">--:--:--</span></div>\n        ";
    document.body.appendChild(timerDiv);
    setInterval(updateTimerDisplay, 1000);
  }

  function updateTimerDisplay() {
    var now = Date.now();
    var timeSinceActivity = now - lastActivity;
    var remaining = Math.max(0, timeout - timeSinceActivity);
    var minutes = Math.floor(remaining / 60000);
    var seconds = Math.floor(remaining % 60000 / 1000);
    var remainingEl = document.getElementById("pin-timer-remaining");

    if (remainingEl) {
      remainingEl.textContent = "".concat(minutes, ":").concat(seconds.toString().padStart(2, "0"));

      if (remaining < 60000) {
        // < 1 Minute
        remainingEl.style.color = "#ff0000";
      } else if (remaining < 120000) {
        // < 2 Minuten
        remainingEl.style.color = "#ff8800";
      } else {
        remainingEl.style.color = "#0f0";
      }
    }

    var lastEl = document.getElementById("pin-timer-last");

    if (lastEl) {
      var lastDate = new Date(lastActivity);
      lastEl.textContent = lastDate.toLocaleTimeString("de-DE", {
        hour12: false
      });
    }
  }

  if (window.location.search.includes("timer=show")) {
    createTimerDisplay();
  }
})();