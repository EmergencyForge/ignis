<?php

return [
    'id'              => 'calendar',
    'name'            => 'Kalender',
    'version'         => '1.0.0',
    'vendor'          => 'EmergencyForge',
    'requires'        => ['ignis' => '>=2026.0.6-beta'],
    'depends'         => [],
    'permissions'     => ['calendar.view', 'calendar.create', 'calendar.manage'],
    'autoload'        => ['Plugin\\Calendar\\' => 'src/'],
    'policies'        => ['calendar' => 'Plugin\\Calendar\\Policies\\CalendarPolicy'],
    'notifications'   => ['Plugin\\Calendar\\Notifications\\CalendarNotificationType'],
    'default_enabled' => true,
    'removable'       => true,
];
