/**
 * system-settings.js — Inline-Logic für templates/settings/system/index.php.
 *
 * Bündelt:
 *   - Pre-Release-Checkbox-Sync (zwei Hidden-Inputs am selben State)
 *   - Dev-Branch-Auswahl (Reload mit ?branch=...)
 *   - Update-Install-Flow (Stable + Dev-Branch teilen denselben Progress-Modal)
 *   - Composer-Pending-Modal-Flow (auto-show beim Page-Load wenn pending)
 *
 * Aufruf vom Template aus:
 *   initSystemSettings({
 *     basePath:        '/',
 *     showComposerOnLoad: true,                // setzt das Modal auf DOMContentLoaded auf
 *     installButton: {                         // optional, nur wenn ein Update verfügbar ist
 *       buttonId:   'install-update-btn',
 *       formId:     'install-update-form',
 *       newVersion: 'X.Y.Z',
 *       onSuccessReload: true,                 // window.location.reload nach Erfolg
 *     },
 *     devInstallButton: {                      // optional, nur in dev-Mode
 *       buttonId:    'dev-install-btn',
 *       formId:      'dev-install-form',
 *       branch:      'main',
 *       sha:         'abc12345',
 *       successUrl:  '?dev',                   // window.location.href nach Erfolg
 *     },
 *   });
 */
