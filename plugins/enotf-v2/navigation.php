<?php

/**
 * eNOTF v2 — hängt sich wie v1 in den Rail-Eintrag „Protokolle" ein.
 * Settings bleiben beim v1-Plugin (POIs, Medikamente, Schnellzugriff
 * verwalten denselben Datenbestand — kein zweiter Einstieg nötig).
 */

return [
    [
        'merge_into' => 'protokolle',
        'id'         => 'enotf-v2',
        'label'      => 'eNOTF v2',
        'icon'       => 'fa-solid fa-file-medical',
        'sections'   => [
            [
                'label' => 'eNOTF v2',
                'items' => [
                    [
                        'label'    => 'eNOTF v2 öffnen',
                        'href'     => BASE_PATH . 'enotf-v2/',
                        'external' => true,
                    ],
                ],
            ],
        ],
    ],
];
