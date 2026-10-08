<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Rules\Key;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Crew-Abmeldung (POST /enotf/loggedout), aus der Übersicht und von der
 * Bestätigungsseite. Ohne `mode` meldet der Controller das ganze Fahrzeug
 * ab.
 */
final class CrewLogoutRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        // new Key statt v::key(): die PHPStan-Ausnahme für v::key() in
        // keySet() gilt nur unter plugins/*/src/Requests.
        return v::keySet(
            new Key('mode',  v::optional(v::stringVal()), false),
            new Key('_csrf', v::optional(v::stringVal()), false),
        );
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{mode:mixed}
     */
    protected static function cast(array $input): array
    {
        return ['mode' => $input['mode'] ?? 'all'];
    }
}
