<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validation für POST /api/personnel/update-profile.
 *
 * Das Profil-Editor-JSON enthält ~13 Felder — Pflichtfelder sind nur
 * `id`, `fullname` und `gebdatum`; alles andere kann leer bleiben
 * (Inline-Edit speichert einzelne Felder, die anderen sind dann leer).
 *
 * Nicht deklarierte Felder werden nicht durch die Validation durchgereicht
 * — aber der Controller liest weiter aus `$_POST` direkt, weil der Dienstnr-
 * Uniqueness-Check und das Change-Detection-Diff gegen das DB-Record-Current-
 * State DB-Zugriff brauchen und nicht sinnvoll in einen FormRequest passen.
 */
class UpdateProfileRequest extends FormRequest
{
    /** Dienstnr-Format: mindestens eine Ziffer, sonst nur Buchstaben/Ziffern/Bindestrich */
    private const DIENSTNR_PATTERN = '/^(?=.*[0-9])[A-Za-z0-9\-]+$/';

    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('id',          v::intVal()->positive()),
            v::key('fullname',    v::stringType()->notBlank()->length(1, 255)),
            v::key('gebdatum',    v::stringType()->date('Y-m-d')),
            v::key('dienstgrad',  v::optional(v::intVal()->min(0)), false),
            v::key('discordtag',  v::optional(v::stringType()->length(0, 255)), false),
            v::key('telefonnr',   v::optional(v::stringType()->length(0, 100)), false),
            v::key('dienstnr',    v::optional(v::oneOf(
                v::stringType()->equals(''),
                v::stringType()->regex(self::DIENSTNR_PATTERN)->length(1, 50)
            )), false),
            v::key('qualird',     v::optional(v::intVal()->min(0)), false),
            v::key('qualifw2',    v::optional(v::intVal()->min(0)), false),
            v::key('geschlecht',  v::optional(v::intVal()->in([0, 1, 2], true)), false),
            v::key('zusatzqual',  v::optional(v::stringType()->length(0, 500)), false),
            v::key('pfp',         v::optional(v::stringType()->length(0, 500)), false),
            v::key('charakterid', v::optional(v::stringType()->length(0, 100)), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'id'         => 'Mitarbeiter-ID muss eine positive Zahl sein.',
            'fullname'   => 'Name ist Pflichtfeld (1–255 Zeichen).',
            'gebdatum'   => 'Geburtsdatum muss im Format YYYY-MM-DD vorliegen.',
            'dienstgrad' => 'Dienstgrad muss eine positive Zahl oder 0 sein.',
            'discordtag' => 'Discord-Tag darf maximal 255 Zeichen haben.',
            'telefonnr'  => 'Telefonnummer darf maximal 100 Zeichen haben.',
            'dienstnr'   => 'Dienstnummer darf nur Buchstaben, Ziffern und Bindestriche enthalten und muss mindestens eine Ziffer haben.',
            'qualird'    => 'RD-Qualifikation muss eine positive Zahl oder 0 sein.',
            'qualifw2'   => 'FW-Qualifikation muss eine positive Zahl oder 0 sein.',
            'geschlecht' => 'Geschlecht muss 0, 1 oder 2 sein.',
            'zusatzqual' => 'Zusatzqualifikation darf maximal 500 Zeichen haben.',
            'pfp'        => 'Profilbild-Pfad darf maximal 500 Zeichen haben.',
            'charakterid' => 'Charakter-ID darf maximal 100 Zeichen haben.',
        ];
    }

    /**
     * Normalize: trim strings, cast numbers, default optionals.
     *
     * @param  array<string,mixed> $input
     * @return array{
     *   id:int,fullname:string,gebdatum:string,dienstgrad:int,discordtag:string,
     *   telefonnr:string,dienstnr:string,qualird:int,qualifw2:int,geschlecht:int,
     *   zusatzqual:string,pfp:string,charakterid:string
     * }
     */
    protected static function cast(array $input): array
    {
        return [
            'id'          => (int) $input['id'],
            'fullname'    => trim((string) $input['fullname']),
            'gebdatum'    => (string) $input['gebdatum'],
            'dienstgrad'  => (int) ($input['dienstgrad'] ?? 0),
            'discordtag'  => trim((string) ($input['discordtag'] ?? '')),
            'telefonnr'   => trim((string) ($input['telefonnr'] ?? '')),
            'dienstnr'    => trim((string) ($input['dienstnr'] ?? '')),
            'qualird'     => (int) ($input['qualird'] ?? 0),
            'qualifw2'    => (int) ($input['qualifw2'] ?? 0),
            'geschlecht'  => (int) ($input['geschlecht'] ?? 0),
            'zusatzqual'  => trim((string) ($input['zusatzqual'] ?? '')),
            'pfp'         => trim((string) ($input['pfp'] ?? '')),
            'charakterid' => trim((string) ($input['charakterid'] ?? '')),
        ];
    }
}
