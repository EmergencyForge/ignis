<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Stellt sicher, dass intra_mitarbeiter_rdquali eine abkuerzung-Spalte hat
 * (varchar(50), optional) und setzt die Abkürzungen der Standard-Qualis:
 * RettSan i.A., RettSan, NotSan i.A., NotSan, Notarzt. Auf frischen
 * Installationen sind Spalte und Werte bereits durch das Seed vorhanden.
 */
class AlterIntraMitarbeiterRdquali04022026Abbreviation extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('intra_mitarbeiter_rdquali');

        if (!$table->hasColumn('abkuerzung')) {
            $table
                ->addColumn('abkuerzung', 'string', [
                    'limit'   => 50,
                    'null'    => true,
                    'default' => null,
                    'after'   => 'name_w',
                ])
                ->update();
        }

        $this->execute("
            UPDATE `intra_mitarbeiter_rdquali`
            SET `abkuerzung` = CASE
                WHEN `id` = 2 THEN 'RettSan i.A.'
                WHEN `id` = 4 THEN 'RettSan'
                WHEN `id` = 5 THEN 'NotSan i.A.'
                WHEN `id` = 6 THEN 'NotSan'
                WHEN `id` = 7 THEN 'Notarzt'
                WHEN `id` = 8 THEN NULL
                ELSE `abkuerzung`
            END
            WHERE `id` IN (2, 4, 5, 6, 7, 8)
        ");
    }

    public function down(): void
    {
        // Kein Rückbau: Die gesetzten Werte entsprechen exakt dem Seed aus
        // insert_intra_mitarbeiter_rdquali — auf frischen Installationen ist
        // das UPDATE ein No-op. Ob die Spalte hier oder schon beim CREATE
        // entstand, lässt sich nachträglich nicht unterscheiden.
    }
}
