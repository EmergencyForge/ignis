<?php

return [
    'id'              => 'knowledge-base',
    'name'            => 'Wissensdatenbank',
    'version'         => '1.0.0',
    'vendor'          => 'EmergencyForge',
    'requires'        => ['ignis' => '>=1.1'],
    'depends'         => [],
    'permissions'     => ['kb.view', 'kb.edit', 'kb.archive'],
    'autoload'        => ['Plugin\\KnowledgeBase\\' => 'src/'],
    'policies'        => ['knowledgebase' => 'Plugin\\KnowledgeBase\\Policies\\KnowledgebasePolicy'],
    'default_enabled' => true,
    'removable'       => true,
];
