/**
 * mitarbeiter-profile.js — Inline-Edit + PFP-Upload + Quali-Modal +
 * Invite-Generierung + AJAX-Pagination für die Mitarbeiter-Profilseite.
 *
 * Ersetzt die historisch in templates/mitarbeiter/profile.php inlined
 * Scripts. Bekommt alle dynamischen Werte (User-ID, Permissions-Flags,
 * aktuelle Felddaten) über eine `initMitarbeiterProfile(config)`-Funktion
 * injiziert — keine Globals, kein PHP-im-JS.
 *
 * Erwartete config:
 *   basePath:       string  — BASE_PATH aus PHP
 *   profileId:      number  — intra_mitarbeiter.id
 *   canEdit:        bool    — schaltet PFP-Upload + Inline-Edit + Quali-Modal frei
 *   canInvite:      bool    — schaltet Invite-Button frei
 *   currentData:    object  — initiale Feldwerte (fullname, gebdatum, ...)
 *   showToast:      function (optional) — fallback auf window.showToast
 */
(function (global) {
  'use strict';

  function getToastFn(config) {
    if (typeof config.showToast === 'function') return config.showToast;
    if (typeof global.showToast === 'function') return global.showToast;
    return function (msg) { console.warn('[profile]', msg); };
  }

  function bindInviteButtons(config) {
    if (!config.canInvite) return;

    const toast = getToastFn(config);
    const inviteUrl = config.basePath + 'api/personnel/generate-invite';

    function handleClick(btn) {
      const fullname = btn.dataset.fullname;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Wird erstellt...';

      fetch(inviteUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ label: fullname })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          btn.outerHTML = '<span style="font-size: var(--font-size-sm);"><i class="fa-solid fa-check text-[#6abf76] mr-1"></i>' +
            '<code class="select-all">' + data.inviteUrl + '</code></span>';
          const resultEl = document.getElementById('inviteResult');
          if (resultEl) resultEl.innerHTML = '';
          const banner = document.getElementById('newCreatedBanner');
          if (banner) {
            banner.className = 'alert alert-success mb-3';
            banner.innerHTML = '<i class="fa-solid fa-check mr-2"></i><strong>Einladungslink erstellt!</strong> ' +
              '<code class="select-all">' + data.inviteUrl + '</code>';
          }
        } else {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa-solid fa-paper-plane mr-1"></i>Einladen';
          toast(data.message || 'Fehler beim Erstellen', 'danger');
        }
      })
      .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane mr-1"></i>Einladen';
        toast('Fehler beim Erstellen des Einladungslinks', 'danger');
      });
    }

    const inviteBtn = document.getElementById('generateInviteBtn');
    if (inviteBtn) inviteBtn.addEventListener('click', function () { handleClick(this); });

    const bannerBtn = document.getElementById('bannerInviteBtn');
    if (bannerBtn) bannerBtn.addEventListener('click', function () { handleClick(this); });
  }

  function bindPfpUpload(config) {
    const toast = getToastFn(config);
    const pfpUpload  = document.getElementById('pfp-upload');
    const pfpPreview = document.getElementById('pfp-preview');
    if (!pfpUpload || !pfpPreview) return;

    pfpPreview.addEventListener('click', () => pfpUpload.click());

    pfpUpload.addEventListener('change', function () {
      const file = this.files[0];
      if (!file) return;

      if (file.size > 2 * 1024 * 1024) {
        toast('Datei zu groß (max. 2 MB)', 'danger');
        this.value = '';
        return;
      }

      const formData = new FormData();
      formData.append('pfp', file);
      formData.append('id', String(config.profileId));

      pfpPreview.style.opacity = '0.5';

      fetch(config.basePath + 'api/personnel/upload-pfp', {
        method: 'POST',
        body: formData
      })
      .then(r => r.json())
      .then(data => {
        pfpPreview.style.opacity = '1';
        if (data.success) {
          pfpPreview.src = data.url + '?t=' + Date.now();
          toast('Profilbild aktualisiert', 'success');
        } else {
          toast(data.message || 'Upload fehlgeschlagen', 'danger');
        }
      })
      .catch(() => {
        pfpPreview.style.opacity = '1';
        toast('Upload fehlgeschlagen', 'danger');
      });
    });
  }

  function bindInlineEdit(config) {
    const toast      = getToastFn(config);
    const profileId  = config.profileId;
    const basePath   = config.basePath;
    // Lokale Kopie der Feldwerte; wird nach jedem erfolgreichen Save
    // aktualisiert und ist die Source-of-Truth für das nächste Edit.
    const currentData = Object.assign({}, config.currentData || {});

    document.querySelectorAll('.inline-edit-cell').forEach(function (cell) {
      cell.addEventListener('click', function () {
        if (cell.classList.contains('inline-editing')) return;

        const field = cell.dataset.field;
        const type  = cell.dataset.type;
        const raw   = cell.dataset.raw || cell.textContent.trim();
        const originalText = cell.textContent.trim();

        cell.classList.add('inline-editing');

        let input;
        if (type === 'select') {
          input = document.createElement('select');
          input.className = 'form-select';
          const opts = JSON.parse(cell.dataset.options);
          for (const k in opts) {
            const opt = document.createElement('option');
            opt.value = k;
            opt.textContent = opts[k];
            if (k === String(currentData[field] || raw)) opt.selected = true;
            input.appendChild(opt);
          }
        } else if (type === 'date') {
          input = document.createElement('input');
          input.type = 'date';
          input.className = 'form-control';
          input.value = currentData[field] || raw;
        } else {
          input = document.createElement('input');
          input.type = 'text';
          input.className = 'form-control';
          input.value = currentData[field] !== undefined ? currentData[field] : originalText;
          if (originalText === 'Keine' || originalText === 'N. hinterlegt') {
            input.value = currentData[field] || '';
          }

          if (field === 'discordtag') {
            input.inputMode = 'numeric';
            input.pattern   = '[0-9]{17,20}';
            input.maxLength = 20;
            input.placeholder = 'Discord-ID (17-20 Ziffern)';
          }
        }

        cell.textContent = '';
        cell.appendChild(input);

        if (field === 'dienstnr') {
          input.id = 'dienstnr';
          const statusDiv = document.createElement('div');
          statusDiv.id = 'dienstnr-status';
          statusDiv.className = 'dienstnr-status';
          cell.classList.add('dienstnr-container');
          cell.appendChild(statusDiv);
          const feedbackDiv = document.createElement('div');
          feedbackDiv.id = 'dienstnr-feedback';
          feedbackDiv.className = 'text-danger small';
          feedbackDiv.style.display = 'none';
          cell.appendChild(feedbackDiv);
          if (typeof initDienstnrCheck === 'function') {
            initDienstnrCheck({ basePath: basePath, excludeId: profileId });
          }
        }

        input.focus();
        if (input.select) input.select();

        function save() {
          const newValue = input.value.trim();
          const oldValue = currentData[field] || '';

          if (newValue === oldValue) {
            cancel();
            return;
          }

          if (field === 'dienstnr' && typeof isDienstnrAvailable === 'function' && !isDienstnrAvailable()) {
            toast('Dienstnummer nicht verfügbar', 'danger');
            return;
          }

          cell.classList.add('inline-saving');

          const payload = buildPayload(currentData, profileId);
          payload[field] = newValue;

          fetch(basePath + 'api/personnel/update-profile', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          })
          .then(r => r.json().then(d => ({ ok: r.ok, data: d })))
          .then(res => {
            cell.classList.remove('inline-saving', 'inline-editing');
            if (res.ok && res.data.success) {
              currentData[field] = newValue;
              const d = res.data.display;
              if (type === 'select') {
                const opts = JSON.parse(cell.dataset.options);
                cell.textContent = opts[newValue] || newValue;
              } else if (type === 'date') {
                cell.textContent = d[field === 'gebdatum' ? 'gebdatum' : field] || newValue;
              } else {
                cell.textContent = newValue || (field === 'zusatzqual' ? 'Keine' : 'N. hinterlegt');
              }
              if (d) {
                const pn = document.getElementById('display-profilename');
                if (pn) pn.textContent = d.profileName;
                const dgt = document.getElementById('display-dgtext');
                if (dgt) dgt.textContent = d.dgText;
              }
              toast('Gespeichert', 'success');
            } else {
              cancel();
              toast(res.data.message || 'Fehler', 'danger');
            }
          })
          .catch(() => {
            cell.classList.remove('inline-saving', 'inline-editing');
            cancel();
            toast('Verbindungsfehler', 'danger');
          });
        }

        function cancel() {
          cell.classList.remove('inline-editing', 'dienstnr-container');
          if (type === 'select') {
            const opts = JSON.parse(cell.dataset.options);
            cell.textContent = opts[currentData[field]] || originalText;
          } else {
            cell.textContent = originalText;
          }
        }

        input.addEventListener('keydown', function (e) {
          if (e.key === 'Enter')  { e.preventDefault(); save(); }
          if (e.key === 'Escape') { cancel(); }
        });
        input.addEventListener('blur', function () {
          setTimeout(save, 100);
        });
      });
    });

    // Quali-Modal Save (gemeinsame currentData-Closure mit Inline-Edit)
    const qualiSaveBtn = document.getElementById('qualiSaveBtn');
    if (qualiSaveBtn) {
      qualiSaveBtn.addEventListener('click', function () {
        const qualiForm = document.getElementById('qualiEditForm');
        const payload = buildPayload(currentData, profileId);
        payload.dienstgrad = qualiForm.dienstgrad.value;
        payload.qualird    = qualiForm.qualird.value;
        payload.qualifw2   = qualiForm.qualifw2.value;

        qualiSaveBtn.disabled = true;
        qualiSaveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Speichern...';

        fetch(basePath + 'api/personnel/update-profile', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
          qualiSaveBtn.disabled = false;
          qualiSaveBtn.innerHTML = '<i class="fa-solid fa-check mr-1"></i>Speichern';
          if (data.success && data.display) {
            const dgt = document.getElementById('display-dgtext');
            if (dgt) dgt.textContent = data.display.dgText;
            const dgb = document.getElementById('display-dgbadge');
            if (dgb && data.display.dgBadge) dgb.src = data.display.dgBadge;
            const pn = document.getElementById('display-profilename');
            if (pn) pn.textContent = data.display.profileName;
            const modalEl = document.getElementById('modalQualiEdit');
            if (modalEl && global.bootstrap && global.bootstrap.Modal) {
              const inst = global.bootstrap.Modal.getInstance(modalEl);
              if (inst) inst.hide();
            }
            toast('Rang & Qualifikationen gespeichert', 'success');
            setTimeout(() => location.reload(), 600);
          } else {
            toast(data.message || 'Fehler', 'danger');
          }
        })
        .catch(() => {
          qualiSaveBtn.disabled = false;
          qualiSaveBtn.innerHTML = '<i class="fa-solid fa-check mr-1"></i>Speichern';
          toast('Verbindungsfehler', 'danger');
        });
      });
    }
  }

  function buildPayload(currentData, profileId) {
    return {
      id:         profileId,
      fullname:   currentData.fullname,
      gebdatum:   currentData.gebdatum,
      dienstgrad: currentData.dienstgrad,
      discordtag: currentData.discordtag,
      telefonnr:  currentData.telefonnr,
      dienstnr:   currentData.dienstnr,
      qualird:    currentData.qualird,
      qualifw2:   currentData.qualifw2,
      geschlecht: currentData.geschlecht,
      zusatzqual: currentData.zusatzqual,
      pfp:        ''
    };
  }

  function bindAjaxPagination(config) {
    const basePath  = config.basePath;
    const profileId = config.profileId;

    function ajaxPaginate(containerSel, endpoint) {
      const container = document.querySelector(containerSel);
      if (!container) return;

      container.addEventListener('click', function (e) {
        const link = e.target.closest('.pagination .page-link');
        if (!link || link.closest('.disabled') || link.closest('.active')) return;

        e.preventDefault();
        const href = link.getAttribute('href');
        if (!href || href === '#') return;

        const params = new URLSearchParams(href.split('?')[1] || '');
        let url = basePath + endpoint + '?id=' + profileId;
        params.forEach(function (v, k) {
          if (k !== 'id') url += '&' + k + '=' + v;
        });

        container.style.opacity = '0.5';
        fetch(url)
          .then(r => r.text())
          .then(html => {
            container.innerHTML = html;
            container.style.opacity = '1';
          })
          .catch(() => {
            container.style.opacity = '1';
          });
      });
    }

    ajaxPaginate('.comment-container', 'api/personnel/profile-comments');
    ajaxPaginate('.log-container',     'api/personnel/profile-logs');
  }

  global.initMitarbeiterProfile = function (config) {
    bindInviteButtons(config);
    if (config.canEdit) {
      bindPfpUpload(config);
      bindInlineEdit(config);
    }
    bindAjaxPagination(config);
  };
})(window);
