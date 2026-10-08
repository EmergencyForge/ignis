<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Rules\Key;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Crew-Anmeldung (POST /enotf/login): das Anmeldeformular
 * (`login_mode=new`) und das versteckte Beitrittsformular
 * (`login_mode=join`).
 *
 * Die Namen und Qualifikationen bleiben null, wenn sie fehlen; welcher
 * Leerwert daraus wird, entscheidet der Controller je nach Verwendung.
 * `data__set` ist der Absendeknopf, `crew__delete` und `crew__switch`
 * stehen nur hier, falls ein Browser die Knöpfe doch mitschickt. `_csrf`
 * prüft die CsrfMiddleware der Crew-Seiten.
 */
final class CrewLoginRequest extends FormRequest
{
    private const FELDER = [
        'login_mode', 'protfzg',
        'fahrername', 'fahrerquali', 'beifahrername', 'beifahrerquali', 'praktikantname', 'praktikantquali',
        'join_position', 'join_name', 'join_quali',
        'data__set', 'crew__delete', 'crew__switch', '_csrf',
    ];

    protected static function rules(): Validatable
    {
        // new Key statt v::key(): die PHPStan-Ausnahme für v::key() in
        // keySet() gilt nur unter plugins/*/src/Requests.
        return v::keySet(...array_map(
            static fn (string $feld): Key => new Key($feld, v::optional(v::stringVal()), false),
            self::FELDER,
        ));
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{login_mode:mixed, protfzg:string, fahrername:mixed, fahrerquali:mixed, beifahrername:mixed, beifahrerquali:mixed, praktikantname:mixed, praktikantquali:mixed, join_position:mixed, join_name:mixed, join_quali:mixed}
     */
    protected static function cast(array $input): array
    {
        return [
            'login_mode'      => $input['login_mode'] ?? 'new',
            'protfzg'         => (string) ($input['protfzg'] ?? ''),
            'fahrername'      => $input['fahrername'] ?? null,
            'fahrerquali'     => $input['fahrerquali'] ?? null,
            'beifahrername'   => $input['beifahrername'] ?? null,
            'beifahrerquali'  => $input['beifahrerquali'] ?? null,
            'praktikantname'  => $input['praktikantname'] ?? null,
            'praktikantquali' => $input['praktikantquali'] ?? null,
            'join_position'   => $input['join_position'] ?? null,
            'join_name'       => $input['join_name'] ?? null,
            'join_quali'      => $input['join_quali'] ?? null,
        ];
    }
}
