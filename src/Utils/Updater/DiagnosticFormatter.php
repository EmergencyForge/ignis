<?php

declare(strict_types=1);

namespace App\Utils\Updater;

/**
 * Stellt einen Diagnosebericht dar: kurz als Text, als HTML für die
 * Update-Seite und ausführlich zum Kopieren für den Support.
 */
final class DiagnosticFormatter
{
    /**
     * Format diagnostic summary for user display (plain text)
     *
     * @param array<string, mixed> $diagnostics
     */
    public static function summary(array $diagnostics): string
    {
        $summary = [];
        $summary[] = "=== Update-Diagnose ===\n";
        $summary[] = "Schweregrad: " . strtoupper($diagnostics['severity']);
        $summary[] = "Zeitpunkt: " . $diagnostics['timestamp'];

        if ($diagnostics['error_analysis']['has_error']) {
            $summary[] = "\nFehlertyp: " . $diagnostics['error_analysis']['error_type'];
            $summary[] = "Nachricht: " . substr($diagnostics['error_analysis']['message'], 0, 200);
        }

        $summary[] = "\n=== System-Status ===";
        $summary[] = "PHP: " . $diagnostics['system_info']['php_version'] . " (" . $diagnostics['system_info']['status'] . ")";
        $summary[] = "Speicher: " . ($diagnostics['disk_space']['free_space_mb'] ?? 'unbekannt') . " MB frei (" . $diagnostics['disk_space']['status'] . ")";
        $summary[] = "Berechtigungen: " . $diagnostics['permissions']['status'];
        $summary[] = "Netzwerk: " . $diagnostics['network']['status'];
        $summary[] = "Vendor: " . ($diagnostics['dependencies']['vendor_directory_exists'] ? 'vorhanden' : 'fehlt');

        $summary[] = "\n=== Problembereiche ===";
        $issues = [];

        if ($diagnostics['system_info']['status'] !== 'ok') {
            $issues[] = "• System-Umgebung: " . $diagnostics['system_info']['status'];
            if (!empty($diagnostics['system_info']['missing_required_extensions'])) {
                $issues[] = "  - Fehlende Extensions: " . implode(', ', $diagnostics['system_info']['missing_required_extensions']);
            }
        }
        if ($diagnostics['permissions']['status'] !== 'ok') {
            $issues[] = "• Berechtigungen: " . $diagnostics['permissions']['status'];
            if (!empty($diagnostics['permissions']['issues'])) {
                foreach (array_slice($diagnostics['permissions']['issues'], 0, 3) as $issue) {
                    $issues[] = "  - " . $issue;
                }
            }
        }
        if ($diagnostics['disk_space']['status'] !== 'ok') {
            $issues[] = "• Speicherplatz: " . $diagnostics['disk_space']['status'];
        }
        if ($diagnostics['network']['status'] !== 'ok') {
            $issues[] = "• Netzwerk: " . $diagnostics['network']['status'];
        }

        if (empty($issues)) {
            $summary[] = "Keine kritischen Probleme erkannt.";
        } else {
            $summary = array_merge($summary, $issues);
        }

        $summary[] = "\nVollständige Diagnose wurde gespeichert.";
        $summary[] = "Bitte kontaktieren Sie den Support mit diesem Bericht.";

        return implode("\n", $summary);
    }

