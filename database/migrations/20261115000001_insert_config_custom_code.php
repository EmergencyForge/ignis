<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Custom code from Settings > System > Design (App\Support\SiteCode).
 *
 * Not editable on the config page: CSS and editor styles are validated on
 * the design page, and JS is limited to full_admin there.
 */
class InsertConfigCustomCode extends AbstractMigration
{
    private const KEYS = [
        'CUSTOM_SCSS'    => 'Eigenes CSS (Quelltext)',
        'CUSTOM_CSS'     => 'Eigenes CSS (übersetzt)',
        'EDITOR_STYLES'  => 'Stile für den Editor',
        'CUSTOM_JS_HEAD' => 'Eigenes JavaScript im Kopf der Seite',
        'CUSTOM_JS_BODY' => 'Eigenes JavaScript am Ende der Seite',
    ];

    public function up(): void
    {
        $stmt = $this->getAdapter()->getConnection()->prepare(
            "INSERT INTO intra_config (config_key, config_value, config_type, category, description, is_editable, display_order)
             VALUES (:key, NULL, 'string', 'design', :description, 0, :order)
             ON DUPLICATE KEY UPDATE
                config_type = VALUES(config_type),
                category = VALUES(category),
                description = VALUES(description),
                is_editable = VALUES(is_editable),
                display_order = VALUES(display_order)"
        );

        $order = 10;
        foreach (self::KEYS as $key => $description) {
            $stmt->execute(['key' => $key, 'description' => $description, 'order' => $order]);
            $order += 10;
        }
    }

    public function down(): void
    {
        $this->execute("DELETE FROM intra_config WHERE config_key IN ('" . implode("', '", array_keys(self::KEYS)) . "')");
    }
}
