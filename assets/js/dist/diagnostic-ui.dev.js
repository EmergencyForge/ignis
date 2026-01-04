"use strict";

/**
 * JavaScript-Funktionen für Diagnose-UI Integration
 *
 * Füge dieses Script in deine Update-Seite ein, um die Diagnose-Funktionen zu nutzen.
 */
// Globale Variable für Diagnose-Daten
var currentDiagnosticData = null;
/**
 * Zeige Diagnose-Bericht im Modal oder Container
 *
 * @param {Object} diagnostics - Diagnose-Daten vom Server
 * @param {string} containerId - ID des Container-Elements
 */

function showDiagnosticReport(diagnostics) {
  var containerId = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : "diagnostic-container";
  var container = document.getElementById(containerId);

  if (!container) {
    console.error("Container nicht gefunden:", containerId);
    return;
  } // Speichere Daten global


  currentDiagnosticData = diagnostics; // Zeige HTML-formatierte Diagnose

  if (diagnostics.diagnostic_html) {
    container.innerHTML = diagnostics.diagnostic_html;
  } else if (diagnostics.diagnostic_summary) {
    // Fallback auf Text-Version
    container.innerHTML = '<pre class="diagnostic-text">' + escapeHtml(diagnostics.diagnostic_summary) + "</pre>";
  } // Füge JavaScript-Funktionalität hinzu


  attachDiagnosticHandlers();
}
/**
 * Kopiere Diagnose-Bericht in die Zwischenablage
 */


function copyDiagnosticReport() {
  if (!currentDiagnosticData) {
    alert("Keine Diagnose-Daten verfügbar.");
    return;
  }

  var text = currentDiagnosticData.diagnostic_support || currentDiagnosticData.diagnostic_summary || "Keine Diagnose-Daten"; // Moderne Clipboard API

  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(function () {
      showToast("Diagnose-Bericht wurde in die Zwischenablage kopiert.", "success");
    })["catch"](function (err) {
      console.error("Fehler beim Kopieren:", err);
      fallbackCopyToClipboard(text);
    });
  } else {
    // Fallback für ältere Browser
    fallbackCopyToClipboard(text);
  }
}
/**
 * Fallback-Methode zum Kopieren (für ältere Browser)
 */


function fallbackCopyToClipboard(text) {
  var textArea = document.createElement("textarea");
  textArea.value = text;
  textArea.style.position = "fixed";
  textArea.style.left = "-999999px";
  textArea.style.top = "-999999px";
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();

  try {
    var successful = document.execCommand("copy");

    if (successful) {
      showToast("Diagnose-Bericht wurde in die Zwischenablage kopiert.", "success");
    } else {
      showToast("Kopieren fehlgeschlagen. Bitte manuell kopieren.", "warning");
    }
  } catch (err) {
    console.error("Fehler beim Kopieren:", err);
    showToast("Kopieren nicht unterstützt. Bitte manuell kopieren.", "warning");
  }

  document.body.removeChild(textArea);
}
/**
 * Download Diagnose-Bericht als Textdatei
 */


function downloadDiagnosticReport() {
  if (!currentDiagnosticData) {
    alert("Keine Diagnose-Daten verfügbar.");
    return;
  }

  var text = currentDiagnosticData.diagnostic_support || currentDiagnosticData.diagnostic_summary || JSON.stringify(currentDiagnosticData.diagnostics, null, 2);
  var timestamp = new Date().toISOString().replace(/[:.]/g, "-").substring(0, 19);
  var filename = "intrarp-diagnostic-".concat(timestamp, ".txt"); // Erstelle Blob und Download-Link

  var blob = new Blob([text], {
    type: "text/plain;charset=utf-8"
  });
  var url = URL.createObjectURL(blob);
  var link = document.createElement("a");
  link.href = url;
  link.download = filename;
  link.style.display = "none";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link); // Cleanup

  setTimeout(function () {
    return URL.revokeObjectURL(url);
  }, 100);
  showToast("Diagnose-Bericht wird heruntergeladen...", "success");
}
/**
 * Zeige Bootstrap-Toast (falls vorhanden)
 */


