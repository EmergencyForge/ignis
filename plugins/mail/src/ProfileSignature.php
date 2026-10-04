<?php

declare(strict_types=1);

namespace Plugin\Mail;

use App\Models\Personnel;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Signatur aus dem Mitarbeiterprofil, wie unter einem Dokument: Name,
 * Dienstgrad, Position, Fachdienste und die Organisation, je eine Zeile.
 * Leere Angaben fallen weg. Sie gilt nur, solange weder eine eigene noch
 * eine Standard-Signatur gespeichert ist, und wird bei jedem Verfassen neu
 * gebaut, folgt also Beförderungen und Wechseln von selbst.
 */
final class ProfileSignature
{
    /** @return array<string,mixed>|null Editor-JSON, null ohne Mitarbeiter */
    public static function for(?Personnel $person): ?array
    {
        if ($person === null || trim((string) $person->fullname) === '') {
            return null;
        }

        $lines = [
            $person->dienstgradModel?->displayName((int) $person->geschlecht),
            $person->zusatz,
            implode(', ', self::fachdienste($person)),
            self::organisation(),
        ];

        $content = [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => trim((string) $person->fullname), 'marks' => [['type' => 'bold']]]]]];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $content[] = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $line]]];
            }
        }

        return ['type' => 'doc', 'content' => $content];
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
