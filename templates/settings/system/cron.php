<?php

/**
 * View: Cron-Jobs-Verwaltung. Die Jobs als ignis-Tabelle mit Chips für
 * Status und Aktivität und Zeilenaktionen (Verlauf, Ausführen, Pausieren,
 * Löschen); der externe Trigger-Endpoint zum Aufklappen darüber, neue Jobs
 * über den Dialog (Dialog.form).
 *
 * @var array<int,array<string,mixed>> $jobs
 * @var string                         $cronEndpointToken
 */

use App\Helpers\DateTimeHelper;
use App\Security\CsrfProtection;

$csrfToken = CsrfProtection::getToken();
$base      = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$publicUrl = rtrim((defined('SYSTEM_URL') ? (string) SYSTEM_URL : (string) ($_SERVER['HTTP_HOST'] ?? '')), '/');
$scheme    = (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['SERVER_PORT'] ?? '') === '443') ? 'https' : 'http';
if ($publicUrl !== '' && !preg_match('~^https?://~i', $publicUrl)) {
    $publicUrl = $scheme . '://' . $publicUrl;
}
$cronUrl = rtrim($publicUrl, '/') . $base . 'cron.php?token=' . htmlspecialchars($cronEndpointToken);

// Letzter Lauf => [Text, Chip-Semantik]
$runStatus = [
    'success' => ['OK', 'ok'],
    'failed'  => ['Fehler', 'danger'],
    'running' => ['läuft', 'info'],
];

