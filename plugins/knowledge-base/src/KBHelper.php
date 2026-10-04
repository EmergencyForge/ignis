<?php

namespace Plugin\KnowledgeBase;

/**
 * Helper class for Knowledge Base functionality
 */
class KBHelper
{
    /**
     * Get competency level information including colors and labels
     * 
     * @param string|null $level The competency level key
     * @return array<string, string>|null Competency information or null if not found
     */
    public static function getCompetencyInfo(?string $level): ?array
    {
        if ($level === null) {
            return null;
        }

        $competencies = [
            'basis' => [
                'label' => 'Basis',
                'color' => '#767171',
                'bg' => '#767171',
                'text' => '#ffffff',
                'desc' => 'Basismaßnahmen; durch jedes Rettungsdienstpersonal ausführbar'
            ],
            'rettsan' => [
                'label' => 'RettSan',
                'color' => '#00b0f0',
                'bg' => '#00b0f0',
                'text' => '#ffffff',
                'desc' => 'Durchführung durch RettSan bei Hinzuziehung/Nachsicht eines Arztes'
            ],
            'notsan_2c' => [
                'label' => 'NFS 2c',
                'color' => '#00b050',
                'bg' => '#00b050',
                'text' => '#ffffff',
                'desc' => 'Eigenständige Durchführung durch NotSan im Rahmen § 4 Abs. 2c NotSanG'
            ],
            'notsan_2a' => [
                'label' => 'NFS 2a',
                'color' => '#ffc000',
                'bg' => '#ffc000',
                'text' => '#000000',
                'desc' => 'Eigenverantwortliche Durchführung durch NotSan im Rahmen § 2a NotSanG'
            ],
            'notarzt' => [
                'label' => 'Notarzt',
                'color' => '#c00000',
                'bg' => '#c00000',
                'text' => '#ffffff',
                'desc' => 'Durchführung nur durch Notärzte vorgesehen'
            ]
        ];

        return $competencies[$level] ?? null;
    }

    /**
     * Get entry type label in German
     * 
     * @param string $type The entry type
     * @return string The localized label
     */
    public static function getTypeLabel(string $type): string
    {
        $types = [
            'general' => 'Allgemein',
            'medication' => 'Medikament',
            'measure' => 'Maßnahme'
        ];
        return $types[$type] ?? $type;
    }

    /**
     * Get type badge color
     * 
     * @param string $type The entry type
     * @return string CSS color value
     */
    public static function getTypeColor(string $type): string
    {
        $colors = [
            'general' => '#6c757d',     // secondary gray
            'medication' => '#17a2b8',  // info teal
            'measure' => '#28a745'      // success green
        ];
        return $colors[$type] ?? '#6c757d';
    }

    /**
     * Check if competency label color needs dark text
     * 
     * @param string|null $level The competency level key
     * @return bool True if dark text should be used
     */
    public static function competencyNeedsDarkText(?string $level): bool
    {
        // Only NFS 2a (yellow/orange) needs dark text
        return $level === 'notsan_2a';
    }

