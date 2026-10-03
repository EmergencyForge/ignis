<?php

/**
 * Mail: der Eintrag in der Einstiegsgruppe mit Zähler der ungelesenen
 * Mails (counters.php) und „Neue Mail“ als Schnellaktion im Drawer. Die
 * Verwaltung (Verteiler, Postfächer, Einstellungen) erscheint als
 * Abschnitt auf /settings/index.
 */

return [
    [
        'merge_into' => 'start',
        'id'         => 'mail',
        'label'      => null,
        'items'      => [
            [
                'label'        => 'Mail',
                'href'         => BASE_PATH . 'mail',
                'icon'         => 'fa-solid fa-envelope',
                'permissions'  => ['admin', 'mail.use'],
                'match'        => ['/mail'],
                'counter'      => 'mail',
                'quick_action' => [
                    'type'   => 'drawer',
                    'target' => BASE_PATH . 'mail/compose',
                    'label'  => 'Neue Mail schreiben',
                ],
            ],
        ],
    ],
    [
        'id'        => 'mail-settings',
        'label'     => 'Mail',
        'placement' => 'settings',
        'items'     => [
            [
                'label'       => 'Verteiler',
                'href'        => BASE_PATH . 'mail/lists',
                'icon'        => 'fa-solid fa-people-group',
                'description' => 'Adressen, die an mehrere Postfächer zustellen: feste Listen oder Regeln.',
                'permissions' => ['admin', 'mail.lists.manage'],
                'match'       => ['/mail/lists'],
            ],
            [
                'label'       => 'Postfächer',
                'href'        => BASE_PATH . 'settings/mail/mailboxes',
                'icon'        => 'fa-solid fa-envelopes-bulk',
                'description' => 'Adressen korrigieren, Postfächer sperren, Domain wechseln. Ohne Einsicht in Mails.',
                'permissions' => ['admin', 'mail.admin'],
                'match'       => ['/settings/mail/mailboxes'],
            ],
            [
                'label'       => 'Mail-Einstellungen',
                'href'        => BASE_PATH . 'settings/mail',
                'icon'        => 'fa-solid fa-sliders',
                'description' => 'Domain, Adressmuster und Standard-Signatur.',
                'permissions' => ['admin', 'mail.admin'],
            ],
        ],
    ],
];
