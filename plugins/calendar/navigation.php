<?php

/**
 * Kalender: steht in der Startgruppe direkt hinter dem Dashboard, mit der
 * Schnellaktion „Neuen Termin erstellen". Fällt die Zielgruppe weg,
 * erscheint das Fragment als eigene Gruppe.
 */

return [
    [
        'merge_into'  => 'start',
        'merge_after' => 'Dashboard',
        'id'          => 'calendar',
        'label'       => 'Kalender',
        'icon'        => 'fa-solid fa-calendar-days',
        'items'       => [
            [
                'label'        => 'Kalender',
                'href'         => BASE_PATH . 'calendar',
                'icon'         => 'fa-solid fa-calendar-days',
                'permissions'  => ['admin', 'calendar.view'],
                'match'        => ['/calendar'],
                'quick_action' => [
                    'type'        => 'drawer',
                    'target'      => BASE_PATH . 'calendar/create',
                    'label'       => 'Neuen Termin erstellen',
                    'permissions' => ['admin', 'calendar.create'],
                ],
            ],
        ],
    ],
];