(function (global) {
  'use strict';

  // ── Pre-Release-Checkbox synchronisiert sich auf zwei Hidden-Inputs ──

  function bindPrereleaseCheckbox() {
    const checkbox          = document.getElementById('include-prerelease-check');
    const mainFormHidden    = document.getElementById('include-prerelease-hidden');
    const forceRefreshHidden = document.getElementById('force-refresh-prerelease');
    if (!checkbox) return;

    checkbox.addEventListener('change', function () {
      const value = this.checked ? '1' : '0';
      if (mainFormHidden)     mainFormHidden.value     = value;
      if (forceRefreshHidden) forceRefreshHidden.value = value;
    });
  }

  // ── Dev-Branch-Auswahl reloaded mit ?branch=... ──

  function bindDevBranchSelect() {
    const select = document.getElementById('dev-branch-select');
    if (!select) return;

    select.addEventListener('change', function () {
      if (this.value) {
        window.location.href = '?dev&branch=' + encodeURIComponent(this.value);
      }
    });
  }

  // ── Generischer Progress-Modal-Upload-Flow ───────────────────────
  //
  // Wird sowohl vom Stable-Update als auch vom Dev-Branch-Install genutzt
  // — beide schicken ein Form an die aktuelle URL, zeigen während dessen
  // einen Modal mit Fake-Progress, und laden nach Erfolg neu bzw.
  // navigieren auf eine andere URL. Vorher waren beide Flows zwei fast
  // identische ~100-Zeilen-Scripts inline im Template.

  const STABLE_UPDATE_STEPS = [
    { percent: 10, text: 'Download wird vorbereitet...' },
    { percent: 25, text: 'Update wird heruntergeladen...' },
    { percent: 40, text: 'Dateien werden extrahiert...' },
    { percent: 55, text: 'Backup wird erstellt...' },
    { percent: 70, text: 'Update wird installiert...' },
    { percent: 85, text: 'Dateien werden kopiert...' },
    { percent: 95, text: 'Installation wird abgeschlossen...' },
  ];

  const DEV_BRANCH_STEPS = [
    { percent: 10, text: 'Download wird vorbereitet...' },
    { percent: 25, text: 'Branch-Commit wird heruntergeladen...' },
    { percent: 40, text: 'Dateien werden extrahiert...' },
    { percent: 55, text: 'Backup wird erstellt...' },
    { percent: 70, text: 'Update wird installiert...' },
    { percent: 85, text: 'Dateien werden kopiert...' },
    { percent: 95, text: 'Installation wird abgeschlossen...' },
  ];

  /**
   * Startet den Progress-Modal-Upload.
   *
   * @param {Object}  opts
   * @param {string}  opts.formId        ID des Forms, dessen Daten gepostet werden
   * @param {Array}   opts.steps         Liste von {percent, text} für die Fake-Progress-Anzeige
   * @param {string}  opts.failureTitle  Modal-Titel bei Fehler
   * @param {Function} opts.onSuccess    Callback nach erfolgreichem Upload
   */
  function runProgressUpload(opts) {
    const modalElement = document.getElementById('update-progress-modal');
    const progressBar  = document.getElementById('update-progress-bar');
    const progressText = document.getElementById('update-progress-text');
    const statusText   = document.getElementById('update-status-text');

    const progressModal = new global.bootstrap.Modal(modalElement);
    progressModal.show();
    modalElement.setAttribute('data-bs-backdrop', 'static');
    modalElement.setAttribute('data-bs-keyboard', 'false');

    let currentStep = 0;
    const advance = () => {
      if (currentStep >= opts.steps.length) return;
      const step = opts.steps[currentStep++];
      progressBar.style.width   = step.percent + '%';
      progressText.textContent  = step.percent + '%';
      statusText.innerHTML      = '<small class="text-gray-400">' + step.text + '</small>';
    };
    const interval = setInterval(advance, 2000);
    advance();

    const formData = new FormData(document.getElementById(opts.formId));

    fetch(window.location.href, {
      method:  'POST',
      body:    formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    .then(async (response) => {
      clearInterval(interval);
      if (!response.ok) {
        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
      }
      const result = await response.json();
      if (result.success) {
        progressBar.style.width  = '100%';
        progressText.textContent = '100%';
        statusText.innerHTML     = '<small class="text-[#6abf76]"><i class="fa-solid fa-check-circle"></i> Update abgeschlossen!</small>';
        setTimeout(opts.onSuccess, 1500);
      } else {
        showFailure(modalElement, progressBar, statusText, opts.failureTitle, result.message || 'Unbekannter Fehler beim Update.');
      }
    })
    .catch((err) => {
      clearInterval(interval);
      showFailure(modalElement, progressBar, statusText, opts.failureTitle, 'Netzwerkfehler: ' + err.message);
    });
  }

  function showFailure(modalElement, progressBar, statusText, title, message) {
    progressBar.classList.remove('progress-bar-animated');
    progressBar.classList.add('bg-danger');

    statusText.innerHTML = '<small class="text-[#d46b6b]"><i class="fa-solid fa-exclamation-triangle"></i> </small>';
    statusText.querySelector('small').appendChild(document.createTextNode(message));

    setTimeout(() => {
      modalElement.querySelector('.modal-header').innerHTML =
        '<h5 class="modal-title text-[#d46b6b]"><i class="fa-solid fa-exclamation-triangle mr-2"></i>' + title + '</h5>' +
        '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
      const alertInfo = modalElement.querySelector('.modal-body .alert-info');
      if (alertInfo) alertInfo.classList.add('hidden');
    }, 1000);
  }

  // ── Update-Install-Button (Stable-Release) ───────────────────────

  function bindInstallButton(cfg) {
    if (!cfg) return;
    const btn = document.getElementById(cfg.buttonId);
    if (!btn) return;

    btn.addEventListener('click', async () => {
      const confirmed = await global.showConfirm(
        'Update auf Version ' + cfg.newVersion + ' installieren?\n\n' +
        'Ein Backup wird automatisch erstellt.\n' +
        'Dieser Vorgang kann einige Minuten dauern.', {
          title:        'Update installieren?',
          confirmText:  'Installieren',
          cancelText:   'Abbrechen',
          confirmClass: 'btn-success',
          danger:       false,
        }
      );
      if (!confirmed) return;

      runProgressUpload({
        formId:       cfg.formId,
        steps:        STABLE_UPDATE_STEPS,
        failureTitle: 'Update fehlgeschlagen',
        onSuccess:    () => { window.location.reload(); },
      });
    });
  }

  // ── Dev-Branch-Install-Button ────────────────────────────────────

  function bindDevInstallButton(cfg) {
    if (!cfg) return;
    const btn = document.getElementById(cfg.buttonId);
    if (!btn) return;

    btn.addEventListener('click', async () => {
      const confirmed = await global.showConfirm(
        'Branch "' + cfg.branch + '" (Commit ' + cfg.sha + ') installieren?\n\n' +
        'Ein Backup wird automatisch erstellt.\n' +
        'Dieser Vorgang kann einige Minuten dauern.', {
          title:        'Branch-Update installieren?',
          confirmText:  'Installieren',
          cancelText:   'Abbrechen',
          confirmClass: 'btn-warning',
          danger:       false,
        }
      );
      if (!confirmed) return;

      runProgressUpload({
        formId:       cfg.formId,
        steps:        DEV_BRANCH_STEPS,
        failureTitle: 'Update fehlgeschlagen',
        onSuccess:    () => { window.location.href = cfg.successUrl || '?dev'; },
      });
    });
  }

  // ── Composer-Pending-Modal ───────────────────────────────────────

  function bindComposerModal(cfg) {
    let composerModal = null;
    const basePath = cfg.basePath || '/';

    function showComposerModal() {
      composerModal = new global.bootstrap.Modal(document.getElementById('composer-modal'));
      composerModal.show();
      checkComposerStatus();
    }

    function checkComposerStatus() {
      fetch(basePath + 'api/system/composer-status?action=check', {
        method:  'GET',
        headers: { 'Content-Type': 'application/json' },
      })
      .then(r => r.json())
      .then(data => {
        if (data.pending) {
          executeComposerInstall();
        } else {
          dismissComposerModal();
        }
      })
      .catch(err => {
        console.error('Error checking composer status:', err);
        showComposerError('Fehler beim Prüfen des Composer-Status: ' + err.message);
      });
    }

    function executeComposerInstall() {
      fetch(basePath + 'api/system/composer-status?action=execute', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          showComposerSuccess();
        } else {
          showComposerError(data.message || 'Unbekannter Fehler bei der Composer-Installation.');
        }
      })
      .catch(err => {
        console.error('Error executing composer install:', err);
        showComposerError('Fehler beim Ausführen von Composer: ' + err.message);
      });
    }

    function showComposerSuccess() {
      document.getElementById('composer-status-content').style.display = 'none';
      document.getElementById('composer-error-content').style.display  = 'none';
      document.getElementById('composer-success-content').style.display = 'block';
    }

    function showComposerError(message) {
      document.getElementById('composer-status-content').style.display  = 'none';
      document.getElementById('composer-success-content').style.display = 'none';
      document.getElementById('composer-error-message').textContent     = message;
      document.getElementById('composer-error-content').style.display   = 'block';
    }

    function retryComposerInstall() {
      document.getElementById('composer-status-content').style.display  = 'block';
      document.getElementById('composer-success-content').style.display = 'none';
      document.getElementById('composer-error-content').style.display   = 'none';
      executeComposerInstall();
    }

    function dismissComposerModal() {
      if (composerModal) composerModal.hide();
    }

    if (cfg.showOnLoad) {
      document.addEventListener('DOMContentLoaded', showComposerModal);
    }

    // Inline-onclick ersetzen — Listener auf den Modal-Buttons binden
    document.addEventListener('DOMContentLoaded', function () {
      const reloadBtn  = document.getElementById('reload-page-btn');
      const retryBtn   = document.getElementById('retry-composer-btn');
      const dismissBtn = document.getElementById('dismiss-composer-btn');
      if (reloadBtn)  reloadBtn.addEventListener('click', () => location.reload());
      if (retryBtn)   retryBtn.addEventListener('click', retryComposerInstall);
      if (dismissBtn) dismissBtn.addEventListener('click', dismissComposerModal);
    });
  }

  // ── Public Entry-Point ───────────────────────────────────────────

  global.initSystemSettings = function (config) {
    bindPrereleaseCheckbox();
    bindDevBranchSelect();
    bindInstallButton(config.installButton || null);
    bindDevInstallButton(config.devInstallButton || null);
    bindComposerModal({
      basePath:   config.basePath,
      showOnLoad: !!config.showComposerOnLoad,
    });
  };
})(window);
