<?php

declare(strict_types=1);

namespace App\Http;

use EmergencyForge\Http\Exceptions\HttpResponseException;
use EmergencyForge\Http\Response;

/**
 * Bricht einen Controller mit der 403-Seite ab.
 *
 * Dasselbe Muster wie die Weiterleitung aus `Controller::redirect()`: der
 * Router fängt sie am Handler und macht daraus die Antwort, also sehen die
 * Haken des Routers sie und ein Feature-Test bekommt sie als Antwort statt
 * eines beendeten Prozesses.
 */
final class ForbiddenException extends HttpResponseException
{
    public function __construct(
        private readonly string $reason,
        private readonly ?string $backUrl = null,
        private readonly string $backLabel = 'Zurück zum Dashboard',
        private readonly ?string $path = null,
    ) {
        parent::__construct($reason, 403);
    }

    public function toResponse(): Response
    {
        return ErrorPage::forbidden($this->reason, $this->path, $this->backUrl, $this->backLabel);
    }
}
