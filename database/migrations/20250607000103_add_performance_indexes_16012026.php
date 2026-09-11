<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Legt Performance-Indizes über die meistgenutzten Tabellen an (Protokolle,
 * Personal, Fahrzeuge, Wissensdatenbank, Einsätze, Dashboard, Users,
 * Notifications, Dokumente, MANV, Audit-Log, Config). Jeder Index wird nur
 * erstellt, wenn Tabelle und Index-Name es zulassen — Tabellen aus optionalen
 * Plugins dürfen fehlen.
 */
class AddPerformanceIndexes16012026 extends AbstractMigration
{
    public function up(): void
    {
        // INTRA_EDIVI (eNotf-Protokolle)
        $this->addIndexIfMissing('intra_edivi', 'idx_edivi_enr', ['enr']);
        $this->addIndexIfMissing('intra_edivi', 'idx_edivi_fzg', ['fzg_na', 'fzg_transp']);
        $this->addIndexIfMissing('intra_edivi', 'idx_edivi_edatum', ['edatum']);
        $this->addIndexIfMissing('intra_edivi', 'idx_edivi_sendezeit', ['sendezeit']);
        $this->addIndexIfMissing('intra_edivi', 'idx_edivi_prot_by', ['prot_by']);

        // INTRA_MITARBEITER (Personalverwaltung)
        $this->addIndexIfMissing('intra_mitarbeiter', 'idx_mitarbeiter_dienstgrad', ['dienstgrad']);
        $this->addIndexIfMissing('intra_mitarbeiter', 'idx_mitarbeiter_fullname', ['fullname'], ['limit' => ['fullname' => 50]]);
        $this->addIndexIfMissing('intra_mitarbeiter', 'idx_mitarbeiter_discord', ['discordtag']);
        $this->addIndexIfMissing('intra_mitarbeiter', 'idx_mitarbeiter_einstdatum', ['einstdatum']);
        $this->addIndexIfMissing('intra_mitarbeiter', 'idx_mitarbeiter_dienstnr', ['dienstnr']);

        // INTRA_FAHRZEUGE
        $this->addIndexIfMissing('intra_fahrzeuge', 'idx_fahrzeuge_name', ['name']);
        $this->addIndexIfMissing('intra_fahrzeuge', 'idx_fahrzeuge_identifier', ['identifier']);
        $this->addIndexIfMissing('intra_fahrzeuge', 'idx_fahrzeuge_rd_type', ['rd_type']);
        $this->addIndexIfMissing('intra_fahrzeuge', 'idx_fahrzeuge_active', ['active']);

        // INTRA_KB_ENTRIES (Wissensdatenbank)
        $this->addIndexIfMissing('intra_kb_entries', 'idx_kb_archived', ['is_archived']);
        $this->addIndexIfMissing('intra_kb_entries', 'idx_kb_pinned', ['is_pinned', 'title'], ['limit' => ['title' => 50]]);
        $this->addIndexIfMissing('intra_kb_entries', 'idx_kb_type_archived', ['type', 'is_archived']);

        // FULLTEXT-Index für Textsuche — best effort, Spalten können je nach
        // Stand der Wissensdatenbank fehlen
        if ($this->hasTable('intra_kb_entries')) {
            $kb = $this->table('intra_kb_entries');
            if (
                !$kb->hasIndexByName('idx_kb_fulltext')
                && $kb->hasColumn('title')
                && $kb->hasColumn('subtitle')
                && $kb->hasColumn('med_wirkstoff')
            ) {
                try {
                    $kb->addIndex(['title', 'subtitle', 'med_wirkstoff'], [
                        'name' => 'idx_kb_fulltext',
                        'type' => 'fulltext',
                    ])->update();
                } catch (\Throwable $e) {
                    error_log('FULLTEXT index creation skipped: ' . $e->getMessage());
                }
            }
        }

        // INTRA_FIRE_INCIDENTS (Feuerwehr-Einsätze)
        $this->addIndexIfMissing('intra_fire_incidents', 'idx_fire_archived', ['archived']);
        $this->addIndexIfMissing('intra_fire_incidents', 'idx_fire_created', ['created_at']);
        $this->addIndexIfMissing('intra_fire_incidents', 'idx_fire_incident_number', ['incident_number']);

        // INTRA_FIRE_INCIDENT_VEHICLES
        $this->addIndexIfMissing('intra_fire_incident_vehicles', 'idx_fire_vehicles_incident', ['incident_id']);

        // INTRA_DASHBOARD (Categories & Tiles)
        $this->addIndexIfMissing('intra_dashboard_categories', 'idx_dashboard_cat_priority', ['priority']);
        $this->addIndexIfMissing('intra_dashboard_tiles', 'idx_dashboard_tiles_cat', ['category', 'priority']);

        // INTRA_USERS
        $this->addIndexIfMissing('intra_users', 'idx_users_discord', ['discord_id']);
        $this->addIndexIfMissing('intra_users', 'idx_users_role', ['role']);

        // INTRA_NOTIFICATIONS
        $this->addIndexIfMissing('intra_notifications', 'idx_notif_user', ['user_id', 'is_read']);
        $this->addIndexIfMissing('intra_notifications', 'idx_notif_created', ['created_at']);

        // INTRA_MITARBEITER_DOKUMENTE
        $this->addIndexIfMissing('intra_mitarbeiter_dokumente', 'idx_docs_profile', ['profileid']);
        $this->addIndexIfMissing('intra_mitarbeiter_dokumente', 'idx_docs_type', ['type']);
        $this->addIndexIfMissing('intra_mitarbeiter_dokumente', 'idx_docs_timestamp', ['timestamp']);

        // INTRA_MANV_LAGEN
        $this->addIndexIfMissing('intra_manv_lagen', 'idx_manv_lagen_status', ['status']);
        $this->addIndexIfMissing('intra_manv_lagen', 'idx_manv_lagen_erstellt', ['erstellt_am']);

        // INTRA_MANV_PATIENTEN
        $this->addIndexIfMissing('intra_manv_patienten', 'idx_manv_pat_lage', ['manv_lage_id']);
        $this->addIndexIfMissing('intra_manv_patienten', 'idx_manv_pat_category', ['sichtungskategorie']);

        // INTRA_AUDIT_LOG
        $this->addIndexIfMissing('intra_audit_log', 'idx_audit_user', ['user']);
        $this->addIndexIfMissing('intra_audit_log', 'idx_audit_timestamp', ['timestamp']);
        $this->addIndexIfMissing('intra_audit_log', 'idx_audit_module', ['module']);

        // INTRA_CONFIG
        $this->addIndexIfMissing('intra_config', 'idx_config_order', ['display_order', 'config_key']);
    }

    public function down(): void
    {
        // Bewusst kein Rückbau: Die Guards oben überspringen Indizes, die
        // bereits vor dieser Migration existierten — ein pauschales Löschen
        // würde solche Indizes mit entfernen. Die Indizes sind rein additiv.
    }

    private function addIndexIfMissing(string $tableName, string $indexName, array $columns, array $options = []): void
    {
        if (!$this->hasTable($tableName)) {
            return;
        }

        $table = $this->table($tableName);
        if ($table->hasIndexByName($indexName)) {
            return;
        }

        $table->addIndex($columns, $options + ['name' => $indexName])->update();
    }
}
