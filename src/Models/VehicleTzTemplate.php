<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Eloquent-Model für `intra_fahrzeuge_tz_templates` — wiederverwendbare
 * Taktische-Zeichen-Vorlagen, die auf alle Fahrzeuge eines Typs
 * angewendet werden können.
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $grundzeichen
 * @property string|null $organisation
 * @property string|null $fachaufgabe
 * @property string|null $einheit
 * @property string|null $symbol
 * @property string|null $typ
 * @property string|null $text
 * @property int|null    $created_by
 */
class VehicleTzTemplate extends Model
{
    protected $table = 'intra_fahrzeuge_tz_templates';

    protected $casts = [
        'id' => 'integer',
    ];
}
