<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Models;

use App\Models\Model;

/**
 * `intra_edivi` — das eNOTF-Protokoll, eine Zeile pro Einsatzprotokoll.
 *
 * PK ist `id`, fachlicher Schlüssel ist überall die Einsatznummer `enr`
 * (varchar, UNIQUE). Über 100 Spalten, historisch gewachsen — Zugriff
 * per Array-Syntax (`$protokoll['spalte']`) funktioniert über den
 * ArrayAccess des Models.
 *
 * Zeitstempel: `sendezeit` (DB-Default) + `last_edit` (explizit NOW()
 * bei jedem Feld-Write, siehe ProtokollService) — kein Eloquent-
 * Timestamp-Paar, daher timestamps=false aus der Basisklasse.
 *
 * Semantik-Fallen:
 *   - `freigegeben=1` heißt Freigabe ODER User-Löschung; Diskriminator
 *     ist `hidden_user` (siehe istGesperrt()/istUserGeloescht()).
 *   - `patname` ist abgeleitet aus pat_nachname/pat_vorname und wird
 *     nie direkt beschrieben (ProtokollService übernimmt das).
 *   - `createdby`: 1 = Leitstelle/EMD-Sync, 2 = manuell (steuert
 *     Löschbarkeit über /api/enotf/delete-protocol).
 *   - `prot_by`: 0 = Rettungsdienst-, 1 = Notarztprotokoll.
 */
class Edivi extends Model
{
    protected $table = 'intra_edivi';

    public const CREATEDBY_LEITSTELLE = 1;
    public const CREATEDBY_MANUELL    = 2;

    public const PROT_BY_RD = 0;
    public const PROT_BY_NA = 1;

    /**
     * Gesperrt = freigegeben (egal ob echte Freigabe oder User-Löschung).
     * Gesperrte Protokolle nehmen keine Feld-Writes mehr an (403).
     */
    public function istGesperrt(): bool
    {
        return (int) $this->freigegeben === 1;
    }

    /**
     * Vom User „gelöscht" — technisch freigegeben=1 + hidden_user=1.
     */
    public function istUserGeloescht(): bool
    {
        return (int) $this->hidden_user === 1;
    }

    /**
     * Scope: offene Protokolle eines Fahrzeugs (Overview-Semantik).
     */
    public function scopeOffenFuerFahrzeug($query, string $vehicleIdentifier)
    {
        return $query
            ->where('freigegeben', 0)
            ->where(function ($q) use ($vehicleIdentifier) {
                $q->where('fzg_transp', $vehicleIdentifier)
                  ->orWhere('fzg_na', $vehicleIdentifier);
            })
            ->where('hidden', 0)
            ->where('hidden_user', 0);
    }
}
