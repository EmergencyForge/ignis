<?php

// Fixture für merge_after: ein Eintrag hinter „Erster", einer mit einem
// Anker, den es nicht gibt (landet am Ende).
return [
    [
        'merge_into'  => 'core',
        'merge_after' => 'Erster',
        'id'          => 'after',
        'label'       => 'After',
        'items'       => [['label' => 'Danach', 'href' => '/danach']],
    ],
    [
        'merge_into'  => 'core',
        'merge_after' => 'Gibt es nicht',
        'id'          => 'after-end',
        'label'       => 'After End',
        'items'       => [['label' => 'Ende', 'href' => '/ende']],
    ],
];
