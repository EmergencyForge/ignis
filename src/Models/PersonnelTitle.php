<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Eloquent-Model für `intra_mitarbeiter_titel`: akademische Titel wie
 * "Dr.", die der Admin pflegt.
 *
 * @property int    $id
 * @property string $name
 * @property int    $priority
 */
class PersonnelTitle extends Model
{
    protected $table = 'intra_mitarbeiter_titel';

    /** @var array<string, string> */
    protected $casts = [
        'id'       => 'integer',
        'priority' => 'integer',
    ];

    /** Kennung eines vorhandenen Titels, sonst null (leer, unbekannt, manipuliert). */
    public static function existingId(mixed $id): ?int
    {
        $id = is_numeric($id) ? (int) $id : 0;

        return $id > 0 && self::query()->whereKey($id)->exists() ? $id : null;
    }

    /**
     * Auswahl für Formulare als Paare, weil ein JSON-Objekt Zahlen-Schlüssel
     * im Browser nach Größe sortiert und "Kein Titel" ans Ende rutschen würde.
     *
     * @return list<array{0:string,1:string}>
     */
    public static function options(): array
    {
        $options = [['', 'Kein Titel']];
        foreach (self::query()->orderBy('priority')->orderBy('name')->get() as $titel) {
            $options[] = [(string) $titel->id, (string) $titel->name];
        }

        return $options;
    }
}
