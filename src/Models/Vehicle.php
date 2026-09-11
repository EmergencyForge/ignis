<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_fahrzeuge` — Fahrzeugstamm.
 *
 * rd_type: 0=Andere, 1=RD mit NA, 2=RD ohne NA, 3=Feuerwehr
 *
 * Neben den Stammdaten trägt die Tabelle die Taktischen-Zeichen-Spalten
 * (grundzeichen, organisation, fachaufgabe, einheit, symbol, typ, text,
 * tz_name) sowie den Live-Status aus dem EMD-Sync (current_status,
 * status_updated_at, status_source).
 *
 * @property int         $id
 * @property string      $name
 * @property string      $identifier
 * @property string      $veh_type
 * @property int         $rd_type
 * @property string|null $kennzeichen
 * @property int         $priority
 * @property int         $active
 * @property string|null $allowed_jobs
 */
class Vehicle extends Model
{
    protected $table = 'intra_fahrzeuge';

    protected $casts = [
        'id'      => 'integer',
        'rd_type' => 'integer',
    ];

    /**
     * Die FMS-Status, die ein Fahrzeug tragen kann — dieselbe Menge, die
     * fireTab beim Einzelfahrzeug erlaubt (FireController::setVehicleStatus)
     * und die der EMD-Sync liefert.
     */
    public const STATUS_LABELS = [
        '0' => 'Dringender Sprechwunsch',
        '1' => 'Einsatzbereit Funk',
        '2' => 'Einsatzbereit Wache',
        '3' => 'Einsatz übernommen',
        '4' => 'Am Einsatzort',
        '5' => 'Sprechwunsch',
        '6' => 'Nicht einsatzbereit',
    ];

    public function defects(): HasMany
    {
        return $this->hasMany(VehicleDefect::class, 'vehicle_id', 'id');
    }
}
