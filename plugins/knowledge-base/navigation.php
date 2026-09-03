<?php

/**
 * Wissensdatenbank — ein Eintrag in der Einstiegsgruppe der Sidebar,
 * mit Schnellaktion für einen neuen Artikel. Fällt die Zielgruppe weg,
 * erscheint das Fragment als eigene Gruppe ohne Überschrift.
 */

return [
    [
        'merge_into' => 'start',
        'id'         => 'lexicon',
        'label'      => null,
        'items'      => [
            [
                'label'        => 'Lexikon',
                'href'         => BASE_PATH . 'lexicon/index',
                'icon'         => 'fa-solid fa-book-medical',
                'match'        => ['/lexicon'],
                'quick_action' => [
                    'type'   => 'link',
                    'target' => BASE_PATH . 'lexicon/create',
                    'label'  => 'Neuen Artikel schreiben',
                ],
            ],
        ],
    ],
];
