<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Seedet die zwei Standard-Antragstypen des dynamischen Antragssystems:
 * Beförderungsantrag und Urlaubsantrag, jeweils mit ihren Formularfeldern.
 */
class InsertIntraDynamicAntraege30092025 extends AbstractMigration
{
    public function up(): void
    {
        $this->seedTyp(
            [
                'name'         => 'Beförderungsantrag',
                'beschreibung' => 'Antrag auf Beförderung in den nächsten Rank',
                'icon'         => 'fa-solid fa-angles-up',
                'aktiv'        => 1,
                'sortierung'   => 1,
                'tabelle_name' => null,
            ],
            [
                ['feldname' => 'name_dn',    'label' => 'Name und Dienstnummer', 'feldtyp' => 'text',     'pflichtfeld' => 1, 'sortierung' => 1, 'breite' => 'half', 'platzhalter' => null, 'readonly' => 1, 'auto_fill' => 'fullname_dienstnr'],
                ['feldname' => 'dienstgrad', 'label' => 'Aktueller Rank',        'feldtyp' => 'text',     'pflichtfeld' => 1, 'sortierung' => 2, 'breite' => 'half', 'platzhalter' => null, 'readonly' => 1, 'auto_fill' => 'dienstgrad'],
                ['feldname' => 'freitext',   'label' => 'Schriftlicher Antrag',  'feldtyp' => 'textarea', 'pflichtfeld' => 1, 'sortierung' => 3, 'breite' => 'full', 'platzhalter' => null, 'readonly' => 0, 'auto_fill' => null],
            ]
        );

        $this->seedTyp(
            [
                'name'         => 'Urlaubsantrag',
                'beschreibung' => 'Beantragung von Urlaub oder Dienstfreistellung',
                'icon'         => 'fa-solid fa-umbrella-beach',
                'aktiv'        => 1,
                'sortierung'   => 2,
                'tabelle_name' => null,
            ],
            [
                ['feldname' => 'name_dn',    'label' => 'Name und Dienstnummer', 'feldtyp' => 'text',     'pflichtfeld' => 1, 'sortierung' => 1, 'breite' => 'half', 'platzhalter' => null,                                          'readonly' => 0, 'auto_fill' => 'fullname_dienstnr'],
                ['feldname' => 'dienstgrad', 'label' => 'Rank',                  'feldtyp' => 'text',     'pflichtfeld' => 1, 'sortierung' => 2, 'breite' => 'half', 'platzhalter' => null,                                          'readonly' => 0, 'auto_fill' => 'dienstgrad'],
                ['feldname' => 'von_datum',  'label' => 'Urlaub von',            'feldtyp' => 'date',     'pflichtfeld' => 1, 'sortierung' => 3, 'breite' => 'half', 'platzhalter' => 'TT.MM.JJJJ',                                  'readonly' => 0, 'auto_fill' => null],
                ['feldname' => 'bis_datum',  'label' => 'Urlaub bis',            'feldtyp' => 'date',     'pflichtfeld' => 1, 'sortierung' => 4, 'breite' => 'half', 'platzhalter' => 'TT.MM.JJJJ',                                  'readonly' => 0, 'auto_fill' => null],
                ['feldname' => 'grund',      'label' => 'Grund / Anmerkungen',   'feldtyp' => 'textarea', 'pflichtfeld' => 0, 'sortierung' => 5, 'breite' => 'full', 'platzhalter' => 'Optional: Begründung für den Urlaubsantrag', 'readonly' => 0, 'auto_fill' => null],
            ]
        );
    }

    public function down(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        // Felder hängen per FK ON DELETE CASCADE am Typ — Typ löschen reicht.
        $delete = $pdo->prepare('DELETE FROM intra_antrag_typen WHERE name = ?');
        $delete->execute(['Beförderungsantrag']);
        $delete->execute(['Urlaubsantrag']);
    }

    /**
     * Legt einen Antragstyp samt Feldern an, sofern noch kein Typ dieses
     * Namens existiert (Idempotenz für bereits befüllte Installationen).
     *
     * @param array<string, mixed>              $typ
     * @param array<int, array<string, mixed>>  $felder
     */
    private function seedTyp(array $typ, array $felder): void
    {
        $pdo = $this->getAdapter()->getConnection();

        $check = $pdo->prepare('SELECT id FROM intra_antrag_typen WHERE name = ?');
        $check->execute([$typ['name']]);
        if ($check->fetchColumn() !== false) {
            return;
        }

        $this->table('intra_antrag_typen')->insert([$typ])->saveData();

        $check->execute([$typ['name']]);
        $typId = (int) $check->fetchColumn();

        $rows = [];
        foreach ($felder as $feld) {
            $rows[] = ['antragstyp_id' => $typId] + $feld;
        }
        $this->table('intra_antrag_felder')->insert($rows)->saveData();
    }
}
