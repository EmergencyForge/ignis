<?php

declare(strict_types=1);

namespace App\Http\Requests\Antraege;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Ein Feld eines Antragstyps (`intra_antrag_felder`).
 *
 * `feldtyp` und `breite` sind in der Datenbank ENUMs. Bisher nahm der
 * Controller, was im Post stand: ein Wert außerhalb der Aufzählung ging je
 * nach SQL-Modus als leerer String durch oder ließ das Anlegen in einer
 * PDOException enden. Hier stehen dieselben Werte wie in der Migration.
 *
 * `feldname` ist der technische Name und landet als Schlüssel in den
 * Antragsdaten. Deshalb nur, was auch ein Bezeichner sein darf.
 */
class AddFormFieldRequest extends FormRequest
{
    /** @var array<int,string> Wie das ENUM in der Migration. */
    public const FELDTYPEN = ['text', 'textarea', 'number', 'date', 'select', 'checkbox', 'email', 'time', 'tel'];

    /** @var array<int,string> */
    public const BREITEN = ['full', 'half'];

    /** @var array<int,string> Wie die Auswahl im Formular. */
    public const AUTO_FILL = ['fullname_dienstnr', 'fullname', 'dienstnr', 'dienstgrad', 'discordtag'];

    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('feldname',    v::stringType()->notBlank()->length(1, 100)->regex('~^[a-z][a-z0-9_]*$~i')),
            v::key('label',       v::stringType()->notBlank()->length(1, 255)),
            v::key('feldtyp',     v::in(self::FELDTYPEN, true)),
            v::key('breite',      v::optional(v::in(self::BREITEN, true)), false),
            v::key('platzhalter', v::optional(v::stringType()->length(0, 255)), false),
            v::key('hinweistext', v::optional(v::stringType()->length(0, 2000)), false),
            v::key('optionen',    v::optional(v::stringType()->length(0, 5000)), false),
            v::key('auto_fill',   v::optional(v::in([...self::AUTO_FILL, ''], true)), false),
            v::key('pflichtfeld', v::optional(v::stringType()), false),
            v::key('readonly',    v::optional(v::stringType()), false),
            v::key('add_feld',    v::optional(v::stringType()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'notBlank' => 'Feldname und Beschriftung sind erforderlich.',
            'length'   => 'Ein Eintrag ist länger als {{maxValue}} Zeichen.',
            'regex'    => 'Der Feldname darf nur Buchstaben, Ziffern und Unterstriche enthalten und muss mit einem Buchstaben beginnen.',
            'in'       => 'Feldtyp, Breite oder Auto-Fill ist kein bekannter Wert.',
        ];
    }

    protected static function cast(array $input): array
    {
        $autoFill = trim((string) ($input['auto_fill'] ?? ''));

        return [
            'feldname'    => trim((string) $input['feldname']),
            'label'       => trim((string) $input['label']),
            'feldtyp'     => (string) $input['feldtyp'],
            'breite'      => (string) ($input['breite'] ?? 'full'),
            'platzhalter' => trim((string) ($input['platzhalter'] ?? '')),
            'hinweistext' => trim((string) ($input['hinweistext'] ?? '')),
            'optionen'    => trim((string) ($input['optionen'] ?? '')),
            'auto_fill'   => $autoFill === '' ? null : $autoFill,
            'pflichtfeld' => isset($input['pflichtfeld']) ? 1 : 0,
            'readonly'    => isset($input['readonly']) ? 1 : 0,
        ];
    }
}
