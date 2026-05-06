// Global state
let truppTimers = {
  1: { running: false, startTime: null, elapsedSeconds: 0, interval: null },
  2: { running: false, startTime: null, elapsedSeconds: 0, interval: null },
  3: { running: false, startTime: null, elapsedSeconds: 0, interval: null },
};

// Constants
const MAX_TIME_SECONDS = 60 * 60; // 60 minutes
const CIRCLE_CIRCUMFERENCE = 471.24; // 2 * PI * 75 (neuer Radius)

// Initialize on load
document.addEventListener("DOMContentLoaded", function () {
  updateCurrentTime();
  setInterval(updateCurrentTime, 1000);

  // Set today's date as default in DD.MM.YYYY format
  const today = new Date();
  const day = String(today.getDate()).padStart(2, "0");
  const month = String(today.getMonth() + 1).padStart(2, "0");
  const year = today.getFullYear();
  const missionDateField = document.getElementById("missionDate");
  if (missionDateField) {
    missionDateField.value = `${day}.${month}.${year}`;
  }

  // Initialize timer displays
  for (let i = 1; i <= 3; i++) {
    updateTruppDisplay(i);
  }
});

// Update current time display
function updateCurrentTime() {
  const now = new Date();
  const hours = String(now.getHours()).padStart(2, "0");
  const minutes = String(now.getMinutes()).padStart(2, "0");

  const timeElement = document.getElementById("currentTime");
  if (timeElement) {
    timeElement.textContent = `${hours}:${minutes}`;
  }
}

