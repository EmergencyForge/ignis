<?php

/**
 * Zähler „Ausbildung“: Anmeldungen, die auf die Bestätigung der Leitung
 * warten, Prüfungen, die man jetzt ablegen kann, und Versuche, die auf
 * einen als Prüfer warten.
 */

declare(strict_types=1);

use Plugin\Training\Courses;
use Plugin\Training\Policies\TrainingPolicy;

return [
    'training' => static fn (): ?int => (Courses::pendingConfirmations((int) ($_SESSION['userid'] ?? 0))
        + Courses::openExams()
        + (TrainingPolicy::teach() ? count(Courses::reviewQueue()) : 0)) ?: null,
];
