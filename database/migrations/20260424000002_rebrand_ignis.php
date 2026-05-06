<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Rebrand auf ıgnıs — setzt SYSTEM_NAME und SYSTEM_LOGO auf die neuen
 * Default-Werte, aber nur wenn die Installation bisher den alten Default
 * („intraRP" bzw. das alte Logo-Asset) trägt. Wer den Namen oder das Logo
 * bereits manuell geändert hat, behält seinen Wert.
 */
class RebrandIgnis extends AbstractMigration
{
    public function change(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        $stmt = $pdo->prepare(
            "UPDATE intra_config
                SET config_value = :new
              WHERE config_key = :key AND config_value = :old"
        );

        $stmt->execute([
            'key' => 'SYSTEM_NAME',
            'new' => 'ıgnıs',
            'old' => 'intraRP',
        ]);

        $stmt->execute([
            'key' => 'SYSTEM_LOGO',
            'new' => '/assets/img/ignis-wordmark.svg',
            'old' => '/assets/img/defaultLogo.webp',
        ]);
    }
}
