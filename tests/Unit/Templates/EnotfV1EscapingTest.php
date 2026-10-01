<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * eNOTF v1 gibt Werte aus der Datenbank und aus dem Request nur escaped aus.
 *
 * Geprüft wird jede Ausgabe (`<?=`, echo, print, $SITE_TITLE) in den
 * v1-Templates und -Helfern: kommt darin $row (auch $log_row usw.), $daten,
 * $enr, $_GET oder $_POST vor, muss der Wert durch e(), htmlspecialchars(),
 * rawurlencode() oder EnotfUrl laufen, im Script durch json_encode() mit
 * JSON_HEX_TAG, als Zahl gecastet sein oder nur in einer Bedingung stehen
 * (`$daten['x'] == 1 ? 'checked' : ''`, Index in eine Label-Tabelle).
 * e() ist der Helfer aus illuminate/support.
 *
 * Werte, die über eine andere Variable laufen, sieht der Test nicht; die
 * Regel gilt für sie trotzdem.
 */
final class EnotfV1EscapingTest extends TestCase
{
    private const DIRS = ['plugins/enotf/templates', 'assets/components/enotf', 'assets/functions/enotf'];

    private const RAW = '(?:\w*row|daten|enr|_GET|_POST)\b';

    /** Datei => Ausdruck => Begründung */
    private const ALLOWED = [
        'plugins/enotf/templates/enotf/protokoll/massnahmen/medikamente/save_medikament.php' => [
            '"Eintrag mit ENR $enr nicht gefunden"' => 'Antwort ist text/plain, kein HTML.',
        ],
        'plugins/enotf/templates/enotf/admin/qm-log-modal.php' => [
            'preg_match(\'~^<span class="(?:ignis-chip(?: ignis-chip--\w+)?|badge(?: (?:text-)?bg-\w+)?)"(?: style="line-height: var\(--bs-body-line-height\); border-radius: 0;")?>[\p{L} ]+</span>$~uD\', (string) $log_row[\'kommentar\']) === 1 ? $log_row[\'kommentar\'] : e($log_row[\'kommentar\'])'
                => 'Statusänderungen speichert qm-actions-modal.php als festen Status-Chip, ältere Einträge als Bootstrap-Badge; nur diese Muster bleiben Markup.',
        ],
    ];

    public function testV1OutputIsEscaped(): void
    {
        $root = dirname(__DIR__, 3);
        $hits = [];
        foreach (self::DIRS as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                foreach (self::violations((string) file_get_contents($file->getPathname())) as [$line, $expr]) {
                    if (!isset(self::ALLOWED[$path][$expr])) {
                        $hits[] = "$path:$line  $expr";
                    }
                }
            }
        }

