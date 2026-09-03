<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Befüllt die Felddefinitionen der System-Dokumentvorlagen:
 *
 *   - Ernennungs-/Beförderungsurkunde: Rank (db_dg) + Ausstellungsdatum
 *   - Entlassungsurkunde: nur Ausstellungsdatum
 *   - Ausbildungszertifikat: Qualifikation (db_rdq)
 *   - Lehrgangs-/Fachlehrgangszertifikat: Qualifikation als Select
 *     (Optionsliste zusätzlich in der Template-Config hinterlegt)
 *   - Schreiben (Abmahnung, Dienstenthebung, Dienstentfernung, Kündigung):
 *     Begründung als Richtext, Dienstenthebung zusätzlich mit Suspendierungsende
 */
class InsertTemplateFields30092025 extends AbstractMigration
{
    private static function qualiOptionsLehrgang(): string
    {
        return json_encode([
            ["value" => "0", "label" => "Brandmeister/-in", "label_m" => "Brandmeister", "label_w" => "Brandmeisterin"],
            ["value" => "1", "label" => "Gruppenführer/-in", "label_m" => "Gruppenführer", "label_w" => "Gruppenführerin"],
            ["value" => "2", "label" => "Zugführer/-in", "label_m" => "Zugführer", "label_w" => "Zugführerin"],
            ["value" => "4", "label" => "Sonderfahrzeug-Maschinist/-in", "label_m" => "Sonderfahrzeug-Maschinist", "label_w" => "Sonderfahrzeug-Maschinistin"],
        ]);
    }

