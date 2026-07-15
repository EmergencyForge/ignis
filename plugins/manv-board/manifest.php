<?php

return [
    'id'              => 'manv-board',
    'name'            => 'MANV-Board',
    'version'         => '1.0.0',
    'vendor'          => 'EmergencyForge',
    'requires'        => ['ignis' => '>=1.1'],
    'depends'         => [],
    'permissions'     => ['mci.manage'],
    'autoload'        => ['Plugin\\ManvBoard\\' => 'src/'],
    'policies'        => ['mci' => 'Plugin\\ManvBoard\\Policies\\MciPolicy'],
    'default_enabled' => true,
    'removable'       => true,
];