        self::assertSame([], $hits, 'Ausgabe escapen: e() im HTML, rawurlencode() in URLs, json_encode() mit JSON_HEX_* in JavaScript.');
    }

    public function testTheScannerFlagsWhatItShould(): void
    {
        $src = implode("\n", [
            '<?= e($row["name"]) ?>',
            '<input value="<?= $daten["patname"] ?>">',
            '<?= $daten["x"] == 1 ? "checked" : "" ?>',
            '<?= $labels[$daten["x"] ?? ""] ?? "" ?>',
            '<a href="<?= EnotfUrl::protokoll($enr) ?>?x=<?= rawurlencode($_GET["x"]) ?>">',
            '<?= !empty($daten["x"]) ? $daten["x"] : "-" ?>',
            '<?php echo "<td>" . $row["text"] . "</td>"; ?>',
            "<script>const enr = '<?= \$enr ?>';</script>",
            '<?= (int) $daten["id"] ?> <?= json_encode($enr, JSON_HEX_TAG) ?>',
            '<?= isset($_GET["view"]) && $_GET["view"] == 1 ? "active" : "" ?>',
            '<?php echo "<td>{$row[\'patname\']}</td>"; ?>',
            '<?= ($daten["x"] ?? "") == 3 ? "checked" : "" ?> <?= EnotfUrl::print($enr) ?>',
            '<?php $SITE_TITLE = "[#" . $daten["enr"] . "] &rsaquo; eNOTF"; ?>',
            '<?php $SITE_TITLE = "[#" . e($daten["enr"]) . "] &rsaquo; eNOTF"; $titel = $daten["enr"]; ?>',
            '<script>var d = <?= json_encode($daten) ?>;</script> <button onclick="go(<?= e(json_encode($enr)) ?>)">',
        ]);

        self::assertSame([2, 6, 7, 8, 11, 13, 15], array_column(self::violations($src), 0));
    }

    /** @return list<array{int, string}> Zeile und Ausdruck jeder ungeschützten Ausgabe */
    private static function violations(string $src): array
    {
        // TOKEN_PARSE, sonst wird EnotfUrl::print( zu einem print-Token.
        $tokens = token_get_all($src, TOKEN_PARSE);
        $found = [];
        foreach ($tokens as $i => $token) {
            if (!is_array($token)) {
                continue;
            }
            $start = $i;
            // _head.php gibt $SITE_TITLE ungefiltert aus, die Zuweisung zählt als Ausgabe.
            if ($token[0] === T_VARIABLE && $token[1] === '$SITE_TITLE') {
                do {
                    $start++;
                } while (is_array($tokens[$start] ?? null) && $tokens[$start][0] === T_WHITESPACE);
                if (($tokens[$start] ?? null) !== '=') {
                    continue;
                }
            } elseif (!in_array($token[0], [T_OPEN_TAG_WITH_ECHO, T_ECHO, T_PRINT], true)) {
                continue;
            }

            // Ausdruck bis zum Semikolon oder Tag-Ende; Stringliterale werden
            // zu S, damit Text in Anführungszeichen nicht als Variable zählt.
            $expr = '';
            $depth = 0;
            for ($j = $start + 1; isset($tokens[$j]); $j++) {
                $t = $tokens[$j];
                if (is_array($t)) {
                    if ($t[0] === T_CLOSE_TAG && $depth === 0) {
                        break;
                    }
                    $expr .= $t[0] === T_CONSTANT_ENCAPSED_STRING ? 'S' : $t[1];
                    continue;
                }
                if ($t === ';' && $depth === 0) {
                    break;
                }
                $depth += match ($t) {
                    '(', '[' => 1,
                    ')', ']' => -1,
                    default  => 0,
                };
                $expr .= $t;
            }

            if (self::isRaw($expr)) {
                $found[] = [$token[2], self::original($tokens, $start)];
            }
        }

        return $found;
    }

    private static function isRaw(string $expr): bool
    {
        $raw = self::RAW;
        // Escaping, Zahlen und reine Prüfungen
        $expr = (string) preg_replace('~(?:\b(?:e|htmlspecialchars|rawurlencode|urlencode|intval|floatval|count|isset|empty|in_array|date|strtotime|number_format)|EnotfUrl::\w+)\s*(\((?:[^()]++|(?1))*\))~', 'X', $expr);
        // json_encode() steht ohne e() im <script>, dort braucht es die HEX-Flags.
        $expr = (string) preg_replace_callback(
            '~\bjson_encode\s*(\((?:[^()]++|(?1))*\))~',
            static fn (array $m): string => str_contains($m[1], 'JSON_HEX_TAG') ? 'X' : $m[0],
            $expr,
        );
        $patterns = [
            '~\((?:int|float|bool)\)\s*\$\w+(?:\[[^\]]*\])*~',
            // Index in eine andere Tabelle: $labels[$daten['x'] ?? '']
            '~\$(?!' . $raw . ')\w+(\[(?:[^\[\]]++|(?1))*\])+~',
            // Vergleiche liefern nur true/false
            '~\(\s*\$' . $raw . '(?:\[[^\]]*\])*\s*\?\?\s*S\s*\)\s*(?:===?|!==?|<=|>=|<>|<|>)~',
            '~\$' . $raw . '(?:\[[^\]]*\])*\s*(?:===?|!==?|<=|>=|<>|<|>)~',
            '~(?:===?|!==?|<=|>=|<>|<|>)\s*\$' . $raw . '(?:\[[^\]]*\])*~',
        ];
        foreach ($patterns as $pattern) {
            $expr = (string) preg_replace($pattern, 'X', $expr);
        }

        return preg_match('~\$' . $raw . '~', $expr) === 1;
    }

    /**
     * Der Ausdruck im Originaltext, für Meldung und Allowlist.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function original(array $tokens, int $start): string
    {
        $text = '';
        $depth = 0;
        for ($j = $start + 1; isset($tokens[$j]); $j++) {
            $t = $tokens[$j];
            if (is_array($t) && $t[0] === T_CLOSE_TAG && $depth === 0) {
                break;
            }
            if ($t === ';' && $depth === 0) {
                break;
            }
            if (in_array($t, ['(', '['], true)) {
                $depth++;
            } elseif (in_array($t, [')', ']'], true)) {
                $depth--;
            }
            $text .= is_array($t) ? $t[1] : $t;
        }

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
