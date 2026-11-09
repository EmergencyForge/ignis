<?php

/**
 * Zähler „Ausbildung“: Anmeldungen, die auf die Bestätigung der Leitung
 * warten. Prüfungen und Bewertungen kommen dazu, sobald es sie gibt.
 */

declare(strict_types=1);

use Plugin\Training\Courses;

return [
    'training' => static fn (): ?int => Courses::pendingConfirmations((int) ($_SESSION['userid'] ?? 0)) ?: null,
];
