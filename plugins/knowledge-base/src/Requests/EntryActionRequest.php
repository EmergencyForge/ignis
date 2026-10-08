<?php

declare(strict_types=1);

namespace Plugin\KnowledgeBase\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Knöpfe an einem Eintrag: Archivieren und Wiederherstellen
 * (POST /lexicon/archive), Anpinnen und Lösen (POST /lexicon/pin),
 * Bearbeiternamen ein- und ausblenden (POST /lexicon/toggle-editor).
 *
 * Welche `action` erlaubt ist, hängt an der Adresse und steht deshalb im
 * Controller.
 */
final class EntryActionRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('id',     v::optional(v::stringVal()), false),
            v::key('action', v::optional(v::stringVal()), false),
        );
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{id:int|null, action:string}
     */
    protected static function cast(array $input): array
    {
        return [
            'id'     => filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'action' => (string) ($input['action'] ?? ''),
        ];
    }
}
