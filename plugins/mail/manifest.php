<?php

/**
 * Internes Mailmodul (ForgeBoard #129): Postfächer für Mitarbeiter,
 * Verfassen, Ordner, Verteiler. Keine Nachricht verlässt das System.
 * Die gemeinsame Logik (Adressen, Empfänger-Auflösung, Antworten) kommt
 * aus dem Paket emergencyforge/mail, dieses Plugin bringt Tabellen,
 * Oberfläche und die Anbindung an Mitarbeiter, Glocke und Suche.
 */

return [
    'id'              => 'mail',
    'name'            => 'Mail',
    'version'         => '0.1.0',
    'vendor'          => 'EmergencyForge',
    'requires'        => ['ignis' => '>=1.1'],
    'depends'         => [],
    'permissions'     => ['mail.use', 'mail.lists.manage', 'mail.domain.choose', 'mail.admin'],
    'autoload'        => ['Plugin\\Mail\\' => 'src/'],
    'policies'        => [],
    'search'          => [],
    'notifications'   => ['Plugin\\Mail\\Notifications\\MailType'],
    'default_enabled' => true,
    'removable'       => true,
];