    /**
     * Format diagnostic summary as HTML for UI display
     * 
     * @param array<string, mixed> $diagnostics Complete diagnostic data
     * @return string HTML-formatted diagnostic summary
     */
    public static function html(array $diagnostics): string
    {
        $severityClass = match ($diagnostics['severity']) {
            'error' => 'danger',
            'warning' => 'warn',
            'info' => 'info',
            'ok' => 'ok',
            default => 'secondary'
        };

        $severityIcon = match ($diagnostics['severity']) {
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            'ok' => '✅',
            default => '•'
        };

        $html = [];
        $html[] = "<div class='diagnostic-report'>";
        $html[] = "  <div class='ignis-alert ignis-alert--{$severityClass}'>";
        $html[] = "    <h4>{$severityIcon} Update-Diagnose</h4>";
        $html[] = "    <div class='grid grid-cols-2 gap-3 mb-2'>";
        $html[] = "      <div><strong>Schweregrad:</strong> " . strtoupper($diagnostics['severity']) . "</div>";
        $html[] = "      <div><strong>Zeitpunkt:</strong> {$diagnostics['timestamp']}</div>";
        $html[] = "    </div>";

        if ($diagnostics['error_analysis']['has_error']) {
            $html[] = "    <hr>";
            $html[] = "    <div class='error-details'>";
            $html[] = "      <strong>Fehlertyp:</strong> <code>{$diagnostics['error_analysis']['error_type']}</code><br>";
            $html[] = "      <strong>Nachricht:</strong> " . htmlspecialchars(substr($diagnostics['error_analysis']['message'], 0, 300)) . "";
            $html[] = "    </div>";
        }

        $html[] = "  </div>";

        // System Status
        $html[] = "  <div class='ignis-card mb-3'>";
        $html[] = "    <div class='ignis-card__header'><strong>System-Status</strong></div>";
        $html[] = "    <div class='ignis-card__body'>";
        $html[] = "      <div class='grid grid-cols-1 gap-3 md:grid-cols-3'>";
        $html[] = "        <div>";
        $html[] = "          <strong>PHP:</strong> {$diagnostics['system_info']['php_version']}<br>";
        $html[] = "          <span class='ignis-chip ignis-chip--" . self::statusClass($diagnostics['system_info']['status']) . "'>{$diagnostics['system_info']['status']}</span>";
        $html[] = "        </div>";
        $html[] = "        <div>";
        $html[] = "          <strong>Speicher:</strong> " . ($diagnostics['disk_space']['free_space_mb'] ?? 'unbekannt') . " MB<br>";
        $html[] = "          <span class='ignis-chip ignis-chip--" . self::statusClass($diagnostics['disk_space']['status']) . "'>{$diagnostics['disk_space']['status']}</span>";
        $html[] = "        </div>";
        $html[] = "        <div>";
        $html[] = "          <strong>Netzwerk:</strong> GitHub API<br>";
        $html[] = "          <span class='ignis-chip ignis-chip--" . self::statusClass($diagnostics['network']['status']) . "'>{$diagnostics['network']['status']}</span>";
        $html[] = "        </div>";
        $html[] = "      </div>";
        $html[] = "      <div class='grid grid-cols-1 gap-3 md:grid-cols-3 mt-2'>";
        $html[] = "        <div>";
        $html[] = "          <strong>Berechtigungen:</strong><br>";
        $html[] = "          <span class='ignis-chip ignis-chip--" . self::statusClass($diagnostics['permissions']['status']) . "'>{$diagnostics['permissions']['status']}</span>";
        $html[] = "        </div>";
        $html[] = "        <div>";
        $html[] = "          <strong>Vendor:</strong><br>";
        $html[] = "          <small>" . ($diagnostics['dependencies']['vendor_directory_exists'] ? '✓ vorhanden' : '✗ fehlt') . "</small>";
        $html[] = "        </div>";
        $html[] = "      </div>";
        $html[] = "    </div>";
        $html[] = "  </div>";

        // Problems
        $problems = [];
        if ($diagnostics['system_info']['status'] !== 'ok') {
            $problems[] = [
                'title' => 'System-Umgebung',
                'status' => $diagnostics['system_info']['status'],
                'details' => !empty($diagnostics['system_info']['missing_required_extensions'])
                    ? 'Fehlende Extensions: ' . implode(', ', $diagnostics['system_info']['missing_required_extensions'])
                    : null
            ];
        }
        if ($diagnostics['permissions']['status'] !== 'ok') {
            $problems[] = [
                'title' => 'Berechtigungen',
                'status' => $diagnostics['permissions']['status'],
                'details' => !empty($diagnostics['permissions']['issues'])
                    ? implode('<br>', array_slice($diagnostics['permissions']['issues'], 0, 3))
                    : null
            ];
        }
        if ($diagnostics['disk_space']['status'] !== 'ok') {
            $details = 'Nur ' . ($diagnostics['disk_space']['free_space_mb'] ?? 0) . ' MB frei';
            if (isset($diagnostics['disk_space']['storage_size_mb'])) {
                $details .= '<br>storage: ' . $diagnostics['disk_space']['storage_size_mb'] . ' MB';
            }
            if (isset($diagnostics['disk_space']['backup_size_mb'])) {
                $details .= ', backups: ' . $diagnostics['disk_space']['backup_size_mb'] . ' MB';
            }
            if (isset($diagnostics['disk_space']['temp_update_dirs_count']) && $diagnostics['disk_space']['temp_update_dirs_count'] > 0) {
                $details .= '<br>' . $diagnostics['disk_space']['temp_update_dirs_count'] . ' fehlgeschlagene Update-Verzeichnisse';
            }

            $problems[] = [
                'title' => 'Speicherplatz',
                'status' => $diagnostics['disk_space']['status'],
                'details' => $details
            ];
        }
        if ($diagnostics['network']['status'] !== 'ok') {
            $problems[] = [
                'title' => 'Netzwerk',
                'status' => $diagnostics['network']['status'],
                'details' => 'GitHub API nicht erreichbar'
            ];
        }

        if (!empty($problems)) {
            $html[] = "  <div class='ignis-card mb-3'>";
            $html[] = "    <div class='ignis-card__header'><strong>⚠️ Problembereiche</strong></div>";
            $html[] = "    <div class='ignis-card__body'>";
            $html[] = "      <ul class='mb-0'>";
            foreach ($problems as $problem) {
                $html[] = "        <li>";
                $html[] = "          <strong>{$problem['title']}:</strong> ";
                $html[] = "          <span class='ignis-chip ignis-chip--" . self::statusClass($problem['status']) . "'>{$problem['status']}</span>";
                if ($problem['details']) {
                    $html[] = "          <br><small class='text-gray-400'>{$problem['details']}</small>";
                }
                $html[] = "        </li>";
            }
            $html[] = "      </ul>";
            $html[] = "    </div>";
            $html[] = "  </div>";
        } else {
            $html[] = "  <div class='ignis-alert ignis-alert--ok'>";
            $html[] = "    ✓ Keine kritischen Probleme erkannt.";
            $html[] = "  </div>";
        }

        // Support Info
        $html[] = "  <div class='ignis-card'>";
        $html[] = "    <div class='ignis-card__body text-center'>";
        $html[] = "      <p class='mb-2'><strong>Diagnose wurde gespeichert.</strong></p>";
        $html[] = "      <p class='mb-2'>Bitte kontaktieren Sie den Support mit diesem Bericht.</p>";
        $html[] = "      <button class='ignis-btn ignis-btn--primary ignis-btn--sm' onclick='copyDiagnosticReport()'>📋 In Zwischenablage kopieren</button>";
        $html[] = "      <button class='ignis-btn ignis-btn--ghost ignis-btn--sm' onclick='downloadDiagnosticReport()'>💾 Als Datei herunterladen</button>";
        $html[] = "    </div>";
        $html[] = "  </div>";

        $html[] = "</div>";

        return implode("\n", $html);
    }

