<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Billing-Anbindung für eDIVI-Protokolle: Flag + Zeitstempel, ob/wann ein
 * Protokoll bereits für die Abrechnung abgerufen wurde, plus Index für die
 * Billing-Abfragen.
 */
class AlterIntraEdivi30122025Billing extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_edivi');

        if (!$table->hasColumn('billing_sent')) {
            $table->addColumn('billing_sent', 'boolean', [
                'null'    => true,
                'default' => 0,
                'comment' => 'Gibt an, ob das Protokoll bereits für Billing abgerufen wurde',
                'after'   => 'freigegeben',
            ]);
        }

        if (!$table->hasColumn('billing_sent_at')) {
            $table->addColumn('billing_sent_at', 'datetime', [
                'null'    => true,
                'default' => null,
                'comment' => 'Zeitpunkt, zu dem das Protokoll für Billing abgerufen wurde',
                'after'   => 'billing_sent',
            ]);
        }

        if (!$table->hasIndexByName('idx_billing_sent')) {
            $table->addIndex(['billing_sent', 'freigegeben', 'created_at'], ['name' => 'idx_billing_sent']);
        }

        $table->update();
    }
}
