<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * MAIL_SEND_COOLDOWN: höchstens eine Mail alle N Sekunden je Postfach
 * (0 = aus). Gepflegt unter /settings/mail, deshalb wie die übrigen
 * MAIL_*-Werte nicht in der allgemeinen Konfiguration editierbar.
 */
final class InsertIntraMailSendCooldown30092026 extends AbstractMigration
{
    public function up(): void
    {
        if (is_array($this->fetchRow("SELECT 1 FROM intra_config WHERE config_key = 'MAIL_SEND_COOLDOWN'"))) {
            return;
        }
        $this->table('intra_config')->insert([[
            'config_key'    => 'MAIL_SEND_COOLDOWN',
            'config_value'  => '10',
            'config_type'   => 'integer',
            'category'      => 'mail',
            'description'   => 'Sendepause je Postfach in Sekunden',
            'is_editable'   => 0,
            'display_order' => 5,
        ]])->saveData();
    }

    public function down(): void
    {
        $this->execute("DELETE FROM intra_config WHERE config_key = 'MAIL_SEND_COOLDOWN'");
    }
}
