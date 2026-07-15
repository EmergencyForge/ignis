<?php

/**
 * eNOTF — hängt seine Sections in die bestehenden Rail-Einträge
 * „Protokolle" und „Einstellungen" ein. Fällt ein Ziel-Eintrag weg,
 * erscheint das Fragment als eigener Rail-Eintrag (deshalb die
 * vollständigen Felder).
 */

use Plugin\Enotf\Helpers\EnotfUrl;

return [
    [
        'merge_into' => 'protokolle',
        'id'         => 'enotf',
        'label'      => 'eNOTF',
        'icon'       => 'fa-solid fa-file-medical',
        'sections'   => [
            [
                'label' => 'eNOTF',
                'items' => [
                    [
                        'label'    => 'eNOTF öffnen',
                        'href'     => BASE_PATH . 'enotf/',
                        'external' => true,
                    ],
                    [
                        'label'       => 'Prüfliste',
                        'href'        => EnotfUrl::admin('list'),
                        'permissions' => ['admin', 'edivi.view'],
                    ],
                ],
            ],
        ],
    ],
    [
        'merge_into' => 'settings',
        'id'         => 'enotf-settings',
        'label'      => 'eNOTF-Einstellungen',
        'icon'       => 'fa-solid fa-file-medical',
        'sections'   => [
            [
                'label'       => 'eNOTF',
                'permissions' => ['admin', 'edivi.view', 'pois.view'],
                'items'       => [
                    [
                        'label'        => 'POIs',
                        'href'         => BASE_PATH . 'settings/pois/index',
                        'permissions'  => ['admin', 'pois.view'],
                        'quick_action' => [
                            'type'   => 'modal',
                            'target' => 'poi-create',
                            'label'  => 'Neuen POI anlegen',
                        ],
                    ],
                    [
                        'label'        => 'Medikamente',
                        'href'         => BASE_PATH . 'settings/medications/index',
                        'permissions'  => ['admin', 'edivi.view'],
                        'quick_action' => [
                            'type'   => 'modal',
                            'target' => 'medikament-create',
                            'label'  => 'Neues Medikament anlegen',
                        ],
                    ],
                    [
                        'label'        => 'Schnellzugriff',
                        'href'         => BASE_PATH . 'settings/enotf/index',
                        'permissions'  => ['admin', 'edivi.view'],
                        'quick_action' => [
                            'type'   => 'modal',
                            'target' => 'schnellzugriff-link-create',
                            'label'  => 'Neuen Link anlegen',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
