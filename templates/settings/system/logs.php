<?php
/**
 * View: System-Logs / Error-Lookup
 *
 * @var array<int,array<string,mixed>> $files
 * @var \PDO                           $pdo
 */

use App\Helpers\Flash;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../../assets/components/_base/admin/head.php'; ?>
    <style>
        .log-search-input {
            font-family: 'JetBrains Mono', 'Fira Code', Consolas, monospace;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .log-detail {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-top: 1rem;
        }
        .log-detail dt {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-dimmed, #888);
            margin-top: 0.75rem;
        }
        .log-detail dd {
            font-family: 'JetBrains Mono', Consolas, monospace;
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
            word-break: break-all;
        }
        .log-trace {
            background: #0f1014;
            border: 1px solid #2a2d36;
            color: #d3d6e0;
            padding: 0.85rem 1rem;
            border-radius: 6px;
            font-size: 0.78rem;
            line-height: 1.55;
            font-family: 'JetBrains Mono', Consolas, monospace;
            white-space: pre;
            overflow-x: auto;
            max-height: 480px;
        }
        .log-level-pill {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 2px 8px;
            border-radius: 999px;
            border: 1px solid currentColor;
        }
        .log-level-CRITICAL { color: #ff6b8a; }
        .log-level-ERROR    { color: #ff7d4d; }
        .log-level-WARNING  { color: #ffc857; }
        .log-level-NOTICE   { color: #6ec1ff; }
        .log-level-INFO     { color: #6effaa; }
        .log-level-DEBUG    { color: #b3a6ff; }
        .log-result-row {
            cursor: pointer;
            transition: background 0.1s;
        }
        .log-result-row:hover {
            background: rgba(255, 255, 255, 0.04);
        }
        .log-result-row.active {
            background: rgba(255, 255, 255, 0.07);
        }
        .log-result-message {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-size: 0.82rem;
        }
        .copy-btn {
            cursor: pointer;
            opacity: 0.5;
            transition: opacity 0.15s;
        }
        .copy-btn:hover {
            opacity: 1;
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
                        <span class="current">Logs</span>
                    </nav>
                    <div class="page-header mb-4">
                        <h1>System-Logs</h1>
                    </div>
                    <?php Flash::render(); ?>

                    <!-- Error-ID Lookup -->
                    <div class="intra__tile p-4 mb-4">
                        <h5 class="mb-3"><i class="fa-solid fa-key me-2"></i>Error-ID Lookup</h5>
                        <p class="text-muted small mb-3">
                            Gib die 8-stellige Error-ID aus der Production-Fehlerseite ein, um den vollständigen Stack-Trace und die Fehlerinfo abzurufen.
                        </p>
                        <div class="row g-2">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-hashtag"></i></span>
                                    <input type="text"
                                           id="errorIdInput"
                                           class="form-control log-search-input"
                                           placeholder="A1B2C3D4"
                                           maxlength="8"
                                           autocomplete="off"
                                           pattern="[A-Fa-f0-9]{8}">
                                    <button type="button" class="btn btn-soft-primary" id="errorIdLookupBtn">
                                        <i class="fa-solid fa-magnifying-glass me-1"></i>Suchen
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div id="errorIdResult"></div>
                    </div>

                    <!-- Volltext-Suche -->
                    <div class="intra__tile p-4 mb-4">
                        <h5 class="mb-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Volltext-Suche</h5>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <input type="text"
                                       id="searchQuery"
                                       class="form-control"
                                       placeholder="Suchbegriff (Datei, Klasse, Message…)"
                                       autocomplete="off">
                            </div>
                            <div class="col-md-2">
                                <select id="searchLevel" class="form-select">
                                    <option value="">Alle Level</option>
                                    <option value="CRITICAL">Critical</option>
                                    <option value="ERROR">Error</option>
                                    <option value="WARNING">Warning</option>
                                    <option value="NOTICE">Notice</option>
                                    <option value="INFO">Info</option>
                                    <option value="DEBUG">Debug</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select id="searchFile" class="form-select">
                                    <option value="">Alle Dateien (neueste zuerst)</option>
                                    <?php foreach ($files as $f): ?>
                                        <option value="<?= htmlspecialchars($f['name']) ?>">
                                            <?= htmlspecialchars($f['name']) ?> (<?= number_format($f['size'] / 1024, 0) ?> KB)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-soft-primary w-100" id="searchBtn">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </button>
                            </div>
                        </div>
                        <div id="searchResults"></div>
                    </div>

                    <!-- Log-Files Übersicht -->
                    <div class="intra__tile p-4">
                        <h5 class="mb-3"><i class="fa-solid fa-folder-tree me-2"></i>Verfügbare Log-Dateien</h5>
                        <?php if (empty($files)): ?>
                            <p class="text-muted">Keine Log-Dateien vorhanden.</p>
                        <?php else: ?>
                            <table class="table table-sm">
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
                        <?php endif; ?>
                        <div id="tailResults"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const apiUrl = '<?= BASE_PATH ?>settings/system/logs.php';

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        }

        function levelPill(level) {
            return '<span class="log-level-pill log-level-' + escapeHtml(level) + '">' + escapeHtml(level) + '</span>';
        }

        function copyButton(text) {
            return '<i class="fa-solid fa-copy ms-2 copy-btn" data-copy="' + escapeHtml(text) + '" title="Kopieren"></i>';
        }

        function renderEntryDetail(entry) {
            const file = entry.file || '–';
            const filePath = entry.file_path || '';
            const line = entry.line ? ':' + entry.line : '';
            const exception = entry.exception || '–';
            const errorId = entry.error_id || '–';
            const trace = entry.trace || '';
            const sourceFile = entry.source_file || '';

            let html = '<div class="log-detail">';
            html += '<div class="d-flex justify-content-between align-items-start mb-2">';
            html += '<div>' + levelPill(entry.level) + ' <strong class="ms-2">' + escapeHtml(entry.datetime) + '</strong></div>';
            html += '<small class="text-muted">aus <code>' + escapeHtml(sourceFile) + '</code></small>';
            html += '</div>';

            html += '<dl class="row mb-0">';
            html += '<dt class="col-sm-3">Error-ID</dt>';
            html += '<dd class="col-sm-9"><code>' + escapeHtml(errorId) + '</code>' + (errorId !== '–' ? copyButton(errorId) : '') + '</dd>';

            html += '<dt class="col-sm-3">Exception</dt>';
            html += '<dd class="col-sm-9"><code>' + escapeHtml(exception) + '</code></dd>';

            html += '<dt class="col-sm-3">Datei</dt>';
            html += '<dd class="col-sm-9"><code>' + escapeHtml(file + line) + '</code>'
                  + (filePath ? '<br><small class="text-muted">' + escapeHtml(filePath) + '</small>' : '') + '</dd>';

            html += '<dt class="col-sm-3">Message</dt>';
            html += '<dd class="col-sm-9">' + escapeHtml(entry.message) + '</dd>';

            if (trace) {
                html += '<dt class="col-sm-3">Stack-Trace</dt>';
                html += '<dd class="col-sm-9"><pre class="log-trace">' + escapeHtml(trace) + '</pre></dd>';
            }

            // Restliche Context-Keys
            const skipKeys = ['error_id', 'exception', 'file', 'line', 'trace'];
            const extraKeys = Object.keys(entry.context || {}).filter(k => skipKeys.indexOf(k) === -1);
            if (extraKeys.length > 0) {
                html += '<dt class="col-sm-3">Weiterer Context</dt>';
                html += '<dd class="col-sm-9"><pre class="log-trace">' + escapeHtml(JSON.stringify(
                    Object.fromEntries(extraKeys.map(k => [k, entry.context[k]])),
                    null,
                    2
                )) + '</pre></dd>';
            }
            html += '</dl></div>';
            return html;
        }

        // ── Error-ID Lookup ──
        const errorIdInput = document.getElementById('errorIdInput');
        const errorIdResult = document.getElementById('errorIdResult');
        const errorIdBtn = document.getElementById('errorIdLookupBtn');

        async function lookupErrorId() {
            const id = (errorIdInput.value || '').trim().toUpperCase();
            if (!/^[A-F0-9]{8}$/.test(id)) {
                errorIdResult.innerHTML = '<div class="alert alert-warning mt-3">Bitte 8-stelligen Hex-Code eingeben.</div>';
                return;
            }
            errorIdResult.innerHTML = '<div class="text-muted mt-3"><i class="fa-solid fa-spinner fa-spin"></i> Suche…</div>';

            try {
                const res = await fetch(apiUrl + '?id=' + encodeURIComponent(id));
                const data = await res.json();
                if (!data.success) {
                    errorIdResult.innerHTML = '<div class="alert alert-info mt-3">'
                        + '<i class="fa-solid fa-circle-info me-2"></i>'
                        + escapeHtml(data.message || 'Keine Treffer.')
                        + '</div>';
                    return;
                }
                errorIdResult.innerHTML = renderEntryDetail(data.entry);
            } catch (e) {
                errorIdResult.innerHTML = '<div class="alert alert-danger mt-3">Fehler: ' + escapeHtml(e.message) + '</div>';
            }
        }

        errorIdBtn.addEventListener('click', lookupErrorId);
        errorIdInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                lookupErrorId();
            }
        });

        // ── Volltext-Suche ──
        const searchQuery = document.getElementById('searchQuery');
        const searchLevel = document.getElementById('searchLevel');
        const searchFile = document.getElementById('searchFile');
        const searchBtn = document.getElementById('searchBtn');
        const searchResults = document.getElementById('searchResults');

        async function runSearch() {
            const params = new URLSearchParams();
            params.set('q', searchQuery.value || '');
            if (searchLevel.value) params.set('level', searchLevel.value);
            if (searchFile.value) params.set('file', searchFile.value);
            params.set('limit', '50');

            searchResults.innerHTML = '<div class="text-muted mt-3"><i class="fa-solid fa-spinner fa-spin"></i> Suche…</div>';
            try {
                const res = await fetch(apiUrl + '?' + params.toString());
                const data = await res.json();
                if (!data.success || !data.results || data.results.length === 0) {
                    searchResults.innerHTML = '<div class="alert alert-info mt-3">Keine Treffer.</div>';
                    return;
                }
                renderResultList(searchResults, data.results, data.count);
            } catch (e) {
                searchResults.innerHTML = '<div class="alert alert-danger mt-3">Fehler: ' + escapeHtml(e.message) + '</div>';
            }
        }

        function renderResultList(container, entries, totalCount) {
            let html = '<div class="mt-3 mb-2 text-muted small">' + entries.length + ' von ' + totalCount + ' Einträgen</div>';
            html += '<div class="table-responsive"><table class="table table-sm table-hover">';
            html += '<thead><tr><th style="width:90px">Level</th><th style="width:160px">Zeit</th><th>Message</th><th style="width:120px">Datei</th></tr></thead><tbody>';
            entries.forEach((e, idx) => {
                html += '<tr class="log-result-row" data-idx="' + idx + '">';
                html += '<td>' + levelPill(e.level) + '</td>';
                html += '<td><small>' + escapeHtml(e.datetime) + '</small></td>';
                html += '<td><div class="log-result-message">' + escapeHtml(e.message) + '</div></td>';
                html += '<td><small>' + escapeHtml(e.file || '–') + (e.line ? ':' + e.line : '') + '</small></td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            html += '<div id="resultDetail"></div>';
            container.innerHTML = html;

            // Click → Detail anzeigen
            const detailBox = container.querySelector('#resultDetail');
            container.querySelectorAll('.log-result-row').forEach(row => {
                row.addEventListener('click', function() {
                    container.querySelectorAll('.log-result-row').forEach(r => r.classList.remove('active'));
                    this.classList.add('active');
                    const idx = parseInt(this.getAttribute('data-idx'), 10);
                    detailBox.innerHTML = renderEntryDetail(entries[idx]);
                    detailBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });
            });
        }

        searchBtn.addEventListener('click', runSearch);
        searchQuery.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                runSearch();
            }
        });

        // ── Tail ──
        const tailResults = document.getElementById('tailResults');
        document.querySelectorAll('.tail-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const file = this.getAttribute('data-file');
                tailResults.innerHTML = '<div class="text-muted mt-3"><i class="fa-solid fa-spinner fa-spin"></i> Lade ' + escapeHtml(file) + '…</div>';
                try {
                    const res = await fetch(apiUrl + '?file_tail=' + encodeURIComponent(file) + '&lines=100');
                    const data = await res.json();
                    if (!data.success || !data.entries || data.entries.length === 0) {
                        tailResults.innerHTML = '<div class="alert alert-info mt-3">Keine Einträge in <code>' + escapeHtml(file) + '</code>.</div>';
                        return;
                    }
                    tailResults.innerHTML = '<h6 class="mt-4">Letzte ' + data.entries.length + ' Einträge aus <code>' + escapeHtml(file) + '</code>:</h6>';
                    const inner = document.createElement('div');
                    tailResults.appendChild(inner);
                    renderResultList(inner, data.entries, data.count);
                } catch (e) {
                    tailResults.innerHTML = '<div class="alert alert-danger mt-3">Fehler: ' + escapeHtml(e.message) + '</div>';
                }
            });
        });

        // Copy-to-clipboard delegation
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('copy-btn')) {
                const text = e.target.getAttribute('data-copy');
                if (text && navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(() => {
                        if (typeof showToast === 'function') showToast('In Zwischenablage kopiert', 'success');
                    });
                }
            }
        });

        // Auto-Lookup wenn ?id= in URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('id')) {
            errorIdInput.value = urlParams.get('id');
            lookupErrorId();
        }
    })();
    </script>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
