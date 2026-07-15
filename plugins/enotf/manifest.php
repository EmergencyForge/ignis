<?php

return [
    'id'              => 'enotf',
    'name'            => 'eNOTF',
    'version'         => '1.0.0',
    'vendor'          => 'EmergencyForge',
    'requires'        => ['ignis' => '>=1.1'],
    'depends'         => [],
    'permissions'     => ['edivi.view', 'edivi.edit', 'enotf.view', 'pois.view', 'pois.manage'],
    'autoload'        => ['Plugin\\Enotf\\' => 'src/'],
    'policies'        => ['enotf' => 'Plugin\\Enotf\\Policies\\EnotfPolicy'],
    'default_enabled' => true,
    'removable'       => true,
];