$layout = 'admin';
$bodyId = 'settings';
$SITE_TITLE = 'Cron-Jobs';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/system/index">System</a></span> <span class="ignis-breadcrumb__item is-active">Cron-Jobs</span></nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Automatisierung</p><h1>Cron-Jobs</h1><p class="twplus-page-header__description">Zeitpläne, letzte Ausführungen und Fehlerstatus geplanter Aufgaben.</p></div>
                <div class="header-actions twplus-page-header__actions">
                    <button type="button" class="ignis-btn ignis-btn--primary" onclick="openCreateCronJobModal()">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Neuer Job
                    </button>
                </div>
            </div>

            <details class="ignis-card mb-4">
                <summary class="ignis-card__header cursor-pointer select-none text-sm">
                    <span><i class="fa-solid fa-link mr-1" aria-hidden="true"></i> <strong>Externer Trigger-Endpoint</strong> <span class="text-[var(--text-3)]">— für cron-job.org, UptimeRobot &amp; Co.</span></span>
                </summary>
                <div class="ignis-card__body">
                    <div class="flex items-center gap-2">
                        <label for="cron-endpoint-url" class="sr-only">Endpoint-URL</label>
                        <input type="password" class="ignis-input ignis-input--sm ignis-mono" id="cron-endpoint-url" value="<?= htmlspecialchars($cronUrl) ?>" readonly>
                        <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="Anzeigen" aria-label="Endpoint anzeigen" onclick="const el=document.getElementById('cron-endpoint-url');el.type=el.type==='password'?'text':'password';this.querySelector('i').className='fa-solid '+(el.type==='text'?'fa-eye-slash':'fa-eye')">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--secondary" onclick="navigator.clipboard.writeText(document.getElementById('cron-endpoint-url').value);this.innerHTML='<i class=\'fa-solid fa-check\'></i> Kopiert';setTimeout(()=>this.innerHTML='<i class=\'fa-solid fa-copy\'></i> Kopieren',1200)">
                            <i class="fa-solid fa-copy" aria-hidden="true"></i> Kopieren
                        </button>
                    </div>
                    <small class="form-hint block">Wenn weder Unix-Cron noch die Piggyback-Middleware laufen, rufe diesen URL minütlich auf — der Scheduler wird dann alle fälligen Jobs abarbeiten. Der Token gilt als Passwort und sollte nur an vertrauenswürdige Dienste weitergegeben werden.</small>
                </div>
            </details>

            <div class="twplus-table-card">
                <div class="twplus-table-card__scroll">
                <table class="ignis-table" id="table-cron-jobs">
                    <thead>
                        <tr>
                            <th scope="col">Job</th>
                            <th scope="col">Zeitplan</th>
                            <th scope="col">Handler</th>
                            <th scope="col">Aktiv</th>
                            <th scope="col">Letzter Lauf</th>
                            <th scope="col">Nächster Lauf</th>
                            <th scope="col" class="ignis-table__num">Fehler</th>
                            <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($jobs === []): ?>
                            <tr><td colspan="8" class="ignis-table-empty">Noch keine Cron-Jobs angelegt.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($jobs as $job):
                            [$runText, $runChip] = $runStatus[$job['last_status']] ?? ['–', 'secondary'];
                            $isActive  = ((int) $job['active']) === 1;
                            $isBuiltin = ((int) $job['is_builtin']) === 1;
                            $jobId     = (int) $job['id'];
                        ?>
                            <tr<?= $isActive ? '' : ' class="is-muted"' ?>>
                                <td>
                                    <div class="font-semibold"><?= htmlspecialchars($job['name']) ?></div>
                                    <small class="text-[var(--text-3)]"><span class="ignis-mono"><?= htmlspecialchars($job['identifier']) ?></span><?php if ($isBuiltin): ?> · <span class="text-[var(--warn)]">built-in</span><?php endif; ?></small>
                                </td>
                                <td><code class="ignis-mono"><?= htmlspecialchars($job['schedule']) ?></code></td>
                                <td>
                                    <span class="ignis-chip ignis-chip--secondary"><?= htmlspecialchars($job['handler_type']) ?></span>
                                    <?php if (!($job['handler_available'] ?? true)): ?>
                                        <span class="ignis-chip ignis-chip--warn" title="Der Console-Command ist nicht registriert; das Plugin ist vermutlich deaktiviert.">Plugin inaktiv</span>
                                    <?php endif; ?>
                                    <div class="max-w-[220px] break-all text-xs text-[var(--text-3)]"><?= htmlspecialchars($job['handler']) ?></div>
                                </td>
                                <td>
                                    <?php if ($isActive): ?>
                                        <span class="ignis-chip ignis-chip--dot ignis-chip--ok">Ja</span>
                                    <?php else: ?>
                                        <span class="ignis-chip ignis-chip--dot ignis-chip--danger">Nein</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="ignis-chip ignis-chip--dot ignis-chip--<?= $runChip ?>"><?= $runText ?></span>
                                    <div class="text-xs text-[var(--text-3)]">
                                        <?= htmlspecialchars(DateTimeHelper::formatShort($job['last_run_at'] ?? null)) ?>
                                    </div>
                                </td>
                                <td class="text-xs">
                                    <?= htmlspecialchars(DateTimeHelper::formatShort($job['next_run_at'] ?? null)) ?>
                                </td>
                                <td class="ignis-table__num"><?= (int) $job['fail_count'] ?></td>
                                <td class="ignis-table__actions">
                                    <div class="ignis-row-actions">
                                        <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="Verlauf" aria-label="Verlauf" onclick="showCronHistory(<?= $jobId ?>, '<?= htmlspecialchars($job['name'], ENT_QUOTES) ?>')">
                                            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                                        </button>
                                        <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="Jetzt ausführen" aria-label="Jetzt ausführen" onclick="runCronJob(<?= $jobId ?>, this)">
                                            <i class="fa-solid fa-play" aria-hidden="true"></i>
                                        </button>
                                        <form method="POST" action="<?= $base ?>settings/system/cron/toggle" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $jobId ?>">
                                            <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="<?= $isActive ? 'Pausieren' : 'Aktivieren' ?>" aria-label="<?= $isActive ? 'Pausieren' : 'Aktivieren' ?>">
                                                <i class="fa-solid <?= $isActive ? 'fa-pause' : 'fa-toggle-on' ?>" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                        <?php if (!$isBuiltin): ?>
                                            <form method="POST" action="<?= $base ?>settings/system/cron/delete" class="inline" onsubmit="<?= confirm_attr('Job wirklich löschen?') ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= $jobId ?>">
                                                <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger ignis-btn--icon" data-ignis-tooltip="Löschen" aria-label="Löschen">
                                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
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
    </div>

    <!-- Form-Body fuer Cron-Job-Create. -->
    <template id="createCronJobFormTemplate">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 mb-3">
            <div>
                <label class="ignis-field__label" for="cron-identifier">Identifier <small class="form-hint">(eindeutig, keine Leerzeichen)</small></label>
                <input type="text" class="ignis-input" name="identifier" id="cron-identifier" pattern="[a-z0-9._-]+" placeholder="z.B. discord.weekly" required>
            </div>
            <div>
                <label class="ignis-field__label" for="cron-name">Anzeigename</label>
                <input type="text" class="ignis-input" name="name" id="cron-name" placeholder="z.B. Wochenstatistik" required>
            </div>
        </div>
        <div class="mb-3">
            <label class="ignis-field__label" for="cron-description">Beschreibung <small class="form-hint">(optional)</small></label>
            <input type="text" class="ignis-input" name="description" id="cron-description" placeholder="Was der Job tut">
        </div>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 mb-3">
            <div>
                <label class="ignis-field__label" for="cron-handler-type">Handler-Typ</label>
                <select name="handler_type" id="cron-handler-type" class="ignis-input" required>
                    <option value="webhook">Webhook (HTTP-URL)</option>
                    <option value="console">Console-Command (aus Allowlist)</option>
                    <option value="job">Queue-Job (FQCN dispatchen)</option>
                </select>
            </div>
            <div>
                <label class="ignis-field__label" for="cron-schedule">Schedule <small class="form-hint">(Cron-Expression)</small></label>
                <input type="text" class="ignis-input ignis-mono" name="schedule" id="cron-schedule" placeholder="*/5 * * * *" required>
            </div>
        </div>
        <div class="mb-3">
            <label class="ignis-field__label" for="cron-handler">Handler</label>
            <input type="text" class="ignis-input" name="handler" id="cron-handler" placeholder="https://discord.com/api/webhooks/… | queue:work | App\Jobs\MyJob" required>
            <small class="form-hint block">Webhook: Ziel-URL · Console: Command-Name · Queue: Job-Klassen-FQCN</small>
        </div>
        <div class="mb-2">
            <label class="ignis-field__label" for="cron-config">Config <small class="form-hint">(JSON, optional)</small></label>
            <textarea class="ignis-input ignis-mono text-xs" name="config" id="cron-config" rows="4" placeholder='{"method":"POST","body":{"content":"Wochenstats {{DATE}}"},"timeout":30}'></textarea>
            <small class="form-hint block">
                Platzhalter (Webhook): <code>{{SERVER_NAME}}</code>, <code>{{SERVER_CITY}}</code>, <code>{{SYSTEM_NAME}}</code>, <code>{{DATE}}</code>, <code>{{TIME}}</code>, <code>{{TIMESTAMP}}</code>, <code>{{ISO8601}}</code>
            </small>
        </div>
    </template>

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

        function openCreateCronJobModal() {
            Dialog.form({
                title:        'Neuer Cron-Job',
                template:     'createCronJobFormTemplate',
                size:         'lg',
                formAction:   <?= json_encode($base . 'settings/system/cron/create') ?>,
                hiddenFields: { csrf_token: <?= json_encode($csrfToken) ?> },
                submitLabel:  'Erstellen',
                submitVariant:'primary',
            });
        }

        function showCronHistory(id, name) {
            // Body als unbefuelltes Skelett aufbauen, Dialog sofort oeffnen,
            // dann den Inhalt asynchron via fetch nachschieben.
            var bodyEl = document.createElement('div');
            bodyEl.className = 'text-sm';
            bodyEl.innerHTML = '<div class="text-[var(--text-3)]">Lade…</div>';

            new Dialog({
                title:   'Verlauf — ' + name,
                size:    'xl',
                body:    bodyEl,
                actions: [{ label: 'Schließen', variant: 'ghost', close: true }],
            }).open();

            fetch(<?= json_encode($base . 'settings/system/cron/history') ?> + '?id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) { bodyEl.innerHTML = '<div class="text-[var(--danger)]">Fehler beim Laden</div>'; return; }
                    if (!data.runs || data.runs.length === 0) {
                        bodyEl.innerHTML = '<div class="text-[var(--text-3)]">Noch keine Läufe.</div>';
                        return;
                    }
                    const chipFor = { success: 'ok', failed: 'danger' };
                    const rows = data.runs.map(r => {
                        const chip = chipFor[r.status] || 'secondary';
                        const output = r.output ? `<pre class="ignis-mono mb-2 whitespace-pre-wrap break-words rounded-md bg-[var(--surface-2)] p-2 text-xs">${escapeHtml(r.output)}</pre>` : '';
                        return `
                            <div class="border-b border-[var(--fill-2)] py-2 last:border-b-0">
                                <div class="mb-1 flex items-center justify-between">
                                    <span class="ignis-chip ignis-chip--dot ignis-chip--${chip}">${escapeHtml(r.status)}</span>
                                    <span class="text-xs text-[var(--text-3)]">${formatLocalTime(r.started_at)} · ${r.duration_ms || 0}ms</span>
                                </div>
                                ${output}
                            </div>`;
                    }).join('');
                    bodyEl.innerHTML = rows;
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
    </script>
