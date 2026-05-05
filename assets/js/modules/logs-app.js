/**
 * System-Logs Page-App.
 *
 * Wird ausschließlich von templates/settings/system/logs.php verwendet.
 * Erwartet vor dem Modul-Load eine globale Config:
 *
 *   window.LogsAppConfig = {
 *       logsApiUrl:    "...settings/system/logs",
 *       initialGroups: [...],
 *       csrfToken:     "..."
 *   };
 *
 * Enthält zwei IIFEs:
 *   1. Inbox + Lookup + Suche: zeigt Error-Groups, hat Filter/Suche und
 *      die Error-ID-Lookup-Funktion an der Hero-Section.
 *   2. Failed-Jobs: Retry/Delete/Bulk-Delete für persistente Job-Fails.
 */
(function () {
    const apiUrl = window.LogsAppConfig.logsApiUrl;
    const initialGroups = window.LogsAppConfig.initialGroups;
    const inboxList = document.getElementById('inboxList');

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }
    // Escape für HTML-Attribute (escapeHtml reicht nicht — Quotes müssen auch ersetzt werden,
    // sonst bricht data-copy-text="..." wenn der Text Anführungszeichen enthält)
    function escapeAttr(text) {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function levelBadge(level) {
        const lvl = String(level || '').toUpperCase();
        const map = {
            'CRITICAL': 'status-danger',
            'ERROR':    'status-danger',
            'WARNING':  'status-warning',
            'NOTICE':   'status-info',
            'INFO':     'status-info',
            'DEBUG':    'status-muted',
        };
        const cls = map[lvl] || 'status-muted';
        return '<span class="badge-status ' + cls + '"><span class="status-dot"></span>' + escapeHtml(lvl) + '</span>';
    }

    function timeAgo(datetime) {
        if (!datetime) return '';
        const then = new Date(datetime.replace(' ', 'T')).getTime();
        const diff = Date.now() - then;
        if (isNaN(diff)) return datetime;
        const sec = Math.floor(diff / 1000);
        if (sec < 60) return 'gerade eben';
        const min = Math.floor(sec / 60);
        if (min < 60) return 'vor ' + min + ' Min.';
        const hr = Math.floor(min / 60);
        if (hr < 24) return 'vor ' + hr + ' Std.';
        const days = Math.floor(hr / 24);
        if (days < 7) return 'vor ' + days + ' Tag' + (days === 1 ? '' : 'en');
        return datetime.substring(0, 16);
    }

    function buildReportText(entry, group) {
        const lines = [];
        lines.push('=== intraRP Error Report ===');
        if (entry.error_id) lines.push('Error-ID:   ' + entry.error_id);
        if (entry.level)    lines.push('Level:      ' + entry.level);
        if (entry.datetime) lines.push('Zeitpunkt:  ' + entry.datetime);
        if (entry.exception) lines.push('Exception:  ' + entry.exception);
        if (entry.file_path || entry.file) {
            lines.push('Datei:      ' + (entry.file_path || entry.file) + (entry.line ? ':' + entry.line : ''));
        }
        if (entry.message)  lines.push('Message:    ' + entry.message);
        if (group && group.count > 1) {
            lines.push('Vorkommen:  ' + group.count + '× (erst: ' + group.first_seen + ', letzt: ' + group.last_seen + ')');
        }
        if (group && group.error_ids && group.error_ids.length > 0) {
            lines.push('Error-IDs:  ' + group.error_ids.join(', ') + (group.count > group.error_ids.length ? ' (+' + (group.count - group.error_ids.length) + ' weitere)' : ''));
        }
        if (entry.source_file) lines.push('Logfile:    ' + entry.source_file);
        if (entry.trace) {
            lines.push('');
            lines.push('--- Stack-Trace ---');
            lines.push(entry.trace);
        }
        const skipKeys = ['error_id', 'exception', 'file', 'line', 'trace'];
        const extraKeys = Object.keys(entry.context || {}).filter(k => skipKeys.indexOf(k) === -1);
        if (extraKeys.length > 0) {
            lines.push('');
            lines.push('--- Context ---');
            lines.push(JSON.stringify(
                Object.fromEntries(extraKeys.map(k => [k, entry.context[k]])),
                null, 2
            ));
        }
        return lines.join('\n');
    }

    function renderEntryDetail(entry, group) {
        const filePath = entry.file_path || '';
        const fileShort = entry.file || '–';
        const line = entry.line ? ':' + entry.line : '';
        const exception = entry.exception || '–';
        const trace = entry.trace || '';
        const reportText = buildReportText(entry, group);

        let html = '';

        // Action bar: Kopier-Buttons
        html += '<div class="logs-detail-actions">';
        html += '<button type="button" class="ignis-btn ignis-btn--soft-primary ignis-btn--sm copy-btn" data-copy-text="' + escapeAttr(reportText) + '">';
        html += '<i class="fa-solid fa-copy mr-1"></i>Kompletten Report kopieren</button>';
        if (entry.error_id) {
            html += '<button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm copy-btn" data-copy="' + escapeAttr(entry.error_id) + '">';
            html += '<i class="fa-solid fa-hashtag mr-1"></i>' + escapeHtml(entry.error_id) + '</button>';
        }
        if (trace) {
            html += '<button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm copy-btn" data-copy-text="' + escapeAttr(trace) + '">';
            html += '<i class="fa-solid fa-list-ul mr-1"></i>Nur Trace kopieren</button>';
        }
        html += '</div>';

        // Message (prominenteste Info)
        html += '<div class="logs-detail-section">';
        html += '<div class="logs-detail-label">Fehlermeldung</div>';
        html += '<div class="logs-detail-value" style="font-size:0.92rem;">' + escapeHtml(entry.message) + '</div>';
        html += '</div>';

        // Kompaktes Grid mit allen Kern-Infos
        html += '<div class="logs-detail-section logs-detail-grid">';
        html += '<div><div class="logs-detail-label">Exception</div><div class="logs-detail-value">' + escapeHtml(exception) + '</div></div>';
        html += '<div><div class="logs-detail-label">Datei</div><div class="logs-detail-value">' + escapeHtml(fileShort + line);
        if (filePath && filePath !== fileShort) {
            html += '<div style="opacity:.5;font-size:0.7rem;margin-top:2px;">' + escapeHtml(filePath) + '</div>';
        }
        html += '</div></div>';
        if (group) {
            html += '<div><div class="logs-detail-label">Zuletzt gesehen</div><div class="logs-detail-value">' + escapeHtml(timeAgo(group.last_seen)) + '<div style="opacity:.5;font-size:0.7rem;margin-top:2px;">' + escapeHtml(group.last_seen) + '</div></div></div>';
            if (group.count > 1) {
                html += '<div><div class="logs-detail-label">Vorkommen</div><div class="logs-detail-value">' + group.count + '× seit ' + escapeHtml(group.first_seen) + '</div></div>';
            }
        }
        if (entry.source_file) {
            html += '<div><div class="logs-detail-label">Logfile</div><div class="logs-detail-value">' + escapeHtml(entry.source_file) + '</div></div>';
        }
        html += '</div>';

        // Error-IDs (Chips, falls Gruppe)
        if (group && group.error_ids && group.error_ids.length > 1) {
            html += '<div class="logs-detail-section">';
            html += '<div class="logs-detail-label">Error-IDs dieser Gruppe</div>';
            html += '<div class="logs-id-list">';
            group.error_ids.forEach(id => {
                html += '<span class="logs-id-pill copy-btn" data-copy="' + escapeAttr(id) + '" title="Klick zum Kopieren">' + escapeHtml(id) + '</span>';
            });
            if (group.count > group.error_ids.length) {
                html += '<span class="logs-id-pill" style="cursor:default;opacity:0.55">+ ' + (group.count - group.error_ids.length) + ' weitere</span>';
            }
            html += '</div></div>';
        }

        // Stack-Trace
        if (trace) {
            html += '<div class="logs-detail-section">';
            html += '<div class="logs-detail-label">Stack-Trace</div>';
            html += '<pre class="logs-trace">' + escapeHtml(trace) + '</pre>';
            html += '</div>';
        }

        // Extra Context (selten)
        const skipKeys = ['error_id', 'exception', 'file', 'line', 'trace'];
        const extraKeys = Object.keys(entry.context || {}).filter(k => skipKeys.indexOf(k) === -1);
        if (extraKeys.length > 0) {
            html += '<div class="logs-detail-section">';
            html += '<div class="logs-detail-label">Context</div>';
            html += '<pre class="logs-trace">' + escapeHtml(JSON.stringify(
                Object.fromEntries(extraKeys.map(k => [k, entry.context[k]])),
                null, 2
            )) + '</pre>';
            html += '</div>';
        }

        return html;
    }

    function renderGroup(group, idx) {
        const sample = group.sample;
        return `
            <div class="logs-group" data-idx="${idx}">
                <div class="logs-group-row">
                    <div>${levelBadge(sample.level)}</div>
                    <div class="info">
                        <span class="exception">${escapeHtml(sample.exception || '(kein Exception-Typ)')}</span>
                        <div class="message">${escapeHtml(sample.message)}</div>
                        <div class="file">${escapeHtml((sample.file || '–') + (sample.line ? ':' + sample.line : ''))}</div>
                    </div>
                    <div class="count-cell">
                        ${group.count > 1 ? '<span class="ignis-chip ignis-chip--status ignis-chip--warning">×' + group.count + '</span>' : ''}
                    </div>
                    <div class="time-cell">${escapeHtml(timeAgo(group.last_seen))}</div>
                    <div class="chevron"><i class="fa-solid fa-chevron-right"></i></div>
                </div>
                <div class="logs-detail"></div>
            </div>
        `;
    }

    function renderGroups(groups) {
        if (!inboxList) return;
        if (!groups || groups.length === 0) {
            inboxList.innerHTML = '<div class="logs-empty"><i class="fa-solid fa-magnifying-glass"></i><h6>Keine Treffer</h6></div>';
            return;
        }
        inboxList.innerHTML = groups.map((g, i) => renderGroup(g, i)).join('');

        // Toggle nur auf dem Header-Row — Klicks im Detail-Panel schließen nicht mehr
        inboxList.querySelectorAll('.logs-group-row').forEach(headerEl => {
            headerEl.addEventListener('click', function (e) {
                if (e.target.closest('.copy-btn')) return;
                const groupEl = this.closest('.logs-group');
                const idx = parseInt(groupEl.getAttribute('data-idx'), 10);
                const wasExpanded = groupEl.classList.contains('expanded');
                inboxList.querySelectorAll('.logs-group').forEach(g => g.classList.remove('expanded'));
                if (!wasExpanded) {
                    groupEl.classList.add('expanded');
                    const detailEl = groupEl.querySelector('.logs-detail');
                    if (!detailEl.dataset.rendered) {
                        detailEl.innerHTML = renderEntryDetail(groups[idx].sample, groups[idx]);
                        detailEl.dataset.rendered = '1';
                    }
                }
            });
        });
    }

    // Globaler Copy-Handler (funktioniert auch für dynamisch gerenderten Detail-Content)
    async function copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (e) {
            // Fallback für ältere Browser
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (err) {}
            document.body.removeChild(ta);
            return true;
        }
    }
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.copy-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        const text = btn.getAttribute('data-copy-text') || btn.getAttribute('data-copy') || '';
        if (!text) return;
        copyToClipboard(text).then(() => {
            const origHtml = btn.innerHTML;
            const origClass = btn.className;
            if (btn.tagName === 'BUTTON') {
                btn.innerHTML = '<i class="fa-solid fa-check mr-1"></i>Kopiert';
                btn.classList.add('btn-success');
                btn.classList.remove('btn-soft-primary', 'btn-ghost');
            } else {
                btn.classList.add('copied');
                btn.textContent = '✓ kopiert';
            }
            setTimeout(() => {
                btn.innerHTML = origHtml;
                btn.className = origClass;
            }, 1400);
        });
    });

    // ── Initial Render ──
    renderGroups(initialGroups);

    // ── Refresh ──
    document.getElementById('refreshBtn').addEventListener('click', async function () {
        this.disabled = true;
        const orig = this.innerHTML;
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Lade…';
        try {
            const res = await fetch(apiUrl + '?recent=1&grouped=1&limit=200');
            const data = await res.json();
            if (data.success && data.groups) renderGroups(data.groups);
        } catch (e) { console.error(e); }
        finally {
            this.disabled = false;
            this.innerHTML = orig;
        }
    });

    // ── Error-ID Lookup ──
    async function lookupErrorId() {
        const id = (document.getElementById('errorIdInput').value || '').trim().toUpperCase();
        if (!/^[A-F0-9]{8}$/.test(id)) {
            if (typeof showToast === 'function') {
                showToast('Bitte 8-stelligen Hex-Code eingeben.', 'warning');
            } else {
                alert('Bitte 8-stelligen Hex-Code eingeben.');
            }
            return;
        }
        try {
            const res = await fetch(apiUrl + '?id=' + encodeURIComponent(id));
            const data = await res.json();
            if (!data.success) {
                renderGroups([]);
                inboxList.innerHTML = '<div class="logs-empty"><i class="fa-solid fa-magnifying-glass"></i><h6>Keine Treffer für ' + escapeHtml(id) + '</h6><small>Diese Error-ID existiert nicht in den verfügbaren Log-Dateien.</small></div>';
                return;
            }
            const fakeGroup = [{
                fingerprint: 'single',
                count: 1,
                first_seen: data.entry.datetime,
                last_seen: data.entry.datetime,
                sample: data.entry,
                error_ids: data.entry.error_id ? [data.entry.error_id] : [],
            }];
            renderGroups(fakeGroup);
            setTimeout(() => {
                const first = inboxList.querySelector('.logs-group');
                if (first) first.click();
            }, 50);
        } catch (e) {
            inboxList.innerHTML = '<div class="logs-empty"><i class="fa-solid fa-triangle-exclamation"></i><h6>Fehler: ' + escapeHtml(e.message) + '</h6></div>';
        }
    }

    document.getElementById('errorIdLookupBtn').addEventListener('click', lookupErrorId);
    document.getElementById('errorIdInput').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); lookupErrorId(); }
    });
    document.getElementById('errorIdInput').addEventListener('input', function () {
        if (this.value.length === 8) lookupErrorId();
    });

    // ── Volltext-Suche ──
    async function runSearch() {
        const params = new URLSearchParams();
        params.set('q', document.getElementById('searchQuery').value || '');
        const file = document.getElementById('searchFile').value;
        const activeScope = document.querySelector('#inboxScopeFilter [data-scope].active');
        if (activeScope && activeScope.dataset.scope !== 'all') {
            params.set('level', activeScope.dataset.scope);
        }
        if (file) params.set('file', file);
        params.set('limit', '100');

        try {
            const res = await fetch(apiUrl + '?' + params.toString());
            const data = await res.json();
            if (!data.success || !data.results || data.results.length === 0) {
                inboxList.innerHTML = '<div class="logs-empty"><i class="fa-solid fa-magnifying-glass"></i><h6>Keine Treffer</h6></div>';
                return;
            }
            const groups = data.results.map(entry => ({
                fingerprint: entry.fingerprint || '',
                count: 1,
                first_seen: entry.datetime,
                last_seen: entry.datetime,
                sample: entry,
                error_ids: entry.error_id ? [entry.error_id] : [],
            }));
            renderGroups(groups);
        } catch (e) {
            inboxList.innerHTML = '<div class="logs-empty"><i class="fa-solid fa-triangle-exclamation"></i><h6>Fehler: ' + escapeHtml(e.message) + '</h6></div>';
        }
    }

    document.getElementById('searchBtn').addEventListener('click', runSearch);
    document.getElementById('searchQuery').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); runSearch(); }
    });

    // ── Scope-Filter (Alle/Critical/Error/Warning) ──
    document.querySelectorAll('#inboxScopeFilter [data-scope]').forEach(btn => {
        btn.addEventListener('click', async function () {
            document.querySelectorAll('#inboxScopeFilter [data-scope]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const scope = this.dataset.scope;
            if (scope === 'all') {
                renderGroups(initialGroups);
                return;
            }
            try {
                const res = await fetch(apiUrl + '?recent=1&grouped=1&limit=200&level=' + scope);
                const data = await res.json();
                if (data.success && data.groups) renderGroups(data.groups);
            } catch (e) { console.error(e); }
        });
    });

    // ── Reset ──
    document.getElementById('resetBtn').addEventListener('click', function () {
        document.getElementById('searchQuery').value = '';
        document.getElementById('searchFile').value = '';
        document.querySelectorAll('#inboxScopeFilter [data-scope]').forEach(b => b.classList.remove('active'));
        document.querySelector('#inboxScopeFilter [data-scope="all"]').classList.add('active');
        renderGroups(initialGroups);
    });

    // ── Tail ──
    document.querySelectorAll('.tail-btn').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const file = this.getAttribute('data-file');
            try {
                const res = await fetch(apiUrl + '?file_tail=' + encodeURIComponent(file) + '&lines=100');
                const data = await res.json();
                if (!data.success || !data.entries || data.entries.length === 0) return;
                const groups = data.entries.map(entry => ({
                    fingerprint: entry.fingerprint || '',
                    count: 1,
                    first_seen: entry.datetime,
                    last_seen: entry.datetime,
                    sample: entry,
                    error_ids: entry.error_id ? [entry.error_id] : [],
                }));
                renderGroups(groups);
                document.getElementById('inboxContainer').scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (err) { console.error(err); }
        });
    });

    // ── Copy-to-clipboard delegation ──
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.copy-btn');
        if (!btn) return;
        const text = btn.getAttribute('data-copy');
        if (text && navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                if (typeof showToast === 'function') showToast('In Zwischenablage kopiert', 'success');
            });
        }
    });

    // ── Auto-Lookup wenn ?id= in URL ──
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('id')) {
        document.getElementById('errorIdInput').value = urlParams.get('id');
        lookupErrorId();
    }
})();

