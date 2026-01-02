(function () {
  const pinEnabled = document.body.dataset.pinEnabled === "true";
  if (!pinEnabled) return;

  let activityTimer = null;
  const updateInterval = 30000; // Alle 30 Sekunden Session aktualisieren
  const checkInterval = 10000; // Alle 10 Sekunden Timeout prüfen
  const timeout = 300000; // 5 Minuten in Millisekunden

  let lastActivity = Date.now();

  const getBasePath = () => {
    const baseElement = document.querySelector("base");
    if (baseElement) {
      return baseElement.href;
    }
    // Fallback: ROOT-Pfad ermitteln
    const path = window.location.pathname;
    const rootPath = path.substring(0, path.indexOf("/", 1) + 1);
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
    const now = Date.now();
    const timeSinceActivity = now - lastActivity;

    if (timeSinceActivity >= timeout) {
      console.log("🔒 User inactive for 5 minutes, redirecting to lockscreen");
      const basePath = getBasePath();
      window.location.href = basePath + "lockscreen.php";
    }
  }

  const events = [
    "mousedown",
    "mousemove",
    "keypress",
    "scroll",
    "touchstart",
    "click",
    "focus",
    "input",
  ];

  let throttleTimer = null;
  function throttledActivity() {
    if (!throttleTimer) {
      throttleTimer = setTimeout(() => {
        registerActivity();
        throttleTimer = null;
      }, 1000);
    }
  }

  events.forEach((event) => {
    document.addEventListener(event, throttledActivity, true);
  });

  registerActivity();

  const timeoutChecker = setInterval(checkTimeout, checkInterval);

  let warningShown = false;
  const warningTime = 240000; // 4 Minuten - 1 Minute vor Timeout

  // FÜR TESTS: Warnung nach 10 Sekunden statt 4 Minuten
  const testMode = window.location.search.includes("timer=show");
  const effectiveWarningTime = testMode ? 10000 : warningTime; // 10 Sekunden im Test-Modus

  const warningChecker = setInterval(() => {
    const now = Date.now();
    const timeSinceActivity = now - lastActivity;

    if (
      testMode &&
      timeSinceActivity > 5000 &&
      timeSinceActivity < effectiveWarningTime + 1000
    ) {
      console.log(
        "Warning check:",
        Math.floor(timeSinceActivity / 1000) +
          "s / " +
          Math.floor(effectiveWarningTime / 1000) +
          "s"
      );
    }

    if (timeSinceActivity >= effectiveWarningTime && !warningShown) {
      console.log("🔔 Showing inactivity warning");
      console.log(
        "Warning already exists?",
        document.getElementById("pin-inactivity-warning")
      );
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

    const warning = document.createElement("div");
    warning.id = "pin-inactivity-warning";
    warning.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #ff6b6b;
            color: white;
            padding: 15px 20px;
            border-radius: 0;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            z-index: 10000;
            font-size: 14px;
            max-width: 300px;
            animation: slideIn 0.3s ease-out;
        `;
    warning.innerHTML = `
            <strong><i class="fa-solid fa-triangle-exclamation"></i> Inaktivitätswarnung</strong><br>
            System wird in 1 Minute gesperrt
        `;

    const style = document.createElement("style");
    style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(400px);
                    opacity: 0;
                }
            }
        `;
    document.head.appendChild(style);

    document.body.appendChild(warning);
  }

  function hideInactivityWarning() {
    const warning = document.getElementById("pin-inactivity-warning");
    if (warning) {
      warning.style.animation = "slideOut 0.3s ease-out";
      setTimeout(() => warning.remove(), 300);
    }
  }

  window.addEventListener("beforeunload", () => {
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
    const timerDiv = document.createElement("div");
    timerDiv.id = "pin-timer-display";
    timerDiv.style.cssText = `
            position: fixed;
            top: 10px;
            left: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: #0f0;
            padding: 10px 15px;
            border-radius: 0;
            font-family: monospace;
            font-size: 14px;
            z-index: 10001;
            border: 2px solid #0f0;
            min-width: 200px;
        `;
    timerDiv.innerHTML = `
            <div><strong>🔒 PIN Timer (TEST)</strong></div>
            <div>Verbleibend: <span id="pin-timer-remaining">--:--</span></div>
            <div>Letzter Reset: <span id="pin-timer-last">--:--:--</span></div>
        `;
    document.body.appendChild(timerDiv);

    setInterval(updateTimerDisplay, 1000);
  }

  function updateTimerDisplay() {
    const now = Date.now();
    const timeSinceActivity = now - lastActivity;
    const remaining = Math.max(0, timeout - timeSinceActivity);

    const minutes = Math.floor(remaining / 60000);
    const seconds = Math.floor((remaining % 60000) / 1000);

    const remainingEl = document.getElementById("pin-timer-remaining");
    if (remainingEl) {
      remainingEl.textContent = `${minutes}:${seconds
        .toString()
        .padStart(2, "0")}`;

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

    const lastEl = document.getElementById("pin-timer-last");
    if (lastEl) {
      const lastDate = new Date(lastActivity);
      lastEl.textContent = lastDate.toLocaleTimeString("de-DE", {
        hour12: false,
      });
    }
  }

  if (window.location.search.includes("timer=show")) {
    createTimerDisplay();
  }
})();
