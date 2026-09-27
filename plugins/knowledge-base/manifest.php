<?php

return [
    'id'              => 'knowledge-base',
    'name'            => 'Wissensdatenbank',
    'version'         => '1.1.0',
    'vendor'          => 'EmergencyForge',
    'requires'        => ['ignis' => '>=2026.0.6-beta'],
    'depends'         => [],
    'permissions'     => ['kb.view', 'kb.edit', 'kb.archive'],
    'autoload'        => ['Plugin\\KnowledgeBase\\' => 'src/'],
    'policies'        => ['knowledgebase' => 'Plugin\\KnowledgeBase\\Policies\\KnowledgebasePolicy'],
    'search'          => ['Plugin\\KnowledgeBase\\Search\\LexiconSource'],
    'default_enabled' => true,
    'removable'       => true,
];
