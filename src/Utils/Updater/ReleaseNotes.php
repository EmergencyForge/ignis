<?php

declare(strict_types=1);

namespace App\Utils\Updater;

/**
 * Macht aus dem Markdown eines GitHub-Releases schlichtes HTML: Überschriften,
 * Listen, Fettdruck, Absätze. Alles andere wird escaped.
 */
final class ReleaseNotes
{
    public static function toHtml(string $markdown): string
    {
        $lines = explode("\n", $markdown);
        $output = '';
        $inList = false;

        foreach ($lines as $line) {
            $line = trim($line);

            // Headers
            if (preg_match('/^### (.+)$/', $line, $matches)) {
                if ($inList) {
                    $output .= '</ul>';
                    $inList = false;
                }
                $output .= '<h6>' . htmlspecialchars($matches[1]) . '</h6>';
            } elseif (preg_match('/^## (.+)$/', $line, $matches)) {
                if ($inList) {
                    $output .= '</ul>';
                    $inList = false;
                }
                $output .= '<h5>' . htmlspecialchars($matches[1]) . '</h5>';
            } elseif (preg_match('/^# (.+)$/', $line, $matches)) {
                if ($inList) {
                    $output .= '</ul>';
                    $inList = false;
                }
                $output .= '<h4>' . htmlspecialchars($matches[1]) . '</h4>';
            }
            // List items
            elseif (preg_match('/^[\*\-] (.+)$/', $line, $matches)) {
                if (!$inList) {
                    $output .= '<ul>';
                    $inList = true;
                }
                $output .= '<li>' . htmlspecialchars($matches[1]) . '</li>';
            }
            // Bold text
            elseif (preg_match('/\*\*(.+?)\*\*/', $line)) {
                if ($inList) {
                    $output .= '</ul>';
                    $inList = false;
                }
                // First escape entire line, then replace markdown markers with HTML
                $line = htmlspecialchars($line);
                $line = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $line);
                $output .= '<p>' . $line . '</p>';
            }
            // Regular text
            elseif (!empty($line)) {
                if ($inList) {
                    $output .= '</ul>';
                    $inList = false;
                }
                $output .= '<p>' . htmlspecialchars($line) . '</p>';
            }
        }

        if ($inList) {
            $output .= '</ul>';
        }

        return $output;
    }
}
