<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Eine Verlinkung der Dashboard-Konfiguration (`intra_dashboard_tiles`).
 *
 * Das Ziel steht auf dem Dashboard in einem `href`. Bisher nahm der
 * Controller es, wie es kam: `javascript:…` war ein gültiger Wert und
 * damit ein Skript, das jeder ausführt, der die Kachel anklickt. Wer die
 * Kachel anlegen darf, braucht `system.manageDashboard` — es ist also kein
 * Weg von draußen, aber einer von dieser Berechtigung zu allen anderen.
 *
 * Erlaubt sind deshalb nur ein Pfad im eigenen Haus (`/…`, `?…`, `#…` oder
 * ein relativer Pfad) sowie `http`, `https` und `mailto`.
 */
class SaveDashboardTileRequest extends FormRequest
{
    /** @var array<int,string> */
    public const ERLAUBTE_SCHEMATA = ['http', 'https', 'mailto'];

    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('category', v::stringVal()->intVal()->positive()),
            v::key('title',    v::stringType()->notBlank()->length(1, 255)),
            v::key('url',      v::stringType()->notBlank()->length(1, 255)->call(
                static fn (string $url): string => self::schema($url),
                v::in(self::ERLAUBTE_SCHEMATA, true),
            )),
            v::key('icon',     v::optional(v::stringType()->length(0, 100)->regex('~^[a-z0-9 -]*$~i')), false),
            v::key('priority', v::optional(v::stringVal()->intVal()->between(0, 9999)), false),
            v::key('id',       v::optional(v::stringVal()->intVal()), false),
        );
    }

    /**
     * Das Schema eines Ziels — `http` für alles ohne Schema, weil ein
     * Pfad im eigenen Haus genauso harmlos ist wie ein http-Link.
     *
     * Geprüft wird auf das, was der Browser als Schema liest: alles vor
     * dem ersten Doppelpunkt, sofern davor kein `/`, `?` oder `#` steht.
     * `/a:b` ist damit ein Pfad, `javascript:alert(1)` nicht.
     */
    private static function schema(string $url): string
    {
        $url = trim($url);

        if (preg_match('~^([a-z][a-z0-9+.-]*):~i', $url, $m) !== 1) {
            return 'http';
        }

        $vorher = substr($url, 0, (int) strpos($url, ':'));

        return preg_match('~[/?#]~', $vorher) === 1 ? 'http' : strtolower($m[1]);
    }

    protected static function messages(): array
    {
        return [
            'notBlank' => 'Titel und Ziel dürfen nicht leer sein.',
            'length'   => 'Ein Eintrag ist länger als {{maxValue}} Zeichen.',
            'intVal'   => 'Kategorie und Priorität müssen Zahlen sein.',
            'positive' => 'Es muss eine Kategorie gewählt sein.',
            'between'  => 'Die Priorität muss zwischen {{minValue}} und {{maxValue}} liegen.',
            'in'       => 'Als Ziel sind nur ein Pfad dieser Installation, http, https und mailto erlaubt.',
            'regex'    => 'Der Symbolname darf nur Buchstaben, Ziffern und Bindestriche enthalten.',
        ];
    }

    protected static function cast(array $input): array
    {
        $icon = trim((string) ($input['icon'] ?? ''));

        return [
            'id'       => (int) ($input['id'] ?? 0),
            'category' => (int) $input['category'],
            'title'    => trim((string) $input['title']),
            'url'      => trim((string) $input['url']),
            'icon'     => $icon === '' ? 'external-link-alt' : $icon,
            'priority' => (int) ($input['priority'] ?? 0),
        ];
    }
}
