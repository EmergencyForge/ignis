<?php

return [
    'id'              => 'forms',
    'name'            => 'Anträge',
    'version'         => '1.0.0',
    'vendor'          => 'EmergencyForge',
    'requires'        => ['ignis' => '>=2026.0.6-beta'],
    'depends'         => [],
    'permissions'     => ['application.view', 'application.edit'],
    'autoload'        => ['Plugin\\Forms\\' => 'src/'],
    'policies'        => ['forms' => 'Plugin\\Forms\\Policies\\FormsPolicy'],
    'notifications'   => ['Plugin\\Forms\\Notifications\\ApplicationNotificationType'],
    'default_enabled' => true,
    'removable'       => true,
];
