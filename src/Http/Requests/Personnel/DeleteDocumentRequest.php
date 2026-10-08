<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /personnel/document-delete.
 *
 * Felder:
 *   - docid (fehlt sie, meldet der Controller „Dokument-ID fehlt")
 *   - pid   (optional, Profil für das Prüfprotokoll)
 */
class DeleteDocumentRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('docid', v::stringType(), false),
            v::key('pid',   v::stringType(), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'docid' => 'Ungültige Dokument-ID.',
            'pid'    => 'Ungültiges Profil.',
            'keySet' => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'docid' => (string) ($input['docid'] ?? ''),
            'pid'   => (string) ($input['pid'] ?? ''),
        ];
    }
}
