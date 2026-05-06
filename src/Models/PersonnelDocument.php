<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_mitarbeiter_dokumente` — vom Mitarbeiter-Modul
 * ausgestellte Dokumente (Beförderungsurkunden, Zertifikate, Abmahnungen, …).
 *
 * Cross-Module-Joins auf `intra_dokument_templates` und `intra_dokument_kategorien`
 * laufen via Capsule — für diese Tabellen gibt es (noch) kein Eloquent-Model.
 *
 * @property int         $id
 * @property int         $docid             Public-ID, im URL benutzt
 * @property int         $type              Document type ID (siehe PersonnelController::createDocument())
 * @property int         $anrede
 * @property string|null $erhalter
 * @property string|null $inhalt
 * @property \DateTime|null $suspendtime
 * @property \DateTime|null $erhalter_gebdat
 * @property int|null    $erhalter_rang
 * @property int|null    $erhalter_rang_rd
 * @property int|null    $erhalter_quali
 * @property \DateTime|null $ausstellungsdatum
 * @property int         $ausstellerid
 * @property string|null $aussteller_name
 * @property int|null    $aussteller_rang
 * @property \DateTime   $timestamp
 * @property int|null    $profileid         FK auf intra_mitarbeiter.id
 * @property string|null $discordid
 * @property bool        $is_archived       (via spätere ALTER-Migration)
 * @property int|null    $template_id       (via spätere ALTER-Migration)
 * @property-read Mitarbeiter|null $mitarbeiter
 */
class PersonnelDocument extends Model
{
    protected $table = 'intra_mitarbeiter_dokumente';

    protected $casts = [
        'id'                => 'integer',
        'docid'             => 'integer',
        'type'              => 'integer',
        'anrede'            => 'integer',
        'erhalter_rang'     => 'integer',
        'erhalter_rang_rd'  => 'integer',
        'erhalter_quali'    => 'integer',
        'ausstellerid'      => 'integer',
        'aussteller_rang'   => 'integer',
        'profileid'         => 'integer',
        'is_archived'       => 'boolean',
        'template_id'       => 'integer',
        'suspendtime'       => 'date',
        'erhalter_gebdat'   => 'date',
        'ausstellungsdatum' => 'date',
        'timestamp'         => 'datetime',
    ];

    /**
     * Beziehung zum Empfänger-Mitarbeiter via FK profileid.
     * Methodenname `mitarbeiter` und nicht `empfaenger`, weil die View und
     * andere Code-Stellen schon `mitarbeiter`/`empfaenger` als Variablen-Namen
     * benutzen — wir bleiben bei der Standard-Eloquent-Konvention.
     */
    public function mitarbeiter(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'profileid', 'id');
    }
}
