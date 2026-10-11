<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Einstellungen der Atemschutzüberwachung. Die PA-Skripte der Server ziehen
 * unterschiedlich schnell Luft ab, deshalb sind Verbrauch und Fülldruck
 * einstellbar. Vorgabe: 300 bar und 10 bar/min, also rund 30 Minuten.
 * Ein vom Betreiber gesetzter Wert bleibt bei erneutem Lauf erhalten.
 */
class InsertConfigFireAsu extends AbstractMigration
{
    private const ROWS = [
        ['FIRE_ASU_CONSUMPTION', '10', 'Normalverbrauch Pressluftatmer (bar/min)', 'So viel bar pro Minute zieht das PA-Skript des Servers ab. Daraus rechnet die Atemschutzüberwachung, solange noch keine zwei Druckwerte gemeldet sind.', 20],
        ['FIRE_ASU_START_BAR', '300', 'Fülldruck der Flaschen (bar)', 'Vorgabe beim Anschließen eines Trupps. Mit dem Normalverbrauch ergibt sich die erwartete Einsatzzeit.', 21],
    ];

    public function up(): void
    {
        foreach (self::ROWS as [$key, $value, $description, $hint, $order]) {
            $this->execute(sprintf(
                "INSERT INTO intra_config
                    (config_key, config_value, config_type, category, description, hint, is_editable, display_order)
                VALUES (%s, %s, 'integer', 'firetab', %s, %s, 1, %d)
                ON DUPLICATE KEY UPDATE
                    config_type   = VALUES(config_type),
                    category      = VALUES(category),
                    description   = VALUES(description),
                    hint          = VALUES(hint),
                    is_editable   = VALUES(is_editable),
                    display_order = VALUES(display_order)",
                $this->getAdapter()->getConnection()->quote($key),
                $this->getAdapter()->getConnection()->quote($value),
                $this->getAdapter()->getConnection()->quote($description),
                $this->getAdapter()->getConnection()->quote($hint),
                $order
            ));
        }
    }

    public function down(): void
    {
        $this->execute("DELETE FROM intra_config WHERE config_key IN ('FIRE_ASU_CONSUMPTION', 'FIRE_ASU_START_BAR')");
    }
}
