<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Rules\Key;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * PIN-Eingabe am Lockscreen (POST /enotf/lockscreen). `pin` bleibt null,
 * wenn sie fehlt; dann zeigt der Controller nur den Lockscreen.
 */
final class PinRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        // new Key statt v::key(): die PHPStan-Ausnahme für v::key() in
        // keySet() gilt nur unter plugins/*/src/Requests.
        return v::keySet(
            new Key('pin',   v::optional(v::stringVal()), false),
            new Key('_csrf', v::optional(v::stringVal()), false),
        );
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{pin:string|null}
     */
    protected static function cast(array $input): array
    {
        return ['pin' => isset($input['pin']) ? (string) $input['pin'] : null];
    }
}