// ═══════════════════════════════════════════════════════════════════
//  Failed Jobs Section — Expand/Collapse + Retry/Delete/Refresh
// ═══════════════════════════════════════════════════════════════════
(function () {
    const apiUrl      = window.LogsAppConfig.logsApiUrl;
    const failedList  = document.getElementById('failedJobsList');
    if (!failedList) return;

    function toggleFailedRow(rowEl) {
        const groupEl = rowEl.closest('.logs-group');
        if (!groupEl) return;
        groupEl.classList.toggle('expanded');
    }

    // Row-Toggle
    failedList.addEventListener('click', function (e) {
        // Ignorieren wenn auf einen Action-Button geklickt wurde
        if (e.target.closest('.failed-retry-btn, .failed-delete-btn, .copy-btn')) return;

        const headerEl = e.target.closest('.logs-group-row');
        if (headerEl) toggleFailedRow(headerEl);
    });

    const CSRF_TOKEN = window.LogsAppConfig.csrfToken;

    async function postAction(action, id) {
        const body = new URLSearchParams();
        body.set('action', action);
        body.set('csrf_token', CSRF_TOKEN);
        if (id !== null && id !== undefined) body.set('id', String(id));

        const res = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token':  CSRF_TOKEN,
            },
            body: body.toString(),
        });
        return res.json();
    }

    async function refreshFailedJobs() {
        try {
            const res = await fetch(apiUrl + '?failed_jobs=1&limit=100');
            const data = await res.json();
            if (!data.success) return;

            if (!data.table_exists) {
                failedList.innerHTML = '<div class="logs-empty"><i class="fa-solid fa-database"></i><h6>Queue-Tabelle nicht vorhanden</h6><small>Führe die Phinx-Migration aus, um die Job-Queue zu aktivieren.</small></div>';
                return;
            }

            if (!data.jobs || data.jobs.length === 0) {
                failedList.innerHTML = '<div class="logs-empty"><i class="fa-solid fa-circle-check"></i><h6>Keine fehlgeschlagenen Jobs</h6></div>';
                // Seite reloaden um den Zähler/Toolbar-State zu aktualisieren
                setTimeout(() => window.location.reload(), 400);
                return;
            }

            // Re-render — zum Low-Effort einfach reload, damit PHP den kompletten
            // Header inklusive "Alle erneut versuchen"-Button und Zähler aktualisiert.
            window.location.reload();
        } catch (err) {
            console.error('Failed to refresh failed jobs:', err);
        }
    }

    // Refresh-Button
    const refreshBtn = document.getElementById('refreshFailedJobsBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', async function () {
            const orig = this.innerHTML;
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            this.disabled = true;
            try {
                await refreshFailedJobs();
            } finally {
                this.disabled = false;
                this.innerHTML = orig;
            }
        });
    }

    // Einzel-Retry
    failedList.addEventListener('click', async function (e) {
        const btn = e.target.closest('.failed-retry-btn');
        if (!btn) return;
        e.stopPropagation();
        const id = btn.dataset.id;
        if (!id) return;

        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Re-queue…';

        try {
            const data = await postAction('retry', id);
            if (data.success) {
                btn.innerHTML = '<i class="fa-solid fa-check mr-1"></i>In Queue';
                setTimeout(() => refreshFailedJobs(), 700);
            } else {
                btn.innerHTML = '<i class="fa-solid fa-xmark mr-1"></i>Fehler';
                setTimeout(() => { btn.innerHTML = orig; btn.disabled = false; }, 1800);
            }
        } catch (err) {
            btn.innerHTML = orig;
            btn.disabled = false;
            console.error(err);
        }
    });

    // Einzel-Delete
    failedList.addEventListener('click', async function (e) {
        const btn = e.target.closest('.failed-delete-btn');
        if (!btn) return;
        e.stopPropagation();
        const id = btn.dataset.id;
        if (!id) return;

        if (!confirm('Diesen fehlgeschlagenen Job wirklich löschen?')) return;

        btn.disabled = true;
        try {
            const data = await postAction('delete', id);
            if (data.success) {
                const groupEl = btn.closest('.logs-group');
                if (groupEl) groupEl.style.display = 'none';
                setTimeout(() => refreshFailedJobs(), 400);
            } else {
                btn.disabled = false;
            }
        } catch (err) {
            btn.disabled = false;
            console.error(err);
        }
    });

    // Retry All
    const retryAllBtn = document.getElementById('retryAllFailedJobsBtn');
    if (retryAllBtn) {
        retryAllBtn.addEventListener('click', async function () {
            if (!confirm('Alle fehlgeschlagenen Jobs erneut in die Queue legen?')) return;
            const orig = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Re-queue…';
            try {
                const data = await postAction('retry_all', null);
                if (data.success) {
                    this.innerHTML = '<i class="fa-solid fa-check mr-1"></i>' + data.count + ' queued';
                    setTimeout(() => refreshFailedJobs(), 700);
                }
            } catch (err) {
                console.error(err);
                this.innerHTML = orig;
                this.disabled = false;
            }
        });
    }

    // Delete All
    const deleteAllBtn = document.getElementById('deleteAllFailedJobsBtn');
    if (deleteAllBtn) {
        deleteAllBtn.addEventListener('click', async function () {
            if (!confirm('Wirklich ALLE fehlgeschlagenen Jobs löschen? Das kann nicht rückgängig gemacht werden.')) return;
            const orig = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Lösche…';
            try {
                const data = await postAction('delete_all', null);
                if (data.success) {
                    setTimeout(() => refreshFailedJobs(), 400);
                }
            } catch (err) {
                console.error(err);
                this.innerHTML = orig;
                this.disabled = false;
            }
        });
    }
})();
