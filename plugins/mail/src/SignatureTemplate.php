<?php

declare(strict_types=1);

namespace Plugin\Mail;

use App\Models\Personnel;
use EmergencyForge\Editor\Renderer;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Die Standard-Signatur als Vorlage mit Platzhaltern.
 *
 * Die Verwaltung schreibt sie unter Einstellungen › Mail im Editor; die
 * Platzhalter stehen darin als Chips (`{{absender.name}}`), wie in den
 * Dokumentvorlagen. Gespeichert wird Editor-JSON in
 * `MAIL_DEFAULT_SIGNATURE`. Leer heißt: es gilt die eingebaute Vorlage
 * (builtIn(), Name, Dienstgrad, Position, Fachdienste, Organisation).
 *
 * Beim Verfassen setzt resolve() die Angaben des Absenders ein. Eine Zeile
 * (Absatz oder Stück zwischen zwei Umbrüchen mit Umschalt+Enter), deren
 * Platzhalter alle leer bleiben und die sonst keinen Text hat, fällt weg,
 * damit keine leeren Zeilen entstehen. Weil das bei jedem
 * Verfassen neu passiert, folgt die Signatur Beförderungen und Wechseln
 * von selbst. Eine eigene Signatur des Postfachs geht immer vor; auch sie
 * darf Platzhalter tragen und wird genauso ausgefüllt. Ein Platzhalter mit
 * unbekanntem Namen bleibt dabei leer, speichern lässt er sich in der
 * Standard-Signatur gar nicht (parse()).
 *
 * Bei einem Gruppenpostfach ist der Absender das Mitglied, das gerade
 * schreibt; Mailadresse und Postfachname kommen vom Gruppenpostfach.
 */
final class SignatureTemplate
{
    /** Größe des JSON beim Speichern. */
    public const MAX_BYTES = 16384;

    private const MAX_DEPTH = 16;

    private const MARKS = ['bold', 'italic', 'strike', 'link'];

    /**
     * Schlüssel auf Beschriftung, in der Reihenfolge, in der der Editor sie
     * anbietet.
     *
     * @return array<string,string>
     */
    public static function catalog(): array
    {
        return [
            'absender.name'         => 'Name mit Titel',
            'absender.titel'        => 'Titel',
            'absender.dienstgrad'   => 'Dienstgrad mit Abzeichen',
            'absender.position'     => 'Position',
            'absender.fachdienste'  => 'Fachdienste',
            'absender.dienstnummer' => 'Dienstnummer',
            'absender.mailadresse'  => 'Mailadresse',
            'postfach.name'         => 'Postfach',
            'organisation'          => 'Organisation',
        ];
    }

    /**
     * Die eingebaute Vorlage, je Angabe ein Absatz, der Name fett.
     *
     * @return array<string,mixed>
     */
    public static function builtIn(): array
    {
        $line = static fn (string $key, bool $bold = false): array => ['type' => 'paragraph', 'content' => [
            ['type' => 'docVariable', 'attrs' => ['name' => $key]] + ($bold ? ['marks' => [['type' => 'bold']]] : []),
        ]];

        return ['type' => 'doc', 'content' => [
            $line('absender.name', true),
            $line('absender.dienstgrad'),
            $line('absender.position'),
            $line('absender.fachdienste'),
            $line('organisation'),
        ]];
    }

