<?php
/**
 * View: Performance-Diagnostik. Kennzahlen als Kacheln, die Blöcke als
 * ignis-detail__block (Überschrift in Kapitälchen wie die Seitenspalte
 * der Detailseiten), Auslastung als ignis-progress, Platzhalter als
 * ignis-skeleton; die Werte kommen per fetch aus /api/system/performance.
 */

use App\Auth\Permissions;

$layout = 'admin';
$bodyId = 'settings';
$SITE_TITLE = 'Performance';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/system/index">System</a></span> <span class="ignis-breadcrumb__item is-active">Performance</span></nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Diagnostik</p><h1>Performance-Dashboard</h1><p class="twplus-page-header__description">Datenbank, Laufzeitumgebung und Systemauslastung im Überblick.</p></div>
                <div class="header-actions twplus-page-header__actions">
                    <button type="button" class="ignis-btn ignis-btn--secondary" id="refreshBtn" onclick="loadData()">
                        <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> Aktualisieren
                    </button>
                </div>
            </div>

            <!-- Übersicht-Kacheln -->
            <div class="twplus-stats mb-4" aria-label="Kennzahlen">
                <div class="twplus-stats__item">
                    <span class="twplus-stats__label"><i class="fa-solid fa-database mr-1" aria-hidden="true"></i> Datenbank-Größe</span>
                    <span class="twplus-stats__value" id="dbSize">--</span>
                    <span class="block text-xs text-[var(--text-3)]" id="dbSizeDetail">Lade...</span>
                </div>
                <div class="twplus-stats__item">
                    <span class="twplus-stats__label"><i class="fa-solid fa-table mr-1" aria-hidden="true"></i> Tabellen / Zeilen</span>
                    <span class="twplus-stats__value" id="dbTables">--</span>
                    <span class="block text-xs text-[var(--text-3)]" id="dbRows">--</span>
                </div>
                <div class="twplus-stats__item">
                    <span class="twplus-stats__label"><i class="fa-solid fa-users mr-1" aria-hidden="true"></i> Aktive Benutzer</span>
                    <span class="twplus-stats__value" id="activeUsers">--</span>
                    <span class="block text-xs text-[var(--text-3)]" id="activeUsersDetail">Lade...</span>
                </div>
                <div class="twplus-stats__item">
                    <span class="twplus-stats__label"><i class="fa-solid fa-server mr-1" aria-hidden="true"></i> Server-Uptime</span>
                    <span class="twplus-stats__value" id="uptime">--</span>
                    <span class="block text-xs text-[var(--text-3)]" id="uptimeDetail">Lade...</span>
                </div>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="ignis-detail__block">
                    <h4><i class="fa-solid fa-chart-pie mr-1" aria-hidden="true"></i> Content-Statistiken</h4>
                    <div id="contentStats" class="grid gap-2">
                        <span class="ignis-skeleton block h-8"></span>
                        <span class="ignis-skeleton block h-8"></span>
                        <span class="ignis-skeleton block h-8"></span>
                    </div>
                </div>
                <div class="ignis-detail__block">
                    <h4><i class="fa-solid fa-microchip mr-1" aria-hidden="true"></i> Server-Umgebung</h4>
                    <div id="serverInfo" class="grid gap-2">
                        <span class="ignis-skeleton block h-8"></span>
                        <span class="ignis-skeleton block h-8"></span>
                        <span class="ignis-skeleton block h-8"></span>
                    </div>
                </div>
                <div class="ignis-detail__block">
                    <h4><i class="fa-brands fa-php mr-1" aria-hidden="true"></i> PHP-Konfiguration</h4>
                    <div id="phpInfo" class="grid gap-2">
                        <span class="ignis-skeleton block h-8"></span>
                        <span class="ignis-skeleton block h-8"></span>
                        <span class="ignis-skeleton block h-8"></span>
                    </div>
                </div>
            </div>

            <!-- Tabellen-Details -->
            <div class="twplus-table-card mb-4">
                <div class="twplus-table-card__scroll">
                    <table class="ignis-table" id="table-performance">
                        <thead>
                            <tr>
                                <th scope="col">Tabelle</th>
                                <th scope="col" class="ignis-table__num">Zeilen</th>
                                <th scope="col" class="ignis-table__num">Größe</th>
                                <th scope="col" class="w-1/4">Anteil</th>
                            </tr>
                        </thead>
                        <tbody id="tableList">
                            <tr><td colspan="4"><span class="ignis-skeleton block h-8"></span></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="ignis-list-footer">
                    <p class="ignis-list-meta">Die zehn größten Tabellen nach Speicherbedarf.</p>
                </div>
            </div>

            <!-- Verbindungen & System-Status -->
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div class="ignis-detail__block">
                    <h4><i class="fa-solid fa-plug mr-1" aria-hidden="true"></i> Verbindungen</h4>
                    <div id="connectionInfo" class="grid gap-2">
                        <span class="ignis-skeleton block h-8"></span>
                        <span class="ignis-skeleton block h-8"></span>
                    </div>
                </div>
                <div class="ignis-detail__block">
                    <h4><i class="fa-solid fa-code-branch mr-1" aria-hidden="true"></i> System-Status</h4>
                    <div id="systemStatus" class="grid gap-2">
                        <span class="ignis-skeleton block h-8"></span>
                        <span class="ignis-skeleton block h-8"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const BASE_PATH = '<?= BASE_PATH ?>';

        function formatNumber(n) {
            if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
            if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
            return n.toString();
        }

        function formatUptime(seconds) {
            const days = Math.floor(seconds / 86400);
            const hours = Math.floor((seconds % 86400) / 3600);
            const mins = Math.floor((seconds % 3600) / 60);

            if (days > 0) return days + 'd ' + hours + 'h';
            if (hours > 0) return hours + 'h ' + mins + 'm';
            return mins + ' Min';
        }

        function formatUptimeDetail(seconds) {
            const days = Math.floor(seconds / 86400);
            const hours = Math.floor((seconds % 86400) / 3600);
            const mins = Math.floor((seconds % 3600) / 60);
            const parts = [];
            if (days > 0) parts.push(days + ' Tag' + (days !== 1 ? 'e' : ''));
            if (hours > 0) parts.push(hours + ' Stunde' + (hours !== 1 ? 'n' : ''));
            if (mins > 0) parts.push(mins + ' Minute' + (mins !== 1 ? 'n' : ''));
            return parts.join(', ') || '< 1 Minute';
        }

        const CONTENT_LABELS = {
            mitarbeiter: { label: 'Mitarbeiter', icon: 'fa-users' },
            enotf_protokolle: { label: 'eNOTF-Protokolle', icon: 'fa-house-medical-flag' },
            dokumente: { label: 'Dokumente', icon: 'fa-file-lines' },
            kb_eintraege: { label: 'Lexikon', icon: 'fa-book' },
            brandeinsaetze: { label: 'Brandeinsätze', icon: 'fa-fire' }
        };

        // Auslastung: bis 25 % ok, bis 50 % warn, darüber danger (Tabellen);
        // Verbindungen: bis 50 % ok, bis 80 % warn.
        function progressVariant(pct, warnAt, dangerAt) {
            return pct > dangerAt ? 'danger' : pct > warnAt ? 'warning' : 'success';
        }

        function progressBar(pct, warnAt, dangerAt) {
            return '<div class="ignis-progress ignis-progress--' + progressVariant(pct, warnAt, dangerAt) + '"><div class="ignis-progress__bar" style="width: ' + Math.min(100, pct).toFixed(1) + '%"></div></div>';
        }

        // Kennwert-Zeile in einem Block: Label links, Wert rechts.
        function statRow(labelHtml, valueHtml, first) {
            return '<div class="flex items-center justify-between py-2' + (first ? '' : ' border-t border-[var(--fill-2)]') + '"><span>' + labelHtml + '</span><span class="font-semibold">' + valueHtml + '</span></div>';
        }

        function infoGrid(items) {
            return '<div class="grid grid-cols-2 gap-2">' + items.map(([label, value]) =>
                '<div class="rounded-md bg-[var(--fill-1)] p-2"><div class="text-xs uppercase text-[var(--text-3)]">' + label + '</div><div class="font-semibold">' + value + '</div></div>'
            ).join('') + '</div>';
        }

        async function loadData() {
            const btn = document.getElementById('refreshBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Lade...';

            try {
                const response = await fetch(BASE_PATH + 'api/system/performance');
                const data = await response.json();

                if (data.error) {
                    showAlert('Fehler: ' + data.error, {type: 'error', title: 'API-Fehler'});
                    return;
                }

                renderOverview(data);
                renderContent(data.content);
                renderServer(data.server);
                renderPHP(data.php);
                renderTables(data.tables, data.database.size_mb);
                renderConnections(data.server);
                renderSystemStatus(data);
            } catch (error) {
                console.error('Performance API error:', error);
                showAlert('Fehler beim Laden der Daten: ' + error.message, {type: 'error', title: 'Fehler'});
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> Aktualisieren';
            }
        }

        function renderOverview(data) {
            // Datenbank
            document.getElementById('dbSize').textContent = data.database.size_mb + ' MB';
            document.getElementById('dbSizeDetail').textContent = data.database.name;

            document.getElementById('dbTables').textContent = data.database.table_count;
            document.getElementById('dbRows').textContent = formatNumber(data.database.total_rows) + ' Zeilen gesamt';

            // Aktive Benutzer
            document.getElementById('activeUsers').textContent = data.users.active_24h;
            document.getElementById('activeUsersDetail').textContent =
                data.users.active_7d + ' (7T) / ' + data.users.active_30d + ' (30T) / ' + data.users.total + ' gesamt';

            // Uptime
            if (data.server.uptime_seconds) {
                document.getElementById('uptime').textContent = formatUptime(data.server.uptime_seconds);
                document.getElementById('uptimeDetail').textContent = formatUptimeDetail(data.server.uptime_seconds);
            } else {
                document.getElementById('uptime').textContent = 'N/A';
                document.getElementById('uptimeDetail').textContent = '';
            }
        }

        function renderContent(content) {
            const container = document.getElementById('contentStats');
            let html = '';

            for (const [key, value] of Object.entries(content)) {
                const info = CONTENT_LABELS[key] || { label: key, icon: 'fa-circle' };
                html += statRow('<i class="fa-solid ' + info.icon + ' mr-2 text-[var(--text-3)]" aria-hidden="true"></i>' + info.label, formatNumber(value), html === '');
            }

            container.className = '';
            container.innerHTML = html;
        }

        function renderServer(server) {
            const container = document.getElementById('serverInfo');
            container.className = '';
            container.innerHTML = infoGrid([
                ['DB-Version', server.db_version || 'N/A'],
                ['Buffer Pool', server.buffer_pool_mb ? server.buffer_pool_mb + ' MB' : 'N/A'],
                ['Max Connections', server.max_connections || 'N/A'],
                ['Slow Queries', server.slow_queries !== null ? server.slow_queries : 'N/A'],
            ]);
        }

        function renderPHP(php) {
            const container = document.getElementById('phpInfo');
            container.className = '';
            container.innerHTML = infoGrid([
                ['PHP-Version', php.version],
                ['Memory Limit', php.memory_limit],
                ['Max Upload', php.upload_max_filesize],
                ['Max Exec Time', php.max_execution_time + 's'],
            ]);
        }

        function renderTables(tables, totalSizeMb) {
            const tbody = document.getElementById('tableList');
            let html = '';

            tables.forEach(table => {
                const pct = totalSizeMb > 0 ? ((table.size_mb / totalSizeMb) * 100) : 0;

                html += `
                    <tr>
                        <td><code class="ignis-mono">${table.table_name}</code></td>
                        <td class="ignis-table__num">${formatNumber(table.row_count || 0)}</td>
                        <td class="ignis-table__num">${table.size_mb} MB</td>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="grow">${progressBar(pct, 25, 50)}</div>
                                <span class="min-w-[2.5rem] text-right text-sm text-[var(--text-3)]">${pct.toFixed(0)}%</span>
                            </div>
                        </td>
                    </tr>`;
            });

            tbody.innerHTML = html || '<tr><td colspan="4" class="ignis-table-empty">Keine Daten</td></tr>';
        }

        function renderConnections(server) {
            const container = document.getElementById('connectionInfo');
            const used = server.threads_connected || 0;
            const max = server.max_connections || 100;
            const pct = (used / max) * 100;

            container.className = '';
            container.innerHTML = `
                <div class="mb-3">
                    <div class="flex justify-between mb-1">
                        <span class="text-sm">Aktive Verbindungen</span>
                        <span class="text-sm font-bold">${used} / ${max}</span>
                    </div>
                    ${progressBar(pct, 50, 80)}
                </div>
                <div class="text-sm text-[var(--text-3)]">
                    Auslastung: ${pct.toFixed(1)}%
                </div>`;
        }

        function renderSystemStatus(data) {
            const container = document.getElementById('systemStatus');
            const items = [
                { label: 'Migrationen ausgeführt', value: data.migrations.executed, icon: 'fa-code-branch', chip: 'ok' },
                { label: 'Template-Dateien', value: data.templates.count, icon: 'fa-file-code', chip: 'info' },
            ];

            let html = '';
            items.forEach(item => {
                html += statRow(
                    '<i class="fa-solid ' + item.icon + ' mr-2 text-[var(--' + item.chip + ')]" aria-hidden="true"></i>' + item.label,
                    '<span class="ignis-chip ignis-chip--' + item.chip + '">' + item.value + '</span>',
                    html === ''
                );
            });

            container.className = '';
            container.innerHTML = html;
        }

        loadData();
    </script>
