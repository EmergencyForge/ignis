<?php

return [
    'id'              => 'logbook',
    'name'            => 'Fahrtenbuch',
    'version'         => '1.0.0',
    'vendor'          => 'EmergencyForge',
    'requires'        => ['ignis' => '>=2026.0.6-beta'],
    'depends'         => [],
    'permissions'     => ['logbook.view', 'logbook.manage'],
    'autoload'        => ['Plugin\\Logbook\\' => 'src/'],
    'policies'        => ['logbook' => 'Plugin\\Logbook\\Policies\\LogbookPolicy'],
    'default_enabled' => true,
    'removable'       => true,
];
