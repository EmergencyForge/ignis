<?php

/**
 * Wissensdatenbank — Rail-Eintrag für die Sidebar.
 */

return [
    [
        'id'           => 'lexicon',
        'label'        => 'Lexikon',
        'icon'         => 'fa-solid fa-book-medical',
        'href'         => BASE_PATH . 'lexicon/index',
        'data_page'    => 'lexicon',
        'quick_action' => [
            'type'   => 'link',
            'target' => BASE_PATH . 'lexicon/create',
            'label'  => 'Neuen Artikel schreiben',
        ],
    ],
];
