<?php
/**
 * View: Performance-Diagnostik
 *
 * @var \PDO $pdo
 */

use App\Auth\Permissions;
use App\Helpers\Flash;
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php include __DIR__ . '/../../../assets/components/_base/admin/head.php'; ?>
    <style>
        .perf-card {
            background-color: var(--body-bg-lighter);
            border: 1px solid var(--darkgray);
            border-radius: 12px;
            padding: 1.25rem;
        }

        .row .perf-card {
            height: 100%;
        }

        .perf-card h6 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
            color: var(--light-text);
            margin-bottom: 0.75rem;
        }

        .perf-stat {
            font-size: 2rem;
            font-weight: bold;
            line-height: 1.2;
        }

        .perf-stat-sm {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .perf-label {
            font-size: 0.8rem;
            color: var(--light-text);
        }

        .perf-table th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.03rem;
            color: var(--light-text);
            border-bottom-color: var(--darkgray);
        }

        .perf-table td {
            border-bottom-color: var(--darkgray);
            vertical-align: middle;
        }

        .perf-bar-bg {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            height: 8px;
            overflow: hidden;
        }

        .perf-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .perf-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
        }

        .loading-placeholder {
            animation: pulse 1.5s ease-in-out infinite;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
            height: 2rem;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 0.8; }
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }

        .info-grid .info-item {
            padding: 0.5rem;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.03);
        }

        .info-grid .info-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--light-text);
        }

        .info-grid .info-value {
            font-weight: 600;
            font-size: 0.95rem;
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container mt-4 mb-5">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>index">Dashboard</a></li>
                <li class="breadcrumb-item">Einstellungen</li>
                <li class="breadcrumb-item active">Performance</li>
            </ol>
        </nav>

        <div class="mb-4 flex items-center justify-between">
            <h1 class="mb-0">Performance-Dashboard</h1>
            <button class="ignis-btn ignis-btn--outline-secondary ignis-btn--sm" id="refreshBtn" onclick="loadData()">
                <i class="fa-solid fa-arrows-rotate"></i> Aktualisieren
            </button>
        </div>

        <?php Flash::render(); ?>

        <!-- Übersicht-Karten -->
        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="perf-ignis-card">
                <h6><i class="fa-solid fa-database mr-1"></i> Datenbank-Größe</h6>
                <div class="perf-stat" id="dbSize">--</div>
                <div class="perf-label" id="dbSizeDetail">Lade...</div>
            </div>
            <div class="perf-ignis-card">
                <h6><i class="fa-solid fa-table mr-1"></i> Tabellen / Zeilen</h6>
                <div class="perf-stat" id="dbTables">--</div>
                <div class="perf-label" id="dbRows">--</div>
            </div>
            <div class="perf-ignis-card">
                <h6><i class="fa-solid fa-users mr-1"></i> Aktive Benutzer</h6>
                <div class="perf-stat" id="activeUsers">--</div>
                <div class="perf-label" id="activeUsersDetail">Lade...</div>
            </div>
            <div class="perf-ignis-card">
                <h6><i class="fa-solid fa-server mr-1"></i> Server-Uptime</h6>
                <div class="perf-stat" id="uptime">--</div>
                <div class="perf-label" id="uptimeDetail">Lade...</div>
            </div>
        </div>

        <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="perf-ignis-card">
                <h6><i class="fa-solid fa-chart-pie mr-1"></i> Content-Statistiken</h6>
                <div id="contentStats">
                    <div class="loading-placeholder mb-2"></div>
                    <div class="loading-placeholder mb-2"></div>
                    <div class="loading-placeholder"></div>
                </div>
            </div>
            <div class="perf-ignis-card">
                <h6><i class="fa-solid fa-microchip mr-1"></i> Server-Umgebung</h6>
                <div id="serverInfo">
                    <div class="loading-placeholder mb-2"></div>
                    <div class="loading-placeholder mb-2"></div>
                    <div class="loading-placeholder"></div>
                </div>
            </div>
            <div class="perf-ignis-card">
                <h6><i class="fa-brands fa-php mr-1"></i> PHP-Konfiguration</h6>
                <div id="phpInfo">
                    <div class="loading-placeholder mb-2"></div>
                    <div class="loading-placeholder mb-2"></div>
                    <div class="loading-placeholder"></div>
                </div>
            </div>
        </div>

        <!-- Tabellen-Details -->
        <div class="perf-ignis-card mb-4">
            <h6><i class="fa-solid fa-list mr-1"></i> Top 10 Tabellen nach Größe</h6>
            <div class="table-responsive">
                <table class="table table-sm perf-table mb-0">
                    <thead>
                        <tr>
                            <th>Tabelle</th>
                            <th class="text-right">Zeilen</th>
                            <th class="text-right">Größe</th>
                            <th style="width: 25%;">Anteil</th>
                        </tr>
                    </thead>
                    <tbody id="tableList">
                        <tr><td colspan="4"><div class="loading-placeholder"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Verbindungen & System-Status -->
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="perf-ignis-card">
                <h6><i class="fa-solid fa-plug mr-1"></i> Verbindungen</h6>
                <div id="connectionInfo">
                    <div class="loading-placeholder mb-2"></div>
                    <div class="loading-placeholder"></div>
                </div>
            </div>
            <div class="perf-ignis-card">
                <h6><i class="fa-solid fa-code-branch mr-1"></i> System-Status</h6>
                <div id="systemStatus">
                    <div class="loading-placeholder mb-2"></div>
                    <div class="loading-placeholder"></div>
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
            kb_eintraege: { label: 'Wissensdatenbank', icon: 'fa-book' },
            brandeinsaetze: { label: 'Brandeinsätze', icon: 'fa-fire' }
        };

        async function loadData() {
            const ignis-btn = document.getElementById('refreshBtn');
            ignis-btn.disabled = true;
            ignis-btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Lade...';

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
                ignis-btn.disabled = false;
                ignis-btn.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> Aktualisieren';
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
                html += `
                    <div class="flex justify-between items-center py-2 ${html ? 'border-t border-secondary border-opacity-25' : ''}">
                        <span><i class="fa-solid ${info.icon} mr-2 text-[var(--text-dimmed,#818189)]"></i>${info.label}</span>
                        <span class="perf-stat-sm">${formatNumber(value)}</span>
                    </div>`;
            }

            container.innerHTML = html;
        }

        function renderServer(server) {
            const container = document.getElementById('serverInfo');
            container.innerHTML = `
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">DB-Version</div>
                        <div class="info-value">${server.db_version || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Buffer Pool</div>
                        <div class="info-value">${server.buffer_pool_mb ? server.buffer_pool_mb + ' MB' : 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Max Connections</div>
                        <div class="info-value">${server.max_connections || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Slow Queries</div>
                        <div class="info-value">${server.slow_queries !== null ? server.slow_queries : 'N/A'}</div>
                    </div>
                </div>`;
        }

        function renderPHP(php) {
            const container = document.getElementById('phpInfo');
            container.innerHTML = `
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">PHP-Version</div>
                        <div class="info-value">${php.version}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Memory Limit</div>
                        <div class="info-value">${php.memory_limit}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Max Upload</div>
                        <div class="info-value">${php.upload_max_filesize}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Max Exec Time</div>
                        <div class="info-value">${php.max_execution_time}s</div>
                    </div>
                </div>`;
        }

        function renderTables(tables, totalSizeMb) {
            const tbody = document.getElementById('tableList');
            let html = '';

            tables.forEach(table => {
                const pct = totalSizeMb > 0 ? ((table.size_mb / totalSizeMb) * 100) : 0;
                const barColor = pct > 50 ? '#D10000' : pct > 25 ? '#B86B00' : '#198754';

                html += `
                    <tr>
                        <td>
                            <code class="text-sm">${table.table_name}</code>
                        </td>
                        <td class="text-right">${formatNumber(table.row_count || 0)}</td>
                        <td class="text-right">${table.size_mb} MB</td>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="perf-bar-bg grow">
                                    <div class="perf-bar" style="width: ${pct}%; background: ${barColor};"></div>
                                </div>
                                <span class="text-sm text-[var(--text-dimmed,#818189)]" style="min-width: 35px;">${pct.toFixed(0)}%</span>
                            </div>
                        </td>
                    </tr>`;
            });

            tbody.innerHTML = html || '<tr><td colspan="4" class="text-[var(--text-dimmed,#818189)]">Keine Daten</td></tr>';
        }

        function renderConnections(server) {
            const container = document.getElementById('connectionInfo');
            const used = server.threads_connected || 0;
            const max = server.max_connections || 100;
            const pct = (used / max) * 100;
            const barColor = pct > 80 ? '#D10000' : pct > 50 ? '#B86B00' : '#198754';

            container.innerHTML = `
                <div class="mb-3">
                    <div class="flex justify-between mb-1">
                        <span class="text-sm">Aktive Verbindungen</span>
                        <span class="text-sm font-bold">${used} / ${max}</span>
                    </div>
                    <div class="perf-bar-bg">
                        <div class="perf-bar" style="width: ${pct}%; background: ${barColor};"></div>
                    </div>
                </div>
                <div class="text-sm text-[var(--text-dimmed,#818189)]">
                    Auslastung: ${pct.toFixed(1)}%
                </div>`;
        }

        function renderSystemStatus(data) {
            const container = document.getElementById('systemStatus');
            const items = [
                {
                    label: 'Migrationen ausgeführt',
                    value: data.migrations.executed,
                    icon: 'fa-code-branch',
                    color: 'success'
                },
                {
                    label: 'Template-Dateien',
                    value: data.templates.count,
                    icon: 'fa-file-code',
                    color: 'info'
                }
            ];

            let html = '';
            items.forEach(item => {
                html += `
                    <div class="flex justify-between items-center py-2 ${html ? 'border-t border-secondary border-opacity-25' : ''}">
                        <span><i class="fa-solid ${item.icon} mr-2 text-${item.color}"></i>${item.label}</span>
                        <span class="perf-badge bg-${item.color} bg-opacity-25 text-${item.color}">${item.value}</span>
                    </div>`;
            });

            container.innerHTML = html;
        }

        loadData();
    </script>
    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>
