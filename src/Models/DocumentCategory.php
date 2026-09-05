<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_dokument_kategorien` — Kategorien für
 * Dokument-Templates (Name, Farbschlüssel, Icon, Sortierung).
 *
 * `color` speichert einen Farbschlüssel aus CHIP_CLASSES (`ok`, `warn` …),
 * nicht den Klassennamen. Ältere Zeilen tragen noch `ignis-chip--success`
 * oder gar `text-bg-success`; chipClass() und colorKey() verstehen beides,
 * damit Bestandsdaten ohne Migration weiter rendern.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $color
 * @property string|null $icon
 * @property int         $sort_order
 */
class DocumentCategory extends Model
{
    protected $table = 'intra_dokument_kategorien';

    protected $casts = [
        'id' => 'integer',
    ];

    public const DEFAULT_COLOR = 'neutral';

    /**
     * Farbschlüssel → Chip-Klasse. Blau und Cyan waren in ui.scss dieselbe
     * Farbe (--primary und --info teilen sich --info-rgb), darum gibt es
     * nur noch `info`.
     */
    public const CHIP_CLASSES = [
        'neutral' => 'ignis-chip--secondary',
        'info'    => 'ignis-chip--info',
        'ok'      => 'ignis-chip--ok',
        'warn'    => 'ignis-chip--warn',
        'danger'  => 'ignis-chip--danger',
        'dark'    => 'ignis-chip--dark',
    ];

    /** Beschriftung der Farbschlüssel im Formular. */
    public const COLOR_LABELS = [
        'neutral' => 'Grau (Standard)',
        'info'    => 'Blau',
        'ok'      => 'Grün',
        'warn'    => 'Gelb',
        'danger'  => 'Rot',
        'dark'    => 'Dunkel',
    ];

    /** Früher gespeicherte Werte (Chip-Klassen, davor Bootstrap) → Schlüssel. */
    public const LEGACY_COLORS = [
        'ignis-chip--secondary' => 'neutral',
        'ignis-chip--primary'   => 'info',
        'ignis-chip--info'      => 'info',
        'ignis-chip--success'   => 'ok',
        'ignis-chip--ok'        => 'ok',
        'ignis-chip--warning'   => 'warn',
        'ignis-chip--warn'      => 'warn',
        'ignis-chip--danger'    => 'danger',
        'ignis-chip--dark'      => 'dark',
        'text-bg-secondary'     => 'neutral',
        'text-bg-light'         => 'neutral',
        'text-bg-primary'       => 'info',
        'text-bg-info'          => 'info',
        'text-bg-success'       => 'ok',
        'text-bg-warning'       => 'warn',
        'text-bg-danger'        => 'danger',
        'text-bg-dark'          => 'dark',
    ];

    /**
     * Der Farbschlüssel zu einem gespeicherten oder eingegebenen Wert;
     * unbekannte Werte werden zu `neutral`.
     */
    public static function colorKey(?string $stored): string
    {
        $stored = trim((string) $stored);
        if (isset(self::CHIP_CLASSES[$stored])) {
            return $stored;
        }

        return self::LEGACY_COLORS[$stored] ?? self::DEFAULT_COLOR;
    }

    /** Die Chip-Klasse zu einem gespeicherten Wert, alt oder neu. */
    public static function chipClass(?string $stored): string
    {
        return self::CHIP_CLASSES[self::colorKey($stored)];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class, 'category_id', 'id');
    }
}
