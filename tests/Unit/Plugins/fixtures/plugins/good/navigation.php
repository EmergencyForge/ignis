<?php

// Bewusst im älteren Rail-Schema (Einzellink mit href, sections mit
// items): eNOTF liefert so, PluginLoader::mergeNavigation() muss es
// weiter lesen und Icon wie Permissions an die Einträge weitergeben.
return [
    [
        'id' => 'good',
        'label' => 'Good Plugin',
        'icon' => 'fa-solid fa-puzzle-piece',
        'href' => '/good',
    ],
    // Hängt seine Einträge an eine bestehende Gruppe; fällt auf eine
    // eigene Gruppe zurück, wenn das Ziel fehlt.
    [
        'merge_into' => 'core',
        'id' => 'good-extra',
        'label' => 'Good Extra',
        'icon' => 'fa-solid fa-puzzle-piece',
        'sections' => [
            ['label' => 'Good Tools', 'permissions' => ['good.view'], 'items' => [['label' => 'Tool', 'href' => '/good/tool']]],
        ],
    ],
];
