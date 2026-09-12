<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Fehlgeschlagener Datei-Upload. Der Status trennt die Faelle, die der
 * Aufrufer verschuldet (400), von denen, die am Server liegen (500).
 */
class UploadException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400)
    {
        parent::__construct($message);
    }
}
