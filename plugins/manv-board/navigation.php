<?php

/**
 * MANV-Board — hängt seine Section in den bestehenden Rail-Eintrag
 * „Protokolle" ein. Fällt der Ziel-Eintrag weg, erscheint das Fragment
 * als eigener Rail-Eintrag (deshalb die vollständigen Felder).
 */

return [
    [
        'merge_into' => 'protokolle',
        'id'         => 'manv-board',
        'label'      => 'MANV-Board',
        'icon'       => 'fa-solid fa-truck-medical',
        'sections'   => [
            [
                'label'       => 'MANV-Board',
                'permissions' => ['admin', 'mci.manage'],
                'items'       => [
                    [
                        'label'        => 'MANV-Board',
                        'href'         => BASE_PATH . 'mci/',
                        'quick_action' => [
                            'type'   => 'link',
                            'target' => BASE_PATH . 'mci/create',
                            'label'  => 'Neue MANV-Lage anlegen',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
