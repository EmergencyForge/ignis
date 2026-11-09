<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Termine aus dem Lehrgangsmodul (Unterricht, Praxistag, Prüfung) tragen
 * source='training' und in source_ref_id die Termin-ID des Lehrgangs.
 */
class AddTrainingSourceToIntraCalendarEvents extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_calendar_events')
            ->changeColumn('source', 'enum', ['values' => ['manual', 'antrag', 'training'], 'default' => 'manual', 'null' => false])
            ->update();
    }

    public function down(): void
    {
        $this->execute("UPDATE intra_calendar_events SET source = 'manual' WHERE source = 'training'");
        $this->table('intra_calendar_events')
            ->changeColumn('source', 'enum', ['values' => ['manual', 'antrag'], 'default' => 'manual', 'null' => false])
            ->update();
    }
}