function showToast(message) {
  var type = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : "info";

  // Versuche Bootstrap Toast
  if (typeof bootstrap !== "undefined" && bootstrap.Toast) {
    var toastHTML = "\n            <div class=\"toast align-items-center text-white bg-".concat(type, " border-0\" role=\"alert\">\n                <div class=\"d-flex\">\n                    <div class=\"toast-body\">").concat(escapeHtml(message), "</div>\n                    <button type=\"button\" class=\"btn-close btn-close-white me-2 m-auto\" data-bs-dismiss=\"toast\"></button>\n                </div>\n            </div>\n        ");
    var toastContainer = document.getElementById("toast-container");

    if (!toastContainer) {
      toastContainer = document.createElement("div");
      toastContainer.id = "toast-container";
      toastContainer.className = "toast-container position-fixed bottom-0 end-0 p-3";
      document.body.appendChild(toastContainer);
    }

    var toastElement = document.createElement("div");
    toastElement.innerHTML = toastHTML;
    toastContainer.appendChild(toastElement.firstElementChild);
    var toast = new bootstrap.Toast(toastContainer.lastElementChild);
    toast.show(); // Auto-remove nach Ausblenden

    toastElement.addEventListener("hidden.bs.toast", function () {
      toastElement.remove();
    });
  } else {
    // Fallback auf Alert
    alert(message);
  }
}
/**
 * Escape HTML für sichere Anzeige
 */


function escapeHtml(text) {
  var div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}
/**
 * Füge Event-Handler für Diagnose-Buttons hinzu
 */


function attachDiagnosticHandlers() {
  // Diese Funktion wird nach dem Einfügen der HTML aufgerufen
  // Event-Handler für dynamisch erstellte Buttons
  // Kopieren-Button
  var copyBtn = document.querySelector('[onclick="copyDiagnosticReport()"]');

  if (copyBtn) {
    copyBtn.onclick = copyDiagnosticReport;
  } // Download-Button


  var downloadBtn = document.querySelector('[onclick="downloadDiagnosticReport()"]');

  if (downloadBtn) {
    downloadBtn.onclick = downloadDiagnosticReport;
  }
}
/**
 * Zeige Diagnose in Modal
 */


function showDiagnosticModal(diagnostics) {
  // Erstelle Modal falls nicht vorhanden
  var modal = document.getElementById("diagnosticModal");

  if (!modal) {
    var modalHTML = "\n            <div class=\"modal fade\" id=\"diagnosticModal\" tabindex=\"-1\">\n                <div class=\"modal-dialog modal-lg modal-dialog-scrollable\">\n                    <div class=\"modal-content\">\n                        <div class=\"modal-header\">\n                            <h5 class=\"modal-title\">Update-Diagnose</h5>\n                            <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>\n                        </div>\n                        <div class=\"modal-body\" id=\"diagnostic-modal-body\">\n                        </div>\n                        <div class=\"modal-footer\">\n                            <button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Schlie\xDFen</button>\n                        </div>\n                    </div>\n                </div>\n            </div>\n        ";
    document.body.insertAdjacentHTML("beforeend", modalHTML);
    modal = document.getElementById("diagnosticModal");
  } // Setze Content


  showDiagnosticReport(diagnostics, "diagnostic-modal-body"); // Öffne Modal

  if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
    var bsModal = new bootstrap.Modal(modal);
    bsModal.show();
  } else {
    modal.style.display = "block";
    modal.classList.add("show");
  }
} // Beispiel-Integration für Update-Prozess

/**
 * Beispiel: Update mit Diagnose-Anzeige
 */


function performUpdate(updateUrl, version) {
  var statusDiv = document.getElementById("update-status");
  statusDiv.innerHTML = '<div class="spinner-border"></div> Update wird durchgeführt...';
  fetch("/api/system/update", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      url: updateUrl,
      version: version
    })
  }).then(function (response) {
    return response.json();
  }).then(function (data) {
    if (data.success) {
      statusDiv.innerHTML = '<div class="alert alert-success">✓ Update erfolgreich!</div>';
    } else {
      // Fehler - zeige Diagnose
      statusDiv.innerHTML = '<div class="alert alert-danger">✗ Update fehlgeschlagen</div>';

      if (data.diagnostic_html) {
        // Zeige HTML-Diagnose
        document.getElementById("diagnostic-container").innerHTML = data.diagnostic_html;
        showDiagnosticReport(data, "diagnostic-container");
      } else if (data.diagnostic_summary) {
        // Fallback auf Text
        statusDiv.innerHTML += "<pre>" + escapeHtml(data.diagnostic_summary) + "</pre>";
      } // Optional: Zeige in Modal
      // showDiagnosticModal(data);

    }
  })["catch"](function (error) {
    statusDiv.innerHTML = '<div class="alert alert-danger">Fehler: ' + error.message + "</div>";
  });
} // Export für Module


if (typeof module !== "undefined" && module.exports) {
  module.exports = {
    showDiagnosticReport: showDiagnosticReport,
    showDiagnosticModal: showDiagnosticModal,
    copyDiagnosticReport: copyDiagnosticReport,
    downloadDiagnosticReport: downloadDiagnosticReport
  };
}