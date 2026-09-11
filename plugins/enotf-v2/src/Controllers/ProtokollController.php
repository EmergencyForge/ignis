<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Controllers;

use App\Http\Request;
use Plugin\EnotfV2\Support\ProtokollService;

/**
 * ProtokollController — Shell des v2-Protokoll-Editors.
 *
 * Rendert das gemeinsame Layout mit Section-Stepper (statt der alten
 * Kachel-Navigation) und lädt in den Content-Bereich das Template der
 * aktiven Section.
 *
 * Section-Template-Auflösung: templates/protokoll/{section}.php wenn
 * vorhanden, sonst der Platzhalter section-stub.php. Neue Sections
 * brauchen also nur eine Datei mit dem Section-Namen — keine Routen-
 * oder Controller-Änderung.
 */
class ProtokollController extends EnotfV2Controller
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
     * GET /enotf-v2/p/{enr}[/{section}] — Editor-Shell.
     */
    public function show(Request $request, string $enr, ?string $section = null): void
    {
        $this->bootPage();
        $this->requireCrewSession();

        $service   = app(ProtokollService::class);
        $protokoll = $service->findByEnr($enr);

        if ($protokoll === null) {
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

        $this->renderView('protokoll/index', [
            'enr'                 => $enr,
            'protokoll'           => $protokoll,
            'sections'            => self::SECTIONS,
            'activeSection'       => $activeSection,
            'fokusThema'          => $fokusThema,
            'sectionTemplateFile' => $this->resolveSectionTemplate($activeSection),
            'istGesperrt'         => $service->istGesperrt($protokoll),
            'istUserGeloescht'    => $service->istUserGeloescht($protokoll),
            'crew'                => $this->crewContext(),
        ]);
    }

    /**
     * Absoluter Pfad zum Section-Template — Stub, solange die echte
     * Section-Datei noch nicht existiert.
     */
    private function resolveSectionTemplate(string $section): string
    {
        $candidate = $this->viewBasePath() . '/protokoll/' . $section . '.php';
        if (is_file($candidate)) {
            return $candidate;
        }

        return $this->viewBasePath() . '/protokoll/section-stub.php';
    }
}
