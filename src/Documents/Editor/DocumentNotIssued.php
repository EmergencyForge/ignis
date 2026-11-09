<?php

declare(strict_types=1);

namespace App\Documents\Editor;

use RuntimeException;

/**
 * Das Dokument wurde nicht ausgestellt. Die Meldung ist für den Nutzer
 * geschrieben. `alreadyIssued` sagt, ob jemand anderes schneller war: dann
 * gibt es keinen Entwurf mehr, zu dem man zurückkehren könnte.
 */
final class DocumentNotIssued extends RuntimeException
{
    public function __construct(string $message, public readonly bool $alreadyIssued = false)
    {
        parent::__construct($message);
    }
}
