<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eloquent-Model für `intra_registration_codes`: Einladungs- und
 * Registrierungscodes für neue System-Benutzer.
 *
 * @property int         $id
 * @property string      $code
 * @property string|null $label
 * @property int|null    $mitarbeiter_id Mitarbeiter, mit dem das neue Konto verknüpft wird
 * @property int|null    $created_by
 * @property Carbon|null $created_at
 * @property int|null    $used_by
 * @property Carbon|null    $used_at
 * @property Carbon|null    $expires_at
 * @property bool        $is_used
 * @property-read User|null $creator
 * @property-read User|null $usedByUser
 * @property-read Personnel|null $mitarbeiter
 *
 * @method static Builder<static> unused()
 */
class RegistrationCode extends Model
{
    protected $table = 'intra_registration_codes';

    /** @var array<string, string> */
    protected $casts = [
        'id'         => 'integer',
        'created_by' => 'integer',
        'used_by'    => 'integer',
        'mitarbeiter_id' => 'integer',
        'is_used'    => 'boolean',
        'created_at' => 'datetime',
        'used_at'    => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Beziehung: Code → User der ihn erstellt hat.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Beziehung: Code → User der ihn eingelöst hat (falls bereits benutzt).
     *
     * @return BelongsTo<User, $this>
     */
    public function usedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by', 'id');
    }

    /**
     * Beziehung: Code → Mitarbeiter, für den die Einladung gedacht ist.
     *
     * @return BelongsTo<Personnel, $this>
     */
    public function mitarbeiter(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'mitarbeiter_id', 'id');
    }

    /**
     * Ist dieser Code aktuell einlösbar (nicht benutzt + nicht abgelaufen)?
     */
    public function isRedeemable(): bool
    {
        if ($this->is_used) {
            return false;
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }

    /**
     * Reserviert einen einlösbaren Code atomar: nur wer die Zeile mit
     * diesem UPDATE bekommt, darf ein Konto anlegen. In derselben
     * Transaktion aufrufen wie das Anlegen, sonst bleibt bei einem Fehler
     * ein verbrauchter Code ohne Konto zurück.
     */
    public static function reserve(string $code): ?self
    {
        $now = date('Y-m-d H:i:s');
        $reserved = self::query()->where('code', $code)
            ->where('is_used', 0)->whereNull('used_at')
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->update(['used_at' => $now, 'is_used' => 1]);

        return $reserved === 1 ? self::query()->where('code', $code)->first() : null;
    }

    /**
     * Trägt das neue Konto als Einlöser ein und verknüpft es mit dem
     * Mitarbeiter der Einladung (ADR-0002).
     */
    public function redeemFor(User $user): void
    {
        self::query()->whereKey($this->id)->update(['used_by' => $user->id]);
        \App\Personnel\AccountLink::linkInvited((int) $user->id, $this->mitarbeiter_id);
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeUnused(Builder $query): void
    {
        $query->where('is_used', 0);
    }

    /**
     * Absoluter Einladungslink zu einem Code: SYSTEM_URL, wenn eingetragen,
     * sonst Schema und Host des aktuellen Requests.
     */
    public static function inviteUrl(string $code): string
    {
        return self::baseUrl() . (defined('BASE_PATH') ? (string) BASE_PATH : '/') . 'invite?code=' . rawurlencode($code);
    }

    /** Schema und Host der Instanz, ohne Pfad und ohne Schrägstrich am Ende. */
    public static function baseUrl(): string
    {
        $sysUrl = (defined('SYSTEM_URL') && SYSTEM_URL !== '' && SYSTEM_URL !== 'CHANGE_ME')
            ? rtrim((string) SYSTEM_URL, '/')
            : '';
        if ($sysUrl !== '' && !preg_match('#^https?://#i', $sysUrl)) {
            $sysUrl = 'https://' . $sysUrl;
        }
        if ($sysUrl !== '') {
            return $sysUrl;
        }
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';

        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
