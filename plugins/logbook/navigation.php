<?php

/**
 * Fahrtenbuch: hängt seinen Eintrag in die Gruppe „Fahrzeuge" ein. Fällt
 * die Zielgruppe weg, erscheint das Fragment als eigene Gruppe (deshalb
 * die vollständigen Felder).
 */

return [
    [
        'merge_into' => 'fahrzeuge',
        'id'         => 'logbook',
        'label'      => 'Fahrtenbuch',
        'icon'       => 'fa-solid fa-road',
        'items'      => [
            [
                'label'       => 'Fahrtenbuch',
                'href'        => BASE_PATH . 'logbook/index',
                'icon'        => 'fa-solid fa-road',
                'permissions' => ['admin', 'logbook.view', 'logbook.manage'],
                'match'       => ['/logbook'],
            ],
        ],
    ],
];
