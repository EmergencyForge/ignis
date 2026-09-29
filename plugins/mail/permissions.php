<?php

/**
 * Mail — Permission-Katalog für die Rollen-Verwaltung. `mail.admin`
 * verwaltet Postfächer (Adresse, Sperre, Domain), liest aber keine
 * fremden Mails; das gibt es für niemanden.
 */

declare(strict_types=1);

return [
    'Mail' => [
        'mail.use'           => 'Mail nutzen (eigenes Postfach)',
        'mail.lists.manage'  => 'Verteiler verwalten',
        'mail.domain.choose' => 'Domain eines Postfachs wählen',
        'mail.admin'         => 'Postfächer verwalten (ohne Einsicht in Inhalte)',
    ],
];
