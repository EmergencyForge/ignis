<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Darstellungsmodus pro Konto: dark, light oder system. head.php setzt ihn
 * als data-theme am <html>, geändert wird er über POST /profile/theme
 * (ProfileController). Unabhängig von theme_config, das die Akzentfarbe
 * des Nutzers trägt.
 */
final class AddThemeToIntraUsers extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_users');

        if (!$table->hasColumn('theme')) {
            $table->addColumn('theme', 'string', [
                'limit'   => 10,
                'default' => 'dark',
                'null'    => false,
                'after'   => 'theme_config',
            ])->update();
        }
    }
}
