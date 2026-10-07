<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * ignisTab heißt jetzt ef_bridge. Die Beschriftung der Tablet-Anmeldung in
 * der System-Konfiguration zieht nach, aber nur wo noch der ab Werk
 * gesetzte Text steht: eine eigene Beschriftung bleibt.
 */
final class EfBridgeLabels extends AbstractMigration
{
    private const OLD = 'Anmeldung über ignisTab';
    private const NEW = 'Anmeldung über ef_bridge';

    public function up(): void
    {
        $this->rename(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->rename(self::NEW, self::OLD);
    }

    private function rename(string $from, string $to): void
    {
        $this->getAdapter()->getConnection()
            ->prepare("UPDATE intra_config SET description = ? WHERE config_key = 'TABLET_LOGIN_ENABLED' AND description = ?")
            ->execute([$to, $from]);
    }
}
