<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Controllers;

use EmergencyForge\Http\Request;
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
     * Die 7 Sections des Protokolls in Navigations-Reihenfolge.
     */
    public const SECTIONS = [
        'rettdaten'  => ['label' => 'Rett. Daten', 'icon' => 'fa-solid fa-clipboard-list'],
        'erstbefund' => ['label' => 'Erstbefund',  'icon' => 'fa-solid fa-stethoscope'],
        'anamnese'   => ['label' => 'Anamnese',    'icon' => 'fa-solid fa-comment-medical'],
        'diagnose'   => ['label' => 'Diagnose',    'icon' => 'fa-solid fa-file-medical'],
        'verlauf'    => ['label' => 'Verlauf',     'icon' => 'fa-solid fa-chart-line'],
        'massnahmen' => ['label' => 'Maßnahmen',   'icon' => 'fa-solid fa-syringe'],
        'abschluss'  => ['label' => 'Abschluss',   'icon' => 'fa-solid fa-flag-checkered'],
    ];

    public const DEFAULT_SECTION = 'rettdaten';

    /**
     * GET /enotf/p/{enr}[/{section}]: Editor-Shell.
     */
    public function show(Request $request, string $enr, ?string $section = null): void
    {
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
        // einer Section (z. B. erstbefund?t=atemwege). Validierung der
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
            'sectionTemplateFile' => $this->viewBasePath() . '/protokoll/' . $activeSection . '.php',
            'istGesperrt'         => $nurLesen || $service->istGesperrt($protokoll),
            'nurLesen'            => $nurLesen,
            'crew'                => $this->crewContext(),
        ]);
    }
}
