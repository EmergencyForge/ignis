<?php

/**
 * Anträge: die Antragsliste unter „Personal", die Antragstypen in den
 * Einstellungen unter „Inhalte" hinter den Dokumentvorlagen. Fällt eine
 * Zielgruppe weg, erscheint das Fragment als eigene Gruppe (deshalb die
 * vollständigen Felder).
 */

return [
    [
        'merge_into' => 'personal',
        'id'         => 'forms',
        'label'      => 'Anträge',
        'icon'       => 'fa-solid fa-file-signature',
        'items'      => [
            [
                'label'       => 'Anträge',
                'href'        => BASE_PATH . 'forms/admin/list',
                'icon'        => 'fa-solid fa-file-signature',
                'permissions' => ['admin', 'application.view'],
                'match'       => ['/forms/admin'],
            ],
        ],
    ],
    [
        'merge_into'  => 'templates-forms',
        'merge_after' => 'Dokumentvorlagen',
        'id'          => 'forms-settings',
        'label'       => 'Antragstypen',
        'placement'   => 'settings',
        'items'       => [
            [
                'label'        => 'Antragstypen',
                'href'         => BASE_PATH . 'settings/forms/list',
                'icon'         => 'fa-solid fa-list-check',
                'description'  => 'Welche Anträge Mitarbeiter stellen können.',
                'permissions'  => ['admin'],
                'match'        => ['/settings/forms'],
                'quick_action' => [
                    'type'   => 'link',
                    'target' => BASE_PATH . 'settings/forms/create',
                    'label'  => 'Neuen Antragstyp anlegen',
                ],
            ],
        ],
    ],
];
