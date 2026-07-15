<?php

return [
    [
        'id' => 'good',
        'label' => 'Good Plugin',
        'icon' => 'fa-solid fa-puzzle-piece',
        'href' => '/good',
    ],
    // Hängt seine Section in einen bestehenden Rail-Eintrag ein; fällt auf
    // einen eigenen Eintrag zurück, wenn das Ziel fehlt.
    [
        'merge_into' => 'core',
        'id' => 'good-extra',
        'label' => 'Good Extra',
        'icon' => 'fa-solid fa-puzzle-piece',
        'sections' => [
            ['label' => 'Good Tools', 'items' => [['label' => 'Tool', 'href' => '/good/tool']]],
        ],
    ],
];
