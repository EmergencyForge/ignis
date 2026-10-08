<?php

declare(strict_types=1);

namespace Plugin\KnowledgeBase\Requests;

use App\Http\Requests\FormRequest;
use Plugin\KnowledgeBase\KBHelper;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Ein Lexikoneintrag, angelegt (POST /lexicon/create) oder geändert
 * (POST /lexicon/edit).
 *
 * Titel und Typ prüft der Controller, er zeigt alle Fehler zusammen im
 * Formular an. Die Editorfelder bekommen keine Typregel: ein Wert, der
 * kein Editor-JSON ist, kommt hier als null an und der Controller meldet
 * ihn mit seinem eigenen Text.
 */
final class SaveEntryRequest extends FormRequest
{
    /** Felder, die im Formular ein Editor sind. Wirkstoff und Wirkstoffgruppe bleiben Klartext. */
    public const EDITOR_FIELDS = [
        'content',
        'med_wirkmechanismus', 'med_indikationen', 'med_kontraindikationen',
        'med_uaw', 'med_dosierung', 'med_besonderheiten',
        'mass_wirkprinzip', 'mass_indikationen', 'mass_kontraindikationen',
        'mass_risiken', 'mass_alternativen', 'mass_durchfuehrung',
    ];

    private const TEXTFELDER = [
        'type', 'category_id', 'title', 'subtitle', 'competency_level',
        'med_wirkstoff', 'med_wirkstoffgruppe', 'is_pinned', 'hide_editor',
    ];

    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('tags',      v::optional(v::arrayType()), false),
            v::key('relations', v::optional(v::arrayType()), false),
            ...array_map(
                static fn (string $feld): Validatable => v::key($feld, v::optional(v::stringVal()), false),
                self::TEXTFELDER,
            ),
            ...array_map(
                static fn (string $feld): Validatable => v::key($feld, null, false),
                self::EDITOR_FIELDS,
            ),
        );
    }

    protected static function messages(): array
    {
        return array_fill_keys([...self::TEXTFELDER, 'tags', 'relations', 'keySet'], 'Ungültige Eingabe.');
    }

    /**
     * `editor` enthält nur die Felder, die mitkamen: ein Editor schickt sein
     * JSON erst, wenn er geladen ist.
     *
     * @param  array<string,mixed> $input
     * @return array{type:mixed, title:string, subtitle:string, competency_level:mixed, category_id:int|null, tags:array<mixed>, relations:array<mixed>, med_wirkstoff:string, med_wirkstoffgruppe:string, is_pinned:int, hide_editor:int, editor:array<string,string|null>}
     */
    protected static function cast(array $input): array
    {
        $editor = [];
        foreach (self::EDITOR_FIELDS as $feld) {
            if (isset($input[$feld])) {
                $editor[$feld] = is_string($input[$feld]) ? KBHelper::fromEditorJson($input[$feld]) : null;
            }
        }

        return [
            'type'                => $input['type'] ?? 'general',
            'title'               => trim((string) ($input['title'] ?? '')),
            'subtitle'            => trim((string) ($input['subtitle'] ?? '')),
            'competency_level'    => !empty($input['competency_level']) ? $input['competency_level'] : null,
            'category_id'         => !empty($input['category_id']) ? (int) $input['category_id'] : null,
            'tags'                => is_array($input['tags'] ?? null) ? $input['tags'] : [],
            'relations'           => is_array($input['relations'] ?? null) ? $input['relations'] : [],
            'med_wirkstoff'       => trim((string) ($input['med_wirkstoff'] ?? '')),
            'med_wirkstoffgruppe' => trim((string) ($input['med_wirkstoffgruppe'] ?? '')),
            'is_pinned'           => isset($input['is_pinned']) ? 1 : 0,
            'hide_editor'         => isset($input['hide_editor']) ? 1 : 0,
            'editor'              => $editor,
        ];
    }
}
