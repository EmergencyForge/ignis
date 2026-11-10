<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Controllers;

use EmergencyForge\Http\Exceptions\RedirectException;
use EmergencyForge\Http\Request;
use Plugin\Enotf\Helpers\EnotfUrl;
use Plugin\Enotf\Crew\Support\ProtokollAccessGuard;
use Plugin\Enotf\Crew\Support\ProtokollService;

/**
 * ProtokollController: Shell des Protokoll-Editors.
 *
 * Rendert das gemeinsame Layout mit Section-Navigation und lädt in den
 * Content-Bereich das Template der aktiven Section
 * (templates/protokoll/{section}.php).
 *
 * Bearbeiten darf nur die Crew des Fahrzeugs. Panel-Nutzer mit Leserecht
 * (Prüfliste, Benachrichtigungen) und Mitarbeiter, die im Protokoll als
 * Personal stehen (Dashboard), sehen es ohne Fahrzeuganmeldung, aber
 * schreibgeschützt wie ein freigegebenes.
 */
class ProtokollController extends CrewController
{
    /**
     * Die 7 Sections des Protokolls in Navigations-Reihenfolge. Der Schlüssel
     * ist der Slug in der URL, 'template' die Datei unter templates/protokoll/.
     */
    public const SECTIONS = [
        'mission'       => ['label' => 'Rett. Daten', 'icon' => 'fa-solid fa-clipboard-list',  'template' => 'rettdaten'],
        'assessment'    => ['label' => 'Erstbefund',  'icon' => 'fa-solid fa-stethoscope',     'template' => 'erstbefund'],
        'history'       => ['label' => 'Anamnese',    'icon' => 'fa-solid fa-comment-medical', 'template' => 'anamnese'],
        'diagnosis'     => ['label' => 'Diagnose',    'icon' => 'fa-solid fa-file-medical',    'template' => 'diagnose'],
        'progress'      => ['label' => 'Verlauf',     'icon' => 'fa-solid fa-chart-line',      'template' => 'verlauf'],
        'interventions' => ['label' => 'Maßnahmen',   'icon' => 'fa-solid fa-syringe',         'template' => 'massnahmen'],
        'completion'    => ['label' => 'Abschluss',   'icon' => 'fa-solid fa-flag-checkered',  'template' => 'abschluss'],
    ];

    /**
     * Slugs vor der Umstellung auf Englisch. Sie stehen in Lesezeichen,
     * Benachrichtigungen und Discord und leiten deshalb dauerhaft weiter.
     */
    public const LEGACY_SECTIONS = [
        'rettdaten'  => 'mission',
        'erstbefund' => 'assessment',
        'anamnese'   => 'history',
        'diagnose'   => 'diagnosis',
        'verlauf'    => 'progress',
        'massnahmen' => 'interventions',
        'abschluss'  => 'completion',
    ];

    public const DEFAULT_SECTION = 'mission';

    /**
     * GET /enotf/p/{enr}[/{section}]: Editor-Shell.
     */
    public function show(Request $request, string $enr, ?string $section = null): void
    {
        if ($section !== null && isset(self::LEGACY_SECTIONS[$section])) {
            $query = http_build_query($request->query);
            throw new RedirectException(
                EnotfUrl::protokoll($enr, self::LEGACY_SECTIONS[$section]) . ($query !== '' ? '?' . $query : ''),
                301,
            );
        }

        $this->bootPage();
        // Ohne Konto geht es nur mit Crew-Sitzung. Wer angemeldet ist, darf
        // lesen, was der Guard erlaubt (Leserecht oder eigenes Protokoll).
        if (empty($_SESSION['userid'])) {
            $this->requireCrewSession();
        }

        $service   = app(ProtokollService::class);
        $protokoll = $service->findByEnr($enr);

        // Fremde Fahrzeuge erfahren nicht, dass es die ENR gibt.
        if ($protokoll === null || !ProtokollAccessGuard::canRead($protokoll)) {
            http_response_code(404);
            $this->renderView('protokoll/not-found', ['enr' => $enr]);
            return;
        }

        $activeSection = ($section !== null && isset(self::SECTIONS[$section]))
            ? $section
            : self::DEFAULT_SECTION;

        // Kachel-Drilldown: ?t={thema} wählt die Fokus-Ansicht innerhalb
        // einer Section (z. B. assessment?t=atemwege). Validierung der
        // erlaubten Themen-Keys übernimmt das Section-Template.
        $fokusThema = $request->query['t'] ?? null;
        $fokusThema = is_string($fokusThema) && $fokusThema !== '' ? $fokusThema : null;

        $nurLesen = !ProtokollAccessGuard::vehicleMatches($protokoll);

        $this->renderView('protokoll/index', [
            'enr'                 => $enr,
            'protokoll'           => $protokoll,
            'sections'            => self::SECTIONS,
            'activeSection'       => $activeSection,
            'fokusThema'          => $fokusThema,
            'sectionTemplateFile' => $this->viewBasePath() . '/protokoll/' . self::SECTIONS[$activeSection]['template'] . '.php',
            'istGesperrt'         => $nurLesen || $service->istGesperrt($protokoll),
            'nurLesen'            => $nurLesen,
            'crew'                => $this->crewContext(),
        ]);
    }
}
