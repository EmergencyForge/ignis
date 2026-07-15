<?php

return [
    'id'              => 'firetab',
    'name'            => 'fireTab',
    'version'         => '1.0.0',
    'vendor'          => 'EmergencyForge',
    'requires'        => ['ignis' => '>=1.1'],
    'depends'         => [],
    'permissions'     => ['fire.incident.qm'],
    'autoload'        => ['Plugin\\Firetab\\' => 'src/'],
    'policies'        => ['fireIncident' => 'Plugin\\Firetab\\Policies\\FireIncidentPolicy'],
    'default_enabled' => true,
    'removable'       => true,
];
