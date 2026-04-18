<?php

declare(strict_types=1);

namespace App\Http\Requests\Mitarbeiter;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validation für POST /mitarbeiter/profile.php mit `new=6` (Dokument anlegen).
 *
 * Pflichtfelder sind nur `profileid` und `docType`; alle anderen Felder
 * sind formular-spezifisch (je nach Dokumententyp sind andere Felder aktiv).
 *
 * Das `ausstellungsdatum_{N}`-Feld ist dynamisch — N leitet sich aus
 * `docType` ab. Wir akzeptieren hier alle bekannten Varianten (0–13)
 * plus die generische `ausstellungsdatum_0`-Fallback-Variante; der
 * Controller wählt den passenden Wert aus.
 *
 * Optionale Strings werden als String akzeptiert (inkl. leer). Die
 * Date-Normalisierung (`strtotime` → `Y-m-d`) bleibt absichtlich
 * tolerant — das Form rendert den Wert direkt aus `<input type="date">`
 * bzw. deutschen Datumsformaten.
 */
class CreateDocumentRequest extends FormRequest
{
    /** Bekannte Dokumenten-Typen (synchron zur Notification-Map im Controller). */
    private const KNOWN_DOC_TYPES = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13'];

    protected static function rules(): Validatable
    {
        $optionalString = v::optional(v::stringType()->length(0, 500));
        $optionalDate   = v::optional(v::stringType()->length(0, 50));

        return v::keySet(
            v::key('profileid', v::stringVal()->intVal()->positive()),
            v::key('docType',   v::stringVal()->in(self::KNOWN_DOC_TYPES, true)),
            v::key('new',       v::optional(v::stringType()), false),

            // Empfänger-Daten
            v::key('anrede',           $optionalString, false),
            v::key('erhalter',         $optionalString, false),
            v::key('erhalter_gebdat',  $optionalDate,   false),
            v::key('erhalter_rang',    $optionalString, false),
            v::key('erhalter_rang_rd', $optionalString, false),
            v::key('erhalter_quali',   $optionalString, false),

            // Inhalt + spezifische Felder
            v::key('inhalt',      v::optional(v::stringType()->length(0, 10000)), false),
            v::key('suspendtime', $optionalDate, false),

            // Aussteller-Daten
            v::key('ausstellerid',    $optionalString, false),
            v::key('aussteller_name', $optionalString, false),
            v::key('aussteller_rang', $optionalString, false),

            // Dynamic ausstellungsdatum — Legacy-Mapping: docType ∈ {10..13} → Suffix 10,
            // sonst Suffix = docType, plus Fallback-Feld mit Suffix 0.
            v::key('ausstellungsdatum_0',  $optionalDate, false),
            v::key('ausstellungsdatum_1',  $optionalDate, false),
            v::key('ausstellungsdatum_2',  $optionalDate, false),
            v::key('ausstellungsdatum_3',  $optionalDate, false),
            v::key('ausstellungsdatum_4',  $optionalDate, false),
            v::key('ausstellungsdatum_5',  $optionalDate, false),
            v::key('ausstellungsdatum_6',  $optionalDate, false),
            v::key('ausstellungsdatum_7',  $optionalDate, false),
            v::key('ausstellungsdatum_8',  $optionalDate, false),
            v::key('ausstellungsdatum_9',  $optionalDate, false),
            v::key('ausstellungsdatum_10', $optionalDate, false),
        );
    }

    protected static function messages(): array
    {
        return [
            'profileid' => 'Profil-ID ist Pflicht und muss eine positive Zahl sein.',
            'docType'   => 'Ungültiger Dokumententyp.',
            'inhalt'    => 'Inhalt darf maximal 10.000 Zeichen haben.',
        ];
    }

    protected static function cast(array $input): array
    {
        $docType   = (string) $input['docType'];
        $ausstDtNr = in_array($docType, ['10', '11', '12', '13'], true) ? '10' : $docType;
        $rawDate   = (string) ($input['ausstellungsdatum_' . $ausstDtNr] ?? $input['ausstellungsdatum_0'] ?? '');

        return [
            'profileid'         => (int) $input['profileid'],
            'docType'           => $docType,
            'anrede'            => self::nullableStr($input, 'anrede'),
            'erhalter'          => self::nullableStr($input, 'erhalter'),
            'inhalt'            => self::nullableStr($input, 'inhalt'),
            'suspendtime'       => self::nullableStr($input, 'suspendtime'),
            'erhalter_gebdat'   => self::nullableStr($input, 'erhalter_gebdat'),
            'erhalter_rang'     => self::nullableStr($input, 'erhalter_rang'),
            'erhalter_rang_rd'  => self::nullableStr($input, 'erhalter_rang_rd'),
            'erhalter_quali'    => self::nullableStr($input, 'erhalter_quali'),
            'ausstellerid'      => self::nullableStr($input, 'ausstellerid'),
            'aussteller_name'   => self::nullableStr($input, 'aussteller_name'),
            'aussteller_rang'   => self::nullableStr($input, 'aussteller_rang'),
            'ausstellungsdatum' => $rawDate !== '' ? date('Y-m-d', (int) strtotime($rawDate)) : date('Y-m-d'),
        ];
    }

    /**
     * @param  array<string,mixed> $input
     */
    private static function nullableStr(array $input, string $key): ?string
    {
        if (!isset($input[$key])) {
            return null;
        }
        $val = trim((string) $input[$key]);
        return $val === '' ? null : $val;
    }
}
