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
     * @return array|null Competency information or null if not found
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
     * Sanitize HTML content for safe output
     * Allows only safe HTML tags used by CKEditor
     * 
     * @param string|null $content The HTML content to sanitize
     * @return string Sanitized HTML
     */
    public static function sanitizeContent(?string $content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        // Define allowed tags that CKEditor uses
        $allowedTags = '<p><br><strong><b><em><i><u><s><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><a><table><thead><tbody><tr><th><td><span><div>';
        
        // Strip tags except allowed ones
        $sanitized = strip_tags($content, $allowedTags);
        
        // Remove potentially dangerous attributes
        // This is a basic sanitization - for production, consider using HTMLPurifier
        $sanitized = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $sanitized);
        $sanitized = preg_replace('/\s*javascript\s*:/i', '', $sanitized);
        $sanitized = preg_replace('/\s*data\s*:/i', '', $sanitized);
        $sanitized = preg_replace('/\s*vbscript\s*:/i', '', $sanitized);
        
        return $sanitized;
    }
}
