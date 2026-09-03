<?php

namespace Plugin\ManvBoard\Models;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Repository für `intra_manv_log` — Aktionslog einer MANV-Lage.
 *
 * Läuft über die Eloquent-Capsule (Query Builder); die Rückgabeformate
 * (Assoc-Arrays) bleiben für alle Konsumenten unverändert.
 */
class MANVLog
{
    /**
     * Erstellt einen Log-Eintrag
     */
    public function log(int $lageId, string $aktion, ?string $beschreibung = null, ?int $userId = null, ?string $userName = null, ?string $referenzTyp = null, ?int $referenzId = null): int
    {
        return (int) Capsule::table('intra_manv_log')->insertGetId([
            'manv_lage_id' => $lageId,
            'aktion' => $aktion,
            'beschreibung' => $beschreibung,
            'benutzer_id' => $userId,
            'benutzer_name' => $userName,
            'referenz_typ' => $referenzTyp,
            'referenz_id' => $referenzId,
        ]);
    }

    /**
     * Ruft alle Log-Einträge einer MANV-Lage ab
     */
    public function getByLage(int $lageId, int $limit = 100): array
    {
        return Capsule::table('intra_manv_log')
            ->where('manv_lage_id', $lageId)
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * Ruft Log-Einträge für eine bestimmte Referenz ab
     */
    public function getByReference(int $lageId, string $referenzTyp, int $referenzId): array
    {
        return Capsule::table('intra_manv_log')
            ->where('manv_lage_id', $lageId)
            ->where('referenz_typ', $referenzTyp)
            ->where('referenz_id', $referenzId)
            ->orderBy('timestamp', 'desc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }
}
