<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Support;

use App\Auth\Permissions;
use App\Personnel\AccountLink;
use Plugin\Enotf\Crew\Policies\CrewPolicy;

/**
 * ProtokollAccessGuard: Fahrzeug-Scoping für die Protokoll-APIs.
 *
 * Die Feld-Endpoints (save-fields, vitals, medis, poi/save-address,
 * patient-sync, plausibility, sync-status) prüfen über diesen Guard, ob
 * das angefragte Protokoll überhaupt zum Aufrufer gehört: die reine
 * Existenz einer Crew-Session reicht nicht, sonst könnte jede Crew per
 * ENR in die Protokolle fremder Fahrzeuge schreiben.
 *
 * Regeln:
 *   - Crew-Sessions: Protokoll muss zum eigenen Fahrzeug gehören.
 *     Geteilte NA/RD-Protokolle tragen BEIDE Fahrzeuge (fzg_transp +
 *     fzg_na). Ein Match auf einem der beiden Felder genügt. Frisch
 *     angelegte Protokolle sind abgedeckt, weil der Create-Flow das
 *     eigene Fahrzeugfeld sofort setzt.
 *   - Panel-User bleiben fahrzeuglos zugriffsberechtigt (QM-Kontext):
 *     lesend über viewModule (admin/enotf.view/edivi.view), schreibend
 *     über admin/edivi.edit.
 *   - Klinikzugriff (Einmalcode) darf genau das Protokoll seiner ENR lesen.
 *   - Wer mit verknüpftem Mitarbeiter als Personal im Protokoll steht, liest
 *     es, auch ohne Crew-Session (Dashboard „Eigene eNOTF-Protokolle“).
 *
 * Der Guard beantwortet nur die Zugehörigkeitsfrage (403-Fall). Ob
 * überhaupt eine Anmeldung vorliegt (401-Fall), prüfen die Controller
 * vorher wie bisher.
 */
final class ProtokollAccessGuard
{
    /** Felder, in denen Namen der Besatzung stehen */
    private const PERSONAL_FELDER = [
        'pfname',
        'fzg_transp_perso', 'fzg_transp_perso_2', 'fzg_transp_perso_3',
        'fzg_na_perso', 'fzg_na_perso_2', 'fzg_na_perso_3',
    ];

    /**
     * Lesender Zugriff auf ein Protokoll?
     *
     * @param array<string,mixed> $protokoll
     */
    public static function canRead(array $protokoll): bool
    {
        if (CrewPolicy::viewModule()) {
            return true;
        }
        if (self::klinikMatches($protokoll)) {
            return true;
        }
        if (self::vehicleMatches($protokoll)) {
            return true;
        }

        return self::isOwn($protokoll);
    }

    /**
     * Steht der Mitarbeiter des angemeldeten Kontos als Personal im
     * Protokoll? Gleiche Felder und Teiltreffer wie die Dashboard-Liste,
     * damit jeder Eintrag dort auch aufgeht.
     *
     * @param array<string,mixed> $protokoll
     */
    public static function isOwn(array $protokoll): bool
    {
        $name = trim((string) (AccountLink::current()->fullname ?? ''));
        if ($name === '') {
            return false;
        }

        foreach (self::PERSONAL_FELDER as $feld) {
            if (mb_stripos((string) ($protokoll[$feld] ?? ''), $name) !== false) {
                return true;
            }
        }

        return false;
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
    public static function vehicleMatches(array $protokoll): bool
    {
        if (!CrewPolicy::hasCrewSession()) {
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
        if (!CrewPolicy::hasKlinikAccess()) {
            return false;
        }

        return (string) ($_SESSION['klinik_access_enr'] ?? '') === (string) ($protokoll['enr'] ?? '');
    }
}
