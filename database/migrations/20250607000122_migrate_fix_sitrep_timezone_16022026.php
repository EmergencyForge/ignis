<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Einmalige Datenkorrektur: Lokal erstellte Sitreps und Incidents wurden mit
 * Berlin-Zeit statt UTC gespeichert — diese Migration rechnet die Zeiten nach
 * UTC um. Via EMD-Sync erstellte Sitreps (source = 'leitstelle') sind bereits
 * korrekt in UTC und bleiben unangetastet.
 */
class MigrateFixSitrepTimezone16022026 extends AbstractMigration
{
    public function up(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        // 1. Sitreps: Lokale Einträge von Berlin nach UTC korrigieren
        if ($this->hasTable('intra_fire_incident_sitreps')) {
            $stmt = $pdo->query("
                SELECT id, report_time
                FROM intra_fire_incident_sitreps
                WHERE report_time IS NOT NULL
                AND (source IS NULL OR source != 'leitstelle')
            ");
            $upd = $pdo->prepare('UPDATE intra_fire_incident_sitreps SET report_time = ? WHERE id = ?');

            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $utcTime = $this->berlinToUtc($row['report_time']);
                if ($utcTime !== $row['report_time']) {
                    $upd->execute([$utcTime, $row['id']]);
                }
            }
        }

        // 2. Incidents: started_at korrigieren
        if ($this->hasTable('intra_fire_incidents')) {
            $stmt = $pdo->query("
                SELECT id, started_at
                FROM intra_fire_incidents
                WHERE started_at IS NOT NULL
            ");
            $upd = $pdo->prepare('UPDATE intra_fire_incidents SET started_at = ? WHERE id = ?');

            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $utcTime = $this->berlinToUtc($row['started_at']);
                if ($utcTime !== $row['started_at']) {
                    $upd->execute([$utcTime, $row['id']]);
                }
            }
        }
    }

    public function down(): void
    {
        // Nicht umkehrbar: Nach der Korrektur ist nicht mehr erkennbar, welche
        // Zeilen verschoben wurden — eine Rückrechnung würde auch ursprünglich
        // korrekte UTC-Zeiten verfälschen.
    }

    private function berlinToUtc(string $time): string
    {
        $dt = new \DateTime($time, new \DateTimeZone('Europe/Berlin'));
        $dt->setTimezone(new \DateTimeZone('UTC'));

        return $dt->format('Y-m-d H:i:s');
    }
}
