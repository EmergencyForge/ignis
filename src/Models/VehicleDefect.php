<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_fahrzeuge_defects` — Fahrzeug-Defektmeldungen.
 *
 * Status-Lifecycle: open → in_progress/deferred → resolved.
 * `vehicle_operable = 0` markiert das Fahrzeug als nicht einsatzfähig und
 * deaktiviert es, solange die Meldung offen ist.
 *
 * @property int         $id
 * @property int         $vehicle_id
 * @property string      $title
 * @property string|null $description
 * @property string      $category
 * @property int         $vehicle_operable
 * @property string      $status
 * @property int|null    $reported_by
 * @property int|null    $assigned_to
 * @property int|null    $resolved_by
 */
class VehicleDefect extends Model
{
    protected $table = 'intra_fahrzeuge_defects';

    /**
     * Anzeigenamen der Kategorien (Whitelist in
     * App\Http\Requests\Vehicles\CreateDefectRequest::ALLOWED_CATEGORIES).
     */
    public const CATEGORY_LABELS = [
        'aufbau_karosserie'      => 'Aufbau / Karosserie',
        'ausbau'                 => 'Ausbau',
        'batterie'               => 'Batterie',
        'beleuchtung'            => 'Beleuchtung',
        'bremsen'                => 'Bremsen',
        'elektrik'               => 'Elektrik',
        'fahrwerk'               => 'Fahrwerk',
        'getriebe'               => 'Getriebe',
        'motor'                  => 'Motor',
        'reifen'                 => 'Reifen',
        'service_pruefintervall' => 'Service / Prüfintervall',
        'signalanlage'           => 'Signalanlage',
        'sonstiges'              => 'Sonstiges',
        'windschutzscheibe'      => 'Windschutzscheibe',
    ];

    /** Status => [Anzeigename, Chip-Modifier (ignis-chip--*)], in Lebenslauf-Reihenfolge. */
    public const STATUS_LABELS = [
        'open'        => ['Offen', 'danger'],
        'in_progress' => ['In Bearbeitung', 'warn'],
        'deferred'    => ['Aufgeschoben', 'info'],
        'resolved'    => ['Gelöst', 'ok'],
    ];

    protected $casts = [
        'id'         => 'integer',
        'vehicle_id' => 'integer',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function logEntries(): HasMany
    {
        return $this->hasMany(VehicleDefectLog::class, 'defect_id', 'id');
    }
}
