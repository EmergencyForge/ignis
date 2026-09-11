<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Config-Option FIRE_INCIDENT_REQUIRE_USER_AUTH: steuert, ob die
 * Fahrzeuganmeldung im Einsatzprotokoll eine Registrierung/Anmeldung im
 * Hauptsystem voraussetzt. Default: false.
 */
class InsertConfig24122025FireAuth extends AbstractMigration
{
    private const CONFIG_KEY = 'FIRE_INCIDENT_REQUIRE_USER_AUTH';

    public function up(): void
    {
        // Bewusst als INSERT ... ON DUPLICATE KEY UPDATE wie in der
        // ursprünglichen Migration: Metadaten werden angeglichen, ein vom
        // Nutzer gesetzter config_value bleibt unangetastet. Auch das
        // Auto-Increment-Verhalten (der Duplikatsfall verbraucht eine ID)
        // bleibt so identisch zum Original-Schema.
        $this->execute("
            INSERT INTO intra_config
                (config_key, config_value, config_type, category, description, is_editable, display_order)
            VALUES (
                '" . self::CONFIG_KEY . "',
                'false',
                'boolean',
                'funktionen',
                'Wird eine Registrierung/Anmeldung im Hauptsystem für die Fahrzeuganmeldung im Einsatzprotokoll vorausgesetzt?',
                1,
                35
            )
            ON DUPLICATE KEY UPDATE
                config_type   = VALUES(config_type),
                category      = VALUES(category),
                description   = VALUES(description),
                is_editable   = VALUES(is_editable),
                display_order = VALUES(display_order)
        ");
    }

    public function down(): void
    {
        $this->execute(
            "DELETE FROM intra_config WHERE config_key = '" . self::CONFIG_KEY . "'"
        );
    }
}