// Format seconds to MM:SS
function formatTime(seconds) {
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${String(mins).padStart(2, "0")}:${String(secs).padStart(2, "0")}`;
}

// Update trupp timer display
function updateTruppDisplay(truppNum) {
  const timer = truppTimers[truppNum];
  const timeElement = document.getElementById(`trupp${truppNum}Time`);
  const progressCircle = document.getElementById(
    `trupp${truppNum}ProgressCircle`
  );
  const handElement = document.getElementById(`trupp${truppNum}Hand`);
  const progressBar = document.getElementById(`trupp${truppNum}ProgressBar`);
  const percentElement = document.getElementById(`trupp${truppNum}Percent`);

  if (timeElement) {
    timeElement.textContent = formatTime(timer.elapsedSeconds);

    // Update warning states
    timeElement.classList.remove("text-warning", "text-danger");
    if (timer.elapsedSeconds >= 3000) {
      // 50 minutes
      timeElement.classList.add("text-danger");
    } else if (timer.elapsedSeconds >= 2400) {
      // 40 minutes
      timeElement.classList.add("text-warning");
    }
  }

  // Calculate percentage
  const percentage = timer.elapsedSeconds / MAX_TIME_SECONDS;
  const percentValue = Math.round(percentage * 100);

  // Safety-Tier aus Einsatzzeit ableiten. Normal-Zustand nutzt die Brand-
  // Farbe aus der CSS-Variable, damit Accent-Color-Switch (rot/blau/grün/...)
  // im laufenden Betrieb mitwechselt. Warning/Danger sind fix, weil
  // Safety-Semantik farbcodiert (Gelb/Rot = Rückzugsschwelle).
  let tierStroke;
  if (timer.elapsedSeconds >= 3000) {
    tierStroke = "#ef4444"; // danger
  } else if (timer.elapsedSeconds >= 2400) {
    tierStroke = "#ffc107"; // warning
  } else {
    const cssMain = getComputedStyle(document.documentElement)
      .getPropertyValue("--main-color").trim();
    tierStroke = cssMain || "#ff4d00";
  }

  // Update SVG circle progress
  if (progressCircle) {
    const offset = CIRCLE_CIRCUMFERENCE * (1 - percentage);
    progressCircle.style.strokeDashoffset = offset;
    progressCircle.style.stroke = tierStroke;
  }

  // Update clock hand (rotates 360 degrees over 60 minutes)
  if (handElement) {
    const degrees = (timer.elapsedSeconds / MAX_TIME_SECONDS) * 360;
    handElement.style.transform = `rotate(${degrees}deg)`;
    handElement.style.stroke = tierStroke;
  }

  // Update progress bar — asu-warning/asu-danger triggern Safety-Farben,
  // fehlen beide bedeutet: Brand-Farbe über CSS-Default.
  if (progressBar) {
    progressBar.style.width = percentValue + "%";
    progressBar.setAttribute("aria-valuenow", percentValue);
    progressBar.classList.remove("asu-warning", "asu-danger");
    if (timer.elapsedSeconds >= 3000) {
      progressBar.classList.add("asu-danger");
    } else if (timer.elapsedSeconds >= 2400) {
      progressBar.classList.add("asu-warning");
    }
  }

  // Update percentage text
  if (percentElement) {
    percentElement.textContent = percentValue + "%";
  }
}

// Start trupp timer
function startTrupp(truppNum) {
  const timer = truppTimers[truppNum];

  if (timer.running) {
    console.log(`Trupp ${truppNum} is already running`);
    return;
  }

  // Set start time if not already set
  const startTimeInput = document.getElementById(`trupp${truppNum}StartTime`);
  if (startTimeInput && !startTimeInput.value) {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, "0");
    const minutes = String(now.getMinutes()).padStart(2, "0");
    startTimeInput.value = `${hours}:${minutes}`;
  }

  timer.running = true;
  timer.startTime = Date.now() - timer.elapsedSeconds * 1000;

  timer.interval = setInterval(() => {
    if (timer.running) {
      const elapsed = Math.floor((Date.now() - timer.startTime) / 1000);
      timer.elapsedSeconds = Math.min(elapsed, MAX_TIME_SECONDS);
      updateTruppDisplay(truppNum);

      // Auto-stop at max time
      if (timer.elapsedSeconds >= MAX_TIME_SECONDS) {
        stopTrupp(truppNum);
      }
    }
  }, 1000);

  console.log(`Trupp ${truppNum} started`);
}

// Stop trupp timer
function stopTrupp(truppNum) {
  const timer = truppTimers[truppNum];

  if (!timer.running) {
    console.log(`Trupp ${truppNum} is not running`);
    return;
  }

  timer.running = false;

  if (timer.interval) {
    clearInterval(timer.interval);
    timer.interval = null;
  }

  // Set end time if not already set
  const endTimeInput = document.getElementById(`trupp${truppNum}End`);
  if (endTimeInput && !endTimeInput.value) {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, "0");
    const minutes = String(now.getMinutes()).padStart(2, "0");
    endTimeInput.value = `${hours}:${minutes}`;
  }

  console.log(
    `Trupp ${truppNum} stopped at ${formatTime(timer.elapsedSeconds)}`
  );
}

// Reset trupp timer
function resetTrupp(truppNum) {
  stopTrupp(truppNum);

  const timer = truppTimers[truppNum];
  timer.elapsedSeconds = 0;
  timer.startTime = null;

  updateTruppDisplay(truppNum);
}

// Get trupp data
function getTruppData(truppNum) {
  const getFieldValue = (id) => {
    const element = document.getElementById(id);
    return element ? element.value : "";
  };

  return {
    truppNumber: truppNum,
    elapsedTime: truppTimers[truppNum].elapsedSeconds,
    tf: getFieldValue(`trupp${truppNum}TF`),
    tm1: getFieldValue(`trupp${truppNum}TM1`),
    tm2: getFieldValue(`trupp${truppNum}TM2`),
    startPressure: getFieldValue(`trupp${truppNum}StartPressure`),
    startTime: getFieldValue(`trupp${truppNum}StartTime`),
    mission: getFieldValue(`trupp${truppNum}Mission`),
    check1: getFieldValue(`trupp${truppNum}Check1`),
    check2: getFieldValue(`trupp${truppNum}Check2`),
    objective: getFieldValue(`trupp${truppNum}Objective`),
    retreat: getFieldValue(`trupp${truppNum}Retreat`),
    end: getFieldValue(`trupp${truppNum}End`),
    remarks: getFieldValue(`trupp${truppNum}Remarks`),
  };
}

// Get all data
function getAllData() {
  const getFieldValue = (id) => {
    const element = document.getElementById(id);
    return element ? element.value : "";
  };

  return {
    missionNumber: getFieldValue("missionNumber"),
    missionLocation: getFieldValue("missionLocation"),
    missionDate: getFieldValue("missionDate"),
    supervisor: getFieldValue("supervisor"),
    trupp1: getTruppData(1),
    trupp2: getTruppData(2),
    trupp3: getTruppData(3),
    timestamp: new Date().toISOString(),
  };
}

// Clear all data
function clearAll() {
  showConfirm("Möchten Sie wirklich alle Daten löschen?", {
    title: "Daten löschen",
    danger: true,
    confirmText: "Löschen",
    cancelText: "Abbrechen",
  }).then((confirmed) => {
    if (!confirmed) {
      return;
    }

    // Stop all timers
    for (let i = 1; i <= 3; i++) {
      resetTrupp(i);
    }

    // Clear all inputs except mission date and pre-filled fields
    const inputs = document.querySelectorAll(
      '#asuForm input[type="text"]:not(#missionNumber):not(#missionLocation), #asuForm input[type="number"], #asuForm input[type="time"], #asuForm textarea, #asuForm select'
    );
    inputs.forEach((input) => {
      input.value = "";
    });

    console.log("All data cleared");
  });
}

// Send data to server
function sendData() {
  const data = getAllData();

  // Validate required fields
  if (
    !data.missionNumber ||
    !data.missionLocation ||
    !data.missionDate ||
    !data.supervisor
  ) {
    showAlert(
      "Bitte füllen Sie alle Pflichtfelder aus: Einsatznummer, Einsatzort, Einsatzdatum, Überwacher",
      {
        title: "Fehlende Pflichtfelder",
        type: "warning",
      }
    );
    return;
  }

  // Check if at least one trupp has data
  let hasTruppData = false;
  for (let i = 1; i <= 3; i++) {
    const truppData = data[`trupp${i}`];
    if (truppData.tf && truppData.tm1) {
      hasTruppData = true;
      break;
    }
  }

  if (!hasTruppData) {
    showAlert(
      "Bitte geben Sie mindestens für einen Trupp die Pflichtfelder (TF und TM1) an!",
      {
        title: "Fehlende Trupp-Daten",
        type: "warning",
      }
    );
    return;
  }

  console.log("Sending ASU data:", data);

  // Get incident ID from hidden field (if exists) or URL parameter
  let incidentId = null;
  const incidentIdField = document.getElementById("incidentId");
  if (incidentIdField) {
    incidentId = incidentIdField.value;
  } else {
    // Fallback to URL parameter for backwards compatibility
    const urlParams = new URLSearchParams(window.location.search);
    incidentId = urlParams.get("id");
  }

  // Check if we are editing an existing ASU protocol
  const asuIdField = document.getElementById("asuId");
  const asuId = asuIdField ? asuIdField.value : "";
  const isEdit = asuId !== "";

  // Send to backend
  const params = {
    action: isEdit ? "update_asu" : "add_asu",
    incident_id: incidentId || "",
    asu_data: JSON.stringify(data),
  };
  if (isEdit) {
    params.asu_id = asuId;
  }

  fetch(basePath + "firetab/actions", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams(params),
  })
    .then((response) => response.text())
    .then((result) => {
      console.log("ASU data saved:", result);

      // Show success message
      showAlert("Das ASU-Protokoll wurde erfolgreich gespeichert.", {
        title: "Erfolgreich gespeichert",
        type: "success",
      });

      // Reload the page to show the new protocol in the list
      setTimeout(() => {
        window.location.reload();
      }, 1500);
    })
    .catch((error) => {
      console.error("Error sending ASU data:", error);
      showAlert("Fehler beim Senden der Daten!", {
        title: "Fehler",
        type: "error",
      });
    });
}

// View ASU Protocol — baut das Markup als String und uebergibt es als
// Dialog-Body. Modal-Instanz und viewContainer-Element-Lookup entfallen,
// weil das alte Bootstrap-Modal-Markup geloescht ist.
function viewASUProtocol(data) {
  let html = `
    <div class="row mb-3">
      <div class="col-md-3">
        <strong>Einsatznummer:</strong><br>
        ${escapeHtml(data.missionNumber || "-")}
      </div>
      <div class="col-md-3">
        <strong>Einsatzort:</strong><br>
        ${escapeHtml(data.missionLocation || "-")}
      </div>
      <div class="col-md-3">
        <strong>Datum:</strong><br>
        ${escapeHtml(data.missionDate || "-")}
      </div>
      <div class="col-md-3">
        <strong>Überwacher:</strong><br>
        ${escapeHtml(data.supervisor || "-")}
      </div>
    </div>
    <hr>
  `;

  // Display each trupp
  for (let i = 1; i <= 3; i++) {
    const trupp = data[`trupp${i}`];
    if (!trupp || !trupp.tf) continue;

    const truppName = i === 3 ? "Sicherheitstrupp" : `${i}. Trupp`;

    html += `
      <div class="card mb-3">
        <div class="card-header bg-dark">
          <h5 class="mb-0">${truppName}</h5>
        </div>
        <div class="card-body">
          <div class="row mb-2">
            <div class="col-md-4"><strong>Truppführer:</strong> ${escapeHtml(
              trupp.tf || "-"
            )}</div>
            <div class="col-md-4"><strong>Truppmann 1:</strong> ${escapeHtml(
              trupp.tm1 || "-"
            )}</div>
            <div class="col-md-4"><strong>Truppmann 2:</strong> ${escapeHtml(
              trupp.tm2 || "-"
            )}</div>
          </div>
          <div class="row mb-2">
            <div class="col-md-4"><strong>Anfangsdruck:</strong> ${escapeHtml(
              trupp.startPressure || "-"
            )} bar</div>
            <div class="col-md-4"><strong>Einsatzbeginn:</strong> ${escapeHtml(
              trupp.startTime || "-"
            )}</div>
            <div class="col-md-4"><strong>Auftrag:</strong> ${escapeHtml(
              trupp.mission || "-"
            )}</div>
          </div>
          <div class="row mb-2">
            <div class="col-md-6"><strong>1. Kontrolle:</strong> ${escapeHtml(
              trupp.check1 || "-"
            )}</div>
            <div class="col-md-6"><strong>2. Kontrolle:</strong> ${escapeHtml(
              trupp.check2 || "-"
            )}</div>
          </div>
          <div class="row mb-2">
            <div class="col-md-4"><strong>Einsatzziel:</strong> ${escapeHtml(
              trupp.objective || "-"
            )}</div>
            <div class="col-md-4"><strong>Rückzug:</strong> ${escapeHtml(
              trupp.retreat || "-"
            )}</div>
            <div class="col-md-4"><strong>Einsatzende:</strong> ${escapeHtml(
              trupp.end || "-"
            )}</div>
          </div>
          <div class="row mb-2">
            <div class="col-md-4"><strong>Einsatzzeit:</strong> ${formatTime(
              trupp.elapsedTime || 0
            )}</div>
          </div>
          ${
            trupp.remarks
              ? `
          <div class="row">
            <div class="col-12">
              <strong>Bemerkungen:</strong><br>
              ${escapeHtml(trupp.remarks)}
            </div>
          </div>
          `
              : ""
          }
        </div>
      </div>
    `;
  }

  // Dialog ersetzt das alte Bootstrap-Modal — Body wird mit dem
  // JS-gebauten HTML befuellt, ohne Helper-Container im DOM noetig.
  new Dialog({
    title: 'ASU-Protokoll Ansicht',
    size:  'xl',
    body:  html,
    actions: [{ label: 'Schließen', variant: 'ghost', close: true }],
  }).open();
}

// Delete ASU Protocol
function deleteASUProtocol(asuId) {
  showConfirm("Möchten Sie dieses ASU-Protokoll wirklich löschen?", {
    title: "Protokoll löschen",
    danger: true,
    confirmText: "Löschen",
    cancelText: "Abbrechen",
  }).then((confirmed) => {
    if (!confirmed) {
      return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const incidentId = urlParams.get("id");

    fetch(basePath + "firetab/actions", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams({
        action: "delete_asu",
        incident_id: incidentId,
        asu_id: asuId,
        return_tab: "asu",
      }),
    })
      .then((response) => {
        if (response.redirected) {
          window.location.href = response.url;
        }
      })
      .catch((error) => {
        console.error("Error deleting ASU protocol:", error);
        showAlert("Fehler beim Löschen des Protokolls!", {
          title: "Fehler",
          type: "error",
        });
      });
  });
}

// Helper function to escape HTML
function escapeHtml(text) {
  if (!text) return "-";
  const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  };
  return text.toString().replace(/[&<>"']/g, (m) => map[m]);
}
