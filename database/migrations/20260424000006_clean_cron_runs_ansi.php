<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Einmalige Säuberung: ANSI-Escape-Codes (Symfony-Console-Farbcodes) aus
 * historischen `intra_cron_runs.output`-Einträgen entfernen. Ab jetzt filtert
 * der `ConsoleHandler` den Output vor dem Schreiben — diese Migration ist
 * nur für Installationen relevant, die bereits Runs vor dem Fix angesammelt
 * haben.
 *
 * Läuft PHP-seitig (nicht als SQL-REGEXP_REPLACE), um unabhängig von der
 * MySQL-Version zu bleiben.
 */
class CleanCronRunsAnsi extends AbstractMigration
{
    public function up(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        $stmt = $pdo->query("SELECT id, output FROM intra_cron_runs WHERE output LIKE CONCAT('%', CHAR(27), '[%')");
        if ($stmt === false) {
            return;
        }

        $update = $pdo->prepare("UPDATE intra_cron_runs SET output = :out WHERE id = :id");
        $pattern = '/\x1b\[[0-9;]*[A-Za-z]/';

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $cleaned = preg_replace($pattern, '', (string) $row['output']);
            if ($cleaned === null || $cleaned === $row['output']) {
                continue;
            }
            $update->execute([
                ':out' => $cleaned,
                ':id'  => (int) $row['id'],
            ]);
        }
    }

    public function down(): void
    {
        // ANSI-Codes lassen sich nicht wiederherstellen — no-op.
    }
}
