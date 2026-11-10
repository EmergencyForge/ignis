<?php

/**
 * Ausbildung: Lehrgangsarten mit Lehrplan, Lehrgänge mit
 * Anmeldung und Warteliste, Unterlagen, Fortschritt, Termine und
 * Anwesenheit. Status, Fortschritt und Dateiprüfung kommen aus dem Paket
 * emergencyforge/training, das Plugin bringt Tabellen, Oberfläche und die
 * Anbindung an Mitarbeiter, Kalender und Benachrichtigungen.
 */

return [
    'id'              => 'training',
    'name'            => 'Ausbildung',
    'version'         => '0.3.0',
    'vendor'          => 'EmergencyForge',
    'requires'        => ['ignis' => '>=2026.0.6-beta'],
    'depends'         => [],
    'permissions'     => ['training.view', 'training.teach', 'training.exams', 'training.manage'],
    'autoload'        => ['Plugin\\Training\\' => 'src/'],
    'policies'        => ['training' => 'Plugin\\Training\\Policies\\TrainingPolicy'],
    'notifications'   => ['Plugin\\Training\\Notifications\\TrainingNotificationType'],
    'default_enabled' => true,
    'removable'       => true,
];
