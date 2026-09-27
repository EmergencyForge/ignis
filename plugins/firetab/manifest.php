<?php

return [
    'id'              => 'firetab',
    'name'            => 'fireTab',
    'version'         => '1.1.0',
    'vendor'          => 'EmergencyForge',
    'requires'        => ['ignis' => '>=2026.0.6-beta'],
    'depends'         => [],
    'permissions'     => ['fire.incident.qm'],
    'autoload'        => ['Plugin\\Firetab\\' => 'src/'],
    'policies'        => ['fireIncident' => 'Plugin\\Firetab\\Policies\\FireIncidentPolicy'],
    'search'          => ['Plugin\\Firetab\\Search\\IncidentSource'],
    'notifications'   => ['Plugin\\Firetab\\Notifications\\FireProtocolType'],
    'default_enabled' => true,
    'removable'       => true,
];
