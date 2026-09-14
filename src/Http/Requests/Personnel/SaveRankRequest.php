<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Ein Dienstgrad, angelegt oder geändert
 * (`intra_mitarbeiter_dienstgrade`).
 *
 * Vorher las der Controller die sechs Felder einzeln aus `$_POST` und
 * prüfte nur, ob drei davon nicht leer sind. Die Länge prüfte niemand: ein
 * Name über 255 Zeichen lief in eine PDOException, die als „exception" im
 * Hinweis landete, und je nach SQL-Modus schnitt die Datenbank ihn
 * stattdessen stumm ab.
 *
 * `id` ist Teil derselben Menge, weil Anlegen und Ändern dasselbe Formular
 * benutzen: beim Anlegen fehlt es, beim Ändern steht es drin. Dass es beim
 * Ändern auch größer als null sein muss, prüft der Controller — nur er
 * weiß, welche der beiden Aktionen gerade läuft.
 *
 * Die beiden Schalter kommen als Kästchen: sie stehen im Post, wenn sie
 * gesetzt sind, und fehlen sonst ganz.
 */
class SaveRankRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('name',     v::stringType()->notBlank()->length(1, 255)),
            v::key('name_m',   v::stringType()->notBlank()->length(1, 255)),
            v::key('name_w',   v::stringType()->notBlank()->length(1, 255)),
            v::key('priority', v::optional(v::stringVal()->intVal()->between(0, 9999)), false),
            v::key('badge',    v::optional(v::stringType()->length(0, 255)), false),
            v::key('archive',  v::optional(v::stringType()), false),
            v::key('id',       v::optional(v::stringVal()->intVal()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'notBlank' => 'Bezeichnung, männliche und weibliche Form dürfen nicht leer sein.',
            'length'   => 'Ein Eintrag ist länger als {{maxValue}} Zeichen.',
            'intVal'   => 'Die Priorität muss eine Zahl sein.',
            'between'  => 'Die Priorität muss zwischen {{minValue}} und {{maxValue}} liegen.',
        ];
    }

    protected static function cast(array $input): array
    {
        $badge = trim((string) ($input['badge'] ?? ''));

        return [
            'id'       => (int) ($input['id'] ?? 0),
            'name'     => trim((string) $input['name']),
            'name_m'   => trim((string) $input['name_m']),
            'name_w'   => trim((string) $input['name_w']),
            'priority' => (int) ($input['priority'] ?? 0),
            'badge'    => $badge === '' ? null : $badge,
            'archive'  => isset($input['archive']) ? 1 : 0,
        ];
    }
}
