<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Controllers;

use App\Models\Vehicle;
use Plugin\EnotfV2\Helpers\EnotfV2Url;
use Plugin\EnotfV2\Models\Edivi;

/**
 * CreateController — Protokoll anlegen.
 *
 * store() ist die Anlage-Logik aus assets/functions/enotf/enrbridge.php
 * als saubere Controller-Action, Semantik EXAKT wie dort:
 *
 *   1. Protokoll existiert + relevantes Fahrzeugfeld (fzg_na bei
 *      rd_type=1, sonst fzg_transp) belegt + kein force_create
 *      → Redirect zum bestehenden Protokoll.
 *   2. dito, aber force_create=1 → neue ENR mit Suffix `_1`, `_2`, …
 *      (race-anfällig wie v1; UNIQUE uk_enr fängt Kollisionen).
 *   3. Protokoll existiert, eigenes Fahrzeugfeld leer → UPDATE:
 *      Fahrzeug + Personal ("Name (Quali)") aus der Session eintragen.
 *   4. Protokoll existiert nicht → INSERT mit enr, prot_by, Fahrzeugfeld,
 *      edatum=heute, ezeit=jetzt, createdby=2 (manuell) + Personal.
 *
 * Der Konflikt-Dialog VOR dem Submit läuft clientseitig über den
 * v1-Endpoint POST /api/enotf/check-conflict (create.php + dialog.js);
 * dieser Server-Pfad ist der verbindliche Fallback.
 */
class CreateController extends EnotfV2Controller
{
    /**
     * GET /enotf-v2/create — Formular (ENR + Protokollart).
     */
    public function form(): void
    {
        $this->bootPage();
        $this->requireCrewSession();

        $this->renderView('create', [
            'crew' => $this->crewContext(),
        ]);
    }

    /**
     * POST /enotf-v2/create — Anlage-Logik (siehe Klassen-Docblock).
     */
    public function store(): void
    {
        $this->bootPage();
        $this->requireCrewSession();

        date_default_timezone_set('Europe/Berlin');

        $enr         = trim((string) ($_POST['enr'] ?? ''));
        $protBy      = (int) ($_POST['prot_by'] ?? 0);
        $forceCreate = (int) ($_POST['force_create'] ?? 0);

        // ENR-Format wie das v1-Frontend erzwingt: Ziffern + Unterstrich
        if ($enr === '' || !preg_match('/^[0-9_]+$/', $enr)) {
            $this->redirectAbsolute(EnotfV2Url::page('create', ['error' => 'invalid_enr']));
        }

        $existing = Edivi::query()
            ->where('enr', $enr)
            ->first(['fzg_transp', 'fzg_na']);

        // Fahrzeugtyp bestimmt das relevante Fahrzeugfeld
        $vehicleId = (string) $_SESSION['protfzg'];
        $fahrzeug  = Vehicle::query()
            ->where('identifier', $vehicleId)
            ->first(['rd_type']);

        $isDoctorVehicle = $fahrzeug !== null && (int) $fahrzeug->rd_type === 1;
        $fzgField        = $isDoctorVehicle ? 'fzg_na' : 'fzg_transp';
        $persoPrefix     = $isDoctorVehicle ? 'fzg_na_perso' : 'fzg_transp_perso';

        $fahrer     = $this->formatCrewMember('fahrername', 'fahrerquali');
        $beifahrer  = $this->formatCrewMember('beifahrername', 'beifahrerquali');
        $praktikant = $this->formatCrewMember('praktikantname', 'praktikantquali');

        if ($existing !== null && !empty($existing->{$fzgField})) {
            if ($forceCreate !== 1) {
                // Fall 1: belegt, kein force → zum bestehenden Protokoll
                $this->redirectAbsolute(EnotfV2Url::protokoll($enr));
            }

            // Fall 2: force → freie ENR mit Suffix suchen
            $enr = $this->nextFreeEnr($enr);
        } elseif ($existing !== null) {
            // Fall 3: existiert, eigener Slot leer → Fahrzeug + Personal ergänzen
            $update = [$fzgField => $vehicleId];
            if ($fahrer !== null) {
                $update[$persoPrefix] = $fahrer;
            }
            if ($beifahrer !== null) {
                $update[$persoPrefix . '_2'] = $beifahrer;
            }
            if ($praktikant !== null) {
                $update[$persoPrefix . '_3'] = $praktikant;
            }

            Edivi::query()->where('enr', $enr)->update($update);

            $this->redirectAbsolute(EnotfV2Url::protokoll($enr));
        }

        // Fall 4: neu anlegen (komplett neu oder mit Suffix aus Fall 2)
        $insert = [
            'enr'       => $enr,
            'prot_by'   => $protBy,
            $fzgField   => $vehicleId,
            'edatum'    => date('Y-m-d'),
            'ezeit'     => date('H:i'),
            'createdby' => Edivi::CREATEDBY_MANUELL,
        ];
        if ($fahrer !== null) {
            $insert[$persoPrefix] = $fahrer;
        }
        if ($beifahrer !== null) {
            $insert[$persoPrefix . '_2'] = $beifahrer;
        }
        if ($praktikant !== null) {
            $insert[$persoPrefix . '_3'] = $praktikant;
        }

        Edivi::query()->create($insert);

        $this->redirectAbsolute(EnotfV2Url::protokoll($enr));
    }

    /**
     * Nächste freie ENR mit Suffix `_1`, `_2`, … (v1-Semantik aus
     * enrbridge.php — race-anfällig, UNIQUE-Constraint fängt).
     */
    private function nextFreeEnr(string $originalEnr): string
    {
        $suffix = 1;
        do {
            $candidate = $originalEnr . '_' . $suffix;
            $exists    = Edivi::query()->where('enr', $candidate)->exists();
            if ($exists) {
                $suffix++;
            }
        } while ($exists);

        return $candidate;
    }
}
