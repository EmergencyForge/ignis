<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Kalender-Events: persoenliche und geteilte Termine, role-getaggte Dienste,
 * Recurring-Series. Eine Tabelle deckt alle Quellen ab — manuelle Eintraege,
 * spaetere Antrag-Bridges (Urlaub) und Recurrence-Exceptions leben hier
 * nebeneinander.
 */
class CreateIntraCalendarEvents extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_calendar_events')) {
            return;
        }

        $this->table('intra_calendar_events', ['id' => 'id', 'signed' => false])
            ->addColumn('title',              'string',    ['limit' => 160])
            ->addColumn('description',        'text',      ['null' => true])
            ->addColumn('location',           'string',    ['limit' => 255, 'null' => true])
            ->addColumn('starts_at',          'datetime')
            ->addColumn('ends_at',            'datetime')
            ->addColumn('all_day',            'boolean',   ['default' => false])
            ->addColumn('color',              'string',    ['limit' => 16, 'default' => 'orange'])
            ->addColumn('category',           'string',    ['limit' => 32, 'default' => 'general'])
            // visibility: private|attendees|role|all
            ->addColumn('visibility',         'enum',      ['values' => ['private', 'attendees', 'role', 'all'], 'default' => 'attendees'])
            // signed, weil intra_users_roles.id ein signed int(11) ist — FK-Spalten
            // müssen in Signedness exakt matchen, sonst errno 150/3780.
            ->addColumn('visibility_role_id', 'integer',   ['null' => true])
            // Bridge zu anderen Quellen (Phase 2: source='antrag', source_ref_id=intra_antraege.id)
            ->addColumn('source',             'enum',      ['values' => ['manual', 'antrag'], 'default' => 'manual'])
            ->addColumn('source_ref_id',      'integer',   ['null' => true, 'signed' => false])
            ->addColumn('created_by',         'integer')
            // Recurrence (subset RFC 5545 RRULE) — Parser in src/Calendar/RecurrenceExpander.php.
            // parent_event_id zeigt bei Exception-Rows auf die Master-Series.
            ->addColumn('recurrence_rule',    'string',    ['limit' => 255, 'null' => true])
            ->addColumn('recurrence_until',   'datetime',  ['null' => true])
            ->addColumn('parent_event_id',    'integer',   ['null' => true, 'signed' => false])
            ->addColumn('created_at',         'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',         'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['starts_at', 'ends_at'],            ['name' => 'idx_calendar_range'])
            ->addIndex(['visibility', 'visibility_role_id'],['name' => 'idx_calendar_visibility'])
            ->addIndex(['source', 'source_ref_id'],         ['name' => 'idx_calendar_source'])
            ->addIndex(['parent_event_id'],                 ['name' => 'idx_calendar_parent'])
            ->addForeignKey('created_by',         'intra_users',           'id', ['delete' => 'CASCADE'])
            ->addForeignKey('visibility_role_id', 'intra_users_roles',     'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('parent_event_id',    'intra_calendar_events', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
