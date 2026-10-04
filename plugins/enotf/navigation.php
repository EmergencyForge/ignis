<?php

/**
 * eNOTF: hängt seine Sections in die bestehenden Rail-Einträge
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
        'merge_into' => 'enotf',
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
                        'icon'         => 'fa-solid fa-location-dot',
                        // Abteilungen und Zugangscodes hängen unter /settings/pois/.
                        'match'        => ['/settings/pois'],
                        'description'  => 'Points of Interest für Einsätze verwalten.',
                        'permissions'  => ['admin', 'pois.view'],
                        'quick_action' => [
                            'type'   => 'modal',
                            'target' => 'poi-create',
                            'label'  => 'Neuen POI anlegen',
                        ],
                    ],
                    [
                        'label'       => 'Krankenhaus-Zugangscodes',
                        'href'        => BASE_PATH . 'settings/pois/access-codes',
                        'icon'        => 'fa-solid fa-key',
                        'description' => 'Codes, mit denen Kliniken im Verfügbarkeits-Portal ihre Fachrichtungen pflegen.',
                        'permissions' => ['admin', 'pois.manage'],
                    ],
                    [
                        'label'        => 'Medikamente',
                        'href'         => BASE_PATH . 'settings/medications/index',
                        'icon'         => 'fa-solid fa-pills',
                        'description'  => 'Medikamentenliste für eNOTF verwalten.',
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
                        'icon'         => 'fa-solid fa-bolt',
                        'match'        => ['/settings/enotf'],
                        'description'  => 'Schnellzugriffs-Links im eNOTF pflegen.',
                        'permissions'  => ['admin', 'edivi.view'],
                        'quick_action' => [
                            'type'   => 'modal',
                            'target' => 'schnellzugriff-link-create',
                            'label'  => 'Neuen Link anlegen',
                        ],
                    ],
                    [
                        'label'       => 'Schnellzugriff-Kategorien',
                        'href'        => BASE_PATH . 'settings/enotf/kategorien/index',
                        'icon'        => 'fa-solid fa-folder-tree',
                        'description' => 'Gruppen, in denen die Schnellzugriffs-Links stehen.',
                        'permissions' => ['admin', 'edivi.view'],
                    ],
                ],
            ],
        ],
    ],
];
