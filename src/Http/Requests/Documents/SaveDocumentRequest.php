<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Eingaben des Dokumenten-Editors: der Titel, das Dokument-JSON und die
 * Marke, ob der Aufruf von der Autosave kommt.
 *
 * Alle Felder sind optional, weil derselbe Satz zwei Aufrufer bedient —
 * das Anlegen aus einer Vorlage schickt `template_id` und `title`, das
 * Speichern `content` und `title`. Was inhaltlich fehlen darf, entscheidet
 * der Controller; hier steht nur, was formal durchgeht. `content` wird
 * bewusst nicht als JSON validiert: dafür ist der SectionGuard zuständig,
 * der ohnehin gegen die Vorlage prüfen muss.
 */
class SaveDocumentRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('title', v::optional(v::stringType()->length(0, 200)), false),
            v::key('content', v::optional(v::stringType()), false),
            v::key('autosave', v::optional(v::stringType()), false),
            v::key('template_id', v::optional(v::stringType()), false),
            // Der Token steht im selben Formular; keySet weist sonst den
            // ganzen Satz als unbekannten Schluessel zurueck.
            v::key('csrf_token', v::optional(v::stringType()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'title'  => 'Titel darf höchstens 200 Zeichen lang sein.',
            'length' => 'Titel darf höchstens 200 Zeichen lang sein.',
        ];
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,string>
     */
    protected static function cast(array $input): array
    {
        return [
            'title'       => trim((string) ($input['title'] ?? '')),
            'content'     => (string) ($input['content'] ?? ''),
            'autosave'    => (string) ($input['autosave'] ?? ''),
            'template_id' => (string) ($input['template_id'] ?? ''),
        ];
    }
}
