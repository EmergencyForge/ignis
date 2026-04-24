<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Feature-Flag für die überarbeitete Sidebar-Navigation (Icon-Rail + Flyout).
 *
 * Default ist 'false' — bestehende Installationen behalten die klassische
 * Sidebar. Admins können den Flag über Einstellungen › Konfiguration in der
 * Kategorie "Funktionen" umschalten.
 */
class InsertConfigUiNewNavbar extends AbstractMigration
{
    public function change(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        $stmt = $pdo->prepare(
            "INSERT INTO intra_config (config_key, config_value, config_type, category, description, is_editable, display_order)
             VALUES (:key, :value, :type, :category, :description, :editable, :order)
             ON DUPLICATE KEY UPDATE
                config_type = VALUES(config_type),
                category = VALUES(category),
                description = VALUES(description),
                is_editable = VALUES(is_editable),
                display_order = VALUES(display_order)"
        );

        $stmt->execute([
            'key'         => 'UI_NEW_NAVBAR_ENABLED',
            'value'       => 'false',
            'type'        => 'boolean',
            'category'    => 'funktionen',
            'description' => 'Neue Sidebar-Navigation mit Icon-Rail und aufklappbarem Flyout aktivieren (experimentell)',
            'editable'    => 1,
            'order'       => 110,
        ]);
    }
}
