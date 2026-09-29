<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Vorgaben des Mailmoduls in `intra_config`, Kategorie `mail`.
 *
 * Die Werte sind bewusst nicht editierbar (`is_editable = 0`): die
 * allgemeine System-Konfiguration kennt keine Prüfung je Feld, eine
 * ungültige Domain oder eine riesige Signatur ginge dort ungeprüft durch.
 * Bearbeitet werden sie auf der Einstellungsseite des Plugins
 * (/settings/mail), die jeden Wert prüft.
 *
 * - MAIL_DOMAIN: Standard-Domain neuer Postfächer.
 * - MAIL_ADDRESS_PATTERN: `initial_dot_last` (v.nachname) oder
 *   `first_dot_last` (vorname.nachname), gilt für neue Postfächer.
 * - MAIL_ALLOWED_DOMAINS: weitere Domains, getrennt durch Komma.
 * - MAIL_DEFAULT_SIGNATURE: Editor-JSON, leer = keine.
 */
final class InsertIntraMailConfig29092026 extends AbstractMigration
{
    /** @return list<array<string,mixed>> */
    private function rows(): array
    {
        return [
            ['config_key' => 'MAIL_DOMAIN', 'config_value' => 'ignis.ef', 'description' => 'Standard-Domain neuer Postfächer', 'display_order' => 1],
            ['config_key' => 'MAIL_ADDRESS_PATTERN', 'config_value' => 'initial_dot_last', 'description' => 'Adressmuster neuer Postfächer', 'display_order' => 2],
            ['config_key' => 'MAIL_ALLOWED_DOMAINS', 'config_value' => '', 'description' => 'Weitere erlaubte Domains', 'display_order' => 3],
            ['config_key' => 'MAIL_DEFAULT_SIGNATURE', 'config_value' => '', 'description' => 'Standard-Signatur', 'display_order' => 4],
        ];
    }

    public function up(): void
    {
        foreach ($this->rows() as $row) {
            if (is_array($this->fetchRow("SELECT 1 FROM intra_config WHERE config_key = '" . $row['config_key'] . "'"))) {
                continue;
            }
            $this->table('intra_config')->insert([$row + [
                'config_type' => 'string',
                'category'    => 'mail',
                'is_editable' => 0,
            ]])->saveData();
        }
    }

    public function down(): void
    {
        $this->execute("DELETE FROM intra_config WHERE config_key IN ('MAIL_DOMAIN', 'MAIL_ADDRESS_PATTERN', 'MAIL_ALLOWED_DOMAINS', 'MAIL_DEFAULT_SIGNATURE')");
    }
}
