<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Support;

use App\Auth\Permissions;
use Plugin\EnotfV2\Policies\EnotfV2Policy;

/**
 * ProtokollAccessGuard — Fahrzeug-Scoping für die Protokoll-APIs.
 *
 * Die Feld-Endpoints (save-fields, vitals, medis, poi/save-address,
 * patient-sync, plausibility, sync-status) prüfen über diesen Guard, ob
 * das angefragte Protokoll überhaupt zum Aufrufer gehört — die reine
 * Existenz einer Crew-Session reicht nicht, sonst könnte jede Crew per
 * ENR in die Protokolle fremder Fahrzeuge schreiben.
 *
 * Regeln:
 *   - Crew-Sessions: Protokoll muss zum eigenen Fahrzeug gehören.
 *     Geteilte NA/RD-Protokolle tragen BEIDE Fahrzeuge (fzg_transp +
 *     fzg_na) — ein Match auf einem der beiden Felder genügt. Frisch
 *     angelegte Protokolle sind abgedeckt, weil der Create-Flow das
 *     eigene Fahrzeugfeld sofort setzt.
 *   - Panel-User bleiben fahrzeuglos zugriffsberechtigt (QM-Kontext):
 *     lesend über viewModule (admin/enotf.view/edivi.view), schreibend
 *     über admin/edivi.edit.
 *   - Klinikzugriff (Einmalcode) darf genau das Protokoll seiner ENR lesen.
 *
 * Der Guard beantwortet nur die Zugehörigkeitsfrage (403-Fall). Ob
 * überhaupt eine Anmeldung vorliegt (401-Fall), prüfen die Controller
 * vorher wie bisher.
 */
final class ProtokollAccessGuard
{
    /**
     * Lesender Zugriff auf ein Protokoll?
     *
     * @param array<string,mixed> $protokoll
     */
    public static function canRead(array $protokoll): bool
    {
        if (EnotfV2Policy::viewModule()) {
            return true;
        }
        if (self::klinikMatches($protokoll)) {
            return true;
        }

        return self::vehicleMatches($protokoll);
    }

    /**
     * Schreibender Zugriff auf ein Protokoll?
     *
     * @param array<string,mixed> $protokoll
     */
    public static function canWrite(array $protokoll): bool
    {
        if (Permissions::check(['admin', 'edivi.edit'])) {
            return true;
        }

        return self::vehicleMatches($protokoll);
    }

    /**
     * Gehört das Protokoll zum Fahrzeug der aktuellen Crew-Session?
     *
     * @param array<string,mixed> $protokoll
     */
    private static function vehicleMatches(array $protokoll): bool
    {
        if (!EnotfV2Policy::hasCrewSession()) {
            return false;
        }

        $vehicle = (string) $_SESSION['protfzg'];
        if ($vehicle === '') {
            return false;
        }

        return (string) ($protokoll['fzg_transp'] ?? '') === $vehicle
            || (string) ($protokoll['fzg_na'] ?? '') === $vehicle;
    }

    /**
     * Klinikcode-Session, die genau für diese ENR ausgestellt wurde?
     *
     * @param array<string,mixed> $protokoll
     */
    private static function klinikMatches(array $protokoll): bool
    {
        if (!EnotfV2Policy::hasKlinikAccess()) {
            return false;
        }

        return (string) ($_SESSION['klinik_access_enr'] ?? '') === (string) ($protokoll['enr'] ?? '');
    }
}
