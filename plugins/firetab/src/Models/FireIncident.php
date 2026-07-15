<?php

declare(strict_types=1);

namespace Plugin\Firetab\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_fire_incidents` — Feuerwehr-Einsätze
 * (FireTab/Einsatz-Modul).
 *
 * Anders als die meisten intraRP-Tabellen hat `intra_fire_incidents` BEIDE
 * Timestamps (created_at + updated_at via ON UPDATE), daher erbt diese
 * Klasse direkt von EloquentModel und nutzt die normale Eloquent-Timestamp-
 * Logik.
 *
 * Status-Werte (über mehrere ALTER-Migrations gewachsen, jetzt TINYINT):
 *   0 = Ungesehen
 *   1 = In Prüfung
 *   2 = Freigegeben
 *   3 = Ungenügend
 *   4 = Ausgeblendet
 *
 * Der `finalized`-Flag markiert, ob der Einsatz "abgeschlossen" ist
 * (= bearbeitet, jetzt nur noch QM-Status). `archived` markiert finale
 * Archivierung.
 *
 * Multi-Context-Auth: Einsätze werden von eingeloggten Admin-Usern UND
 * von "FireTab"-Sessions bearbeitet, die nur einen Fahrzeug-Login haben.
 * Die FireIncidentSessionPolicy unterscheidet das.
 *
 * @property int         $id
 * @property string|null $incident_number
 * @property string      $location
 * @property string      $keyword
 * @property \DateTime   $started_at
 * @property int|null    $leader_id        FK → intra_mitarbeiter
 * @property string|null $owner_type       enum: geschaedigter|eigentümer|halter
 * @property string|null $owner_name
 * @property string|null $owner_contact
 * @property int         $status           0..4 (siehe oben)
 * @property bool        $finalized
 * @property \DateTime|null $finalized_at
 * @property int|null    $finalized_by     FK → intra_users
 * @property string|null $notes
 * @property bool        $archived
 * @property \DateTime|null $archived_at
 * @property int|null    $archived_by
 * @property string|null $caller_name
 * @property string|null $caller_contact
 * @property int         $current_status
 * @property \DateTime|null $status_updated_at
 * @property float|null  $location_x       GTA-X-Koordinate
 * @property float|null  $location_y       GTA-Y-Koordinate
 * @property \DateTime   $created_at
 * @property int|null    $created_by
 * @property \DateTime|null $updated_at
 * @property int|null    $updated_by
 */
class FireIncident extends EloquentModel
{
    protected $table = 'intra_fire_incidents';

    public $timestamps = true;

    protected $guarded = [];

    public const STATUS_NEW         = 0; // Ungesehen
    public const STATUS_REVIEWING   = 1; // In Prüfung
    public const STATUS_APPROVED    = 2; // Freigegeben
    public const STATUS_INSUFFICIENT = 3; // Ungenügend
    public const STATUS_HIDDEN      = 4; // Ausgeblendet

    public const STATUS_LABELS = [
        self::STATUS_NEW          => 'Ungesehen',
        self::STATUS_REVIEWING    => 'In Prüfung',
        self::STATUS_APPROVED     => 'Freigegeben',
        self::STATUS_INSUFFICIENT => 'Ungenügend',
        self::STATUS_HIDDEN       => 'Ausgeblendet',
    ];

    public const STATUS_BADGES = [
        self::STATUS_NEW          => 'bg-secondary',
        self::STATUS_REVIEWING    => 'bg-warning',
        self::STATUS_APPROVED     => 'bg-success',
        self::STATUS_INSUFFICIENT => 'bg-danger',
        self::STATUS_HIDDEN       => 'bg-dark',
    ];

    protected $casts = [
        'id'                => 'integer',
        'leader_id'         => 'integer',
        'status'            => 'integer',
        'finalized'         => 'boolean',
        'finalized_by'      => 'integer',
        'archived'          => 'boolean',
        'archived_by'       => 'integer',
        'current_status'    => 'integer',
        'created_by'        => 'integer',
        'updated_by'        => 'integer',
        'location_x'        => 'float',
        'location_y'        => 'float',
        'started_at'        => 'datetime',
        'finalized_at'      => 'datetime',
        'archived_at'       => 'datetime',
        'status_updated_at' => 'datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    /**
     * Beziehung: alle Fahrzeuge die an diesem Einsatz beteiligt sind/waren.
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(FireIncidentVehicle::class, 'incident_id', 'id');
    }

    /**
     * Convenience: nur aktive Einsätze (nicht abgeschlossen, nicht archiviert).
     */
    public function scopeActive($query)
    {
        return $query->where('finalized', 0)->where('archived', 0);
    }

    /**
     * Convenience: nur abgeschlossene, nicht archivierte Einsätze
     * (für QM-Übersicht).
     */
    public function scopeFinalized($query)
    {
        return $query->where('finalized', 1)->where('archived', 0);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? 'Unbekannt';
    }

    public function statusBadgeClass(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-secondary';
    }
}
