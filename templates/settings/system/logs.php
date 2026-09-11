<?php
/**
 * View: System-Logs / Fehlerprotokoll
 *
 * @var array<int,array<string,mixed>> $files
 * @var array<int,array<string,mixed>> $recent
 * @var array<int,array<string,mixed>> $groups
 * @var array<string,mixed>            $stats
 */

use App\Security\CsrfProtection;

// CSRF-Token für Failed-Jobs-POST-Aktionen generieren/holen
$csrfToken = CsrfProtection::getToken();

$layout = 'admin';
$bodyId = 'settings';
$SITE_TITLE = 'Fehlerprotokoll';
?>
<?php ob_start(); ?>
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
            border-bottom: 1px solid var(--border);
            transition: background-color 0.12s;
        }
        .logs-group:last-child { border-bottom: none; }
        .logs-group:hover { background-color: var(--fill-1); }
        .logs-group.expanded { background-color: var(--fill-2); }

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
            border-top: 1px dashed var(--border);
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
            background: var(--surface-2);
            border: 1px solid var(--border);
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
            background: var(--fill-2);
            border: 1px solid var(--border);
            font-family: var(--font-mono, 'Inconsolata', monospace);
            font-size: 0.72rem;
            padding: 3px 8px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.1s;
        }
        .logs-id-pill:hover { background: var(--fill-3); }

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
<?php $layoutHead = ob_get_clean(); ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="mb-6">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/system/index">System</a></span> <span class="ignis-breadcrumb__item is-active">Fehlerprotokoll</span></nav>
                    <div class="page-header twplus-page-header mb-4">
                        <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Diagnostik</p><h1>Fehlerprotokoll</h1><p class="twplus-page-header__description">Fehler suchen, gruppierte Ereignisse untersuchen und fehlgeschlagene Jobs behandeln.</p></div>
                        <div class="header-actions twplus-page-header__actions">
                            <button type="button" class="ignis-btn ignis-btn--secondary" id="refreshBtn">
                                <i class="fa-solid fa-rotate" aria-hidden="true"></i> Aktualisieren
                            </button>
                        </div>
                    </div>

                    <!-- ───────────── HERO: Error-ID Lookup (primärer Use-Case) ───────────── -->
                    <div class="ignis-card logs-lookup-hero mb-3">
                        <div class="ignis-card__body flex flex-wrap items-center gap-3">
                            <div class="shrink-0">
                                <div class="font-semibold"><i class="fa-solid fa-key mr-2 text-[var(--info)]" aria-hidden="true"></i>Error-ID Lookup</div>
                                <div class="text-xs text-[var(--text-3)]">
                                    8-stellige ID aus der Production-Fehlerseite &mdash; z.B. <code>0B29305D</code>
                                </div>
                            </div>
                            <div class="flex min-w-[260px] flex-1 items-center gap-2">
                                <label for="errorIdInput" class="sr-only">Error-ID</label>
                                <input type="text"
                                       id="errorIdInput"
                                       class="ignis-input lookup-input"
                                       placeholder="A1B2C3D4"
                                       maxlength="8"
                                       autocomplete="off"
                                       pattern="[A-Fa-f0-9]{8}"
                                       autofocus>
                                <button type="button" class="ignis-btn ignis-btn--primary" id="errorIdLookupBtn">
                                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Suchen
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ───────────── Stats ───────────── -->
                    <dl class="twplus-stats twplus-stats--five mb-3" aria-label="Fehlerstatistik">
                        <div class="twplus-stats__item">
                            <dt class="twplus-stats__label">Errors gesamt</dt>
                            <dd class="twplus-stats__value"><?= number_format($stats['total'] ?? 0, 0, ',', '.') ?></dd>
                        </div>
                        <div class="twplus-stats__item">
                            <dt class="twplus-stats__label">Letzte 24h</dt>
                            <dd class="twplus-stats__value text-[var(--warn)]"><?= number_format($stats['last_24h'] ?? 0, 0, ',', '.') ?></dd>
                        </div>
                        <div class="twplus-stats__item">
                            <dt class="twplus-stats__label">Letzte 7 Tage</dt>
                            <dd class="twplus-stats__value text-[var(--warn)]"><?= number_format($stats['last_7d'] ?? 0, 0, ',', '.') ?></dd>
                        </div>
                        <div class="twplus-stats__item">
                            <dt class="twplus-stats__label">Critical</dt>
                            <dd class="twplus-stats__value text-[var(--danger)]"><?= number_format($stats['by_level']['CRITICAL'] ?? 0, 0, ',', '.') ?></dd>
                        </div>
                        <div class="twplus-stats__item">
                            <dt class="twplus-stats__label">Error</dt>
                            <dd class="twplus-stats__value text-[var(--danger)]"><?= number_format($stats['by_level']['ERROR'] ?? 0, 0, ',', '.') ?></dd>
                        </div>
                    </dl>

                    <!-- ───────────── Browse / Filter / Inbox ───────────── -->
                    <div class="ignis-card">
                        <div class="ignis-card__header">
                            <h2 class="ignis-card__title"><i class="fa-solid fa-inbox mr-2" aria-hidden="true"></i>Letzte Fehler <span class="ignis-card__subtitle">Gruppiert nach Exception und Datei, Klick klappt auf.</span></h2>
                            <nav class="ignis-filter-links" id="inboxScopeFilter" aria-label="Stufe">
                                <button type="button" class="is-active" data-scope="all">Alle</button>
                                <button type="button" data-scope="CRITICAL">Critical</button>
                                <button type="button" data-scope="ERROR">Error</button>
                                <button type="button" data-scope="WARNING">Warning</button>
                            </nav>
                        </div>
                        <div class="ignis-card__body">

                        <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center">
                            <div class="flex-1">
                                <input type="text"
                                       id="searchQuery"
                                       class="ignis-input"
                                       placeholder="Volltext-Suche (Datei, Klasse, Message…)"
                                       autocomplete="off">
                            </div>
                            <div class="md:w-56">
                                <label for="searchFile" class="sr-only">Datei</label>
                                <select id="searchFile" class="ignis-input">
                                    <option value="">Alle Dateien</option>
                                    <?php foreach ($files as $f): ?>
                                        <option value="<?= htmlspecialchars($f['name']) ?>">
                                            <?= htmlspecialchars($f['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex gap-1">
                                <button type="button" class="ignis-btn ignis-btn--secondary" id="searchBtn">
                                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Suchen
                                </button>
                                <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--icon" id="resetBtn" data-ignis-tooltip="Zurück zur Inbox" aria-label="Zurück zur Inbox">
                                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
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
                    </div>

                    <!-- ───────────── Failed Jobs Section ───────────── -->
                    <?php
                    $failedTotal = $failedJobsStats['total'] ?? 0;
                    $failed24h   = $failedJobsStats['last_24h'] ?? 0;
                    ?>
                    <div class="ignis-card mt-3">
                        <div class="ignis-card__header">
                            <h2 class="ignis-card__title flex flex-wrap items-center gap-2">
                                <i class="fa-solid fa-hexagon-exclamation text-[var(--warn)]" aria-hidden="true"></i>
                                Fehlgeschlagene Hintergrund-Jobs
                                <?php if ($failedTotal > 0): ?>
                                    <span class="ignis-chip ignis-chip--dot ignis-chip--danger"><?= (int) $failedTotal ?></span>
                                <?php endif; ?>
                                <?php if ($failed24h > 0): ?>
                                    <span class="ignis-chip ignis-chip--dot ignis-chip--warn"><?= (int) $failed24h ?> in 24h</span>
                                <?php endif; ?>
                            </h2>
                            <div class="ignis-card__actions">
                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" id="refreshFailedJobsBtn" data-ignis-tooltip="Liste neu laden" aria-label="Liste neu laden">
                                    <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                                </button>
                                <?php if ($failedTotal > 0): ?>
                                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--secondary" id="retryAllFailedJobsBtn">
                                        <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> Alle erneut versuchen
                                    </button>
                                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger" id="deleteAllFailedJobsBtn">
                                        <i class="fa-solid fa-trash" aria-hidden="true"></i> Alle löschen
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ignis-card__body">

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
                                            <div><span class="ignis-chip ignis-chip--dot ignis-chip--danger">FAILED</span></div>
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
                                                <button type="button" class="ignis-btn ignis-btn--secondary ignis-btn--sm failed-retry-btn" data-id="<?= (int) $fj['id'] ?>">
                                                    <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> Erneut versuchen
                                                </button>
                                                <button type="button" class="ignis-btn ignis-btn--ghost-danger ignis-btn--sm failed-delete-btn" data-id="<?= (int) $fj['id'] ?>">
                                                    <i class="fa-solid fa-trash" aria-hidden="true"></i> Löschen
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
                    </div>

                    <!-- ───────────── Files (collapsed) ───────────── -->
                    <details class="ignis-card mt-3">
                        <summary class="ignis-card__header cursor-pointer select-none font-semibold">
                            <span><i class="fa-solid fa-folder-tree mr-2" aria-hidden="true"></i>Verfügbare Log-Dateien (<?= count($files) ?>)</span>
                        </summary>
                        <div class="twplus-table-card__scroll">
                        <table class="ignis-table" id="table-log-files">
                            <thead>
                                <tr>
                                    <th scope="col">Datei</th>
                                    <th scope="col" class="ignis-table__num">Größe</th>
                                    <th scope="col">Typ</th>
                                    <th scope="col">Letzte Änderung</th>
                                    <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $f): ?>
                                    <tr>
                                        <td><code class="ignis-mono"><?= htmlspecialchars($f['name']) ?></code></td>
                                        <td class="ignis-table__num"><?= number_format($f['size'] / 1024, 1) ?> KB</td>
                                        <td>
                                            <?php if ($f['type'] === 'error'): ?>
                                                <span class="ignis-chip ignis-chip--dot ignis-chip--danger">error</span>
                                            <?php else: ?>
                                                <span class="ignis-chip ignis-chip--dot ignis-chip--secondary">app</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d.m.Y H:i', $f['mtime']) ?></td>
                                        <td class="ignis-table__actions">
                                            <div class="ignis-row-actions">
                                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--secondary tail-btn" data-file="<?= htmlspecialchars($f['name']) ?>">
                                                    <i class="fa-solid fa-eye" aria-hidden="true"></i> Letzte 100
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
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
