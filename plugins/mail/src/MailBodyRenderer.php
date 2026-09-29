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

    /**
     * Klartext aus dem gerenderten HTML, für die Zeilen-Vorschau der Liste
     * und die Suche: Blockenden und <br> werden zu Leerzeichen („Hallo
     * Anna, anbei …“ statt „Hallo Anna,anbei …“), Entities zu Zeichen,
     * Leerraum zu einem Leerzeichen. Wie lex_html_text() in Lex.
     */
    public static function plainText(?string $html): string
    {
        $spaced = preg_replace('~<br\s*/?>|</(?:p|li|h[1-6]|blockquote|div|td|th|pre)>~i', ' ', (string) $html);
        $text   = html_entity_decode(strip_tags((string) $spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
