<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_mitarbeiter`: Mitarbeiter (= Personen, die
 * für die Fraktion arbeiten). Distinkt von App\Models\User, das System-
 * Login-Accounts repräsentiert.
 *
 * Die Verbindung zwischen User-Account und Mitarbeiter-Profil ist
 * `intra_users.aktenid`, siehe App\Personnel\AccountLink.
 *
 * Geschlecht: 0=männlich, 1=weiblich, 2=divers
 *
 * @property int         $id
 * @property string      $fullname
 * @property int|null    $titel_id
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
 * @property-read PersonnelTitle|null $titel
 *
 * @method static Builder<static> active(array<int> $archiveDienstgradIds = [])
 * @method static Builder<static> archived(array<int> $archiveDienstgradIds = [])
 */
class Personnel extends Model
{
    protected $table = 'intra_mitarbeiter';

    public const GENDER_MALE   = 0;
    public const GENDER_FEMALE = 1;
    public const GENDER_DIVERSE = 2;

    /** @var array<string, string> */
    protected $casts = [
        'id'          => 'integer',
        'geschlecht'  => 'integer',
        'titel_id'    => 'integer',
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
     *
     * @return BelongsTo<Rank, $this>
     */
    public function dienstgradModel(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'dienstgrad', 'id');
    }

    /**
     * @return BelongsTo<FdSkill, $this>
     */
    public function fwQualiModel(): BelongsTo
    {
        return $this->belongsTo(FdSkill::class, 'qualifw2', 'id');
    }

    /**
     * @return BelongsTo<AmbSkill, $this>
     */
    public function rdQualiModel(): BelongsTo
    {
        return $this->belongsTo(AmbSkill::class, 'qualird', 'id');
    }

    /**
     * @return BelongsTo<PersonnelTitle, $this>
     */
    public function titel(): BelongsTo
    {
        return $this->belongsTo(PersonnelTitle::class, 'titel_id', 'id');
    }

    /**
     * Name für Profilkopf, Signaturen, Dokumente und Absender. Listen,
     * Suche und Zuordnungen nehmen weiter fullname ohne Titel.
     */
    public function formalName(): string
    {
        $titel = trim((string) $this->titel?->name);

        return $titel === '' ? (string) $this->fullname : $titel . ' ' . $this->fullname;
    }

    /**
     * Liefert den geschlechts-spezifischen Rank-Anzeigenamen.
     * Verwendet die Relation, also vorher mit `with('dienstgradModel')` laden.
     */
    public function dienstgradLabel(): string
    {
        return $this->dienstgradModel?->displayName($this->geschlecht) ?? '-';
    }

    public function rdQualiLabel(): string
    {
        return $this->rdQualiModel?->displayName($this->geschlecht) ?? '-';
    }

    public function fwQualiLabel(): string
    {
        return $this->fwQualiModel?->displayName($this->geschlecht) ?? '-';
    }

    /**
     * Scope: nur Mitarbeiter, die NICHT im Archiv-Rank sind.
     * Akzeptiert die Archive-Rank-IDs als Argument, weil das Model
     * sie nicht implizit kennt.
     *
     * @param Builder<self> $query
     * @param array<int> $archiveDienstgradIds
     */
    public function scopeActive(Builder $query, array $archiveDienstgradIds = []): void
    {
        if ($archiveDienstgradIds === []) {
            return;
        }
        $query->whereNotIn('dienstgrad', $archiveDienstgradIds);
    }

    /**
     * @param Builder<self> $query
     * @param array<int> $archiveDienstgradIds
     */
    public function scopeArchived(Builder $query, array $archiveDienstgradIds = []): void
    {
        if ($archiveDienstgradIds === []) {
            $query->whereRaw('1 = 0'); // empty result
            return;
        }
        $query->whereIn('dienstgrad', $archiveDienstgradIds);
    }
}