    /**
     * Generate support export text (easy to copy/paste)
     * 
     * @param array<string, mixed> $diagnostics Complete diagnostic data
     * @return string Formatted text for support ticket
     */
    public static function support(array $diagnostics): string
    {
        $lines = [];
        $lines[] = "========================================";
        $lines[] = "ıgnıs System-Diagnose";
        $lines[] = "========================================";
        $lines[] = "";
        $lines[] = "Zeitpunkt: " . $diagnostics['timestamp'];
        $lines[] = "Schweregrad: " . strtoupper($diagnostics['severity']);
        $lines[] = "Version: " . ($diagnostics['update_history']['current_version']['version'] ?? 'unbekannt');
        $lines[] = "";

        if ($diagnostics['error_analysis']['has_error']) {
            $lines[] = "FEHLER-DETAILS:";
            $lines[] = "---------------";
            $lines[] = "Typ: " . $diagnostics['error_analysis']['error_type'];
            $lines[] = "Nachricht: " . $diagnostics['error_analysis']['message'];
            $lines[] = "";
        }

        $lines[] = "SYSTEM-INFORMATION:";
        $lines[] = "-------------------";
        $lines[] = "PHP Version: " . $diagnostics['system_info']['php_version'];
        $lines[] = "Betriebssystem: " . $diagnostics['system_info']['os'];
        $lines[] = "SAPI: " . $diagnostics['system_info']['sapi'];
        $lines[] = "Memory Limit: " . $diagnostics['system_info']['memory_limit'];
        $lines[] = "Max Execution Time: " . $diagnostics['system_info']['max_execution_time'] . "s";

        if (!empty($diagnostics['system_info']['missing_required_extensions'])) {
            $lines[] = "Fehlende Extensions: " . implode(', ', $diagnostics['system_info']['missing_required_extensions']);
        }
        $lines[] = "";

        $lines[] = "SPEICHER:";
        $lines[] = "---------";
        $lines[] = "Frei: " . ($diagnostics['disk_space']['free_space_mb'] ?? 'unbekannt') . " MB";
        $lines[] = "Gesamt: " . ($diagnostics['disk_space']['total_space_mb'] ?? 'unbekannt') . " MB";
        $lines[] = "Auslastung: " . ($diagnostics['disk_space']['usage_percent'] ?? 'unbekannt') . "%";
        if (isset($diagnostics['disk_space']['storage_size_mb'])) {
            $lines[] = "Storage-Verzeichnis: " . $diagnostics['disk_space']['storage_size_mb'] . " MB";
        }
        if (isset($diagnostics['disk_space']['backup_size_mb'])) {
            $lines[] = "Backup-Verzeichnis: " . $diagnostics['disk_space']['backup_size_mb'] . " MB";
        }
        if (isset($diagnostics['disk_space']['temp_update_dirs_count']) && $diagnostics['disk_space']['temp_update_dirs_count'] > 0) {
            $lines[] = "Fehlgeschlagene Update-Verzeichnisse: " . $diagnostics['disk_space']['temp_update_dirs_count'];
        }
        $lines[] = "Status: " . $diagnostics['disk_space']['status'];
        $lines[] = "";

        $lines[] = "BERECHTIGUNGEN:";
        $lines[] = "---------------";
        $lines[] = "Status: " . $diagnostics['permissions']['status'];
        if (!empty($diagnostics['permissions']['issues'])) {
            foreach ($diagnostics['permissions']['issues'] as $issue) {
                $lines[] = "  - " . $issue;
            }
        }
        $lines[] = "";

        $lines[] = "NETZWERK:";
        $lines[] = "---------";
        $lines[] = "Status: " . $diagnostics['network']['status'];
        $lines[] = "GitHub API: " . ($diagnostics['network']['tests']['github_api']['accessible'] ? 'erreichbar' : 'nicht erreichbar');
        if (isset($diagnostics['network']['tests']['github_api']['response_time_ms'])) {
            $lines[] = "Response Time: " . $diagnostics['network']['tests']['github_api']['response_time_ms'] . " ms";
        }
        $lines[] = "";

        $lines[] = "ABHÄNGIGKEITEN:";
        $lines[] = "---------------";
        $lines[] = "Composer: " . ($diagnostics['dependencies']['composer_available'] ? 'verfügbar' : 'nicht verfügbar');
        if ($diagnostics['dependencies']['composer_version']) {
            $lines[] = "  Version: " . $diagnostics['dependencies']['composer_version'];
        }
        $lines[] = "Vendor: " . ($diagnostics['dependencies']['vendor_directory_exists'] ? 'vorhanden' : 'fehlt');
        $lines[] = "Autoload: " . ($diagnostics['dependencies']['autoload_exists'] ? 'vorhanden' : 'fehlt');
        $lines[] = "";

        $lines[] = "KONFIGURATION:";
        $lines[] = "--------------";
        $lines[] = "Hosting: " . ($diagnostics['configuration']['is_plesk'] ? 'Plesk' : ($diagnostics['configuration']['is_cpanel'] ? 'cPanel' : 'Standard'));
        $lines[] = "Git Repository: " . ($diagnostics['configuration']['git_repository'] ? 'ja' : 'nein');
        $lines[] = "";

        $lines[] = "========================================";
        $lines[] = "Ende des Diagnose-Berichts";
        $lines[] = "========================================";

        return implode("\n", $lines);
    }

    /**
     * Helper: Get Bootstrap CSS class for status
     */
    private static function statusClass(string $status): string
    {
        return match ($status) {
            'ok' => 'ok',
            'info' => 'info',
            'warning' => 'warn',
            'error' => 'danger',
            default => 'secondary'
        };
    }
}
