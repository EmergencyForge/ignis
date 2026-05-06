<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Exceptions\ValidationException;
use App\Models\FormField;
use Respect\Validation\Exceptions\ValidationException as RespectValidationException;
use Respect\Validation\Validator as v;

/**
 * Dynamischer Validator für Antragsformulare.
 *
 * Ein Antragstyp definiert seine Felder via `intra_antrag_felder`, Shape
 * und Typen sind also erst zur Laufzeit bekannt. Deshalb bauen wir die
 * Regel-Map aus den geladenen FormField-Models und prüfen das POST-
 * Array dagegen.
 *
 * Verhalten:
 *   - `readonly`-Felder werden übersprungen (Server füllt via auto_fill /
 *     standardwert; ein per Browser-DevTools geänderter POST-Wert darf
 *     NIE ins `intra_antraege_daten`-Record landen).
 *   - `pflichtfeld`-Felder müssen vorhanden und nicht-leer sein.
 *   - Typ-Validierung je nach `feldtyp` (email, date, time, number,
 *     select, checkbox, ...).
 *   - Nur deklarierte Feldnamen werden durchgereicht → Mass-Assignment-
 *     sicher.
 */
final class AntragFieldValidator
{
    /** @var int Max-Länge für freie Text-/Textarea-Felder */
    private const MAX_TEXT_LENGTH     = 1000;
    private const MAX_TEXTAREA_LENGTH = 10000;
    private const MAX_EMAIL_LENGTH    = 255;
    private const MAX_TEL_LENGTH      = 50;

    /**
     * @param  iterable<FormField> $felder
     * @param  array<string,mixed>   $input  Typischerweise `$_POST`
     * @return array<string,string>  Map Feldname → gereinigter String
     * @throws ValidationException
     */
    public static function validate(iterable $felder, array $input): array
    {
        $errors    = [];
        $validated = [];

        foreach ($felder as $feld) {
            if ((bool) $feld->readonly) {
                continue;
            }

            $name  = (string) $feld->feldname;
            $label = (string) ($feld->label ?: $name);
            $raw   = $input[$name] ?? null;
            $value = is_scalar($raw) ? trim((string) $raw) : '';

            // Pflichtfeld-Check
            if ((bool) $feld->pflichtfeld && $value === '') {
                $errors[$name] = sprintf('%s ist ein Pflichtfeld.', $label);
                continue;
            }

            // Optional + leer: akzeptieren ohne Typ-Check
            if ($value === '') {
                $validated[$name] = '';
                continue;
            }

            try {
                self::validatorFor($feld)->setName($label)->assert($value);
                $validated[$name] = $value;
            } catch (RespectValidationException $e) {
                $errors[$name] = sprintf('%s: ungültiger Wert.', $label);
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return $validated;
    }

    private static function validatorFor(FormField $feld): v
    {
        return match ($feld->feldtyp) {
            'email'    => v::stringType()->email()->length(1, self::MAX_EMAIL_LENGTH),
            'date'     => v::stringType()->date('Y-m-d'),
            'time'     => v::stringType()->regex('/^\d{2}:\d{2}(:\d{2})?$/'),
            'number'   => v::numericVal(),
            'tel'      => v::stringType()->regex('/^[\d\s+\-()\/]+$/')->length(1, self::MAX_TEL_LENGTH),
            'checkbox' => v::in(['0', '1', 'on', 'off', 'true', 'false'], true),
            'select'   => self::selectRule($feld),
            'textarea' => v::stringType()->length(0, self::MAX_TEXTAREA_LENGTH),
            default    => v::stringType()->length(0, self::MAX_TEXT_LENGTH),
        };
    }

    private static function selectRule(FormField $feld): v
    {
        $options = $feld->selectOptions();
        if ($options === []) {
            return v::stringType()->length(0, self::MAX_TEXT_LENGTH);
        }
        return v::in($options, true);
    }
}
