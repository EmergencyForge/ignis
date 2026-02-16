<?php
if (getenv('GITHUB_ACTIONS') === 'true' || getenv('CI') === 'true') {
    echo "⚠️  Skipping database setup in CI (GitHub Actions).\n";
    exit(0);
}

function findProjectRoot(): string
{
    $currentDir = __DIR__;
    $workingDir = getcwd();

    echo "DEBUG: Script-Pfad: $currentDir\n";
    echo "DEBUG: Arbeitsverzeichnis: $workingDir\n";

    $candidates = [
        dirname($currentDir),
        dirname($workingDir),
        dirname($workingDir, 2),
        dirname($workingDir, 3),
        dirname($workingDir, 4),
        $workingDir,
        dirname($currentDir, 2),
        dirname($currentDir, 3),
        dirname($currentDir, 4),
    ];

    foreach ($candidates as $candidate) {
        $candidate = realpath($candidate);
        if (!$candidate) continue;

        echo "DEBUG: Teste Root-Kandidat: $candidate\n";

        if (
            file_exists($candidate . '/composer.json') &&
            file_exists($candidate . '/.env') &&
            is_dir($candidate . '/vendor')
        ) {
            echo "✓ Project-Root gefunden: $candidate\n";
            return $candidate;
        }
    }

    throw new Exception("Konnte Project-Root nicht finden. Getestete Pfade: " . implode(', ', $candidates));
}

try {
    $projectRoot = findProjectRoot();
} catch (Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
    exit(1);
}

$autoloadPath = $projectRoot . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo "❌ Autoloader nicht gefunden: $autoloadPath\n";
    exit(1);
}

require_once $autoloadPath;
echo "✓ Autoloader geladen von: $autoloadPath\n";

try {
    $dotenv = Dotenv\Dotenv::createImmutable($projectRoot, null, false);
    $dotenv->load();
    echo "✓ .env geladen von: $projectRoot/.env\n";
} catch (Exception $e) {
    echo "❌ Konnte .env nicht laden: " . $e->getMessage() . "\n";
    echo "Suchpfad war: $projectRoot/.env\n";
    exit(1);
}

$requiredVars = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'];
foreach ($requiredVars as $var) {
    if (!isset($_ENV[$var]) || empty($_ENV[$var])) {
        echo "❌ Umgebungsvariable $var nicht gesetzt\n";
        exit(1);
    }
}

$db_host = $_ENV['DB_HOST'];
$db_user = $_ENV['DB_USER'];
$db_pass = $_ENV['DB_PASS'];
$db_name = $_ENV['DB_NAME'];

$dsn = "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    echo "✓ Datenbankverbindung erfolgreich\n";

    // Check SQL mode for debugging environment-specific issues
    try {
        $stmt = $pdo->query("SELECT @@sql_mode");
        $sqlMode = $stmt->fetchColumn();
        echo "ℹ️  SQL Mode: " . ($sqlMode ?: '(empty)') . "\n";

        // Check for problematic modes
        $modes = array_map('trim', explode(',', $sqlMode));
        $recommendedModes = ['STRICT_TRANS_TABLES', 'NO_ZERO_IN_DATE', 'NO_ZERO_DATE', 'ERROR_FOR_DIVISION_BY_ZERO', 'NO_ENGINE_SUBSTITUTION'];
        $problematicModes = ['TRADITIONAL', 'STRICT_ALL_TABLES'];

        foreach ($problematicModes as $mode) {
            if (in_array($mode, $modes)) {
                echo "⚠️  SQL Mode '$mode' ist aktiv (kann zu strengeren Validierungen führen)\n";
            }
        }

        // Check MySQL/MariaDB version
        $stmt = $pdo->query("SELECT VERSION()");
        $version = $stmt->fetchColumn();
        echo "ℹ️  Datenbankversion: $version\n";
    } catch (PDOException $e) {
        // Non-critical, continue anyway
        echo "⚠️  Konnte SQL Mode nicht abfragen: " . $e->getMessage() . "\n";
    }
} catch (PDOException $e) {
    echo "❌ Datenbankverbindung fehlgeschlagen: " . $e->getMessage() . "\n";
    exit(1);
}

function ensureMigrationsTable(PDO $pdo): void
{
    $sql = "CREATE TABLE IF NOT EXISTS intra_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_migration (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);
}