    /**
     * Gespeicherter Wert → Vorlage, `null` ohne gespeicherte Vorlage. Ein
     * Wert, der kein Editor-Dokument ist, stammt aus der Zeit, als die
     * Standard-Signatur Klartext war: jede Zeile wird ein Absatz.
     *
     * @return array<string,mixed>|null
     */
    public static function decode(?string $stored): ?array
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return null;
        }

        $doc = json_decode($stored, true);
        if (is_array($doc) && ($doc['type'] ?? null) === 'doc') {
            return $doc;
        }

        $content = [];
        foreach (preg_split('/\R/u', $stored) ?: [] as $line) {
            $line = trim($line);
            $content[] = $line === ''
                ? ['type' => 'paragraph']
                : ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $line]]];
        }

        return ['type' => 'doc', 'content' => $content];
    }

    /**
     * Prüft die Eingabe aus dem Formular (Editor-JSON) und baut sie neu auf:
     * nur Absätze mit Text, Zeilenumbrüchen und bekannten Platzhaltern,
     * Fett, Kursiv, Durchgestrichen und Links. Liefert das Dokument oder die
     * Meldung fürs Formular.
     *
     * @return array<string,mixed>|string
     */
    public static function parse(mixed $raw): array|string
    {
        if (!is_string($raw)) {
            return 'Ungültige Eingabe.';
        }
        if (strlen($raw) > self::MAX_BYTES) {
            return 'Die Signatur ist zu lang (höchstens ' . intdiv(self::MAX_BYTES, 1024) . ' KB).';
        }

        $doc = json_decode($raw, true, self::MAX_DEPTH);
        if (!is_array($doc) || ($doc['type'] ?? null) !== 'doc' || !is_array($doc['content'] ?? [])) {
            return 'Die Signatur ist kein gültiges Dokument.';
        }

        $content = [];
        foreach ($doc['content'] ?? [] as $node) {
            $paragraph = is_array($node) && ($node['type'] ?? null) === 'paragraph' ? self::paragraph($node) : null;
            if (!is_array($paragraph)) {
                return $paragraph ?? 'Die Signatur enthält etwas anderes als Absätze.';
            }
            $content[] = $paragraph;
        }

        return ['type' => 'doc', 'content' => $content];
    }

    /**
     * Was in `MAIL_DEFAULT_SIGNATURE` landet: leer, wenn die Vorlage nichts
     * enthält oder genau der eingebauten entspricht (dann folgt sie deren
     * künftigen Änderungen), sonst das JSON.
     *
     * @param array<string,mixed> $doc Ergebnis von parse()
     */
    public static function toStored(array $doc): string
    {
        if (!self::hasContent($doc) || $doc === self::parse((string) json_encode(self::builtIn()))) {
            return '';
        }

        return (string) json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Setzt die Angaben des Absenders in die Vorlage ein. Eine Zeile, die
     * Platzhalter hatte und danach keinen Text mehr, fällt weg, ein Absatz
     * ohne verbliebene Zeile ebenso.
     *
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    public static function resolve(array $template, ?Personnel $person, string $address, string $mailboxName = ''): array
    {
        $values  = self::values($person, $address, $mailboxName);
        $content = [];
        foreach ((array) ($template['content'] ?? []) as $node) {
            if (!is_array($node)) {
                continue;
            }
            $hadVariable = false;
            $node = self::fillLines($node, $values, $hadVariable);
            if ($hadVariable && !self::hasText($node)) {
                continue;
            }
            $content[] = $node;
        }

        return ['type' => 'doc', 'content' => $content];
    }

    /**
     * Die Angaben je Platzhalter als Inline-Knoten. Leer heißt: keine
     * Knoten. Der Dienstgrad bekommt sein Abzeichen davor, wenn es eines
     * gibt und der Editor-Renderer die Adresse zulässt (Pfad auf ignis,
     * keine fremde Adresse). Öffentlich für die Vorschau im Signatur-Editor.
     *
     * @return array<string,list<array<string,mixed>>>
     */
    public static function values(?Personnel $person, string $address, string $mailboxName = ''): array
    {
        $text = static function (mixed $value): array {
            $value = trim((string) $value);

            return $value === '' ? [] : [['type' => 'text', 'text' => $value]];
        };

        $values = [
            'absender.mailadresse' => $text($address),
            'postfach.name'        => $text($mailboxName),
            'organisation'         => $text(self::organisation()),
        ];
        if ($person === null) {
            return $values;
        }

        $rank  = $person->dienstgradModel;
        $badge = $rank?->badgeUrl();
        $rankText = $text($rank?->displayName((int) $person->geschlecht));
        $values['absender.dienstgrad'] = $rankText !== [] && $badge !== null && Renderer::isAllowedImageSrc($badge)
            ? [['type' => 'image', 'attrs' => ['src' => $badge, 'alt' => '']], ...$rankText]
            : $rankText;

        $values['absender.name']         = $text($person->formalName());
        $values['absender.titel']        = $text($person->titel?->name);
        $values['absender.position']     = $text($person->zusatz);
        $values['absender.fachdienste']  = $text(implode(', ', self::fachdienste($person)));
        $values['absender.dienstnummer'] = $text($person->dienstnr);

        return $values;
    }

    /**
     * Füllt einen Absatz Zeile für Zeile; Zeilen trennt ein Umbruch
     * (hardBreak, Umschalt+Enter). Eine Zeile, die Platzhalter hatte und
     * danach keinen Text, fällt samt ihrem Umbruch weg. Sonst bliebe eine
     * Leerzeile, wenn die Platzhalter in einem Absatz untereinander stehen.
     * Eine Leerzeile ohne Platzhalter ist gewollt und bleibt.
     *
     * @param array<string,mixed>                       $node
     * @param array<string,list<array<string,mixed>>>   $values
     * @return array<string,mixed>
     */
    private static function fillLines(array $node, array $values, bool &$hadVariable): array
    {
        if (($node['type'] ?? null) !== 'paragraph' || !is_array($node['content'] ?? null)) {
            return self::fill($node, $values, $hadVariable);
        }

        $lines = [[]];
        foreach ($node['content'] as $child) {
            if (is_array($child) && ($child['type'] ?? null) === 'hardBreak') {
                $lines[] = [];
                continue;
            }
            $lines[array_key_last($lines)][] = $child;
        }

        $content = [];
        $kept    = 0;
        foreach ($lines as $line) {
            $lineHadVariable = false;
            $filled = self::fill(['type' => 'paragraph', 'content' => $line], $values, $lineHadVariable)['content'];
            $hadVariable = $hadVariable || $lineHadVariable;
            if ($lineHadVariable && !self::hasText(['content' => $filled])) {
                continue;
            }
            if ($kept++ > 0) {
                $content[] = ['type' => 'hardBreak'];
            }
            array_push($content, ...$filled);
        }
        $node['content'] = $content;

        return $node;
    }

    /**
     * Ersetzt jeden Platzhalter durch seine Knoten. Markierungen des
     * Platzhalters (fett) gehen auf den Text über, nicht aufs Abzeichen.
     *
     * @param array<string,mixed>                       $node
     * @param array<string,list<array<string,mixed>>>   $values
     * @return array<string,mixed>
     */
    private static function fill(array $node, array $values, bool &$hadVariable): array
    {
        if (!is_array($node['content'] ?? null)) {
            return $node;
        }

        $children = [];
        foreach ($node['content'] as $child) {
            if (!is_array($child)) {
                continue;
            }
            if (($child['type'] ?? null) !== 'docVariable') {
                $children[] = self::fill($child, $values, $hadVariable);
                continue;
            }
            $hadVariable = true;
            foreach ($values[(string) ($child['attrs']['name'] ?? '')] ?? [] as $inline) {
                if ($inline['type'] === 'text' && isset($child['marks'])) {
                    $inline['marks'] = $child['marks'];
                }
                $children[] = $inline;
            }
        }
        $node['content'] = $children;

        return $node;
    }

    /**
     * Ein Absatz aus der Eingabe, neu aufgebaut. Ausrichtung und andere
     * Attribute fallen weg, die Signatur kennt sie nicht.
     *
     * @param array<string,mixed> $node
     * @return array<string,mixed>|string|null Meldung bei unerlaubtem Inhalt
     */
    private static function paragraph(array $node): array|string|null
    {
        if (!is_array($node['content'] ?? [])) {
            return null;
        }

        $content = [];
        foreach ($node['content'] ?? [] as $child) {
            $type = is_array($child) ? ($child['type'] ?? null) : null;
            $inline = match ($type) {
                'text'        => is_string($child['text'] ?? null) && $child['text'] !== '' ? ['type' => 'text', 'text' => $child['text']] : null,
                'hardBreak'   => ['type' => 'hardBreak'],
                'docVariable' => is_string($child['attrs']['name'] ?? null) && array_key_exists($child['attrs']['name'], self::catalog())
                    ? ['type' => 'docVariable', 'attrs' => ['name' => $child['attrs']['name']]]
                    : 'Unbekannter Platzhalter: ' . (is_string($child['attrs']['name'] ?? null) ? '{{' . $child['attrs']['name'] . '}}' : '?') . '.',
                default       => null,
            };
            if (!is_array($inline)) {
                return $inline ?? 'Die Signatur enthält nicht erlaubte Inhalte.';
            }

            $marks = self::marks($child['marks'] ?? []);
            if (is_string($marks)) {
                return $marks;
            }
            if ($marks !== [] && $type !== 'hardBreak') {
                $inline['marks'] = $marks;
            }
            $content[] = $inline;
        }

        return $content === [] ? ['type' => 'paragraph'] : ['type' => 'paragraph', 'content' => $content];
    }

    /** @return list<array<string,mixed>>|string */
    private static function marks(mixed $marks): array|string
    {
        if (!is_array($marks)) {
            return 'Die Signatur enthält nicht erlaubte Inhalte.';
        }

        $clean = [];
        foreach ($marks as $mark) {
            $type = is_array($mark) ? ($mark['type'] ?? null) : null;
            if (!in_array($type, self::MARKS, true)) {
                return 'Die Signatur enthält nicht erlaubte Formatierungen.';
            }
            // Den Link prüft der Renderer beim Senden (http, https, mailto).
            $clean[] = $type === 'link'
                ? ['type' => 'link', 'attrs' => ['href' => is_string($mark['attrs']['href'] ?? null) ? $mark['attrs']['href'] : '']]
                : ['type' => $type];
        }

        return $clean;
    }

    /** Text oder Platzhalter irgendwo im Dokument? */
    private static function hasContent(mixed $node): bool
    {
        if (!is_array($node)) {
            return false;
        }
        if (($node['type'] ?? null) === 'docVariable') {
            return true;
        }

        return self::hasText($node) || array_filter((array) ($node['content'] ?? []), self::hasContent(...)) !== [];
    }

    /** Enthält der Knoten irgendwo Text? Leerzeichen zählen nicht. */
    private static function hasText(mixed $node): bool
    {
        if (!is_array($node)) {
            return false;
        }
        if (($node['type'] ?? null) === 'text' && trim((string) ($node['text'] ?? '')) !== '') {
            return true;
        }

        return array_filter((array) ($node['content'] ?? []), self::hasText(...)) !== [];
    }

    /**
     * Namen der Fachdienste in der Reihenfolge ihrer Nummern, wie im Profil.
     * `fachdienste` ist eine JSON-Liste der Nummern, im Altbestand auch als
     * Zahlen oder gar kein JSON.
     *
     * @return list<string>
     */
    private static function fachdienste(Personnel $person): array
    {
        $numbers = json_decode((string) $person->fachdienste, true);
        $numbers = is_array($numbers) ? array_filter($numbers, static fn (mixed $n): bool => (is_string($n) || is_int($n)) && preg_match('/^\d{3}$/', (string) $n) === 1) : [];
        if ($numbers === []) {
            return [];
        }

        return array_values(array_map('strval', Capsule::table('intra_mitarbeiter_fdquali')
            ->whereIn('sgnr', array_map('intval', $numbers))
            ->where('disabled', 0)
            ->orderBy('sgnr')
            ->pluck('sgname')
            ->all()));
    }

    /** „Berufsfeuerwehr Musterstadt“ aus Art der Organisation und Stadt. */
    private static function organisation(): string
    {
        $parts = [];
        foreach (['RP_ORGTYPE', 'SERVER_CITY'] as $constant) {
            if (defined($constant)) {
                $parts[] = trim((string) constant($constant));
            }
        }

        return implode(' ', array_filter($parts, static fn (string $p): bool => $p !== ''));
    }
}
