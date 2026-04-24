<?php

/**
 * View: Cron-Jobs-Verwaltung
 *
 * @var array<int,array<string,mixed>> $jobs
 * @var string                         $cronEndpointToken
 * @var \PDO                           $pdo
 */

use App\Helpers\DateTimeHelper;
use App\Helpers\Flash;
use App\Security\CsrfProtection;

$csrfToken = CsrfProtection::getToken();
$base      = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$publicUrl = rtrim((defined('SYSTEM_URL') ? (string) SYSTEM_URL : (string) ($_SERVER['HTTP_HOST'] ?? '')), '/');
$scheme    = (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['SERVER_PORT'] ?? '') === '443') ? 'https' : 'http';
if ($publicUrl !== '' && !preg_match('~^https?://~i', $publicUrl)) {
    $publicUrl = $scheme . '://' . $publicUrl;
}
$cronUrl = rtrim($publicUrl, '/') . $base . 'cron.php?token=' . htmlspecialchars($cronEndpointToken);
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . '/../../../assets/components/navbar.php'; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="mb-0">Cron-Jobs</h1>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createCronJobModal">
                    <i class="fa-solid fa-plus"></i> Neuer Job
                </button>
            </div>

            <?php Flash::render(); ?>

            <details class="intra__tile mb-4 px-4 py-3">
                <summary class="cursor-pointer text-sm text-gray-400 flex items-center gap-2 select-none">
                    <i class="fa-solid fa-link"></i>
                    <strong>Externer Trigger-Endpoint</strong>
                    <span class="text-gray-500">— für cron-job.org, UptimeRobot &amp; Co.</span>
                </summary>
                <div class="flex items-center gap-2 mt-3">
                    <input type="password" class="form-control form-control-sm" id="cron-endpoint-url" value="<?= htmlspecialchars($cronUrl) ?>" readonly style="font-family: var(--font-mono, monospace); font-size: 0.78rem;">
                    <button type="button" class="btn btn-sm btn-ghost" title="Anzeigen" onclick="const el=document.getElementById('cron-endpoint-url');el.type=el.type==='password'?'text':'password';this.querySelector('i').className='fa-solid '+(el.type==='text'?'fa-eye-slash':'fa-eye')">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-soft-primary" onclick="navigator.clipboard.writeText(document.getElementById('cron-endpoint-url').value);this.innerHTML='<i class=\'fa-solid fa-check\'></i> Kopiert';setTimeout(()=>this.innerHTML='<i class=\'fa-solid fa-copy\'></i> Kopieren',1200)">
                        <i class="fa-solid fa-copy"></i> Kopieren
                    </button>
                </div>
                <small class="text-gray-500 mt-2 block">Wenn weder Unix-Cron noch die Piggyback-Middleware laufen, rufe diesen URL minütlich auf — der Scheduler wird dann alle fälligen Jobs abarbeiten. Der Token gilt als Passwort und sollte nur an vertrauenswürdige Dienste weitergegeben werden.</small>
            </details>

            <div class="intra__tile px-3 py-2">
                <table class="table table-striped" id="table-cron-jobs">
                    <thead>
                        <tr>
                            <th>Identifier</th>
                            <th>Schedule</th>
                            <th>Handler</th>
                            <th>Aktiv</th>
                            <th>Letzter Lauf</th>
                            <th>Nächster Lauf</th>
                            <th>Fails</th>
                            <th style="width:220px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job):
                            $statusBadge = match ($job['last_status']) {
                                'success' => "<span class='badge-status status-success'><span class='status-dot'></span>OK</span>",
                                'failed'  => "<span class='badge-status status-danger'><span class='status-dot'></span>FAIL</span>",
                                'running' => "<span class='badge-status status-info'><span class='status-dot'></span>läuft</span>",
                                default   => "<span class='badge-status status-muted'><span class='status-dot'></span>–</span>",
                            };
                            $activeBadge = ((int) $job['active']) === 1
                                ? "<span class='badge-status status-success'><span class='status-dot'></span>Ja</span>"
                                : "<span class='badge-status status-danger'><span class='status-dot'></span>Nein</span>";
                            $isBuiltin = ((int) $job['is_builtin']) === 1;
                            $jobId = (int) $job['id'];
                        ?>
                            <tr>
                                <td>
                                    <div class="font-bold"><?= htmlspecialchars($job['name']) ?></div>
                                    <small class="text-gray-500"><?= htmlspecialchars($job['identifier']) ?><?php if ($isBuiltin): ?> · <span class="text-warning">built-in</span><?php endif; ?></small>
                                </td>
                                <td><code style="font-size:0.78rem;"><?= htmlspecialchars($job['schedule']) ?></code></td>
                                <td>
                                    <span class="badge text-bg-secondary" style="font-size:0.65rem;"><?= htmlspecialchars($job['handler_type']) ?></span>
                                    <div style="font-size:0.72rem;word-break:break-all;max-width:220px;"><?= htmlspecialchars($job['handler']) ?></div>
                                </td>
                                <td><?= $activeBadge ?></td>
                                <td>
                                    <?= $statusBadge ?>
                                    <div style="font-size:0.7rem;" class="text-gray-500">
                                        <?= htmlspecialchars(DateTimeHelper::formatShort($job['last_run_at'] ?? null)) ?>
                                    </div>
                                </td>
                                <td style="font-size:0.78rem;">
                                    <?= htmlspecialchars(DateTimeHelper::formatShort($job['next_run_at'] ?? null)) ?>
                                </td>
                                <td><?= (int) $job['fail_count'] ?></td>
                                <td>
                                    <div class="flex gap-1">
                                        <button type="button" class="btn btn-sm btn-soft-primary" title="History" onclick="showCronHistory(<?= $jobId ?>, '<?= htmlspecialchars($job['name'], ENT_QUOTES) ?>')">
                                            <i class="fa-solid fa-clock-rotate-left"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-soft-success" title="Jetzt ausführen" onclick="runCronJob(<?= $jobId ?>, this)">
                                            <i class="fa-solid fa-play"></i>
                                        </button>
                                        <form method="POST" action="<?= $base ?>settings/system/cron/toggle" style="display:inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $jobId ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-warning" title="Aktivieren / Pausieren">
                                                <i class="fa-solid <?= ((int) $job['active']) === 1 ? 'fa-pause' : 'fa-play' ?>"></i>
                                            </button>
                                        </form>
                                        <?php if (!$isBuiltin): ?>
                                            <form method="POST" action="<?= $base ?>settings/system/cron/delete" style="display:inline" data-confirm="Job wirklich löschen?">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= $jobId ?>">
                                                <button type="submit" class="btn btn-sm btn-ghost-danger" title="Löschen">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: Neuer Job -->
    <div class="modal fade" id="createCronJobModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="<?= $base ?>settings/system/cron/create" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Neuer Cron-Job</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col">
                                <label class="form-label">Identifier <small class="form-hint">(eindeutig, keine Leerzeichen)</small></label>
                                <input type="text" class="form-control" name="identifier" pattern="[a-z0-9._-]+" required>
                            </div>
                            <div class="col">
                                <label class="form-label">Anzeigename</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Beschreibung <small class="form-hint">(optional)</small></label>
                            <input type="text" class="form-control" name="description">
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label class="form-label">Handler-Typ</label>
                                <select name="handler_type" class="form-select" required>
                                    <option value="webhook">Webhook (HTTP-URL)</option>
                                    <option value="console">Console-Command (aus Allowlist)</option>
                                    <option value="job">Queue-Job (FQCN dispatchen)</option>
                                </select>
                            </div>
                            <div class="col">
                                <label class="form-label">Schedule <small class="form-hint">(Cron-Expression)</small></label>
                                <input type="text" class="form-control" name="schedule" placeholder="*/5 * * * *" required style="font-family:monospace;">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Handler</label>
                            <input type="text" class="form-control" name="handler" placeholder="https://discord.com/api/webhooks/… | queue:work | App\Jobs\MyJob" required>
                            <small class="form-hint">Webhook: Ziel-URL · Console: Command-Name · Queue: Job-Klassen-FQCN</small>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Config <small class="form-hint">(JSON, optional)</small></label>
                            <textarea class="form-control" name="config" rows="4" style="font-family:monospace;font-size:0.78rem;" placeholder='{"method":"POST","body":{"content":"Wochenstats {{DATE}}"},"timeout":30}'></textarea>
                            <small class="form-hint">
                                Platzhalter (Webhook): <code>{{SERVER_NAME}}</code>, <code>{{SERVER_CITY}}</code>, <code>{{SYSTEM_NAME}}</code>, <code>{{DATE}}</code>, <code>{{TIME}}</code>, <code>{{TIMESTAMP}}</code>, <code>{{ISO8601}}</code>
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-success">Erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: History -->
    <div class="modal fade" id="cronHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cronHistoryTitle">History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body">
                    <div id="cronHistoryBody" class="text-sm">
                        <div class="text-gray-500">Lade…</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function runCronJob(id, btn) {
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>'; }
            const fd = new FormData();
            fd.append('id', id);
            fd.append('csrf_token', <?= json_encode($csrfToken) ?>);
            fetch(<?= json_encode($base . 'settings/system/cron/run') ?>, {
                method: 'POST', body: fd
            })
            .then(r => r.json())
            .then(data => {
                const msg = data.ok ? 'Erfolgreich (' + (data.duration_ms || 0) + 'ms)' : ('Fehler: ' + (data.output || data.error || ''));
                if (window.showToast) window.showToast(msg, data.ok ? 'success' : 'danger');
                else alert(msg);
                setTimeout(() => location.reload(), 900);
            })
            .catch(e => {
                alert('Request fehlgeschlagen: ' + e);
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-play"></i>'; }
            });
        }

        function showCronHistory(id, name) {
            document.getElementById('cronHistoryTitle').textContent = 'History — ' + name;
            const body = document.getElementById('cronHistoryBody');
            body.innerHTML = '<div class="text-gray-500">Lade…</div>';
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cronHistoryModal'));
            modal.show();

            fetch(<?= json_encode($base . 'settings/system/cron/history') ?> + '?id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) { body.innerHTML = '<div class="text-danger">Fehler beim Laden</div>'; return; }
                    if (!data.runs || data.runs.length === 0) {
                        body.innerHTML = '<div class="text-gray-500">Noch keine Läufe.</div>';
                        return;
                    }
                    const rows = data.runs.map(r => {
                        const statusClass = r.status === 'success' ? 'status-success' : (r.status === 'failed' ? 'status-danger' : 'status-muted');
                        const output = r.output ? `<pre style="font-size:0.72rem;white-space:pre-wrap;word-break:break-word;margin:0 0 0.5rem 0;padding:0.4rem;background:rgba(255,255,255,0.04);border-radius:4px;">${escapeHtml(r.output)}</pre>` : '';
                        return `
                            <div style="padding:0.6rem 0;border-bottom:1px solid rgba(255,255,255,0.06);">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="badge-status ${statusClass}"><span class="status-dot"></span>${r.status}</span>
                                    <span class="text-gray-500" style="font-size:0.72rem;">${formatLocalTime(r.started_at)} · ${r.duration_ms || 0}ms</span>
                                </div>
                                ${output}
                            </div>`;
                    }).join('');
                    body.innerHTML = rows;
                });
        }

        function escapeHtml(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        // DB-Zeiten kommen als "2026-04-24 10:58:22" (UTC, ohne Z-Suffix) —
        // manuell als UTC parsen und zu Europe/Berlin formatieren.
        function formatLocalTime(utcString) {
            if (!utcString) return '–';
            const iso = utcString.replace(' ', 'T') + 'Z';
            const d = new Date(iso);
            if (isNaN(d.getTime())) return utcString;
            return d.toLocaleString('de-DE', {
                timeZone: 'Europe/Berlin',
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        }

        // Confirm-Guard für Löschen-Buttons
        document.querySelectorAll('form[data-confirm]').forEach(f => {
            f.addEventListener('submit', e => {
                if (!confirm(f.getAttribute('data-confirm'))) e.preventDefault();
            });
        });
    </script>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>
</html>
