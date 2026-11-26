<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Titel eines hochgeladenen PDFs (POST /personnel/{id}/documents/upload).
 * Die Datei selbst prüft FileUpload.
 */
class UploadDocumentRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('title', v::stringType()->notBlank()->length(1, 200)),
        );
    }

    protected static function messages(): array
    {
        return [
            'title'  => 'Titel ist erforderlich (max. 200 Zeichen).',
            'keySet' => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array{title: string}
     */
    protected static function cast(array $input): array
    {
        return ['title' => trim((string) $input['title'])];
    }
}