function migrationHasRun(PDO $pdo, string $migrationName): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM intra_migrations WHERE migration = ?");
    $stmt->execute([$migrationName]);
    return $stmt->fetchColumn() > 0;
}

function markMigrationAsRun(PDO $pdo, string $migrationName): void
{
    $stmt = $pdo->prepare("INSERT IGNORE INTO intra_migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);
}

function extractTableName(string $fileName): ?string
{
    // Extract table name from migration file name
    // Examples: create_intra_users_07062025.php -> intra_users
    //           alter_intra_edivi_03092025.php -> intra_edivi
    if (preg_match('/^(create|alter)_(.+?)_\d{8}\.php$/', $fileName, $matches)) {
        return $matches[2];
    }
    return null;
}

function tableExists(PDO $pdo, string $tableName): bool
{
    try {
        $stmt = $pdo->prepare("
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ?
        ");
        $stmt->execute([$tableName]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function columnExists(PDO $pdo, string $tableName, string $columnName): bool
{
    try {
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = ?
        ");
        $stmt->execute([$tableName, $columnName]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function extractColumnName(string $migrationContent): ?string
{
    // Extract column name from ALTER TABLE ADD COLUMN statements
    // Examples: ADD COLUMN `c_zugang` LONGTEXT NULL -> c_zugang
    if (preg_match('/ADD\s+COLUMN\s+`?([a-zA-Z0-9_]+)`?/i', $migrationContent, $matches)) {
        return $matches[1];
    }
    return null;
}

function isTransactionActive(PDO $pdo): bool
{
    return $pdo->inTransaction();
}

function throwTableCreationError(PDO $pdo, string $missingTableInfo): never
{
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $tableList = empty($tables) ? 'keine' : implode(', ', $tables);
        throw new Exception("$missingTableInfo. Existing tables: $tableList");
    } catch (PDOException $e) {
        throw new Exception("$missingTableInfo. Could not list tables: " . $e->getMessage());
    }
}

ensureMigrationsTable($pdo);

$migrationPath = $projectRoot . '/assets/database';
if (!is_dir($migrationPath)) {
    echo "❌ Migration-Verzeichnis nicht gefunden: $migrationPath\n";
    exit(1);
}

echo "✓ Migration-Verzeichnis: $migrationPath\n\n";

$migrationFiles = [
    // 07.06.2025
    ['file' => 'create_intra_users_roles_07062025.php', 'type' => 'create'],
    ['file' => 'insert_intra_users_roles_07062025.php', 'type' => 'insert'],
    ['file' => 'create_intra_users_07062025.php', 'type' => 'create'],
    ['file' => 'create_intra_audit_log_07062025.php', 'type' => 'create'],
    ['file' => 'add_foreign_keys_07062025.php', 'type' => 'alter'],
    ['file' => 'create_intra_dashboard_categories_07062025.php', 'type' => 'create'],
    ['file' => 'create_intra_dashboard_tiles_07062025.php', 'type' => 'create'],
    ['file' => 'create_intra_edivi_07062025.php', 'type' => 'create'],
    ['file' => 'create_intra_edivi_fahrzeuge_07062025.php', 'type' => 'create'],
    ['file' => 'create_intra_edivi_qmlog_07062025.php', 'type' => 'create'],
    ['file' => 'create_intra_edivi_ziele_07062025.php', 'type' => 'create'],
    ['file' => 'insert_intra_edivi_ziele_07062025.php', 'type' => 'insert'],
    ['file' => 'create_intra_mitarbeiter_dienstgrade_07062025.php', 'type' => 'create'],
    ['file' => 'insert_intra_mitarbeiter_dienstgrade_07062025.php', 'type' => 'insert'],
    ['file' => 'create_intra_mitarbeiter_fwquali_07062025.php', 'type' => 'create'],
    ['file' => 'insert_intra_mitarbeiter_fwquali_07062025.php', 'type' => 'insert'],
    ['file' => 'create_intra_mitarbeiter_log_07062025.php', 'type' => 'create'],
    ['file' => 'create_intra_mitarbeiter_rdquali_07062025.php', 'type' => 'create'],
    ['file' => 'insert_intra_mitarbeiter_rdquali_07062025.php', 'type' => 'insert'],
    ['file' => 'create_intra_mitarbeiter_07062025.php', 'type' => 'create'],
    ['file' => 'create_intra_mitarbeiter_dokumente_07062025.php', 'type' => 'create'],
    //['file' => 'create_intra_uploads_07062025.php', 'type' => 'create'],

    // 13.06.2025
    ['file' => 'create_intra_mitarbeiter_fdquali_13062025.php', 'type' => 'create'],
    ['file' => 'insert_intra_mitarbeiter_fdquali_13062025.php', 'type' => 'insert'],

    // 18.06.2025
    ['file' => 'create_intra_edivi_prereg_18062025.php', 'type' => 'create'],

    // 23.06.2025
    ['file' => 'update_intra_edivi_fahrzeuge_23062025.php', 'type' => 'alter'],
    ['file' => 'create_intra_fahrzeuge_beladung_categories_23062025.php', 'type' => 'create'],
    ['file' => 'create_intra_fahrzeuge_beladung_tiles_23062025.php', 'type' => 'create'],
    ['file' => 'update_intra_mitarbeiter_23062025.php', 'type' => 'alter'],
    ['file' => 'update_intra_mitarbeiter_dokumente_23062025.php', 'type' => 'alter'],

    // 06.07.2025
    ['file' => 'update_intra_edivi_06072025.php', 'type' => 'alter'],
    ['file' => 'create_intra_edivi_vitalparameter_einzelwerte_06072025.php', 'type' => 'create'],

    // 03.09.2025
    ['file' => 'alter_intra_edivi_03092025.php', 'type' => 'alter'],

    // 08.09.2025
    ['file' => 'alter_intra_edivi_08092025.php', 'type' => 'alter'],

    // 30.09.2025
    ['file' => 'update_intra_mitarbeiter_dokumente_30092025_templates.php', 'type' => 'alter'],
    ['file' => 'insert_system_templates_30092025.php', 'type' => 'insert'],
    ['file' => 'migrate_existing_documents_30092025.php', 'type' => 'data'],
    ['file' => 'insert_template_fields_30092025.php', 'type' => 'insert'],
    ['file' => 'create_intra_antrag_typen_30092025.php', 'type' => 'create'],
    ['file' => 'create_intra_antrag_felder_30092025.php', 'type' => 'create'],
    ['file' => 'insert_intra_dynamic_antraege_30092025.php', 'type' => 'insert'],
    ['file' => 'create_intra_antraege_30092025.php', 'type' => 'create'],
    ['file' => 'create_intra_antraege_daten_30092025.php', 'type' => 'create'],

    // 03.10.2025
    ['file' => 'alter_intra_mitarbeiter_dokumente_03102025.php', 'type' => 'alter'],

    // 07.10.2025
    ['file' => 'alter_intra_edivi_07102025.php', 'type' => 'alter'],

    // 09.10.2025
    ['file' => 'alter_intra_edivi_09102025.php', 'type' => 'alter'],

    // 13.10.2025
    ['file' => 'alter_intra_edivi_13102025.php', 'type' => 'alter'],

    // 02.11.2025
    ['file' => 'alter_intra_mitarbeiter_02112025.php', 'type' => 'alter'],
    ['file' => 'create_intra_registration_codes_02112025.php', 'type' => 'create'],

    // 03.11.2025
    ['file' => 'create_intra_notifications_03112025.php', 'type' => 'create'],

    // 04.11.2025
    ['file' => 'alter_intra_mitarbeiter_log_04112025.php', 'type' => 'alter'],
    ['file' => 'create_intra_config_04112025.php', 'type' => 'create'],
    ['file' => 'insert_intra_config_defaults_04112025.php', 'type' => 'insert'],
    ['file' => 'alter_intra_notifications_04112025.php', 'type' => 'alter'],
    ['file' => 'remove_lang_config_04112025.php', 'type' => 'alter'],

    // 29.11.2025
    ['file' => 'create_intra_kb_entries_29112025.php', 'type' => 'create'],
    ['file' => 'insert_intra_kb_config_29112025.php', 'type' => 'insert'],
    ['file' => 'alter_intra_kb_entries_29112025_pinned.php', 'type' => 'alter'],
    ['file' => 'alter_intra_kb_entries_29112025_hide_editor.php', 'type' => 'alter'],

    // 02.12.2025
    ['file' => 'create_intra_edivi_medikamente_02122025.php', 'type' => 'create'],
    ['file' => 'insert_intra_edivi_medikamente_02122025.php', 'type' => 'insert'],

    // 08.12.2025
    ['file' => 'alter_intra_edivi_08122025.php', 'type' => 'alter'],
    ['file' => 'create_intra_edivi_pois_08122025.php', 'type' => 'create'],
    ['file' => 'alter_intra_edivi_pois_08122025_add_typ.php', 'type' => 'alter'],

    // 09.12.2025
    ['file' => 'insert_config_09122025_bz_unit.php', 'type' => 'insert'],

    // 14.12.2025
    ['file' => 'create_intra_manv_lagen_14122025.php', 'type' => 'create'],
    ['file' => 'create_intra_manv_patienten_14122025.php', 'type' => 'create'],
    ['file' => 'create_intra_manv_ressourcen_14122025.php', 'type' => 'create'],
    ['file' => 'create_intra_manv_log_14122025.php', 'type' => 'create'],
    ['file' => 'create_intra_edivi_klinikcodes_14122025.php', 'type' => 'create'],

    // 17.12.2025
    ['file' => 'alter_intra_manv_patienten_17122025.php', 'type' => 'alter'],
    ['file' => 'create_intra_edivi_share_requests_17122025.php', 'type' => 'create'],

    // 23.12.2025
    ['file' => 'create_intra_fire_incidents_23122025.php', 'type' => 'create'],
    ['file' => 'create_intra_fire_incident_vehicles_23122025.php', 'type' => 'create'],
    ['file' => 'create_intra_fire_incident_sitreps_23122025.php', 'type' => 'create'],
    ['file' => 'alter_intra_fire_incidents_20250108_add_incident_number.php', 'type' => 'alter'],
    ['file' => 'create_intra_fire_incident_asu_20250101.php', 'type' => 'create'],

    // 24.12.2025
    ['file' => 'create_intra_fire_incident_log_24122025.php', 'type' => 'create'],
    ['file' => 'insert_config_24122025_fire_auth.php', 'type' => 'insert'],

    // 25.12.2025
    ['file' => 'alter_intra_fire_incidents_20250125_allow_null.php', 'type' => 'alter'],
    ['file' => 'create_intra_fire_incident_map_markers_25122025.php', 'type' => 'create'],
    ['file' => 'alter_intra_fire_incident_map_markers_25122025_tactical_symbols.php', 'type' => 'alter'],
    ['file' => 'alter_intra_fire_incident_map_markers_25122025_add_symbol.php', 'type' => 'alter'],
    ['file' => 'alter_intra_fire_incident_map_markers_25122025_add_text.php', 'type' => 'alter'],
    ['file' => 'alter_intra_fahrzeuge_25122025_tactical_symbols.php', 'type' => 'alter'],
    ['file' => 'create_intra_fire_incident_map_zones_25122025.php', 'type' => 'create'],
    ['file' => 'add_vehicle_id_to_zones_25122025.php', 'type' => 'alter'],
    ['file' => 'add_operator_id_to_markers_25122025.php', 'type' => 'alter'],
    ['file' => 'add_operator_id_to_zones_25122025.php', 'type' => 'alter'],

    // 27.12.2025
    ['file' => 'alter_map_markers_27122025_created_at.php', 'type' => 'alter'],
    ['file' => 'alter_map_zones_27122025_created_at.php', 'type' => 'alter'],
    ['file' => 'alter_intra_fire_incident_log_27122025_created_by_nullable.php', 'type' => 'alter'],
    ['file' => 'alter_fire_incident_vehicles_27122025_add_status.php', 'type' => 'alter'],
    ['file' => 'alter_fire_incidents_27122025_add_caller_fields.php', 'type' => 'alter'],
    ['file' => 'alter_fire_incidents_27122025_add_archived.php', 'type' => 'alter'],
    ['file' => 'alter_intra_fire_incidents_27122025_add_gta_coordinates.php', 'type' => 'alter'],

    // 29.12.2025
    ['file' => 'create_intra_enotf_quicklinks_29122025.php', 'type' => 'create'],
    ['file' => 'insert_intra_enotf_quicklinks_29122025.php', 'type' => 'insert'],
    ['file' => 'create_intra_enotf_categories_29122025.php', 'type' => 'create'],
    ['file' => 'insert_intra_enotf_categories_29122025.php', 'type' => 'insert'],
    ['file' => 'alter_intra_enotf_quicklinks_29122025_category_slug.php', 'type' => 'alter'],

    // 30.12.2025
    ['file' => 'alter_intra_edivi_30122025_billing.php', 'type' => 'alter'],

    // 16.01.2026
    ['file' => 'add_performance_indexes_16012026.php', 'type' => 'alter'],
    ['file' => 'insert_discord_webhooks_config_16012026.php', 'type' => 'insert'],

    // 20.01.2026 - Telemetrie & Globale Announcements
    ['file' => 'insert_telemetry_config_20012026.php', 'type' => 'insert'],
    ['file' => 'create_intra_global_announcements_cache_20012026.php', 'type' => 'create'],
    ['file' => 'create_intra_global_announcements_dismissed_20012026.php', 'type' => 'create'],

    // 21.01.2026 - Krankenhaus-Verfügbarkeit
    ['file' => 'create_intra_edivi_hospital_departments_21012026.php', 'type' => 'create'],
    ['file' => 'create_intra_edivi_hospital_availability_21012026.php', 'type' => 'create'],
    ['file' => 'create_intra_edivi_hospital_access_codes_21012026.php', 'type' => 'create'],
    ['file' => 'alter_intra_edivi_hospital_departments_21012026_sort_order.php', 'type' => 'alter'],
    ['file' => 'alter_intra_edivi_hospital_access_codes_21012026_plaintext.php', 'type' => 'alter'],

    // 04.02.2026 - RD Qualifikationen Abkürzung
    ['file' => 'alter_intra_mitarbeiter_rdquali_04022026_abbreviation.php', 'type' => 'alter'],

    // 04.02.2026 - eNOTF Protokoll Erstellungsherkunft
    ['file' => 'alter_intra_edivi_04022026_createdby.php', 'type' => 'alter'],

    // 09.02.2026 - Symptome (Symptombeginn + NACA)
    ['file' => 'alter_intra_edivi_09022026_symptome.php', 'type' => 'alter'],

    // 09.02.2026 - Einsatzort (Elokation)
    ['file' => 'alter_intra_edivi_09022026_elokation.php', 'type' => 'alter'],

    // 10.02.2026 - Lagemeldungen Source + Synced
    ['file' => 'alter_intra_fire_incident_sitreps_10022026_add_source_synced.php', 'type' => 'alter'],

    // 10.02.2026 - Status-Queue für FiveM-Polling
    ['file' => 'create_intra_fire_status_queue_10022026.php', 'type' => 'create'],

    // 10.02.2026 - Fahrzeug-Status (für status_no_dispatch)
    ['file' => 'alter_intra_fahrzeuge_10022026_status.php', 'type' => 'alter'],

    // 11.02.2026 - Patientenname aufteilen + Sync-Flag
    ['file' => 'alter_intra_edivi_11022026_split_patname.php', 'type' => 'alter'],

    // 15.02.2026 - Praktikant (3. Person) auf Fahrzeugen
    ['file' => 'alter_intra_edivi_15022026_praktikant.php', 'type' => 'alter'],

    // 16.02.2026 - Timezone-Fix: Bestehende Berlin-Zeiten nach UTC migrieren
    ['file' => 'migrate_fix_sitrep_timezone_16022026.php', 'type' => 'data'],
];

$executed = 0;
$skipped = 0;
$alreadyRun = 0;

echo "=== Starte Migrations-Prozess ===\n\n";

foreach ($migrationFiles as $migration) {
    $file = $migration['file'];
    $type = $migration['type'] ?? 'unknown';
    $fullPath = $migrationPath . '/' . $file;

    if (!file_exists($fullPath)) {
        $skipped++;
        echo "⚠️  Migration nicht gefunden: $file\n";
        continue;
    }

    if (migrationHasRun($pdo, $file)) {
        $alreadyRun++;
        echo "⏭️  Bereits ausgeführt: $file\n";
        continue;
    }

    try {
        echo "▶️  Führe aus [$type]: $file\n";

        // Capture output from the migration file
        ob_start();
        include $fullPath;
        $migrationOutput = ob_get_clean();

        // Check if there was any error output from the migration
        // Look for common SQL error patterns
        if (!empty($migrationOutput)) {
            $isError = false;
            $errorPatterns = [
                'SQLSTATE',
                'Table .* doesn\'t exist',
                'Unknown column',
                'Duplicate column',
                'Syntax error',
                'Access denied',
                'Can\'t create table',
                'Can\'t DROP',
                'foreign key constraint fails'
            ];

            foreach ($errorPatterns as $pattern) {
                if (preg_match('/' . $pattern . '/i', $migrationOutput)) {
                    $isError = true;
                    break;
                }
            }

            if ($isError) {
                throw new Exception("Migration produced error output: " . $migrationOutput);
            } else {
                // Non-error output, just display it
                echo $migrationOutput;
            }
        }

        // For CREATE migrations, verify the table was actually created
        if ($type === 'create') {
            // Check if migration specifies multiple tables to verify
            if (isset($migration['tables']) && is_array($migration['tables'])) {
                if (empty($migration['tables'])) {
                    throw new Exception("Migration has 'tables' parameter but it is empty");
                }

                $missingTables = [];
                foreach ($migration['tables'] as $tableName) {
                    if (!tableExists($pdo, $tableName)) {
                        $missingTables[] = $tableName;
                    }
                }

                if (!empty($missingTables)) {
                    $missingList = implode(', ', $missingTables);
                    throwTableCreationError($pdo, "Tables were not created successfully: $missingList");
                }
            } else {
                // Single table migration - extract table name from filename
                $tableName = extractTableName($file);
                if ($tableName) {
                    $exists = tableExists($pdo, $tableName);
                    if (!$exists) {
                        throwTableCreationError($pdo, "Table '$tableName' was not created successfully");
                    }
                }
            }
        }

        // For ALTER migrations, verify the table exists and column was added if applicable
        if ($type === 'alter') {
            $tableName = extractTableName($file);
            if ($tableName) {
                // Check if table exists before altering
                if (!tableExists($pdo, $tableName)) {
                    throw new Exception("Cannot alter table '$tableName' - table does not exist");
                }

                // Check if this is an ADD COLUMN migration
                $migrationContent = file_get_contents($fullPath);
                $columnName = extractColumnName($migrationContent);
                if ($columnName && !columnExists($pdo, $tableName, $columnName)) {
                    throw new Exception("Column '$columnName' was not added to table '$tableName' successfully");
                }
            }
        }

        markMigrationAsRun($pdo, $file);
        $executed++;
        echo "✅ Erfolgreich: $file\n\n";
    } catch (Exception $e) {
        echo "❌ Fehlgeschlagen: $file\n";
        echo "   Fehler: " . $e->getMessage() . "\n\n";

        if ($type === 'create' || $type === 'alter') {
            echo "⚠️  Kritischer Fehler bei $type-Migration. Abbruch.\n";
            echo "   Bitte überprüfen Sie:\n";
            echo "   1. Datenbankberechtigungen (CREATE, ALTER, INDEX Rechte)\n";
            echo "   2. MySQL/MariaDB Version und Kompatibilität\n";
            echo "   3. Verfügbarer Speicherplatz\n";
            echo "   4. MySQL-Fehlerlog für detaillierte Fehlermeldungen\n\n";
            exit(1);
        }

        echo "⚠️  Überspringe diese Migration und fahre fort...\n\n";
    }
}

echo "\n=== Database Setup Abgeschlossen ===\n";
echo "Project Root: $projectRoot\n";
echo "Neue Migrationen ausgeführt: $executed\n";
echo "Bereits ausgeführt (übersprungen): $alreadyRun\n";
echo "Nicht gefunden: $skipped\n";
echo "Gesamt: " . count($migrationFiles) . " Migrationen\n";

// Verify critical tables exist
$criticalTables = [
    'intra_users',
    'intra_users_roles',
    'intra_migrations',
    'intra_audit_log'
];

$missingTables = [];
foreach ($criticalTables as $table) {
    if (!tableExists($pdo, $table)) {
        $missingTables[] = $table;
    }
}

if (!empty($missingTables)) {
    echo "\n⚠️  WARNUNG: Folgende kritische Tabellen fehlen:\n";
    foreach ($missingTables as $table) {
        echo "   - $table\n";
    }
    echo "\nBitte führen Sie 'composer db:migrate' erneut aus oder überprüfen Sie die Datenbankberechtigungen.\n";
    exit(1);
}

echo "\n✅ Alle kritischen Tabellen wurden erfolgreich erstellt.\n\n";

exit(0);
