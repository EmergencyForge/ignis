<?php
/**
 * View: System-Logs / Fehlerprotokoll
 *
 * @var array<int,array<string,mixed>> $files
 * @var array<int,array<string,mixed>> $recent
 * @var array<int,array<string,mixed>> $groups
 * @var array<string,mixed>            $stats
 * @var \PDO                           $pdo
 */

use App\Helpers\Flash;
use App\Security\CsrfProtection;

// CSRF-Token für Failed-Jobs-POST-Aktionen generieren/holen
$csrfToken = CsrfProtection::getToken();

/**
 * Mappt einen Log-Level auf das System-Status-Badge.
 */
function logs_level_badge(string $level): string
{
    $level = strtoupper($level);
    $map = [
        'CRITICAL' => 'status-danger',
        'ERROR'    => 'status-danger',
        'WARNING'  => 'status-warning',
        'NOTICE'   => 'status-info',
        'INFO'     => 'status-info',
        'DEBUG'    => 'status-muted',
    ];
    $cls = $map[$level] ?? 'status-muted';
    return "<span class='badge-status {$cls}'><span class='status-dot'></span>" . htmlspecialchars($level) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../../assets/components/_base/admin/head.php'; ?>
    <style>
        /* Lookup-Hero: prominenter Eingabebereich für Error-IDs */
        .logs-lookup-hero {
            position: relative;
        }
        .logs-lookup-hero .lookup-input {
            font-family: var(--font-mono, 'Inconsolata', 'JetBrains Mono', Consolas, monospace);
            letter-spacing: 0.14em;
            text-transform: uppercase;
            font-weight: 600;
        }
        .logs-lookup-hero .lookup-input::placeholder {
            opacity: 0.3;
            letter-spacing: 0.14em;
            font-weight: 400;
        }

        /* Group rows: kompakt, nutzen System-Border-Tokens */
        .logs-group {
            border-bottom: 1px solid var(--bs-border-color, rgba(255, 255, 255, 0.08));
            transition: background-color 0.12s;
        }
        .logs-group:last-child { border-bottom: none; }
        .logs-group:hover { background-color: rgba(255, 255, 255, 0.03); }
        .logs-group.expanded { background-color: rgba(255, 255, 255, 0.045); }

        .logs-group-row {
            display: grid;
            grid-template-columns: 110px 1fr 70px 130px 24px;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            cursor: pointer;
        }
        .logs-group-row .info { min-width: 0; }
        .logs-group-row .info .exception {
            font-family: var(--font-mono, 'Inconsolata', monospace);
            font-weight: 600;
            font-size: 0.85rem;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .logs-group-row .info .message {
            font-size: 0.78rem;
            opacity: 0.7;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-top: 2px;
        }
        .logs-group-row .info .file {
            font-family: var(--font-mono, 'Inconsolata', monospace);
            font-size: 0.7rem;
            opacity: 0.5;
            margin-top: 2px;
        }
        .logs-group-row .count-cell { text-align: center; }
        .logs-group-row .time-cell { text-align: right; font-size: 0.72rem; opacity: 0.65; }
        .logs-group-row .chevron { text-align: center; opacity: 0.45; transition: transform 0.18s; }
        .logs-group.expanded .chevron { transform: rotate(90deg); opacity: 0.85; }

        .logs-detail {
            display: none;
            padding: 14px 18px 18px 18px;
            border-top: 1px dashed var(--bs-border-color, rgba(255, 255, 255, 0.08));
        }
        .logs-group.expanded .logs-detail { display: block; }
        .logs-detail-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .logs-detail-section { margin-bottom: 14px; }
        .logs-detail-section:last-child { margin-bottom: 0; }
        .logs-detail-label {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            opacity: 0.55;
            margin-bottom: 4px;
            font-weight: 600;
        }
        .logs-detail-value {
            font-family: var(--font-mono, 'Inconsolata', monospace);
            font-size: 0.82rem;
            word-break: break-all;
        }
        .logs-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px 18px;
        }
        .logs-trace {
            background: var(--bs-tertiary-bg, #1a1a1a);
            border: 1px solid var(--bs-border-color, rgba(255, 255, 255, 0.1));
            padding: 0.85rem 1rem;
            border-radius: 6px;
            font-size: 0.75rem;
            line-height: 1.55;
            font-family: var(--font-mono, 'Inconsolata', monospace);
            white-space: pre;
            overflow-x: auto;
            max-height: 420px;
            margin-top: 4px;
        }
        .logs-id-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 2px;
        }
        .logs-id-pill {
            background: var(--bs-tertiary-bg, rgba(255, 255, 255, 0.06));
            border: 1px solid var(--bs-border-color, rgba(255, 255, 255, 0.1));
            font-family: var(--font-mono, 'Inconsolata', monospace);
            font-size: 0.72rem;
            padding: 3px 8px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.1s;
        }
        .logs-id-pill:hover { background: var(--bs-secondary-bg, rgba(255, 255, 255, 0.12)); }

        .logs-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2.5rem 1rem;
            opacity: 0.55;
        }
        .logs-empty > i {
            font-size: 2rem !important;
            margin: 0 0 0.75rem 0 !important;
            padding: 0 !important;
            background: none !important;
            display: block !important;
            width: auto !important;
            height: auto !important;
        }
        .logs-empty > h6 { margin-bottom: 0.5rem; }
        .logs-empty > small { max-width: 560px; line-height: 1.5; }

        .copy-btn {
            cursor: pointer;
            opacity: 0.4;
            margin-left: 6px;
            transition: opacity 0.15s;
        }
        .copy-btn:hover { opacity: 1; }

        @media (max-width: 768px) {
            .logs-group-row {
                grid-template-columns: 90px 1fr 50px 24px;
                gap: 10px;
            }
            .logs-group-row .time-cell { display: none; }
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
                            <button type="button" class="btn btn-ghost" id="refreshBtn">
                                <i class="fa-solid fa-rotate me-1"></i>Aktualisieren
                            </button>
                        </div>
                    </div>
                    <?php Flash::render(); ?>

                    <!-- ───────────── HERO: Error-ID Lookup (primärer Use-Case) ───────────── -->
                    <div class="intra__tile p-3 mb-3 logs-lookup-hero">
                        <div class="d-flex align-items-center flex-wrap gap-3">
                            <div class="flex-shrink-0">
                                <div class="fw-semibold"><i class="fa-solid fa-key me-2 text-primary"></i>Error-ID Lookup</div>
                                <div class="text-muted" style="font-size: 0.72rem;">
                                    8-stellige ID aus der Production-Fehlerseite &mdash; z.B. <code>0B29305D</code>
                                </div>
                            </div>
                            <div class="flex-grow-1" style="min-width: 260px;">
                                <div class="input-group">
                                    <input type="text"
                                           id="errorIdInput"
                                           class="form-control lookup-input"
                                           placeholder="A1B2C3D4"
                                           maxlength="8"
                                           autocomplete="off"
                                           pattern="[A-Fa-f0-9]{8}"
                                           autofocus>
                                    <button type="button" class="btn btn-soft-primary" id="errorIdLookupBtn">
                                        <i class="fa-solid fa-magnifying-glass me-1"></i>Suchen
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ───────────── Stats ───────────── -->
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md">
                            <div class="intra__tile p-3 text-center h-100">
                                <div class="text-muted small text-uppercase" style="letter-spacing:0.05em;">Errors gesamt</div>
                                <div class="fs-4 fw-bold mt-1"><?= number_format($stats['total'] ?? 0, 0, ',', '.') ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="intra__tile p-3 text-center h-100">
                                <div class="text-muted small text-uppercase" style="letter-spacing:0.05em;">Letzte 24h</div>
                                <div class="fs-4 fw-bold mt-1 text-warning"><?= number_format($stats['last_24h'] ?? 0, 0, ',', '.') ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="intra__tile p-3 text-center h-100">
                                <div class="text-muted small text-uppercase" style="letter-spacing:0.05em;">Letzte 7 Tage</div>
                                <div class="fs-4 fw-bold mt-1 text-warning"><?= number_format($stats['last_7d'] ?? 0, 0, ',', '.') ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="intra__tile p-3 text-center h-100">
                                <div class="text-muted small text-uppercase" style="letter-spacing:0.05em;">Critical</div>
                                <div class="fs-4 fw-bold mt-1 text-danger"><?= number_format($stats['by_level']['CRITICAL'] ?? 0, 0, ',', '.') ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="intra__tile p-3 text-center h-100">
                                <div class="text-muted small text-uppercase" style="letter-spacing:0.05em;">Error</div>
                                <div class="fs-4 fw-bold mt-1 text-danger"><?= number_format($stats['by_level']['ERROR'] ?? 0, 0, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- ───────────── Browse / Filter / Inbox ───────────── -->
                    <div class="intra__tile p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <div>
                                <h5 class="mb-0"><i class="fa-solid fa-inbox me-2"></i>Letzte Fehler</h5>
                                <small class="text-muted">Gruppiert nach Exception &amp; Datei. Klick zum Aufklappen.</small>
                            </div>
                            <div class="btn-toolbar-group" id="inboxScopeFilter">
                                <button type="button" class="btn active" data-scope="all">Alle</button>
                                <button type="button" class="btn" data-scope="CRITICAL">Critical</button>
                                <button type="button" class="btn" data-scope="ERROR">Error</button>
                                <button type="button" class="btn" data-scope="WARNING">Warning</button>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md">
                                <input type="text"
                                       id="searchQuery"
                                       class="form-control"
                                       placeholder="Volltext-Suche (Datei, Klasse, Message…)"
                                       autocomplete="off">
                            </div>
                            <div class="col-md-3">
                                <select id="searchFile" class="form-select">
                                    <option value="">Alle Dateien</option>
                                    <?php foreach ($files as $f): ?>
                                        <option value="<?= htmlspecialchars($f['name']) ?>">
                                            <?= htmlspecialchars($f['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-auto">
                                <button type="button" class="btn btn-soft-primary" id="searchBtn">
                                    <i class="fa-solid fa-magnifying-glass me-1"></i>Suchen
                                </button>
                                <button type="button" class="btn btn-ghost" id="resetBtn" title="Zurück zur Inbox">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </button>
                            </div>
                        </div>

                        <div id="inboxContainer">
                            <?php if (empty($groups)): ?>
                                <div class="logs-empty">
                                    <i class="fa-solid fa-circle-check"></i>
                                    <h6>Keine Fehler vorhanden</h6>
                                    <small>Es liegen aktuell keine Errors in den Log-Dateien vor.</small>
                                </div>
                            <?php else: ?>
                                <div id="inboxList"></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ───────────── Failed Jobs Section ───────────── -->
                    <?php
                    $failedTotal = $failedJobsStats['total'] ?? 0;
                    $failed24h   = $failedJobsStats['last_24h'] ?? 0;
                    ?>
                    <div class="intra__tile p-3 mt-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-hexagon-exclamation text-warning"></i>
                                <h6 class="mb-0 fw-semibold">Fehlgeschlagene Hintergrund-Jobs</h6>
                                <?php if ($failedTotal > 0): ?>
                                    <span class="badge-status status-danger">
                                        <span class="status-dot"></span><?= (int) $failedTotal ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($failed24h > 0): ?>
                                    <span class="badge-status status-warning">
                                        <span class="status-dot"></span><?= (int) $failed24h ?> in 24h
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="btn-toolbar-group">
                                <button type="button" class="btn btn-sm btn-ghost" id="refreshFailedJobsBtn" title="Liste neu laden">
                                    <i class="fa-solid fa-rotate"></i>
                                </button>
                                <?php if ($failedTotal > 0): ?>
                                    <button type="button" class="btn btn-sm btn-soft-primary" id="retryAllFailedJobsBtn">
                                        <i class="fa-solid fa-arrows-rotate me-1"></i>Alle erneut versuchen
                                    </button>
                                    <button type="button" class="btn btn-sm btn-ghost text-danger" id="deleteAllFailedJobsBtn">
                                        <i class="fa-solid fa-trash me-1"></i>Alle löschen
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div id="failedJobsList">
                            <?php if (empty($failedJobs)): ?>
                                <div class="logs-empty">
                                    <i class="fa-solid fa-circle-check"></i>
                                    <h6>Keine fehlgeschlagenen Jobs</h6>
                                    <small>
                                        Hintergrund-Jobs, die nach allen Retries nicht erfolgreich durchgelaufen sind,
                                        erscheinen hier und können manuell nachverfolgt oder neu gestartet werden.
                                    </small>
                                </div>
                            <?php else: ?>
                                <?php foreach ($failedJobs as $fj): ?>
                                    <div class="logs-group" data-failed-id="<?= (int) $fj['id'] ?>">
                                        <div class="logs-group-row">
                                            <div><span class="badge-status status-danger"><span class="status-dot"></span>FAILED</span></div>
                                            <div class="info">
                                                <span class="exception"><?= htmlspecialchars($fj['job_class'] ?? 'Unbekannter Job') ?></span>
                                                <div class="message"><?= htmlspecialchars($fj['short_message'] ?? '–') ?></div>
                                                <div class="file">Queue: <?= htmlspecialchars($fj['queue']) ?> &middot; UUID: <?= htmlspecialchars(substr($fj['uuid'], 0, 8)) ?>…</div>
                                            </div>
                                            <div class="count-cell"></div>
                                            <div class="time-cell"><?= htmlspecialchars($fj['failed_at_formatted']) ?></div>
                                            <div class="chevron"><i class="fa-solid fa-chevron-right"></i></div>
                                        </div>
                                        <div class="logs-detail" data-rendered="1">
                                            <div class="logs-detail-actions">
                                                <button type="button" class="btn btn-soft-primary btn-sm failed-retry-btn" data-id="<?= (int) $fj['id'] ?>">
                                                    <i class="fa-solid fa-arrows-rotate me-1"></i>Erneut versuchen
                                                </button>
                                                <button type="button" class="btn btn-ghost btn-sm text-danger failed-delete-btn" data-id="<?= (int) $fj['id'] ?>">
                                                    <i class="fa-solid fa-trash me-1"></i>Löschen
                                                </button>
                                                <button type="button" class="btn btn-ghost btn-sm copy-btn" data-copy="<?= htmlspecialchars($fj['uuid'], ENT_QUOTES) ?>" title="UUID kopieren">
                                                    <i class="fa-regular fa-copy"></i> UUID
                                                </button>
                                            </div>
                                            <div class="logs-detail-section logs-detail-grid">
                                                <div>
                                                    <div class="logs-detail-label">Job-Klasse</div>
                                                    <div class="logs-detail-value"><?= htmlspecialchars($fj['job_class'] ?? '–') ?></div>
                                                </div>
                                                <div>
                                                    <div class="logs-detail-label">Queue</div>
                                                    <div class="logs-detail-value"><?= htmlspecialchars($fj['queue']) ?></div>
                                                </div>
                                                <div>
                                                    <div class="logs-detail-label">UUID</div>
                                                    <div class="logs-detail-value" style="word-break:break-all;"><?= htmlspecialchars($fj['uuid']) ?></div>
                                                </div>
                                                <div>
                                                    <div class="logs-detail-label">Fehlgeschlagen</div>
                                                    <div class="logs-detail-value"><?= htmlspecialchars($fj['failed_at_formatted']) ?></div>
                                                </div>
                                            </div>
                                            <div class="logs-detail-section">
                                                <div class="logs-detail-label">Exception</div>
                                                <pre class="logs-trace"><?= htmlspecialchars($fj['exception']) ?></pre>
                                            </div>
                                            <div class="logs-detail-section">
                                                <div class="logs-detail-label">Payload</div>
                                                <pre class="logs-trace"><?= htmlspecialchars($fj['payload']) ?></pre>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ───────────── Files (collapsed) ───────────── -->
                    <details class="intra__tile p-3 mt-3">
                        <summary class="fw-bold" style="cursor:pointer;">
                            <i class="fa-solid fa-folder-tree me-2"></i>Verfügbare Log-Dateien (<?= count($files) ?>)
                        </summary>
                        <table class="table table-striped table-sm mt-3 mb-0">
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
                                                <span class="badge-status status-danger"><span class="status-dot"></span>error</span>
                                            <?php else: ?>
                                                <span class="badge-status status-muted"><span class="status-dot"></span>app</span>
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
            html += '<button type="button" class="btn btn-soft-primary btn-sm copy-btn" data-copy-text="' + escapeAttr(reportText) + '">';
            html += '<i class="fa-solid fa-copy me-1"></i>Kompletten Report kopieren</button>';
            if (entry.error_id) {
                html += '<button type="button" class="btn btn-ghost btn-sm copy-btn" data-copy="' + escapeAttr(entry.error_id) + '">';
                html += '<i class="fa-solid fa-hashtag me-1"></i>' + escapeHtml(entry.error_id) + '</button>';
            }
            if (trace) {
                html += '<button type="button" class="btn btn-ghost btn-sm copy-btn" data-copy-text="' + escapeAttr(trace) + '">';
                html += '<i class="fa-solid fa-list-ul me-1"></i>Nur Trace kopieren</button>';
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
                            ${group.count > 1 ? '<span class="badge-status status-warning"><span class="status-dot"></span>×' + group.count + '</span>' : ''}
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
                    btn.innerHTML = '<i class="fa-solid fa-check me-1"></i>Kopiert';
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
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Lade…';
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
            const activeScope = document.querySelector('#inboxScopeFilter .btn.active');
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
        document.querySelectorAll('#inboxScopeFilter .btn').forEach(btn => {
            btn.addEventListener('click', async function () {
                document.querySelectorAll('#inboxScopeFilter .btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const scope = this.dataset.scope;
                if (scope === 'all') {
                    renderGroups(initialGroups);
                    return;
                }
                try {
                    const res = await fetch(apiUrl + '?recent=1&grouped=1&limit=200&min_level=' + scope);
                    const data = await res.json();
                    if (data.success && data.groups) renderGroups(data.groups);
                } catch (e) { console.error(e); }
            });
        });

        // ── Reset ──
        document.getElementById('resetBtn').addEventListener('click', function () {
            document.getElementById('searchQuery').value = '';
            document.getElementById('searchFile').value = '';
            document.querySelectorAll('#inboxScopeFilter .btn').forEach(b => b.classList.remove('active'));
            document.querySelector('#inboxScopeFilter .btn[data-scope="all"]').classList.add('active');
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
        const apiUrl      = '<?= BASE_PATH ?>settings/system/logs.php';
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

        const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

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
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Re-queue…';

            try {
                const data = await postAction('retry', id);
                if (data.success) {
                    btn.innerHTML = '<i class="fa-solid fa-check me-1"></i>In Queue';
                    setTimeout(() => refreshFailedJobs(), 700);
                } else {
                    btn.innerHTML = '<i class="fa-solid fa-xmark me-1"></i>Fehler';
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
                this.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Re-queue…';
                try {
                    const data = await postAction('retry_all', null);
                    if (data.success) {
                        this.innerHTML = '<i class="fa-solid fa-check me-1"></i>' + data.count + ' queued';
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
                this.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Lösche…';
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
    </script>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
