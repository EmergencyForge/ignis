<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_fahrtenbuch` — Fahrtenbuch-Einträge.
 *
 * Anders als die meisten intraRP-Tabellen hat das Fahrtenbuch BEIDE
 * Timestamps (created_at + updated_at) — daher erbt diese Klasse direkt von
 * EloquentModel statt von App\Models\Model und nutzt die normale
 * Eloquent-Timestamp-Logik.
 *
 * `source` markiert woher der Eintrag kommt: admin (Backoffice), enotf
 * (eingetragen während eines eNOTF-Protokolls) oder firetab (aus dem
 * FireTab-Modul). Die Multi-Context-Auth im FahrtenbuchController prüft,
 * ob der aktuelle Aktor ein Recht zum Bearbeiten dieses spezifischen
 * Source-Eintrags hat.
 *
 * @property int         $id
 * @property int|null    $vehicle_id
 * @property string      $vehicle_identifier
 * @property \DateTime   $datum
 * @property string      $abfahrt
 * @property string|null $ankunft
 * @property string      $stationierungsort
 * @property float|null  $kilometer
 * @property string|null $grund
 * @property string      $fahrttyp
 * @property string      $fahrer_name
 * @property string      $source              'admin'|'enotf'|'firetab'
 * @property int|null    $created_by
 * @property \DateTime   $created_at
 * @property \DateTime   $updated_at
 */
class Fahrt extends EloquentModel
{
    protected $table = 'intra_fahrtenbuch';

    /** Diese Tabelle hat BEIDE Timestamps — Eloquent macht das automatisch. */
    public $timestamps = true;

    protected $guarded = [];

    public const SOURCE_ADMIN   = 'admin';
    public const SOURCE_ENOTF   = 'enotf';
    public const SOURCE_FIRETAB = 'firetab';

    public const FAHRTTYPEN = [
        'einsatzfahrt'   => 'Einsatzfahrt',
        'bewegungsfahrt' => 'Bewegungsfahrt',
        'werkstattfahrt' => 'Werkstattfahrt',
        'uebungsfahrt'   => 'Übungsfahrt',
        'dienstfahrt'    => 'Dienstfahrt',
        'sonstige'       => 'Sonstige',
    ];

    public const FAHRTTYP_BADGES = [
        'einsatzfahrt'   => 'danger',
        'bewegungsfahrt' => 'info',
        'werkstattfahrt' => 'warning',
        'uebungsfahrt'   => 'success',
        'dienstfahrt'    => 'primary',
        'sonstige'       => 'secondary',
    ];

    protected $casts = [
        'id'         => 'integer',
        'vehicle_id' => 'integer',
        'created_by' => 'integer',
        'kilometer'  => 'float',
        'datum'      => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Beziehung zum Fahrzeug (intra_fahrzeuge). Kein eigenes Eloquent-Model
     * für Fahrzeuge in dieser Phase — wir geben null oder eine stdClass via
     * Capsule, wenn wir Daten brauchen.
     */
    public function vehicle(): BelongsTo
    {
        // Verwende ein generisches Eloquent-Model wäre überdimensioniert —
        // FahrtenbuchController joint stattdessen via Capsule für die Liste.
        return $this->belongsTo(self::class, 'vehicle_id', 'id');
    }
}
