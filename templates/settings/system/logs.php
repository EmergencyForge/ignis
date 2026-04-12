<?php
/**
 * View: System-Logs / Error-Lookup (WBB-style Inbox)
 *
 * @var array<int,array<string,mixed>> $files
 * @var array<int,array<string,mixed>> $recent
 * @var array<int,array<string,mixed>> $groups
 * @var array<string,mixed>            $stats
 * @var \PDO                           $pdo
 */

use App\Helpers\Flash;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../../assets/components/_base/admin/head.php'; ?>
    <style>
        /* ── Stat Cards ── */
        .log-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 1.25rem;
        }
        .log-stat-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 8px;
            padding: 14px 18px;
        }
        .log-stat-card .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-dimmed, #888);
        }
        .log-stat-card .value {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1.1;
            margin-top: 4px;
            font-family: 'JetBrains Mono', Consolas, monospace;
        }
        .log-stat-card.crit .value { color: #ff6b8a; }
        .log-stat-card.err .value  { color: #ff7d4d; }
        .log-stat-card.warn .value { color: #ffc857; }

        /* ── Inbox / Group Rows ── */
        .log-inbox {
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            overflow: hidden;
        }
        .log-group {
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            transition: background 0.12s;
        }
        .log-group:last-child { border-bottom: none; }
        .log-group:hover { background: rgba(255, 255, 255, 0.025); }
        .log-group.expanded { background: rgba(255, 255, 255, 0.04); }

        .log-group-row {
            display: grid;
            grid-template-columns: 110px 1fr 90px 170px 32px;
            align-items: center;
            gap: 14px;
            padding: 12px 18px;
            cursor: pointer;
        }
        .log-group-row .level-cell {
            display: flex;
            align-items: center;
        }
        .log-group-row .info {
            min-width: 0;
        }
        .log-group-row .info .exception {
            font-family: 'JetBrains Mono', Consolas, monospace;
            font-weight: 700;
            font-size: 0.85rem;
            color: #fff;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .log-group-row .info .message {
            font-size: 0.78rem;
            color: var(--text-dimmed, #999);
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-top: 2px;
        }
        .log-group-row .info .file {
            font-family: 'JetBrains Mono', Consolas, monospace;
            font-size: 0.7rem;
            color: var(--text-dimmed, #777);
            margin-top: 2px;
        }
        .log-group-row .count-cell {
            text-align: center;
            font-family: 'JetBrains Mono', Consolas, monospace;
            font-size: 0.85rem;
            color: var(--text-dimmed, #999);
        }
        .log-group-row .count-cell .badge {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 0.72rem;
            padding: 3px 8px;
            border-radius: 999px;
            font-weight: 700;
        }
        .log-group-row .time-cell {
            font-size: 0.75rem;
            color: var(--text-dimmed, #999);
            text-align: right;
        }
        .log-group-row .chevron {
            color: var(--text-dimmed, #777);
            transition: transform 0.18s;
            text-align: center;
        }
        .log-group.expanded .chevron {
            transform: rotate(90deg);
        }

        /* ── Expanded Detail ── */
        .log-detail-pane {
            display: none;
            padding: 0 18px 18px 18px;
            background: rgba(0, 0, 0, 0.25);
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }
        .log-group.expanded .log-detail-pane {
            display: block;
        }
        .log-detail-pane dl {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 4px 16px;
            margin: 14px 0 0 0;
        }
        .log-detail-pane dt {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-dimmed, #888);
            padding-top: 4px;
        }
        .log-detail-pane dd {
            margin: 0;
            font-family: 'JetBrains Mono', Consolas, monospace;
            font-size: 0.82rem;
            word-break: break-all;
            color: #d3d6e0;
        }
        .log-trace {
            background: #0f1014;
            border: 1px solid #2a2d36;
            color: #d3d6e0;
            padding: 0.85rem 1rem;
            border-radius: 6px;
            font-size: 0.75rem;
            line-height: 1.55;
            font-family: 'JetBrains Mono', Consolas, monospace;
            white-space: pre;
            overflow-x: auto;
            max-height: 420px;
            margin-top: 6px;
        }
        .log-id-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 4px;
        }
        .log-id-pill {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            font-family: 'JetBrains Mono', Consolas, monospace;
            font-size: 0.72rem;
            padding: 3px 8px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.1s;
        }
        .log-id-pill:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        /* ── Level Pills ── */
        .log-level-pill {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 3px 9px;
            border-radius: 4px;
            border: 1px solid currentColor;
            min-width: 78px;
            text-align: center;
        }
        .log-level-CRITICAL { color: #ff6b8a; background: rgba(255, 107, 138, 0.08); }
        .log-level-ERROR    { color: #ff7d4d; background: rgba(255, 125, 77, 0.08); }
        .log-level-WARNING  { color: #ffc857; background: rgba(255, 200, 87, 0.08); }
        .log-level-NOTICE   { color: #6ec1ff; background: rgba(110, 193, 255, 0.08); }
        .log-level-INFO     { color: #6effaa; background: rgba(110, 255, 170, 0.08); }
        .log-level-DEBUG    { color: #b3a6ff; background: rgba(179, 166, 255, 0.08); }

        /* ── Search Bar ── */
        .log-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 1rem;
            align-items: center;
        }
        .log-toolbar input,
        .log-toolbar select {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .log-search-input {
            font-family: 'JetBrains Mono', Consolas, monospace;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .log-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-dimmed, #888);
        }
        .log-empty i {
            font-size: 2.5rem;
            opacity: 0.4;
            margin-bottom: 1rem;
            display: block;
        }

        .copy-btn {
            cursor: pointer;
            opacity: 0.5;
            transition: opacity 0.15s;
        }
        .copy-btn:hover {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .log-group-row {
                grid-template-columns: 90px 1fr 60px 24px;
                gap: 10px;
                padding: 10px 12px;
            }
            .log-group-row .time-cell {
                display: none;
            }
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . '/../../../assets/components/navbar.php'; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <nav class="admin-breadcrumb">
                        <a href="<?= BASE_PATH ?>index.php">Dashboard</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span>Einstellungen</span>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span>System</span>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span class="current">Fehlerprotokoll</span>
                    </nav>
                    <div class="page-header mb-4">
                        <h1>Fehlerprotokoll</h1>
                        <div class="header-actions">
                            <button type="button" class="btn btn-soft-primary" id="refreshBtn">
                                <i class="fa-solid fa-rotate me-1"></i>Aktualisieren
                            </button>
                        </div>
                    </div>
                    <?php Flash::render(); ?>

                    <!-- Stats -->
                    <div class="log-stats">
                        <div class="log-stat-card">
                            <div class="label">Errors gesamt</div>
                            <div class="value"><?= number_format($stats['total'] ?? 0, 0, ',', '.') ?></div>
                        </div>
                        <div class="log-stat-card warn">
                            <div class="label">Letzte 24h</div>
                            <div class="value"><?= number_format($stats['last_24h'] ?? 0, 0, ',', '.') ?></div>
                        </div>
                        <div class="log-stat-card warn">
                            <div class="label">Letzte 7 Tage</div>
                            <div class="value"><?= number_format($stats['last_7d'] ?? 0, 0, ',', '.') ?></div>
                        </div>
                        <div class="log-stat-card crit">
                            <div class="label">Critical</div>
                            <div class="value"><?= number_format($stats['by_level']['CRITICAL'] ?? 0, 0, ',', '.') ?></div>
                        </div>
                        <div class="log-stat-card err">
                            <div class="label">Error</div>
                            <div class="value"><?= number_format($stats['by_level']['ERROR'] ?? 0, 0, ',', '.') ?></div>
                        </div>
                    </div>

                    <!-- Toolbar -->
                    <div class="intra__tile p-3 mb-3">
                        <div class="log-toolbar">
                            <div class="input-group" style="max-width:240px;">
                                <span class="input-group-text"><i class="fa-solid fa-hashtag"></i></span>
                                <input type="text"
                                       id="errorIdInput"
                                       class="form-control log-search-input"
                                       placeholder="Error-ID"
                                       maxlength="8"
                                       autocomplete="off">
                            </div>
                            <input type="text"
                                   id="searchQuery"
                                   class="form-control flex-grow-1"
                                   style="min-width:200px;max-width:400px;"
                                   placeholder="Volltext-Suche (Datei, Exception, Message…)"
                                   autocomplete="off">
                            <select id="searchLevel" class="form-select" style="max-width:140px;">
                                <option value="">Alle Level</option>
                                <option value="CRITICAL">Critical</option>
                                <option value="ERROR">Error</option>
                                <option value="WARNING">Warning</option>
                                <option value="NOTICE">Notice</option>
                                <option value="INFO">Info</option>
                                <option value="DEBUG">Debug</option>
                            </select>
                            <select id="searchFile" class="form-select" style="max-width:240px;">
                                <option value="">Alle Dateien</option>
                                <?php foreach ($files as $f): ?>
                                    <option value="<?= htmlspecialchars($f['name']) ?>">
                                        <?= htmlspecialchars($f['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-soft-primary" id="searchBtn">
                                <i class="fa-solid fa-magnifying-glass me-1"></i>Suchen
                            </button>
                            <button type="button" class="btn btn-ghost" id="resetBtn" title="Zurück zur Inbox">
                                <i class="fa-solid fa-inbox"></i>
                            </button>
                        </div>
                        <div class="text-muted small">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            Fehler werden nach Fingerprint (Exception + Datei + Zeile) gruppiert. Klick auf eine Zeile öffnet den Stack-Trace.
                        </div>
                    </div>

                    <!-- Inbox -->
                    <div id="inbox-container">
                        <?php if (empty($groups)): ?>
                            <div class="log-empty intra__tile">
                                <i class="fa-solid fa-inbox"></i>
                                <h5>Keine Fehler vorhanden</h5>
                                <p>Es liegen aktuell keine Errors in den Log-Dateien vor.</p>
                            </div>
                        <?php else: ?>
                            <div class="log-inbox" id="inboxList"></div>
                        <?php endif; ?>
                    </div>

                    <!-- Files Übersicht (collapsed) -->
                    <details class="intra__tile p-3 mt-4">
                        <summary class="fw-bold" style="cursor:pointer;">
                            <i class="fa-solid fa-folder-tree me-2"></i>Verfügbare Log-Dateien (<?= count($files) ?>)
                        </summary>
                        <table class="table table-sm mt-3 mb-0">
                            <thead>
                                <tr>
                                    <th>Datei</th>
                                    <th class="text-end">Größe</th>
                                    <th>Typ</th>
                                    <th>Letzte Änderung</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $f): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($f['name']) ?></code></td>
                                        <td class="text-end"><?= number_format($f['size'] / 1024, 1) ?> KB</td>
                                        <td>
                                            <?php if ($f['type'] === 'error'): ?>
                                                <span class="badge bg-danger">error</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">app</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d.m.Y H:i', $f['mtime']) ?></td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-soft-primary tail-btn" data-file="<?= htmlspecialchars($f['name']) ?>">
                                                <i class="fa-solid fa-eye me-1"></i>Letzte 100
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </details>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const apiUrl = '<?= BASE_PATH ?>settings/system/logs.php';
        const initialGroups = <?= json_encode($groups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const inboxList = document.getElementById('inboxList');

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        }

        function levelPill(level) {
            return '<span class="log-level-pill log-level-' + escapeHtml(level) + '">' + escapeHtml(level) + '</span>';
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

        function renderEntryDetail(entry, group) {
            const filePath = entry.file_path || '';
            const fileShort = entry.file || '–';
            const line = entry.line ? ':' + entry.line : '';
            const exception = entry.exception || '–';
            const trace = entry.trace || '';

            let html = '<dl>';

            if (group && group.error_ids && group.error_ids.length > 0) {
                html += '<dt>Error-IDs</dt><dd><div class="log-id-list">';
                group.error_ids.forEach(id => {
                    html += '<span class="log-id-pill copy-btn" data-copy="' + escapeHtml(id) + '" title="Klick zum Kopieren">' + escapeHtml(id) + '</span>';
                });
                if (group.count > group.error_ids.length) {
                    html += '<span class="log-id-pill" style="cursor:default;opacity:0.6">+ ' + (group.count - group.error_ids.length) + ' weitere</span>';
                }
                html += '</div></dd>';
            } else if (entry.error_id) {
                html += '<dt>Error-ID</dt><dd><code>' + escapeHtml(entry.error_id) + '</code> <i class="fa-solid fa-copy copy-btn" data-copy="' + escapeHtml(entry.error_id) + '"></i></dd>';
            }

            html += '<dt>Exception</dt><dd><code>' + escapeHtml(exception) + '</code></dd>';
            html += '<dt>Datei</dt><dd><code>' + escapeHtml(fileShort + line) + '</code>'
                  + (filePath ? '<br><small style="opacity:.55">' + escapeHtml(filePath) + '</small>' : '') + '</dd>';
            html += '<dt>Message</dt><dd>' + escapeHtml(entry.message) + '</dd>';

            if (group) {
                html += '<dt>Zuerst gesehen</dt><dd>' + escapeHtml(group.first_seen) + '</dd>';
                html += '<dt>Zuletzt gesehen</dt><dd>' + escapeHtml(group.last_seen) + ' <span style="opacity:.55">(' + timeAgo(group.last_seen) + ')</span></dd>';
            }

            if (trace) {
                html += '<dt>Stack-Trace</dt><dd><pre class="log-trace">' + escapeHtml(trace) + '</pre></dd>';
            }

            const skipKeys = ['error_id', 'exception', 'file', 'line', 'trace'];
            const extraKeys = Object.keys(entry.context || {}).filter(k => skipKeys.indexOf(k) === -1);
            if (extraKeys.length > 0) {
                html += '<dt>Context</dt><dd><pre class="log-trace">' + escapeHtml(JSON.stringify(
                    Object.fromEntries(extraKeys.map(k => [k, entry.context[k]])),
                    null, 2
                )) + '</pre></dd>';
            }

            if (entry.source_file) {
                html += '<dt>Logfile</dt><dd><code>' + escapeHtml(entry.source_file) + '</code></dd>';
            }

            html += '</dl>';
            return html;
        }

        function renderGroup(group, idx) {
            const sample = group.sample;
            const html = `
                <div class="log-group" data-idx="${idx}">
                    <div class="log-group-row">
                        <div class="level-cell">${levelPill(sample.level)}</div>
                        <div class="info">
                            <span class="exception">${escapeHtml(sample.exception || '(kein Exception-Typ)')}</span>
                            <div class="message">${escapeHtml(sample.message)}</div>
                            <div class="file">${escapeHtml((sample.file || '–') + (sample.line ? ':' + sample.line : ''))}</div>
                        </div>
                        <div class="count-cell">
                            ${group.count > 1 ? '<span class="badge">×' + group.count + '</span>' : ''}
                        </div>
                        <div class="time-cell">${escapeHtml(timeAgo(group.last_seen))}</div>
                        <div class="chevron"><i class="fa-solid fa-chevron-right"></i></div>
                    </div>
                    <div class="log-detail-pane"></div>
                </div>
            `;
            return html;
        }

        function renderGroups(groups) {
            if (!groups || groups.length === 0) {
                inboxList.innerHTML = '<div class="log-empty"><i class="fa-solid fa-circle-check"></i><h5>Keine Treffer</h5></div>';
                return;
            }
            inboxList.innerHTML = groups.map((g, i) => renderGroup(g, i)).join('');

            inboxList.querySelectorAll('.log-group').forEach(rowEl => {
                rowEl.addEventListener('click', function (e) {
                    if (e.target.closest('.copy-btn')) return; // ignore copy clicks
                    const idx = parseInt(this.getAttribute('data-idx'), 10);
                    const wasExpanded = this.classList.contains('expanded');
                    inboxList.querySelectorAll('.log-group').forEach(g => g.classList.remove('expanded'));
                    if (!wasExpanded) {
                        this.classList.add('expanded');
                        const detailEl = this.querySelector('.log-detail-pane');
                        if (!detailEl.dataset.rendered) {
                            detailEl.innerHTML = renderEntryDetail(groups[idx].sample, groups[idx]);
                            detailEl.dataset.rendered = '1';
                        }
                    }
                });
            });
        }

        // ── Initial Render ──
        if (inboxList) {
            renderGroups(initialGroups);
        }

        // ── Refresh ──
        document.getElementById('refreshBtn').addEventListener('click', async function () {
            this.disabled = true;
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Lade…';
            try {
                const res = await fetch(apiUrl + '?recent=1&grouped=1&limit=200');
                const data = await res.json();
                if (data.success && data.groups) {
                    renderGroups(data.groups);
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.disabled = false;
                this.innerHTML = '<i class="fa-solid fa-rotate me-1"></i>Aktualisieren';
            }
        });

        // ── Error-ID Lookup ──
        async function lookupErrorId() {
            const id = (document.getElementById('errorIdInput').value || '').trim().toUpperCase();
            if (!/^[A-F0-9]{8}$/.test(id)) {
                alert('Bitte 8-stelligen Hex-Code eingeben.');
                return;
            }
            try {
                const res = await fetch(apiUrl + '?id=' + encodeURIComponent(id));
                const data = await res.json();
                if (!data.success) {
                    inboxList.innerHTML = '<div class="log-empty"><i class="fa-solid fa-magnifying-glass"></i><h5>Keine Treffer für ' + escapeHtml(id) + '</h5><p>Diese Error-ID existiert nicht in den verfügbaren Log-Dateien.</p></div>';
                    return;
                }
                // Wrap single entry as a one-element group
                const fakeGroup = [{
                    fingerprint: 'single',
                    count: 1,
                    first_seen: data.entry.datetime,
                    last_seen: data.entry.datetime,
                    sample: data.entry,
                    error_ids: data.entry.error_id ? [data.entry.error_id] : [],
                }];
                renderGroups(fakeGroup);
                // Auto-expand
                setTimeout(() => {
                    const first = inboxList.querySelector('.log-group');
                    if (first) first.click();
                }, 50);
            } catch (e) {
                inboxList.innerHTML = '<div class="log-empty"><i class="fa-solid fa-triangle-exclamation"></i><h5>Fehler: ' + escapeHtml(e.message) + '</h5></div>';
            }
        }

        document.getElementById('errorIdInput').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                lookupErrorId();
            }
        });
        // Auto-search wenn ID-Feld 8 Zeichen hat
        document.getElementById('errorIdInput').addEventListener('input', function () {
            if (this.value.length === 8) lookupErrorId();
        });

        // ── Volltext-Suche ──
        async function runSearch() {
            const params = new URLSearchParams();
            params.set('q', document.getElementById('searchQuery').value || '');
            const lvl = document.getElementById('searchLevel').value;
            const file = document.getElementById('searchFile').value;
            if (lvl) params.set('level', lvl);
            if (file) params.set('file', file);
            params.set('limit', '100');

            try {
                const res = await fetch(apiUrl + '?' + params.toString());
                const data = await res.json();
                if (!data.success || !data.results || data.results.length === 0) {
                    inboxList.innerHTML = '<div class="log-empty"><i class="fa-solid fa-magnifying-glass"></i><h5>Keine Treffer</h5></div>';
                    return;
                }
                // Convert flat results to single-entry groups
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
                inboxList.innerHTML = '<div class="log-empty"><i class="fa-solid fa-triangle-exclamation"></i><h5>Fehler: ' + escapeHtml(e.message) + '</h5></div>';
            }
        }

        document.getElementById('searchBtn').addEventListener('click', runSearch);
        document.getElementById('searchQuery').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                runSearch();
            }
        });

        // ── Reset ──
        document.getElementById('resetBtn').addEventListener('click', function () {
            document.getElementById('errorIdInput').value = '';
            document.getElementById('searchQuery').value = '';
            document.getElementById('searchLevel').value = '';
            document.getElementById('searchFile').value = '';
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
                    window.scrollTo({ top: inboxList.offsetTop - 80, behavior: 'smooth' });
                } catch (err) {
                    console.error(err);
                }
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
    </script>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
