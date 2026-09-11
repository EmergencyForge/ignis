<?php

/**
 * MANV-Board — hängt seinen Eintrag in die Gruppe „Protokolle" ein. Fällt
 * die Zielgruppe weg, erscheint das Fragment als eigene Gruppe (deshalb
 * die vollständigen Felder).
 */

return [
    [
        'merge_into' => 'protokolle',
        'id'         => 'manv-board',
        'label'      => 'MANV-Board',
        'icon'       => 'fa-solid fa-truck-medical',
        'items'      => [
            [
                'label'        => 'MANV-Board',
                'href'         => BASE_PATH . 'mci/',
                'icon'         => 'fa-solid fa-truck-medical',
                'permissions'  => ['admin', 'mci.manage'],
                'match'        => ['/mci'],
                'quick_action' => [
                    'type'   => 'link',
                    'target' => BASE_PATH . 'mci/create',
                    'label'  => 'Neue MANV-Lage anlegen',
                ],
            ],
        ],
    ],
];
