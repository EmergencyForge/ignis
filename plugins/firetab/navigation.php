<?php

/**
 * fireTab — hängt seine Section in den bestehenden Rail-Eintrag
 * „Protokolle" ein. Fällt der Ziel-Eintrag weg, erscheint das Fragment
 * als eigener Rail-Eintrag (deshalb die vollständigen Felder).
 */

return [
    [
        'merge_into' => 'protokolle',
        'id'         => 'firetab',
        'label'      => 'FW Einsatzprotokolle',
        'icon'       => 'fa-solid fa-fire',
        'sections'   => [
            [
                'label' => 'FW Einsatzprotokolle',
                'items' => [
                    [
                        'label'    => 'fireTab öffnen',
                        'href'     => BASE_PATH . 'firetab/',
                        'external' => true,
                    ],
                    [
                        'label'       => 'Qualitätsmanagement',
                        'href'        => BASE_PATH . 'firetab/admin/list',
                        'permissions' => ['admin', 'fire.incident.qm'],
                    ],
                ],
            ],
        ],
    ],
];
