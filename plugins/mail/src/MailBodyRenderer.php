<?php

declare(strict_types=1);

namespace Plugin\Mail;

use EmergencyForge\Editor\Renderer;
use EmergencyForge\Mail\BodyRenderer;

/**
 * Rendert den Mailtext über den Editor-Renderer (editor-php 0.3), immer
 * mit `allowLinks: true`: das Mailmodul ist der einzige Aufrufer in ignis,
 * der Links anzeigt. Die href-Whitelist (http, https, mailto) bleibt
 * dabei scharf, `javascript:` fällt weg.
 */
final class MailBodyRenderer implements BodyRenderer
{
    public function render(array $bodyJson): string
    {
        return (new Renderer())->render($bodyJson, allowLinks: true)->html;
    }
}
