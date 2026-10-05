<?php

declare(strict_types=1);

namespace Plugin\Enotf\Helpers;

/**
 * Code-Listen der Reanimationssituation (7 Abschluss), gemeinsam für
 * v1 und v2. rea_status 1 und 3-6 schließen den Baum ab, nur bei 2
 * (durchgeführt) werden die Details abgefragt.
 */
final class ReanimationCatalog
{
    public const STATUS_DURCHGEFUEHRT = 2;

    /** rea_status: Code => Label (Anzeige in Übersicht/Druck). */
    public const STATUS = [
        1 => 'keine Reanimationssituation',
        2 => 'Reanimation durchgeführt',
        3 => 'Reanimation nicht durchgeführt, weil sichere Todeszeichen',
        4 => 'Reanimation nicht durchgeführt, weil DNR-Order vorhanden',
        5 => 'Reanimation nicht durchgeführt, weil aussichtslose Grunderkrankung bekannt',
        6 => 'Reanimation nicht durchgeführt, weil aussichtslose sonstige Faktoren',
    ];

    /** Begründungen unter "Reanimation nicht durchgeführt," (Button-Text). */
    public const NICHT_DURCHGEFUEHRT = [
        3 => 'weil sichere Todeszeichen',
        4 => 'weil DNR-Order vorhanden',
        5 => 'weil aussichtslose Grunderkrankung bekannt',
        6 => 'weil aussichtslose sonstige Faktoren',
    ];

    /** Reihenfolge wie auf dem Tablet: erste Spalte, dann zweite Spalte. */
    public const URSACHE = [
        1 => 'kardial',
        2 => 'Trauma',
        3 => 'Ertrinken',
        4 => 'Hypoxie',
        5 => 'Intoxikation',
        6 => 'ICB / SAB',
        7 => 'SIDS',
        8 => 'Verbluten',
        9 => 'Stroke',
        10 => 'metabolisch',
        98 => 'Sonstiges',
        11 => 'Sepsis',
        99 => 'nicht bekannt',
    ];

    public const JA_NEIN = [
        1 => 'ja',
        2 => 'nein',
    ];

    public const KOLLAPS = [
        1 => 'Ersthelfer',
        2 => 'First Responder',
        3 => 'KTW-Besatzung',
        4 => 'RTW-Besatzung',
        5 => 'NA-Rettungsmittel',
        98 => 'Sonstige',
        99 => 'nicht bekannt',
    ];

    public const HDM_DURCH = [
        1 => 'Ersthelfer',
        2 => 'First Responder',
        3 => 'KTW-Besatzung',
        4 => 'RTW-Besatzung',
        5 => 'NA-Rettungsmittel',
        98 => 'Sonstige',
    ];

    public const DEFI = [
        1 => 'nicht durchgeführt',
        2 => 'durchgeführt',
    ];

    public const ROSC = [
        1 => 'niemals ROSC',
        2 => 'jemals ROSC',
    ];

    public const KH_AUFNAHME = [
        1 => 'Krankenhausaufnahme mit ROSC',
        2 => 'Krankenhausaufnahme unter Rea',
        3 => 'Keine Aufnahme / Tod',
    ];

    /**
     * Detail-Menü in Tablet-Reihenfolge. typ: radio (mit Optionen),
     * zeit ('HH:MM') oder toggle (1/0). pflicht = roter Punkt.
     *
     * @var array<string, array{label:string, typ:string, pflicht:bool, optionen?:array<int,string>}>
     */
    public const DETAILS = [
        'rea_ursache'       => ['label' => 'Vermutete Ursache', 'typ' => 'radio', 'pflicht' => true, 'optionen' => self::URSACHE],
        'rea_sport'         => ['label' => 'Zusammenhang mit sportlicher Aktivität', 'typ' => 'radio', 'pflicht' => false, 'optionen' => self::JA_NEIN],
        'rea_fr_eintreffen' => ['label' => 'Eintreffen First Responder', 'typ' => 'zeit', 'pflicht' => false],
        'rea_kollaps'       => ['label' => 'Kollaps beobachtet', 'typ' => 'radio', 'pflicht' => true, 'optionen' => self::KOLLAPS],
        'rea_hdm_durch'     => ['label' => 'HDM gestartet durch', 'typ' => 'radio', 'pflicht' => true, 'optionen' => self::HDM_DURCH],
        'rea_hdm_zeit'      => ['label' => 'HDM Zeitpunkt', 'typ' => 'zeit', 'pflicht' => true],
        'rea_defi'          => ['label' => 'Defibrillation', 'typ' => 'radio', 'pflicht' => false, 'optionen' => self::DEFI],
        'rea_rosc'          => ['label' => 'ROSC', 'typ' => 'radio', 'pflicht' => true, 'optionen' => self::ROSC],
        'rea_kh_aufnahme'   => ['label' => 'Krankenhausaufnahme', 'typ' => 'radio', 'pflicht' => true, 'optionen' => self::KH_AUFNAHME],
        'rea_tod_zeit'      => ['label' => 'Tod', 'typ' => 'zeit', 'pflicht' => false],
        'rea_erfolglos'     => ['label' => 'erfolglos', 'typ' => 'toggle', 'pflicht' => false],
    ];

    /** @return list<string> Pflicht-Detailspalten (nur bei rea_status=2). */
    public static function pflichtDetails(): array
    {
        return array_keys(array_filter(self::DETAILS, static fn (array $d): bool => $d['pflicht']));
    }

    /**
     * Fehlende Pflichtdetails als Labels; leer, wenn rea_status != 2.
     *
     * v1 übergibt das Edivi-Model selbst, v2 dessen Attribut-Array.
     *
     * @param array<string,mixed>|\ArrayAccess<string,mixed> $daten
     * @return list<string>
     */
    public static function fehlendeDetails(array|\ArrayAccess $daten): array
    {
        if ((int) ($daten['rea_status'] ?? 0) !== self::STATUS_DURCHGEFUEHRT) {
            return [];
        }

        $fehlend = [];
        foreach (self::pflichtDetails() as $feld) {
            $wert = $daten[$feld] ?? null;
            if ($wert === null || $wert === '') {
                $fehlend[] = self::DETAILS[$feld]['label'];
            }
        }
        return $fehlend;
    }
}
