<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Anmeldung über ignisTab: Einmal-Token (nur ihr SHA-256) und der Schalter
 * TABLET_LOGIN_ENABLED, ab Werk aus.
 */
final class TabletLogin extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_tablet_login_tokens')
            ->addColumn('user_id', 'integer', ['signed' => true, 'null' => false])
            ->addColumn('token_hash', 'char', ['limit' => 64, 'null' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addColumn('used_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex(['token_hash'], ['unique' => true])
            ->addIndex(['user_id', 'created_at'])
            ->addForeignKey('user_id', 'intra_users', 'id', ['delete' => 'CASCADE'])
            ->create();

        if (!is_array($this->fetchRow("SELECT id FROM intra_config WHERE config_key = 'TABLET_LOGIN_ENABLED'"))) {
            $this->table('intra_config')->insert([[
                'config_key'    => 'TABLET_LOGIN_ENABLED',
                'config_value'  => 'false',
                'config_type'   => 'boolean',
                'category'      => 'funktionen',
                'description'   => 'Anmeldung über ignisTab (FiveM-Tablet) erlauben',
                'is_editable'   => 1,
                'display_order' => 60,
            ]])->saveData();
        }
    }

    public function down(): void
    {
        $this->table('intra_tablet_login_tokens')->drop()->save();
        $this->execute("DELETE FROM intra_config WHERE config_key = 'TABLET_LOGIN_ENABLED'");
    }
}
