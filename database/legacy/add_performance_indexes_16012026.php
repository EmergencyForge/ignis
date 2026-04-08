<?php
/**
 * Performance-Indizes hinzufügen
 * Datum: 16.01.2026
 * 
 * Diese Migration fügt wichtige Indizes für bessere Datenbankperformance hinzu.
 * Die Indizes werden nur erstellt, wenn sie noch nicht existieren.
 */

use App\Utils\DatabaseHelper;

try {
    // =========================================================================
    // INTRA_EDIVI (eNotf-Protokolle)
    // =========================================================================
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_edivi', 'idx_edivi_enr', 'enr');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_edivi', 'idx_edivi_fzg', 'fzg_na, fzg_transp');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_edivi', 'idx_edivi_edatum', 'edatum');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_edivi', 'idx_edivi_sendezeit', 'sendezeit');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_edivi', 'idx_edivi_prot_by', 'prot_by');

    // =========================================================================
    // INTRA_MITARBEITER (Personalverwaltung)
    // =========================================================================
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_mitarbeiter', 'idx_mitarbeiter_dienstgrad', 'dienstgrad');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_mitarbeiter', 'idx_mitarbeiter_fullname', 'fullname(50)');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_mitarbeiter', 'idx_mitarbeiter_discord', 'discordtag');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_mitarbeiter', 'idx_mitarbeiter_einstdatum', 'einstdatum');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_mitarbeiter', 'idx_mitarbeiter_dienstnr', 'dienstnr');

    // =========================================================================
    // INTRA_FAHRZEUGE
    // =========================================================================
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_fahrzeuge', 'idx_fahrzeuge_name', 'name');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_fahrzeuge', 'idx_fahrzeuge_identifier', 'identifier');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_fahrzeuge', 'idx_fahrzeuge_rd_type', 'rd_type');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_fahrzeuge', 'idx_fahrzeuge_active', 'active');

    // =========================================================================
    // INTRA_KB_ENTRIES (Wissensdb/Knowledge Base)
    // =========================================================================
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_kb_entries', 'idx_kb_archived', 'is_archived');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_kb_entries', 'idx_kb_pinned', 'is_pinned, title(50)');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_kb_entries', 'idx_kb_type_archived', 'type, is_archived');
    
    // FULLTEXT Index für Textsuche (nur wenn noch nicht vorhanden)
    if (!DatabaseHelper::indexExists($pdo, 'intra_kb_entries', 'idx_kb_fulltext')) {
        try {
            $pdo->exec("ALTER TABLE `intra_kb_entries` ADD FULLTEXT INDEX `idx_kb_fulltext` (title, subtitle, med_wirkstoff)");
        } catch (PDOException $e) {
            // FULLTEXT evtl. nicht unterstützt oder Spalten nicht vorhanden
            error_log("FULLTEXT index creation skipped: " . $e->getMessage());
        }
    }

    // =========================================================================
    // INTRA_FIRE_INCIDENTS (Feuerwehr-Einsätze)
    // =========================================================================
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_fire_incidents', 'idx_fire_archived', 'archived');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_fire_incidents', 'idx_fire_created', 'created_at');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_fire_incidents', 'idx_fire_incident_number', 'incident_number');

    // =========================================================================
    // INTRA_FIRE_INCIDENT_VEHICLES
    // =========================================================================
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_fire_incident_vehicles', 'idx_fire_vehicles_incident', 'incident_id');

    // =========================================================================
    // INTRA_DASHBOARD (Categories & Tiles)
    // =========================================================================
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_dashboard_categories', 'idx_dashboard_cat_priority', 'priority');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_dashboard_tiles', 'idx_dashboard_tiles_cat', 'category, priority');

    // =========================================================================
    // INTRA_USERS
    // =========================================================================
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_users', 'idx_users_discord', 'discord_id');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_users', 'idx_users_role', 'role');

    // =========================================================================
    // INTRA_NOTIFICATIONS
    // =========================================================================
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_notifications', 'idx_notif_user', 'user_id, is_read');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_notifications', 'idx_notif_created', 'created_at');

    // =========================================================================
    // INTRA_MITARBEITER_DOKUMENTE
    // =========================================================================
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_mitarbeiter_dokumente', 'idx_docs_profile', 'profileid');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_mitarbeiter_dokumente', 'idx_docs_type', 'type');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_mitarbeiter_dokumente', 'idx_docs_timestamp', 'timestamp');

    // =========================================================================
    // INTRA_MANV_LAGEN
    // =========================================================================
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_manv_lagen', 'idx_manv_lagen_status', 'status');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_manv_lagen', 'idx_manv_lagen_erstellt', 'erstellt_am');

    // =========================================================================
    // INTRA_MANV_PATIENTEN
    // =========================================================================
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_manv_patienten', 'idx_manv_pat_lage', 'manv_lage_id');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_manv_patienten', 'idx_manv_pat_category', 'sichtungskategorie');

    // =========================================================================
    // INTRA_AUDIT_LOG
    // =========================================================================
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_audit_log', 'idx_audit_user', 'user');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_audit_log', 'idx_audit_timestamp', 'timestamp');
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_audit_log', 'idx_audit_module', 'module');

    // =========================================================================
    // INTRA_CONFIG
    // =========================================================================
    DatabaseHelper::addIndexIfNotExists($pdo, 'intra_config', 'idx_config_order', 'display_order, config_key');

} catch (PDOException $e) {
    // Allgemeiner Fehler - nicht kritisch, da Indizes optional sind
    error_log("Performance index migration error: " . $e->getMessage());
}
