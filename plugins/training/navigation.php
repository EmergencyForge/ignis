<?php

/**
 * Ausbildung unter „Personal“ mit Zähler, die Lehrgangsarten in den
 * Einstellungen neben den Qualifikationen. Fällt eine Zielgruppe weg,
 * erscheint das Fragment als eigene Gruppe.
 */

return [
    [
        'merge_into' => 'personal',
        'id'         => 'training',
        'label'      => 'Ausbildung',
        'icon'       => 'fa-solid fa-graduation-cap',
        'items'      => [
            [
                'label'       => 'Ausbildung',
                'href'        => BASE_PATH . 'training',
                'icon'        => 'fa-solid fa-graduation-cap',
                'permissions' => ['admin', 'training.view', 'training.teach', 'training.manage'],
                'match'       => ['/training'],
                'counter'     => 'training',
            ],
        ],
    ],
    [
        'merge_into' => 'personnel-quals',
        'id'         => 'training-settings',
        'label'      => 'Lehrgangsarten',
        'placement'  => 'settings',
        'items'      => [
            [
                'label'        => 'Lehrgangsarten',
                'href'         => BASE_PATH . 'training/types',
                'icon'         => 'fa-solid fa-graduation-cap',
                'description'  => 'Lehrplan, Fragenpool und Prüfungsordnung je Lehrgang.',
                'keywords'     => 'Ausbildung Lehrgang Lehrplan Unterlagen Prüfung Fragen',
                'permissions'  => ['admin', 'training.manage', 'training.exams'],
                'match'        => ['/training/types'],
                'quick_action' => [
                    'type'   => 'link',
                    'target' => BASE_PATH . 'training/types/create',
                    'label'  => 'Neue Lehrgangsart anlegen',
                ],
            ],
        ],
    ],
];
