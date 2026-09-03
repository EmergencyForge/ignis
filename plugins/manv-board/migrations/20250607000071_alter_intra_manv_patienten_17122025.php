<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Erweitert die Sichtungskategorien in `intra_manv_patienten` um SK5
 * (schwarz/Tot) und SK6 (lila/Beteiligter ohne Verletzung).
 */
class AlterIntraManvPatienten17122025 extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_manv_patienten')
            ->changeColumn('sichtungskategorie', 'enum', [
                'values'  => ['SK1', 'SK2', 'SK3', 'SK4', 'SK5', 'SK6', 'tot'],
                'null'    => true,
                'default' => null,
                'comment' => 'SK1=rot/Akute Lebensgefahr, SK2=gelb/Schwere Folgeschäden, SK3=grün/Spätere Behandlung, SK4=blau/Keine zeitnahe Versorgung, SK5=schwarz/Tot, SK6=lila/Beteiligter ohne Verletzung',
            ])
            ->update();
    }

    public function down(): void
    {
        // Ursprüngliche Enum-Definition aus der Create-Migration. Schlägt fehl,
        // falls bereits Zeilen mit SK5/SK6 existieren — dann ist der Rollback
        // fachlich ohnehin nicht sinnvoll.
        $this->table('intra_manv_patienten')
            ->changeColumn('sichtungskategorie', 'enum', [
                'values'  => ['SK1', 'SK2', 'SK3', 'SK4', 'tot'],
                'null'    => true,
                'default' => null,
                'comment' => 'SK1=rot/sofort, SK2=gelb/dringend, SK3=grün/später, SK4=blau/abwartend',
            ])
            ->update();
    }
}
