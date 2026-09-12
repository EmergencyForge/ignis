<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Eloquent-Model für `intra_edivi_pois` — Orte, die das System kennt:
 * Wachen, Kliniken, Behörden, Einsatzorte.
 *
 * Die Tabelle entstand im eNOTF-Plugin und wird dort weiter über
 * Plugin\Enotf\Models\EdiviPoi angesprochen. Gelesen wird sie aber laengst
 * auch vom Kern (Setup-Checkliste, POI-Hover-Karte, Stationierung am
 * Fahrzeug), deshalb gibt es sie hier ohne Umweg über das Plugin. Der
 * Tabellenname bleibt, wie er ist — ihn umzubenennen wäre eine Migration
 * durch jede Fundstelle in beiden Modulen.
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $strasse
 * @property string|null $hnr
 * @property string      $ort
 * @property string|null $ortsteil
 * @property string|null $typ
 * @property bool        $active
 */
class Poi extends Model
{
    protected $table = 'intra_edivi_pois';

    /** @var array<string,string> */
    protected $casts = [
        'id'     => 'integer',
        'active' => 'boolean',
    ];

    /** Typen, an denen ein Fahrzeug stationiert sein kann. */
    public const STATIONIERUNGS_TYPEN = ['Rettungswache', 'Feuerwache'];

    /** Name mit Ort, wie ihn die Auswahl und das Fahrtenbuch zeigen. */
    public function label(): string
    {
        $ort = trim((string) $this->ort);

        return $ort === '' ? $this->name : $this->name . ' (' . $ort . ')';
    }
}
