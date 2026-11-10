<?php

/**
 * Ausbildung: Permission-Katalog. Was jemand in einem Lehrgang darf,
 * hängt zusätzlich an seiner Rolle dort (Leitung, Ausbilder, Prüfer).
 */

declare(strict_types=1);

return [
    'Ausbildung' => [
        'training.view'   => 'Lehrgänge sehen, sich anmelden, eigene Unterlagen',
        'training.teach'  => 'Lehrgänge leiten und darin ausbilden',
        'training.exams'  => 'Prüfungen verwalten: Fragenpools und Prüfungsordnungen',
        'training.manage' => 'Lehrgangsarten und alle Lehrgänge verwalten',
    ],
];
