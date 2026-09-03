<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Teilt patname in pat_vorname + pat_nachname auf und fügt pat_synced hinzu.
 *
 * Bestehende patname-Werte (Format "Nachname, Vorname" oder nur "Nachname")
 * werden in die neuen Spalten übernommen; patname selbst bleibt erhalten.
 */
class AlterIntraEdivi11022026SplitPatname extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('intra_edivi');

        if (!$table->hasColumn('pat_vorname')) {
            $table->addColumn('pat_vorname', 'string', ['limit' => 255, 'null' => true, 'default' => null, 'after' => 'patname']);
        }
        if (!$table->hasColumn('pat_nachname')) {
            $table->addColumn('pat_nachname', 'string', ['limit' => 255, 'null' => true, 'default' => null, 'after' => 'pat_vorname']);
        }
        if (!$table->hasColumn('pat_synced')) {
            $table->addColumn('pat_synced', 'boolean', ['null' => false, 'default' => 0, 'after' => 'pat_nachname']);
        }
        $table->update();

        // Bestehende patname-Werte in pat_vorname + pat_nachname aufteilen.
        // Läuft PHP-seitig, weil das Komma-Splitting in SQL unnötig fummelig wäre.
        $pdo = $this->getAdapter()->getConnection();
        $rows = $pdo->query(
            "SELECT id, patname FROM intra_edivi
             WHERE patname IS NOT NULL AND patname != ''
               AND (pat_vorname IS NULL AND pat_nachname IS NULL)"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $update = $pdo->prepare('UPDATE intra_edivi SET pat_nachname = ?, pat_vorname = ? WHERE id = ?');
        foreach ($rows as $row) {
            if (strpos($row['patname'], ',') !== false) {
                [$nachname, $vorname] = array_map('trim', explode(',', $row['patname'], 2));
            } else {
                $nachname = trim($row['patname']);
                $vorname = '';
            }
            $update->execute([$nachname, $vorname ?: null, $row['id']]);
        }
    }

    public function down(): void
    {
        // patname wurde nie angetastet — die aufgeteilten Kopien können
        // verlustfrei wieder entfallen.
        $this->table('intra_edivi')
            ->removeColumn('pat_vorname')
            ->removeColumn('pat_nachname')
            ->removeColumn('pat_synced')
            ->update();
    }
}
