<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Die überarbeitete Sidebar-Navigation (Icon-Rail + Flyout) wird Standard.
 *
 * Sie rendert sich deklarativ aus config/navigation.php plus den
 * Plugin-Fragmenten — Einträge erscheinen und verschwinden damit
 * automatisch mit dem Plugin-Status, was die handgepflegte Legacy-Sidebar
 * nie zuverlässig konnte. Der Flag bleibt editierbar als Opt-out für
 * eine Übergangszeit; danach fällt der Legacy-Zweig in navbar.php weg.
 */
class EnableNewNavbarByDefault extends AbstractMigration
{
    public function up(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        $pdo->exec(
            "UPDATE intra_config
             SET config_value = 'true',
                 description = 'Neue Sidebar-Navigation mit Icon-Rail und aufklappbarem Flyout (Standard; deaktivieren stellt die alte Sidebar wieder her)'
             WHERE config_key = 'UI_NEW_NAVBAR_ENABLED'"
        );
    }

    public function down(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        $pdo->exec(
            "UPDATE intra_config
             SET config_value = 'false',
                 description = 'Neue Sidebar-Navigation mit Icon-Rail und aufklappbarem Flyout aktivieren (experimentell)'
             WHERE config_key = 'UI_NEW_NAVBAR_ENABLED'"
        );
    }
}
