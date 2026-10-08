<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /settings/system/plugins.
 *
 * Alle Formulare der Seite und der Bestätigungsseite gehen an dieselbe
 * Adresse, `plugin_action` wählt den Schritt. Deshalb steht hier die
 * Vereinigung ihrer Felder, und jedes ist optional.
 *
 * Felder:
 *   - plugin_action (fehlt sie, wird umgeschaltet)
 *   - plugin_id
 *   - upload_token, expect_update (Bestätigung eines Uploads)
 *   - install_now, accept_risk (Häkchen, `1` wenn gesetzt)
 *
 * Das ZIP kommt über `$_FILES` und gehört nicht hierher.
 */
class PluginActionRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('plugin_action', v::stringType(), false),
            v::key('plugin_id',     v::stringType(), false),
            v::key('upload_token',  v::stringType(), false),
            v::key('expect_update', v::stringType(), false),
            v::key('install_now',   v::stringType(), false),
            v::key('accept_risk',   v::stringType(), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'plugin_action' => 'Unbekannte Aktion.',
            'plugin_id'     => 'Unbekanntes Plugin.',
            'upload_token'  => 'Der Upload ist nicht mehr vorhanden.',
            'expect_update' => 'Ungültiger Wert für Update.',
            'install_now'   => 'Ungültiger Wert für Installieren.',
            'accept_risk'   => 'Ungültiger Wert für den Hinweis.',
            'keySet'        => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'plugin_action' => (string) ($input['plugin_action'] ?? 'toggle'),
            'plugin_id'     => (string) ($input['plugin_id'] ?? ''),
            'upload_token'  => (string) ($input['upload_token'] ?? ''),
            'expect_update' => ($input['expect_update'] ?? '0') === '1',
            'install_now'   => ($input['install_now'] ?? '') === '1',
            'accept_risk'   => ($input['accept_risk'] ?? '') === '1',
        ];
    }
}
