<?php

/**
 * eNOTF v2 — Neubau des Protokoll-Moduls auf dem v1-Datenbestand.
 *
 * Bewusst OHNE eigene Migrationen: v2 liest und schreibt dieselben
 * intra_edivi*- und intra_enotf_*-Tabellen wie das v1-Plugin. Das
 * v1-Plugin bleibt installiert und liefert weiterhin Schema, Events
 * (EnotfProtocolReleased), Permissions und den Crew-Session-Service —
 * daher `depends => ['enotf']`.
 *
 * Auch keine eigenen Permissions: v2 nutzt die von v1 registrierten
 * (edivi.view, edivi.edit, enotf.view, …), damit bestehende Rollen
 * ohne Anpassung für beide Versionen gelten.
 */

return [
    'id'              => 'enotf-v2',
    'name'            => 'eNOTF v2',
    'version'         => '0.1.0',
    'vendor'          => 'EmergencyForge',
    'requires'        => ['ignis' => '>=1.1'],
    'depends'         => ['enotf'],
    'permissions'     => [],
    'autoload'        => ['Plugin\\EnotfV2\\' => 'src/'],
    'policies'        => ['enotf-v2' => 'Plugin\\EnotfV2\\Policies\\EnotfV2Policy'],
    // Beta-Rollout: neue Installationen bekommen v2 deaktiviert, Admins
    // schalten es über die Plugin-Verwaltung frei. Bestehende Zeilen in
    // intra_plugins bleiben unberührt (syncDiscovered schreibt nur INSERTs).
    'default_enabled' => false,
    'removable'       => true,
];
