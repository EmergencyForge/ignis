<?php
/**
 * Seed-Migration: Modularisierung des eNOTF-Protokollsystems
 * ===========================================================
 * Befuellt alle neuen Tabellen mit den bestehenden Feldern, Sektionen,
 * Protokolltypen und Validierungsregeln aus dem statischen System.
 *
 * Muss NACH allen create_intra_edivi_*_29032026.php Migrationen laufen.
 */
try {
    $pdo->beginTransaction();

    // ════════════════════════════════════════════════════════════════
    // 1. Protokolltypen (NF + NA als builtin)
    // ════════════════════════════════════════════════════════════════
    $pdo->exec("INSERT IGNORE INTO `intra_edivi_protocol_types` (`id`, `slug`, `name`, `short_name`, `description`, `color`, `icon`, `is_builtin`, `active`, `sort_order`) VALUES
        (1, 'nf', 'Notfallprotokoll',  'NF', 'Standard-Rettungsdienstprotokoll',            '#dc3545', 'fa-solid fa-truck-medical', 1, 1, 1),
        (2, 'na', 'Notarztprotokoll',  'NA', 'Erweitertes Protokoll mit Notarzt-Dokumentation', '#0d6efd', 'fa-solid fa-user-doctor',   1, 1, 2)
    ");

    // ════════════════════════════════════════════════════════════════
    // 2. Sektionen (7 bestehende als builtin)
    // ════════════════════════════════════════════════════════════════
    $pdo->exec("INSERT IGNORE INTO `intra_edivi_sections` (`id`, `slug`, `name`, `icon`, `is_builtin`, `has_subsections`, `component_template`) VALUES
        (1, 'rettdaten',   'Rett. Daten',  'fa-solid fa-clipboard-list', 1, 0, NULL),
        (2, 'erstbefund',  'Erstbefund',   'fa-solid fa-stethoscope',    1, 1, NULL),
        (3, 'anamnese',    'Anamnese',     'fa-solid fa-notes-medical',  1, 0, NULL),
        (4, 'diagnose',    'Diagnose',     'fa-solid fa-diagnoses',      1, 0, 'diagnose_hierarchy'),
        (5, 'verlauf',     'Verlauf',      'fa-solid fa-chart-line',     1, 0, 'vitals_chart'),
        (6, 'massnahmen',  'Maßnahmen',    'fa-solid fa-syringe',        1, 1, NULL),
        (7, 'abschluss',   'Abschluss',    'fa-solid fa-flag-checkered', 1, 0, NULL)
    ");

    // ════════════════════════════════════════════════════════════════
    // 3. Type-Sections Mapping (beide Typen bekommen alle 7 Sektionen)
    // ════════════════════════════════════════════════════════════════
    foreach ([1, 2] as $typeId) {
        for ($s = 1; $s <= 7; $s++) {
            $pdo->exec("INSERT IGNORE INTO `intra_edivi_type_sections`
                (`protocol_type_id`, `section_id`, `enabled`, `sort_order`, `is_required`)
                VALUES ($typeId, $s, 1, $s, 1)");
        }
    }

    // ════════════════════════════════════════════════════════════════
    // 4. Field Definitions (alle bestehenden Legacy-Felder)
    // ════════════════════════════════════════════════════════════════
    // Format: [field_key, label, field_type, options_json, widget, is_core, input_suffix, section_id]
    $fields = [
        // ──── [1] Rettdaten ────
        ['enr',             'Einsatznummer',              'text',     null, null, 1, null, 1],
        ['pat_vorname',     'Vorname',                    'text',     null, null, 0, null, 1],
        ['pat_nachname',    'Nachname',                   'text',     null, null, 0, null, 1],
        ['patgebdat',       'Geburtsdatum',               'date',     null, null, 0, null, 1],
        ['patsex',          'Geschlecht',                 'radio',    '{"values":[{"value":1,"label":"Männlich"},{"value":2,"label":"Weiblich"},{"value":3,"label":"Divers"}]}', null, 0, null, 1],
        ['edatum',          'Einsatzdatum',               'date',     null, null, 1, null, 1],
        ['ezeit',           'Einsatzzeit',                'time',     null, null, 1, null, 1],
        ['eort',            'Einsatzort',                 'text',     null, 'poi_search', 0, null, 1],
        ['eart',            'Einsatzart',                 'radio',    '{"values":[{"value":1,"label":"Primäreinsatz"},{"value":2,"label":"Sekundäreinsatz"},{"value":3,"label":"Fehleinsatz/sonstige"}]}', null, 0, null, 1],
        ['transportziel',   'Versorgungsart',             'select',   null, null, 0, null, 1],
        ['elokation',       'Einsatzlokalisation',        'select',   null, null, 0, null, 1],
        ['ebesonderheiten', 'Einsatzbesonderheiten',      'json_multi_select', null, null, 0, null, 1],
        ['salarm',          'Alarmzeit',                  'time',     null, null, 0, null, 1],
        ['s1',              'Status 1',                   'time',     null, null, 0, null, 1],
        ['s2',              'Status 2',                   'time',     null, null, 0, null, 1],
        ['s3',              'Status 3',                   'time',     null, null, 0, null, 1],
        ['s4',              'Status 4',                   'time',     null, null, 0, null, 1],
        ['spat',            'Pat. Ankunft',               'time',     null, null, 0, null, 1],
        ['s7',              'Einsatzort ab',              'time',     null, null, 0, null, 1],
        ['s8',              'KH an',                      'time',     null, null, 0, null, 1],
        ['sende',           'Endzeit',                    'time',     null, null, 0, null, 1],
        ['fzg_transp',      'Transportfahrzeug',          'text',     null, null, 0, null, 1],
        ['fzg_transp_perso','Transp. Personal 1',         'text',     null, null, 0, null, 1],
        ['fzg_transp_perso_2','Transp. Personal 2',       'text',     null, null, 0, null, 1],
        ['fzg_transp_perso_3','Transp. Personal 3',       'text',     null, null, 0, null, 1],
        ['fzg_na',          'NA-Fahrzeug',                'text',     null, null, 0, null, 1],
        ['fzg_na_perso',    'NA Personal 1',              'text',     null, null, 0, null, 1],
        ['fzg_na_perso_2',  'NA Personal 2',              'text',     null, null, 0, null, 1],
        ['fzg_na_perso_3',  'NA Personal 3',              'text',     null, null, 0, null, 1],
        ['fzg_sonst',       'Sonstige Fahrzeuge',         'text',     null, null, 0, null, 1],
        ['sonderrechte_anfahrt',    'Sonderrechte Anfahrt',     'radio', '{"values":[{"value":1,"label":"Ja"},{"value":0,"label":"Nein"}]}', null, 0, null, 1],
        ['sonderrechte_transport',  'Sonderrechte Transport',   'radio', '{"values":[{"value":1,"label":"Ja"},{"value":0,"label":"Nein"}]}', null, 0, null, 1],
        ['symptombeginn_datum',     'Symptombeginn Datum',      'date',  null, null, 0, null, 1],
        ['symptombeginn_zeit',      'Symptombeginn Zeit',       'time',  null, null, 0, null, 1],
        ['symptombeginn_geschaetzt','Symptombeginn geschätzt',  'checkbox', null, null, 0, null, 1],
        ['symptombeginn_nf',        'Symptombeginn Notfall',    'checkbox', null, null, 0, null, 1],

        // ──── [2] Erstbefund: Atemwege ────
        ['awfrei_1',        'Atemwegszustand',            'radio',    '{"values":[{"value":1,"label":"frei"},{"value":2,"label":"gefährdet"},{"value":3,"label":"verlegt"}]}', null, 0, null, 2],
        ['awsicherung_1',   'Atemwegssicherung (Befund)', 'radio',    null, null, 0, null, 2],
        ['hws_immo',        'HWS-Immobilisation',         'radio',    '{"values":[{"value":1,"label":"Ja"},{"value":0,"label":"Nein"}]}', null, 0, null, 2],
        ['zyanose_1',       'Zyanose',                    'radio',    '{"values":[{"value":1,"label":"Ja"},{"value":2,"label":"Nein"}]}', null, 0, null, 2],
        ['o2gabe',          'O₂-Gabe',                    'number',   null, null, 0, 'l/min', 2],

        // ──── [2] Erstbefund: Atmung ────
        ['b_symptome',      'Beurteilung Atmung',         'radio',    null, null, 0, null, 2],
        ['b_auskult',       'Auskultation',               'radio',    null, null, 0, null, 2],

        // ──── [2] Erstbefund: Kreislauf ────
        ['c_kreislauf',     'Patientenzustand',           'radio',    null, null, 0, null, 2],
        ['c_ekg',           'EKG-Befund',                 'radio',    null, null, 0, null, 2],
        ['c_puls_rad',      'Radialispuls',               'radio',    '{"values":[{"value":1,"label":"tastbar"},{"value":2,"label":"nicht tastbar"}]}', null, 0, null, 2],
        ['c_puls_reg',      'Puls regelmäßig',            'radio',    '{"values":[{"value":1,"label":"regelmäßig"},{"value":2,"label":"unregelmäßig"}]}', null, 0, null, 2],
        ['c_rekap',         'Rekapillarisierung',         'radio',    null, null, 0, null, 2],
        ['c_blutung',       'Blutung',                    'radio',    null, null, 0, null, 2],

        // ──── [2] Erstbefund: Neurologie ────
        ['d_bewusstsein',   'Bewusstseinslage',           'radio',    null, null, 0, null, 2],
        ['d_ex_1',          'Extremitätenbewegung',       'radio',    null, null, 0, null, 2],
        ['d_pupillenw_1',   'Pupillenweite Links',        'radio',    null, null, 0, null, 2],
        ['d_pupillenw_2',   'Pupillenweite Rechts',       'radio',    null, null, 0, null, 2],
        ['d_lichtreakt_1',  'Lichtreaktion Links',        'radio',    null, null, 0, null, 2],
        ['d_lichtreakt_2',  'Lichtreaktion Rechts',       'radio',    null, null, 0, null, 2],
        ['d_gcs_1',         'GCS Augenöffnung',           'radio',    null, 'gcs_calculator', 0, null, 2],
        ['d_gcs_2',         'GCS Verbale Reaktion',       'radio',    null, 'gcs_calculator', 0, null, 2],
        ['d_gcs_3',         'GCS Motorische Reaktion',    'radio',    null, 'gcs_calculator', 0, null, 2],

        // ──── [2] Erstbefund: Psychisch ────
        ['psych',           'Psychischer Zustand',        'json_multi_select', null, null, 0, null, 2],

        // ──── [2] Erstbefund: Erweitern (Body Diagram) ────
        ['v_muster_k',      'Thorax Status',              'radio',    null, 'body_diagram', 0, null, 2],
        ['v_muster_k1',     'Thorax Details',             'radio',    null, 'body_diagram', 0, null, 2],
        ['v_muster_w',      'Rücken Status',              'radio',    null, 'body_diagram', 0, null, 2],
        ['v_muster_w1',     'Rücken Details',             'radio',    null, 'body_diagram', 0, null, 2],
        ['v_muster_t',      'Abdomen Status',             'radio',    null, 'body_diagram', 0, null, 2],
        ['v_muster_t1',     'Abdomen Details',            'radio',    null, 'body_diagram', 0, null, 2],
        ['v_muster_a',      'Kopf Status',                'radio',    null, 'body_diagram', 0, null, 2],
        ['v_muster_a1',     'Kopf Details',               'radio',    null, 'body_diagram', 0, null, 2],
        ['v_muster_al',     'Arm Links Status',           'radio',    null, 'body_diagram', 0, null, 2],
        ['v_muster_al1',    'Arm Links Details',          'radio',    null, 'body_diagram', 0, null, 2],
        ['v_muster_bl',     'Bein Links Status',          'radio',    null, 'body_diagram', 0, null, 2],
        ['v_muster_bl1',    'Bein Links Details',         'radio',    null, 'body_diagram', 0, null, 2],

        // ──── [2] Erstbefund: Messwerte ────
        ['spo2',            'SpO₂',                       'number',   null, null, 0, '%', 2],
        ['atemfreq',        'Atemfrequenz',               'number',   null, null, 0, '/min', 2],
        ['etco2',           'etCO₂',                      'number',   null, null, 0, 'mmHg', 2],
        ['rrsys',           'RR systolisch',              'number',   null, null, 0, 'mmHg', 2],
        ['rrdias',          'RR diastolisch',             'number',   null, null, 0, 'mmHg', 2],
        ['herzfreq',        'Herzfrequenz',               'number',   null, null, 0, '/min', 2],
        ['bz',              'Blutzucker',                 'number',   null, null, 0, 'mg/dl', 2],
        ['temp',            'Temperatur',                 'number',   null, null, 0, '°C', 2],
        ['sz_nrs',          'NRS Schmerzskala',           'number',   null, 'naca_scale', 0, null, 2],
        ['sz_toleranz_1',   'Schmerztoleranz',            'radio',    null, null, 0, null, 2],

        // ──── [3] Anamnese ────
        ['anmerkungen',     'Anamnese / Anmerkungen',     'textarea', null, null, 0, null, 3],
        ['naca_initial',    'NACA-Score (initial)',        'radio',    null, 'naca_scale', 0, null, 3],

        // ──── [4] Diagnose ────
        ['diagnose_haupt',  'Führende Diagnose',          'composite', null, 'diagnose_hierarchy', 0, null, 4],
        ['diagnose_weitere','Weitere Diagnosen',          'composite', null, 'diagnose_hierarchy', 0, null, 4],
        ['diagnose',        'Diagnose Freitext',          'textarea', null, null, 0, null, 4],

        // ──── [6] Maßnahmen: Atemwege ────
        ['awsicherung_neu', 'Atemwegssicherung',          'radio',    null, null, 0, null, 6],
        ['entlastungspunktion', 'Entlastungspunktion',    'radio',    null, null, 0, null, 6],

        // ──── [6] Maßnahmen: Atmung ────
        ['b_beatmung',      'Beatmung',                   'radio',    null, null, 0, null, 6],

        // ──── [6] Maßnahmen: Zugang ────
        ['c_zugang',        'Gefäßzugang',                'composite', null, 'zugang_picker', 0, null, 6],

        // ──── [6] Maßnahmen: Medikamente ────
        ['medis',           'Medikamente',                'composite', null, 'medikament_picker', 0, null, 6],

        // ──── [6] Maßnahmen: Weitere ────
        ['rettungstechnik', 'Rettungstechnik',            'json_multi_select', null, null, 0, null, 6],
        ['lagerung',        'Lagerung',                   'radio',    null, null, 0, null, 6],
        ['waerme_passiv',   'Wärmeerhalt passiv',         'checkbox', null, null, 0, null, 6],
        ['waerme_aktiv',    'Wärmeerhalt aktiv',          'checkbox', null, null, 0, null, 6],
        ['e_reposition',    'Reposition',                 'checkbox', null, null, 0, null, 6],
        ['e_verband',       'Verband',                    'checkbox', null, null, 0, null, 6],
        ['e_krintervention','Kreislaufintervention',      'checkbox', null, null, 0, null, 6],
        ['e_kuehlung',      'Kühlung',                    'checkbox', null, null, 0, null, 6],
        ['e_narkose',       'Narkose',                    'checkbox', null, null, 0, null, 6],
        ['e_tourniquet',    'Tourniquet',                 'checkbox', null, null, 0, null, 6],
        ['e_cpr',           'CPR',                        'checkbox', null, null, 0, null, 6],

        // ──── [7] Abschluss ────
        ['naca_uebergabe',  'NACA-Score (Übergabe)',      'radio',    null, 'naca_scale', 0, null, 7],
        ['uebergabe_ort',   'Übergabeort',                'select',   null, null, 0, null, 7],
        ['uebergabe_an',    'Übergabe an',                'select',   null, null, 0, null, 7],
        ['na_nachf',        'NA-Nachforderung',           'radio',    '{"values":[{"value":1,"label":"Ja"},{"value":0,"label":"Nein"}]}', null, 0, null, 7],
        ['pfname',          'Protokollant',               'text',     null, null, 0, null, 7],
        ['prot_by',         'Protokollart',               'radio',    '{"values":[{"value":0,"label":"NF (Rettungsdienst)"},{"value":1,"label":"NA (Notarzt)"}]}', null, 0, null, 7],
    ];

    $stmtField = $pdo->prepare("INSERT IGNORE INTO `intra_edivi_field_definitions`
        (`field_key`, `label`, `field_type`, `options_json`, `widget`, `is_legacy_column`, `legacy_column_name`, `is_core`, `input_suffix`)
        VALUES (:field_key, :label, :field_type, :options_json, :widget, 1, :legacy_col, :is_core, :suffix)");

    // Mapping: field_key -> [field_def_id, section_id] (fuer type_fields)
    $fieldMap = [];
    $sortCounters = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0];

    foreach ($fields as $f) {
        [$key, $label, $type, $options, $widget, $isCore, $suffix, $sectionId] = $f;

        $stmtField->execute([
            'field_key'    => $key,
            'label'        => $label,
            'field_type'   => $type,
            'options_json' => $options,
            'widget'       => $widget,
            'legacy_col'   => $key,
            'is_core'      => $isCore,
            'suffix'       => $suffix,
        ]);

        $fieldDefId = $pdo->lastInsertId();
        if ($fieldDefId) {
            $sortCounters[$sectionId]++;
            $fieldMap[] = [
                'field_def_id' => (int)$fieldDefId,
                'section_id'   => $sectionId,
                'sort_order'   => $sortCounters[$sectionId],
                'field_key'    => $key,
            ];
        }
    }

    // ════════════════════════════════════════════════════════════════
    // 5. Type-Fields Mapping (beide Typen bekommen alle Felder)
    // ════════════════════════════════════════════════════════════════
    // Pflichtfeld-Keys aus conditions.php base_required
    $requiredFieldKeys = [
        'patsex', 'edatum', 'ezeit', 'transportziel', 'salarm', 'spat', 'sende', 'eart',
        'awfrei_1', 'zyanose_1', 'b_symptome', 'b_auskult',
        'c_kreislauf', 'c_ekg', 'c_puls_rad', 'c_puls_reg',
        'd_bewusstsein', 'd_ex_1', 'd_pupillenw_1', 'd_pupillenw_2',
        'd_lichtreakt_1', 'd_lichtreakt_2', 'd_gcs_1', 'd_gcs_2', 'd_gcs_3',
        'psych', 'spo2', 'atemfreq', 'rrsys', 'herzfreq', 'bz',
        'naca_initial', 'elokation',
        'diagnose_haupt',
        'awsicherung_neu', 'b_beatmung', 'c_zugang', 'medis',
        'ebesonderheiten', 'na_nachf', 'pfname', 'prot_by',
    ];

    // Gruppen-Keys fuer visuelle Gruppierung
    $groupMap = [
        // Erstbefund Gruppen
        'awfrei_1' => ['atemwege', 'Atemwege'], 'awsicherung_1' => ['atemwege', 'Atemwege'],
        'hws_immo' => ['atemwege', 'Atemwege'], 'zyanose_1' => ['atemwege', 'Atemwege'],
        'o2gabe' => ['atemwege', 'Atemwege'],
        'b_symptome' => ['atmung', 'Atmung'], 'b_auskult' => ['atmung', 'Atmung'],
        'c_kreislauf' => ['kreislauf', 'Kreislauf'], 'c_ekg' => ['kreislauf', 'Kreislauf'],
        'c_puls_rad' => ['kreislauf', 'Kreislauf'], 'c_puls_reg' => ['kreislauf', 'Kreislauf'],
        'c_rekap' => ['kreislauf', 'Kreislauf'], 'c_blutung' => ['kreislauf', 'Kreislauf'],
        'd_bewusstsein' => ['neurologie', 'Neurologie'], 'd_ex_1' => ['neurologie', 'Neurologie'],
        'd_pupillenw_1' => ['neurologie', 'Neurologie'], 'd_pupillenw_2' => ['neurologie', 'Neurologie'],
        'd_lichtreakt_1' => ['neurologie', 'Neurologie'], 'd_lichtreakt_2' => ['neurologie', 'Neurologie'],
        'd_gcs_1' => ['neurologie', 'Neurologie'], 'd_gcs_2' => ['neurologie', 'Neurologie'],
        'd_gcs_3' => ['neurologie', 'Neurologie'],
        'psych' => ['psychisch', 'Psychisch'],
        'v_muster_k' => ['koerper', 'Untersuchungsmuster'], 'v_muster_k1' => ['koerper', 'Untersuchungsmuster'],
        'v_muster_w' => ['koerper', 'Untersuchungsmuster'], 'v_muster_w1' => ['koerper', 'Untersuchungsmuster'],
        'v_muster_t' => ['koerper', 'Untersuchungsmuster'], 'v_muster_t1' => ['koerper', 'Untersuchungsmuster'],
        'v_muster_a' => ['koerper', 'Untersuchungsmuster'], 'v_muster_a1' => ['koerper', 'Untersuchungsmuster'],
        'v_muster_al' => ['koerper', 'Untersuchungsmuster'], 'v_muster_al1' => ['koerper', 'Untersuchungsmuster'],
        'v_muster_bl' => ['koerper', 'Untersuchungsmuster'], 'v_muster_bl1' => ['koerper', 'Untersuchungsmuster'],
        'spo2' => ['messwerte', 'Messwerte'], 'atemfreq' => ['messwerte', 'Messwerte'],
        'etco2' => ['messwerte', 'Messwerte'], 'rrsys' => ['messwerte', 'Messwerte'],
        'rrdias' => ['messwerte', 'Messwerte'], 'herzfreq' => ['messwerte', 'Messwerte'],
        'bz' => ['messwerte', 'Messwerte'], 'temp' => ['messwerte', 'Messwerte'],
        'sz_nrs' => ['messwerte', 'Messwerte'], 'sz_toleranz_1' => ['messwerte', 'Messwerte'],
        // Massnahmen Gruppen
        'awsicherung_neu' => ['m_atemwege', 'Atemwege'], 'entlastungspunktion' => ['m_atemwege', 'Atemwege'],
        'b_beatmung' => ['m_atmung', 'Atmung'],
        'c_zugang' => ['m_zugang', 'Zugang'],
        'medis' => ['m_medikamente', 'Medikamente'],
        'rettungstechnik' => ['m_weitere', 'Weitere Maßnahmen'], 'lagerung' => ['m_weitere', 'Weitere Maßnahmen'],
        'waerme_passiv' => ['m_weitere', 'Weitere Maßnahmen'], 'waerme_aktiv' => ['m_weitere', 'Weitere Maßnahmen'],
        'e_reposition' => ['m_weitere', 'Weitere Maßnahmen'], 'e_verband' => ['m_weitere', 'Weitere Maßnahmen'],
        'e_krintervention' => ['m_weitere', 'Weitere Maßnahmen'], 'e_kuehlung' => ['m_weitere', 'Weitere Maßnahmen'],
        'e_narkose' => ['m_weitere', 'Weitere Maßnahmen'], 'e_tourniquet' => ['m_weitere', 'Weitere Maßnahmen'],
        'e_cpr' => ['m_weitere', 'Weitere Maßnahmen'],
    ];

    $stmtTypeField = $pdo->prepare("INSERT IGNORE INTO `intra_edivi_type_fields`
        (`protocol_type_id`, `section_id`, `field_definition_id`, `enabled`, `is_required`, `sort_order`, `column_width`, `group_key`, `group_label`)
        VALUES (:type_id, :section_id, :field_def_id, 1, :is_required, :sort_order, 'full', :group_key, :group_label)");

    foreach ([1, 2] as $typeId) {
        foreach ($fieldMap as $fm) {
            $isRequired = in_array($fm['field_key'], $requiredFieldKeys) ? 1 : 0;
            $groupKey = isset($groupMap[$fm['field_key']]) ? $groupMap[$fm['field_key']][0] : null;
            $groupLabel = isset($groupMap[$fm['field_key']]) ? $groupMap[$fm['field_key']][1] : null;

            $stmtTypeField->execute([
                'type_id'      => $typeId,
                'section_id'   => $fm['section_id'],
                'field_def_id' => $fm['field_def_id'],
                'is_required'  => $isRequired,
                'sort_order'   => $fm['sort_order'],
                'group_key'    => $groupKey,
                'group_label'  => $groupLabel,
            ]);
        }
    }

    // ════════════════════════════════════════════════════════════════
    // 6. Validation Rules (conditions.php Overrides + Additions)
    // ════════════════════════════════════════════════════════════════
    $stmtRule = $pdo->prepare("INSERT INTO `intra_edivi_validation_rules`
        (`protocol_type_id`, `name`, `rule_json`, `error_message`, `severity`, `active`, `sort_order`)
        VALUES (:type_id, :name, :rule_json, :error_message, 'error', 1, :sort_order)");

    // Override: Fehleinsatz (transportziel=4) → viele Felder werden optional
    $fehleinsatzOverrideFields = [
        'patsex', 'spat', 'awfrei_1', 'zyanose_1', 'b_symptome', 'b_auskult',
        'c_kreislauf', 'c_ekg', 'c_puls_rad', 'c_puls_reg',
        'd_bewusstsein', 'd_ex_1', 'd_pupillenw_1', 'd_pupillenw_2',
        'd_lichtreakt_1', 'd_lichtreakt_2', 'd_gcs_1', 'd_gcs_2', 'd_gcs_3',
        'psych', 'spo2', 'atemfreq', 'rrsys', 'herzfreq', 'bz',
        'naca_initial', 'diagnose_haupt',
        'awsicherung_neu', 'b_beatmung', 'c_zugang', 'medis', 'na_nachf',
    ];

    $fehleinsatzRuleJson = json_encode([
        'type' => 'override',
        'description' => 'Bei Fehleinsatz (transportziel=4) werden patientenbezogene Felder optional',
        'condition' => [
            'type' => 'condition',
            'field' => 'transportziel',
            'operator' => 'equals',
            'value' => '4',
        ],
        'action' => [
            'type' => 'make_optional',
            'target_fields' => $fehleinsatzOverrideFields,
        ],
    ], JSON_UNESCAPED_UNICODE);

    foreach ([1, 2] as $typeId) {
        $stmtRule->execute([
            'type_id'       => $typeId,
            'name'          => 'Fehleinsatz: Patientenfelder optional',
            'rule_json'     => $fehleinsatzRuleJson,
            'error_message' => 'Fehleinsatz: Patientenbezogene Pflichtfelder werden deaktiviert.',
            'sort_order'    => 1,
        ]);
    }

    // Addition: Transport (transportziel=2,21,22) → Zieladresse + Transportzeiten werden Pflicht
    $transportAdditionFields = ['ziel_adresse', 's7', 's8'];
    $transportConditionValues = ['2', '21', '22'];

    $transportRuleJson = json_encode([
        'type' => 'addition',
        'description' => 'Bei Transport werden Zieladresse und Transportzeiten Pflicht',
        'condition' => [
            'type' => 'condition',
            'field' => 'transportziel',
            'operator' => 'in_list',
            'value' => $transportConditionValues,
        ],
        'action' => [
            'type' => 'require',
            'target_fields' => $transportAdditionFields,
        ],
    ], JSON_UNESCAPED_UNICODE);

    foreach ([1, 2] as $typeId) {
        $stmtRule->execute([
            'type_id'       => $typeId,
            'name'          => 'Transport: Zieladresse + Zeiten Pflicht',
            'rule_json'     => $transportRuleJson,
            'error_message' => 'Bei Transport müssen Zieladresse und Transportzeiten (E.-ab, KH an) gesetzt sein.',
            'sort_order'    => 2,
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    // 7. Builtin Presets
    // ════════════════════════════════════════════════════════════════
    $presetNf = json_encode([
        'version' => '1.0',
        'protocol_type' => ['slug' => 'nf', 'name' => 'Notfallprotokoll', 'short_name' => 'NF'],
        'sections' => ['rettdaten', 'erstbefund', 'anamnese', 'diagnose', 'verlauf', 'massnahmen', 'abschluss'],
        'description' => 'Standard DIVI-Notfallprotokoll mit allen Sektionen und Feldern.',
    ], JSON_UNESCAPED_UNICODE);

    $presetNa = json_encode([
        'version' => '1.0',
        'protocol_type' => ['slug' => 'na', 'name' => 'Notarztprotokoll', 'short_name' => 'NA'],
        'sections' => ['rettdaten', 'erstbefund', 'anamnese', 'diagnose', 'verlauf', 'massnahmen', 'abschluss'],
        'description' => 'Standard DIVI-Notarztprotokoll mit allen Sektionen und Feldern.',
    ], JSON_UNESCAPED_UNICODE);

    $presetMinimal = json_encode([
        'version' => '1.0',
        'protocol_type' => ['slug' => 'minimal', 'name' => 'Minimal', 'short_name' => 'MIN'],
        'sections' => ['rettdaten', 'abschluss'],
        'description' => 'Minimales Protokoll nur mit Rettdaten und Abschluss.',
    ], JSON_UNESCAPED_UNICODE);

    $pdo->exec("INSERT IGNORE INTO `intra_edivi_presets` (`name`, `description`, `is_builtin`, `preset_json`, `version`) VALUES
        ('DIVI Standard (NF)', 'Vollständiges Notfallprotokoll nach DIVI-Standard', 1, " . $pdo->quote($presetNf) . ", '1.0'),
        ('DIVI Standard (NA)', 'Vollständiges Notarztprotokoll nach DIVI-Standard', 1, " . $pdo->quote($presetNa) . ", '1.0'),
        ('Minimal',            'Reduziertes Protokoll für einfache Dokumentation',   1, " . $pdo->quote($presetMinimal) . ", '1.0')
    ");

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    $message = $e->getMessage();
    echo "Seed-Migration fehlgeschlagen: " . $message;
}