    /**
     * Create a text snippet from HTML content around the first match of a search query
     *
     * @param string|null $html The HTML content
     * @param string $query The search query
     * @param int $snippetLength Max character length of the snippet
     * @return string|null Plain text snippet or null if no match found
     */
    public static function createSearchSnippet(?string $html, string $query, int $snippetLength = 200): ?string
    {
        if ($html === null || $html === '' || $query === '') {
            return null;
        }

        // Strip HTML tags to get plain text
        $text = strip_tags($html);
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));

        if ($text === '') {
            return null;
        }

        // Find position of first match (case-insensitive)
        $words = preg_split('/\s+/', $query);
        $pos = false;
        foreach ($words as $word) {
            if (mb_strlen($word) < 2) {
                continue;
            }
            $pos = mb_stripos($text, $word);
            if ($pos !== false) {
                break;
            }
        }

        if ($pos === false) {
            // No match in content, return beginning
            if (mb_strlen($text) <= $snippetLength) {
                return $text;
            }
            return mb_substr($text, 0, $snippetLength) . '...';
        }

        // Calculate window around the match
        $halfLen = (int)($snippetLength / 2);
        $start = max(0, $pos - $halfLen);
        $end = min(mb_strlen($text), $start + $snippetLength);

        // Adjust start if we're near the end
        if ($end - $start < $snippetLength && $start > 0) {
            $start = max(0, $end - $snippetLength);
        }

        $snippet = mb_substr($text, $start, $end - $start);

        // Add ellipsis
        if ($start > 0) {
            $snippet = '...' . $snippet;
        }
        if ($end < mb_strlen($text)) {
            $snippet .= '...';
        }

        return $snippet;
    }

    /**
     * Highlight search terms in text with <mark> tags
     *
     * @param string $text The text to highlight in
     * @param string $query The search query (space-separated terms)
     * @return string Text with highlighted matches
     */
    public static function highlightSearchTerms(string $text, string $query): string
    {
        if ($query === '') {
            return $text;
        }

        $words = preg_split('/\s+/', $query);
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word) < 2) {
                continue;
            }
            // Escape regex special chars
            $escaped = preg_quote($word, '/');
            $text = preg_replace('/(' . $escaped . ')/iu', '<mark>$1</mark>', $text);
        }

        return $text;
    }

    /**
     * Elemente, die der CKEditor der Wissensdatenbank erzeugt: Überschriften
     * 1 bis 3 der Toolbar landen als h2 bis h4, Tabellen in figure.table.
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'h4',
        'ul', 'ol', 'li', 'blockquote', 'a',
        'figure', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    /** Elemente, die samt Inhalt entfallen. Alle anderen werden entpackt. */
    private const DROPPED_TAGS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
        'noscript', 'noembed', 'noframes', 'template', 'svg', 'math', 'textarea',
        'select', 'title', 'xmp',
    ];

    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * Bereinigt Editor-HTML über eine Allowlist. Das HTML wird mit libxml
     * geparst und aus dem Baum neu geschrieben: nur erlaubte Elemente mit
     * ihren wenigen erlaubten Attributen, aller Text escaped. Läuft beim
     * Speichern und bei jeder Ausgabe, damit auch Altbestände sauber sind.
     *
     * @param string|null $content Editor-HTML
     * @return string Bereinigtes HTML
     */
    public static function sanitizeContent(?string $content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        // Alles außerhalb von ASCII als numerische Entity: libxml muss dann
        // keine Kodierung raten, und ein meta charset im Inhalt ändert nichts.
        $ascii = mb_encode_numericentity(
            str_replace("\0", '', mb_scrub($content, 'UTF-8')),
            [0x80, 0x10FFFF, 0, 0x1FFFFF],
            'UTF-8'
        );

        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML(
            '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'
                . $ascii . '</body></html>',
            LIBXML_NONET | LIBXML_COMPACT
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // Ab dem Dokument selbst, denn Text hinter einem </html> im Inhalt
        // hängt libxml in ein zweites html-Element neben dem ersten.
        return $loaded ? self::sanitizeChildren($doc) : '';
    }

    private static function sanitizeChildren(\DOMNode $parent): string
    {
        $html = '';
        foreach ($parent->childNodes as $node) {
            if ($node instanceof \DOMText) {
                $text = htmlspecialchars($node->data, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
                // CKEditor schreibt geschützte Leerzeichen als Entity, so bleibt es beim Speichern gleich
                $html .= str_replace("\u{A0}", '&nbsp;', $text);
                continue;
            }
            // Kommentare, Processing Instructions usw. entfallen
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DROPPED_TAGS, true)) {
                continue;
            }
            $attributes = in_array($tag, self::ALLOWED_TAGS, true) ? self::allowedAttributes($tag, $node) : null;
            if ($attributes === null) {
                $html .= self::sanitizeChildren($node);
                continue;
            }

            $html .= '<' . $tag . $attributes . '>';
            if ($tag !== 'br') {
                $html .= self::sanitizeChildren($node) . '</' . $tag . '>';
            }
        }

        return $html;
    }

    /**
     * Erlaubte Attribute eines Elements als fertiger HTML-Schnipsel.
     * null heißt: Element entpacken (Link ohne brauchbares Ziel).
     */
    private static function allowedAttributes(string $tag, \DOMElement $node): ?string
    {
        switch ($tag) {
            case 'a':
                $href = self::safeHref($node->getAttribute('href'));
                if ($href === null) {
                    return null;
                }
                $html = ' href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
                if (strtolower(trim($node->getAttribute('target'))) === '_blank') {
                    $html .= ' target="_blank" rel="noopener noreferrer"';
                }
                return $html;

            case 'td':
            case 'th':
                $html = '';
                foreach (['colspan', 'rowspan'] as $name) {
                    $value = trim($node->getAttribute($name));
                    if (preg_match('/^[1-9][0-9]{0,2}$/', $value) === 1) {
                        $html .= ' ' . $name . '="' . $value . '"';
                    }
                }
                return $html;

            case 'figure':
                return $node->getAttribute('class') === 'table' ? ' class="table"' : '';

            default:
                return '';
        }
    }

    /**
     * Linkziel prüfen. Der Wert ist vom Parser schon entity-dekodiert.
     * Steuerzeichen fallen weg, weil Browser sie in URLs ignorieren
     * ("java\tscript:"). Mit Schema nur http, https und mailto; ohne
     * Schema ist es ein relativer Link und damit unkritisch.
     */
    private static function safeHref(string $href): ?string
    {
        $href = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', '', $href), ' ');
        if ($href === '') {
            return null;
        }
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $href, $match) === 1
            && !in_array(strtolower($match[1]), self::ALLOWED_SCHEMES, true)) {
            return null;
        }

        return $href;
    }
}