    private static function qualiOptionsFachlehrgang(): string
    {
        return json_encode([
            ["value" => "3", "label" => "Leitstellen-Disponent/-in", "label_m" => "Leitstellen-Disponent", "label_w" => "Leitstellen-Disponentin"],
            ["value" => "5", "label" => "Helfergrundmodul (SEG)", "label_m" => "Helfergrundmodul (SEG)", "label_w" => "Helfergrundmodul (SEG)"],
            ["value" => "6", "label" => "SEG-Sanitäter/-in", "label_m" => "SEG-Sanitäter", "label_w" => "SEG-Sanitäterin"],
            ["value" => "7", "label" => "Gruppenführer/-in-BevS", "label_m" => "Gruppenführer-BevS", "label_w" => "Gruppenführerin-BevS"],
            ["value" => "8", "label" => "HEMS-TC", "label_m" => "HEMS-TC", "label_w" => "HEMS-TC"],
            ["value" => "9", "label" => "Luftrettungspilot/-in", "label_m" => "Luftrettungspilot", "label_w" => "Luftrettungspilotin"],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fieldRows(): array
    {
        return [
            // Ernennungsurkunde (14)
            ['template_id' => 14, 'field_name' => 'erhalter_rang',     'field_label' => 'Rank',              'field_type' => 'db_dg',    'is_required' => 1, 'gender_specific' => 1, 'sort_order' => 0],
            ['template_id' => 14, 'field_name' => 'ausstellungsdatum', 'field_label' => 'Ausstellungsdatum', 'field_type' => 'date',     'is_required' => 1, 'sort_order' => 1],
            // Beförderungsurkunde (1)
            ['template_id' => 1,  'field_name' => 'erhalter_rang',     'field_label' => 'Neuer Rank',        'field_type' => 'db_dg',    'is_required' => 1, 'gender_specific' => 1, 'sort_order' => 0],
            ['template_id' => 1,  'field_name' => 'ausstellungsdatum', 'field_label' => 'Ausstellungsdatum', 'field_type' => 'date',     'is_required' => 1, 'sort_order' => 1],
            // Entlassungsurkunde (2)
            ['template_id' => 2,  'field_name' => 'ausstellungsdatum', 'field_label' => 'Ausstellungsdatum', 'field_type' => 'date',     'is_required' => 1, 'sort_order' => 0],
            // Ausbildungszertifikat (5)
            ['template_id' => 5,  'field_name' => 'erhalter_rang_rd',  'field_label' => 'Qualifikation',     'field_type' => 'db_rdq',   'is_required' => 1, 'gender_specific' => 1, 'sort_order' => 0],
            ['template_id' => 5,  'field_name' => 'ausstellungsdatum', 'field_label' => 'Ausstellungsdatum', 'field_type' => 'date',     'is_required' => 1, 'sort_order' => 1],
            // Lehrgangszertifikat (6)
            ['template_id' => 6,  'field_name' => 'erhalter_quali',    'field_label' => 'Qualifikation',     'field_type' => 'select',   'field_options' => self::qualiOptionsLehrgang(), 'is_required' => 1, 'gender_specific' => 1, 'sort_order' => 0],
            ['template_id' => 6,  'field_name' => 'ausstellungsdatum', 'field_label' => 'Ausstellungsdatum', 'field_type' => 'date',     'is_required' => 1, 'sort_order' => 1],
            // Fachlehrgang (7)
            ['template_id' => 7,  'field_name' => 'erhalter_quali',    'field_label' => 'Qualifikation',     'field_type' => 'select',   'field_options' => self::qualiOptionsFachlehrgang(), 'is_required' => 1, 'gender_specific' => 1, 'sort_order' => 0],
            ['template_id' => 7,  'field_name' => 'ausstellungsdatum', 'field_label' => 'Ausstellungsdatum', 'field_type' => 'date',     'is_required' => 1, 'sort_order' => 1],
            // Schriftliche Abmahnung (10)
            ['template_id' => 10, 'field_name' => 'inhalt',            'field_label' => 'Begründung',        'field_type' => 'richtext', 'is_required' => 1, 'sort_order' => 0],
            ['template_id' => 10, 'field_name' => 'ausstellungsdatum', 'field_label' => 'Ausstellungsdatum', 'field_type' => 'date',     'is_required' => 1, 'sort_order' => 1],
            // Vorläufige Dienstenthebung (11)
            ['template_id' => 11, 'field_name' => 'inhalt',            'field_label' => 'Begründung',        'field_type' => 'richtext', 'is_required' => 1, 'sort_order' => 0],
            ['template_id' => 11, 'field_name' => 'suspendtime',       'field_label' => 'Suspendiert bis (leer für unbegrenzt)', 'field_type' => 'date', 'is_required' => 0, 'sort_order' => 1],
            ['template_id' => 11, 'field_name' => 'ausstellungsdatum', 'field_label' => 'Ausstellungsdatum', 'field_type' => 'date',     'is_required' => 1, 'sort_order' => 2],
            // Dienstentfernung (12)
            ['template_id' => 12, 'field_name' => 'inhalt',            'field_label' => 'Begründung',        'field_type' => 'richtext', 'is_required' => 1, 'sort_order' => 0],
            ['template_id' => 12, 'field_name' => 'ausstellungsdatum', 'field_label' => 'Ausstellungsdatum', 'field_type' => 'date',     'is_required' => 1, 'sort_order' => 1],
            // Außerordentliche Kündigung (13)
            ['template_id' => 13, 'field_name' => 'inhalt',            'field_label' => 'Begründung',        'field_type' => 'richtext', 'is_required' => 1, 'sort_order' => 0],
            ['template_id' => 13, 'field_name' => 'ausstellungsdatum', 'field_label' => 'Ausstellungsdatum', 'field_type' => 'date',     'is_required' => 1, 'sort_order' => 1],
        ];
    }

    private static function templateConfig(string $optionsJson): string
    {
        return json_encode([
            "fields" => [
                "erhalter_quali" => [
                    "type" => "select",
                    "label" => "Qualifikation",
                    "options" => json_decode($optionsJson, true),
                    "gender_specific" => true,
                ],
            ],
        ]);
    }

    public function up(): void
    {
        // Bereits vorhandene (template_id, field_name)-Paare überspringen —
        // die Tabelle hat keinen Unique-Key, doppelte Seeds wären sonst möglich.
        $existing = [];
        foreach ($this->fetchAll('SELECT template_id, field_name FROM intra_dokument_template_fields') as $row) {
            $existing[$row['template_id'] . '|' . $row['field_name']] = true;
        }

        $rows = [];
        foreach (self::fieldRows() as $field) {
            if (isset($existing[$field['template_id'] . '|' . $field['field_name']])) {
                continue;
            }
            // Feste Spaltenreihenfolge — Phinx' Bulk-Insert übernimmt die
            // Spaltenliste aus der ersten Row.
            $rows[] = [
                'template_id'     => $field['template_id'],
                'field_name'      => $field['field_name'],
                'field_label'     => $field['field_label'],
                'field_type'      => $field['field_type'],
                'field_options'   => $field['field_options'] ?? null,
                'is_required'     => $field['is_required'],
                'gender_specific' => $field['gender_specific'] ?? 0,
                'sort_order'      => $field['sort_order'],
            ];
        }

        if ($rows !== []) {
            $this->table('intra_dokument_template_fields')->insert($rows)->saveData();
        }

        // Select-Optionen zusätzlich in der Template-Config ablegen
        // (nur wenn die Config noch leer ist)
        $pdo = $this->getAdapter()->getConnection();
        $setConfig = $pdo->prepare(
            "UPDATE intra_dokument_templates SET config = ?
             WHERE id = ? AND (config IS NULL OR config = '{}' OR config = '')"
        );
        $setConfig->execute([self::templateConfig(self::qualiOptionsLehrgang()), 6]);
        $setConfig->execute([self::templateConfig(self::qualiOptionsFachlehrgang()), 7]);
    }

    public function down(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        $delete = $pdo->prepare(
            'DELETE FROM intra_dokument_template_fields WHERE template_id = ? AND field_name = ?'
        );
        foreach (self::fieldRows() as $field) {
            $delete->execute([$field['template_id'], $field['field_name']]);
        }

        // Config nur zurücksetzen, wenn sie noch exakt dem Seed-Wert entspricht
        $resetConfig = $pdo->prepare(
            "UPDATE intra_dokument_templates SET config = '{}' WHERE id = ? AND config = ?"
        );
        $resetConfig->execute([6, self::templateConfig(self::qualiOptionsLehrgang())]);
        $resetConfig->execute([7, self::templateConfig(self::qualiOptionsFachlehrgang())]);
    }
}
