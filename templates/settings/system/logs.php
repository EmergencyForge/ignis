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
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-6">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item">System</span> <span class="ignis-breadcrumb__item is-active">Fehlerprotokoll</span></nav>
                    <div class="page-header mb-4">
                        <h1>Fehlerprotokoll</h1>
                        <div class="header-actions">
                            <button type="button" class="ignis-btn ignis-btn--ghost" id="refreshBtn">
                                <i class="fa-solid fa-rotate mr-1"></i>Aktualisieren
                            </button>
                        </div>
                    </div>
                    <?php Flash::render(); ?>

                    <!-- ───────────── HERO: Error-ID Lookup (primärer Use-Case) ───────────── -->
                    <div class="intra__tile logs-lookup-hero mb-3 p-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="shrink-0">
                                <div class="font-semibold"><i class="fa-solid fa-key mr-2 text-[#7ba3d4]"></i>Error-ID Lookup</div>
                                <div class="text-gray-400" style="font-size: 0.72rem;">
                                    8-stellige ID aus der Production-Fehlerseite &mdash; z.B. <code>0B29305D</code>
                                </div>
                            </div>
                            <div class="flex-1" style="min-width: 260px;">
                                <div class="input-group">
                                    <input type="text"
                                           id="errorIdInput"
                                           class="ignis-input lookup-input"
                                           placeholder="A1B2C3D4"
                                           maxlength="8"
                                           autocomplete="off"
                                           pattern="[A-Fa-f0-9]{8}"
                                           autofocus>
                                    <button type="button" class="ignis-btn ignis-btn--soft-primary" id="errorIdLookupBtn">
                                        <i class="fa-solid fa-magnifying-glass mr-1"></i>Suchen
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ───────────── Stats ───────────── -->
                    <div class="mb-3 grid grid-cols-2 gap-2 md:grid-cols-5">
                        <div class="intra__tile h-full p-3 text-center">
                            <div class="text-xs uppercase text-gray-400" style="letter-spacing:0.05em;">Errors gesamt</div>
                            <div class="mt-1 text-xl font-bold"><?= number_format($stats['total'] ?? 0, 0, ',', '.') ?></div>
                        </div>
                        <div class="intra__tile h-full p-3 text-center">
                            <div class="text-xs uppercase text-gray-400" style="letter-spacing:0.05em;">Letzte 24h</div>
                            <div class="mt-1 text-xl font-bold text-[#ddb84a]"><?= number_format($stats['last_24h'] ?? 0, 0, ',', '.') ?></div>
                        </div>
                        <div class="intra__tile h-full p-3 text-center">
                            <div class="text-xs uppercase text-gray-400" style="letter-spacing:0.05em;">Letzte 7 Tage</div>
                            <div class="mt-1 text-xl font-bold text-[#ddb84a]"><?= number_format($stats['last_7d'] ?? 0, 0, ',', '.') ?></div>
                        </div>
                        <div class="intra__tile h-full p-3 text-center">
                            <div class="text-xs uppercase text-gray-400" style="letter-spacing:0.05em;">Critical</div>
                            <div class="mt-1 text-xl font-bold text-[#d46b6b]"><?= number_format($stats['by_level']['CRITICAL'] ?? 0, 0, ',', '.') ?></div>
                        </div>
                        <div class="intra__tile h-full p-3 text-center">
                            <div class="text-xs uppercase text-gray-400" style="letter-spacing:0.05em;">Error</div>
                            <div class="mt-1 text-xl font-bold text-[#d46b6b]"><?= number_format($stats['by_level']['ERROR'] ?? 0, 0, ',', '.') ?></div>
                        </div>
                    </div>

                    <!-- ───────────── Browse / Filter / Inbox ───────────── -->
                    <div class="intra__tile p-3">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h5 class="mb-0"><i class="fa-solid fa-inbox mr-2"></i>Letzte Fehler</h5>
                                <small class="text-gray-400">Gruppiert nach Exception &amp; Datei. Klick zum Aufklappen.</small>
                            </div>
                            <div class="btn-toolbar-group" id="inboxScopeFilter">
                                <button type="button" class="ignis-btn active" data-scope="all">Alle</button>
                                <button type="button" class="ignis-btn" data-scope="CRITICAL">Critical</button>
                                <button type="button" class="ignis-btn" data-scope="ERROR">Error</button>
                                <button type="button" class="ignis-btn" data-scope="WARNING">Warning</button>
                            </div>
                        </div>

                        <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center">
                            <div class="flex-1">
                                <input type="text"
                                       id="searchQuery"
                                       class="ignis-input"
                                       placeholder="Volltext-Suche (Datei, Klasse, Message…)"
                                       autocomplete="off">
                            </div>
                            <div class="md:w-56">
                                <select id="searchFile" class="form-select">
                                    <option value="">Alle Dateien</option>
                                    <?php foreach ($files as $f): ?>
                                        <option value="<?= htmlspecialchars($f['name']) ?>">
                                            <?= htmlspecialchars($f['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex gap-1">
                                <button type="button" class="ignis-btn ignis-btn--soft-primary" id="searchBtn">
                                    <i class="fa-solid fa-magnifying-glass mr-1"></i>Suchen
                                </button>
                                <button type="button" class="ignis-btn ignis-btn--ghost" id="resetBtn" title="Zurück zur Inbox">
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
                        <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-hexagon-exclamation text-[#ddb84a]"></i>
                                <h6 class="mb-0 font-semibold">Fehlgeschlagene Hintergrund-Jobs</h6>
                                <?php if ($failedTotal > 0): ?>
                                    <span class="ignis-chip ignis-chip--status ignis-chip--danger"><?= (int) $failedTotal ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($failed24h > 0): ?>
                                    <span class="ignis-chip ignis-chip--status ignis-chip--warning"><?= (int) $failed24h ?> in 24h
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="btn-toolbar-group">
                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost" id="refreshFailedJobsBtn" title="Liste neu laden">
                                    <i class="fa-solid fa-rotate"></i>
                                </button>
                                <?php if ($failedTotal > 0): ?>
                                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--soft-primary" id="retryAllFailedJobsBtn">
                                        <i class="fa-solid fa-arrows-rotate mr-1"></i>Alle erneut versuchen
                                    </button>
                                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost text-[#d46b6b]" id="deleteAllFailedJobsBtn">
                                        <i class="fa-solid fa-trash mr-1"></i>Alle löschen
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
                                            <div><span class="ignis-chip ignis-chip--status ignis-chip--danger">FAILED</span></div>
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
                                                <button type="button" class="ignis-btn ignis-btn--soft-primary ignis-btn--sm failed-retry-btn" data-id="<?= (int) $fj['id'] ?>">
                                                    <i class="fa-solid fa-arrows-rotate mr-1"></i>Erneut versuchen
                                                </button>
                                                <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm text-[#d46b6b] failed-delete-btn" data-id="<?= (int) $fj['id'] ?>">
                                                    <i class="fa-solid fa-trash mr-1"></i>Löschen
                                                </button>
                                                <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm copy-btn" data-copy="<?= htmlspecialchars($fj['uuid'], ENT_QUOTES) ?>" title="UUID kopieren">
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
                        <summary class="font-bold" style="cursor:pointer;">
                            <i class="fa-solid fa-folder-tree mr-2"></i>Verfügbare Log-Dateien (<?= count($files) ?>)
                        </summary>
                        <table class="table table-striped table-sm mt-3 mb-0">
                            <thead>
                                <tr>
                                    <th>Datei</th>
                                    <th class="text-right">Größe</th>
                                    <th>Typ</th>
                                    <th>Letzte Änderung</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $f): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($f['name']) ?></code></td>
                                        <td class="text-right"><?= number_format($f['size'] / 1024, 1) ?> KB</td>
                                        <td>
                                            <?php if ($f['type'] === 'error'): ?>
                                                <span class="ignis-chip ignis-chip--status ignis-chip--danger">error</span>
                                            <?php else: ?>
                                                <span class="ignis-chip ignis-chip--status ignis-chip--dark">app</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d.m.Y H:i', $f['mtime']) ?></td>
                                        <td class="text-right">
                                            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--soft-primary tail-btn" data-file="<?= htmlspecialchars($f['name']) ?>">
                                                <i class="fa-solid fa-eye mr-1"></i>Letzte 100
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

    <script>
        window.LogsAppConfig = {
            logsApiUrl:    "<?= BASE_PATH ?>settings/system/logs",
            initialGroups: <?= json_encode($groups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            csrfToken:     <?= json_encode($csrfToken) ?>
        };
    </script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/modules/logs-app.js"></script>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
