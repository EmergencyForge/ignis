<?php
/**
 * Migration File Validation Script
 * 
 * This script checks all migration files for potential naming issues
 * that could cause migration failures.
 * 
 * Usage: php setup/validate-migrations.php
 */

function findProjectRoot(): string
{
    $currentDir = __DIR__;
    $candidates = [
        dirname($currentDir),
        dirname($currentDir, 2),
    ];

    foreach ($candidates as $candidate) {
        $candidate = realpath($candidate);
        if (!$candidate) continue;

        if (file_exists($candidate . '/composer.json') &&
            is_dir($candidate . '/assets/database')) {
            return $candidate;
        }
    }

    throw new Exception("Could not find project root");
}

function extractTableName(string $fileName): ?string
{
    // Extract table name from migration file name
    // Handles CREATE and ALTER types only (critical operations)
    // Date format: DDMMYYYY (8 digits, e.g., 07062025 for June 7, 2025)
    if (preg_match('/^(create|alter)_(.+?)_\d{8}\.php$/', $fileName, $matches)) {
        return $matches[2];
    }
    return null;
}

/**
 * @return array<int, string>
 */
function extractTablesFromSQL(string $content): array
{
    $tables = [];
    
    // Match CREATE TABLE statements
    if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $content, $matches)) {
        $tables = array_merge($tables, $matches[1]);
    }
    
    // Match ALTER TABLE statements
    if (preg_match_all('/ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $content, $matches)) {
        $tables = array_merge($tables, $matches[1]);
    }
    
    return array_unique($tables);
}

/**
 * Special files that don't follow the standard table naming convention
 * These files perform multiple operations or special tasks
 */
const SPECIAL_FILES = [
    'add_foreign_keys_07062025.php',
    'migrate_existing_documents_30092025.php',
    'remove_lang_config_04112025.php'
];

/**
 * @return array<int, string>
 */
function getConfiguredMultiTableFiles(string $projectRoot): array
{
    // Parse database-init.php to find files with 'tables' parameter
    // This identifies multi-table migrations that are already properly configured
    $initFile = $projectRoot . '/setup/database-init.php';
    if (!file_exists($initFile)) {
        return [];
    }
    
    $content = file_get_contents($initFile);
    $multiTableFiles = [];
    
    // Match entries with 'tables' parameter in the migrationFiles array
    // Pattern: ['file' => 'filename.php', ..., 'tables' => [...]
    // Note: This is a simple pattern matcher. If the array format changes significantly,
    // this may need to be updated or replaced with more robust parsing.
    if (preg_match_all("/\['file'\s*=>\s*'([^']+)'[^\]]*'tables'\s*=>\s*\[/", $content, $matches)) {
        $multiTableFiles = $matches[1];
    }
    
    return $multiTableFiles;
}

try {
    $projectRoot = findProjectRoot();
    $migrationPath = $projectRoot . '/assets/database';
    
    if (!is_dir($migrationPath)) {
        echo "❌ Migration directory not found: $migrationPath\n";
        exit(1);
    }
    
    $files = glob($migrationPath . '/*.php');
    sort($files);
    
    $multiTableFiles = getConfiguredMultiTableFiles($projectRoot);
    
    $issues = [];
    $warnings = [];
    $checked = 0;
    
    echo "=== Migration File Validation ===\n";
    echo "Checking " . count($files) . " migration files...\n\n";
    
    foreach ($files as $file) {
        $fileName = basename($file);
        
        // Skip special files that don't follow naming convention
        if (in_array($fileName, SPECIAL_FILES)) {
            continue;
        }
        
        // Skip files that are configured with 'tables' parameter (multi-table migrations)
        if (in_array($fileName, $multiTableFiles)) {
            continue;
        }
        
        // Only validate CREATE and ALTER migrations (critical types)
        $extractedTableName = extractTableName($fileName);
        
        if ($extractedTableName === null) {
            // This is an INSERT, UPDATE, or other non-critical migration type
            continue;
        }
        
        $checked++;
        $content = file_get_contents($file);
        $tablesInSQL = extractTablesFromSQL($content);
        
        if (empty($tablesInSQL)) {
            $warnings[] = [
                'file' => $fileName,
                'message' => 'No CREATE/ALTER TABLE statements found'
            ];
            continue;
        }
        
        // Check if extracted table name matches any table in SQL
        $matches = false;
        foreach ($tablesInSQL as $sqlTable) {
            if ($sqlTable === $extractedTableName) {
                $matches = true;
                break;
            }
        }
        
        if (!$matches) {
            $issues[] = [
                'file' => $fileName,
                'extracted' => $extractedTableName,
                'sql_tables' => $tablesInSQL,
            ];
        }
    }
    
    echo "Checked $checked critical migration files (CREATE/ALTER)\n\n";
    
    if (!empty($issues)) {
        echo "❌ ISSUES FOUND (" . count($issues) . "):\n\n";
        foreach ($issues as $issue) {
            echo "File: {$issue['file']}\n";
            echo "  Extracted table name: {$issue['extracted']}\n";
            echo "  Actual SQL tables: " . implode(', ', $issue['sql_tables']) . "\n";
            echo "  ⚠️  This mismatch could cause migration failures!\n\n";
        }
        exit(1);
    }
    
    if (!empty($warnings)) {
        echo "⚠️  WARNINGS (" . count($warnings) . "):\n\n";
        foreach ($warnings as $warning) {
            echo "File: {$warning['file']}\n";
            echo "  {$warning['message']}\n\n";
        }
    }
    
    echo "✅ All critical migration files validated successfully!\n";
    echo "   No naming mismatches found.\n";
    exit(0);
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
