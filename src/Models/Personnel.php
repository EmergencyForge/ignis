<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_mitarbeiter` — Mitarbeiter (= Personen, die
 * für die Fraktion arbeiten). Distinkt von App\Models\User, das System-
 * Login-Accounts repräsentiert.
 *
 * Die Verbindung zwischen User-Account und Mitarbeiter-Profil läuft über
 * `discordtag` (Mitarbeiter) ↔ `discord_id` (User).
 *
 * Geschlecht: 0=männlich, 1=weiblich, 2=divers
 *
 * @property int         $id
 * @property string      $fullname
 * @property \DateTime   $gebdatum
 * @property string      $charakterid
 * @property int         $geschlecht
 * @property int|null    $forumprofil
 * @property string|null $discordtag
 * @property string|null $telefonnr
 * @property string      $dienstnr
 * @property \DateTime   $einstdatum
 * @property int         $dienstgrad
 * @property int         $qualifw2
 * @property int         $qualird
 * @property string|null $zusatz
 * @property string|null $fachdienste     Legacy: longtext, evtl. JSON
 * @property string|null $pfp             Profile-Picture-URL
 * @property \DateTime   $createdate
 * @property-read Rank|null $dienstgradModel
 * @property-read FdSkill|null    $fwQualiModel
 * @property-read AmbSkill|null    $rdQualiModel
 */
class Personnel extends Model
{
    protected $table = 'intra_mitarbeiter';

    public const GENDER_MALE   = 0;
    public const GENDER_FEMALE = 1;
    public const GENDER_DIVERSE = 2;

    protected $casts = [
        'id'          => 'integer',
        'geschlecht'  => 'integer',
        'forumprofil' => 'integer',
        'dienstgrad'  => 'integer',
        'qualifw2'    => 'integer',
        'qualird'     => 'integer',
        'gebdatum'    => 'date',
        'einstdatum'  => 'date',
        'createdate'  => 'datetime',
    ];

    /**
     * BelongsTo-Relation auf Rank. Methoden-Name endet auf `Model`,
     * weil das Property `dienstgrad` schon die FK-ID hält und sonst Eloquent
     * sich verschluckt.
     */
    public function dienstgradModel(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'dienstgrad', 'id');
    }

    public function fwQualiModel(): BelongsTo
    {
        return $this->belongsTo(FdSkill::class, 'qualifw2', 'id');
    }

    public function rdQualiModel(): BelongsTo
    {
        return $this->belongsTo(AmbSkill::class, 'qualird', 'id');
    }

    /**
     * Liefert den geschlechts-spezifischen Rank-Anzeigenamen.
     * Verwendet die Relation, also vorher mit `with('dienstgradModel')` laden.
     */
    public function dienstgradLabel(): string
    {
        return $this->dienstgradModel?->displayName($this->geschlecht) ?? '—';
    }

    public function rdQualiLabel(): string
    {
        return $this->rdQualiModel?->displayName($this->geschlecht) ?? '—';
    }

    public function fwQualiLabel(): string
    {
        return $this->fwQualiModel?->displayName($this->geschlecht) ?? '—';
    }

    /**
     * Scope: nur Mitarbeiter, die NICHT im Archiv-Rank sind.
     * Akzeptiert die Archive-Rank-IDs als Argument, weil das Model
     * sie nicht implizit kennt.
     *
     * @param array<int> $archiveDienstgradIds
     */
    public function scopeActive($query, array $archiveDienstgradIds = [])
    {
        if ($archiveDienstgradIds === []) {
            return $query;
        }
        return $query->whereNotIn('dienstgrad', $archiveDienstgradIds);
    }

    public function scopeArchived($query, array $archiveDienstgradIds = [])
    {
        if ($archiveDienstgradIds === []) {
            return $query->whereRaw('1 = 0'); // empty result
        }
        return $query->whereIn('dienstgrad', $archiveDienstgradIds);
    }
}
