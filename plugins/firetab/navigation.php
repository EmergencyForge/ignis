<?php

/**
 * fireTab — hängt seine Einträge in die Gruppe „Protokolle" ein. Fällt
 * die Zielgruppe weg, erscheint das Fragment als eigene Gruppe (deshalb
 * die vollständigen Felder).
 */

return [
    [
        'merge_into' => 'protokolle',
        'id'         => 'firetab',
        'label'      => 'FW Einsatzprotokolle',
        'icon'       => 'fa-solid fa-fire',
        'items'      => [
            [
                'label'    => 'fireTab öffnen',
                'href'     => BASE_PATH . 'firetab/',
                'icon'     => 'fa-solid fa-fire',
                'external' => true,
            ],
            [
                'label'       => 'Einsatz-QM',
                'href'        => BASE_PATH . 'firetab/admin/list',
                'icon'        => 'fa-solid fa-clipboard-check',
                'permissions' => ['admin', 'fire.incident.qm'],
                'match'       => ['/firetab/admin'],
            ],
        ],
    ],
];
