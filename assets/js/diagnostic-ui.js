/**
 * JavaScript-Funktionen für Diagnose-UI Integration
 *
 * Füge dieses Script in deine Update-Seite ein, um die Diagnose-Funktionen zu nutzen.
 */

// Globale Variable für Diagnose-Daten
let currentDiagnosticData = null;

/**
 * Zeige Diagnose-Bericht im Modal oder Container
 *
 * @param {Object} diagnostics - Diagnose-Daten vom Server
 * @param {string} containerId - ID des Container-Elements
 */
function showDiagnosticReport(
  diagnostics,
  containerId = "diagnostic-container"
) {
  const container = document.getElementById(containerId);
  if (!container) {
    console.error("Container nicht gefunden:", containerId);
    return;
  }

  // Speichere Daten global
  currentDiagnosticData = diagnostics;

  // Zeige HTML-formatierte Diagnose
  if (diagnostics.diagnostic_html) {
    container.innerHTML = diagnostics.diagnostic_html;
  } else if (diagnostics.diagnostic_summary) {
    // Fallback auf Text-Version
    container.innerHTML =
      '<pre class="diagnostic-text">' +
      escapeHtml(diagnostics.diagnostic_summary) +
      "</pre>";
  }

  // Füge JavaScript-Funktionalität hinzu
  attachDiagnosticHandlers();
}

/**
 * Kopiere Diagnose-Bericht in die Zwischenablage
 */
function copyDiagnosticReport() {
  if (!currentDiagnosticData) {
    showAlert("Keine Diagnose-Daten verfügbar.", { type: 'warning', title: 'Hinweis' });
    return;
  }

  const text =
    currentDiagnosticData.diagnostic_support ||
    currentDiagnosticData.diagnostic_summary ||
    "Keine Diagnose-Daten";

  // Moderne Clipboard API
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard
      .writeText(text)
      .then(() => {
        showToast(
          "Diagnose-Bericht wurde in die Zwischenablage kopiert.",
          "success"
        );
      })
      .catch((err) => {
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
  const textArea = document.createElement("textarea");
  textArea.value = text;
  textArea.style.position = "fixed";
  textArea.style.left = "-999999px";
  textArea.style.top = "-999999px";
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();

  try {
    const successful = document.execCommand("copy");
    if (successful) {
      showToast(
        "Diagnose-Bericht wurde in die Zwischenablage kopiert.",
        "success"
      );
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
    showAlert("Keine Diagnose-Daten verfügbar.", { type: 'warning', title: 'Hinweis' });
    return;
  }

  const text =
    currentDiagnosticData.diagnostic_support ||
    currentDiagnosticData.diagnostic_summary ||
    JSON.stringify(currentDiagnosticData.diagnostics, null, 2);

  const timestamp = new Date()
    .toISOString()
    .replace(/[:.]/g, "-")
    .substring(0, 19);
  const filename = `intrarp-diagnostic-${timestamp}.txt`;

  // Erstelle Blob und Download-Link
  const blob = new Blob([text], { type: "text/plain;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  link.style.display = "none";

  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  // Cleanup
  setTimeout(() => URL.revokeObjectURL(url), 100);

  showToast("Diagnose-Bericht wird heruntergeladen...", "success");
}

/**
 * Zeige Bootstrap-Toast (falls vorhanden)
 */
function showToast(message, type = "info") {
  // Versuche Bootstrap Toast
  if (typeof bootstrap !== "undefined" && bootstrap.Toast) {
    const toastHTML = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${escapeHtml(message)}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;

    let toastContainer = document.getElementById("toast-container");
    if (!toastContainer) {
      toastContainer = document.createElement("div");
      toastContainer.id = "toast-container";
      toastContainer.className =
        "toast-container position-fixed bottom-0 end-0 p-3";
      document.body.appendChild(toastContainer);
    }

    const toastElement = document.createElement("div");
    toastElement.innerHTML = toastHTML;
    toastContainer.appendChild(toastElement.firstElementChild);

    const toast = new bootstrap.Toast(toastContainer.lastElementChild);
    toast.show();

    // Auto-remove nach Ausblenden
    toastElement.addEventListener("hidden.bs.toast", () => {
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
  const div = document.createElement("div");
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
  const copyBtn = document.querySelector('[onclick="copyDiagnosticReport()"]');
  if (copyBtn) {
    copyBtn.onclick = copyDiagnosticReport;
  }

  // Download-Button
  const downloadBtn = document.querySelector(
    '[onclick="downloadDiagnosticReport()"]'
  );
  if (downloadBtn) {
    downloadBtn.onclick = downloadDiagnosticReport;
  }
}

/**
 * Zeige Diagnose in Modal
 */
function showDiagnosticModal(diagnostics) {
  // Erstelle Modal falls nicht vorhanden
  let modal = document.getElementById("diagnosticModal");

  if (!modal) {
    const modalHTML = `
            <div class="modal fade" id="diagnosticModal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Update-Diagnose</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="diagnostic-modal-body">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

    document.body.insertAdjacentHTML("beforeend", modalHTML);
    modal = document.getElementById("diagnosticModal");
  }

  // Setze Content
  showDiagnosticReport(diagnostics, "diagnostic-modal-body");

  // Öffne Modal
  if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
  } else {
    modal.style.display = "block";
    modal.classList.add("show");
  }
}

// Beispiel-Integration für Update-Prozess
/**
 * Beispiel: Update mit Diagnose-Anzeige
 */
function performUpdate(updateUrl, version) {
  const statusDiv = document.getElementById("update-status");
  statusDiv.innerHTML =
    '<div class="spinner-border"></div> Update wird durchgeführt...';

  fetch("/api/system/update", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ url: updateUrl, version: version }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        statusDiv.innerHTML =
          '<div class="alert alert-success">✓ Update erfolgreich!</div>';
      } else {
        // Fehler - zeige Diagnose
        statusDiv.innerHTML =
          '<div class="alert alert-danger">✗ Update fehlgeschlagen</div>';

        if (data.diagnostic_html) {
          // Zeige HTML-Diagnose
          document.getElementById("diagnostic-container").innerHTML =
            data.diagnostic_html;
          showDiagnosticReport(data, "diagnostic-container");
        } else if (data.diagnostic_summary) {
          // Fallback auf Text
          statusDiv.innerHTML +=
            "<pre>" + escapeHtml(data.diagnostic_summary) + "</pre>";
        }

        // Optional: Zeige in Modal
        // showDiagnosticModal(data);
      }
    })
    .catch((error) => {
      statusDiv.innerHTML =
        '<div class="alert alert-danger">Fehler: ' + error.message + "</div>";
    });
}

// Export für Module
if (typeof module !== "undefined" && module.exports) {
  module.exports = {
    showDiagnosticReport,
    showDiagnosticModal,
    copyDiagnosticReport,
    downloadDiagnosticReport,
  };
}
