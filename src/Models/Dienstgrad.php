<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_mitarbeiter_dienstgrade` — Dienstgrad-Definitionen.
 *
 * Dienstgrade haben drei Namens-Varianten (neutral, männlich, weiblich) und
 * werden je nach Mitarbeiter-Geschlecht angezeigt. Der Helper `displayName($g)`
 * spart das wiederholte if/elseif in Templates.
 *
 * Der `archive`-Flag markiert einen "Archiv-Dienstgrad", der für entlassene
 * Mitarbeiter benutzt wird (statt sie zu löschen).
 *
 * @property int    $id
 * @property int    $priority
 * @property string $name        Geschlechts-neutraler Name
 * @property string $name_m      Männliche Variante
 * @property string $name_w      Weibliche Variante
 * @property string|null $badge  Pfad zum Badge-Bild
 * @property bool   $archive
 * @property \DateTime $created_at
 */
class Dienstgrad extends Model
{
    protected $table = 'intra_mitarbeiter_dienstgrade';

    protected $casts = [
        'id'         => 'integer',
        'priority'   => 'integer',
        'archive'    => 'boolean',
        'created_at' => 'datetime',
    ];

    public function mitarbeiter(): HasMany
    {
        return $this->hasMany(Mitarbeiter::class, 'dienstgrad', 'id');
    }

    /**
     * Liefert den Anzeigenamen passend zum Geschlecht des Mitarbeiters.
     *
     * @param int|null $geschlecht 0=männlich, 1=weiblich, sonst neutral
     */
    public function displayName(?int $geschlecht): string
    {
        return match ($geschlecht) {
            0 => $this->name_m ?: $this->name,
            1 => $this->name_w ?: $this->name,
            default => $this->name,
        };
    }

    /**
     * Convenience-Scope: nur nicht-archivierte Dienstgrade, sortiert nach
     * Priority. Wird für Selektoren in Forms benutzt.
     */
    public function scopeActive($query)
    {
        return $query->where('archive', 0)->orderBy('priority');
    }
}
