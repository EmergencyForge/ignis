<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Controllers;

use App\Federation\FederatedPersonnel;
use App\Session\SessionManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Enotf\EnotfSession as CrewSessionService;
use Plugin\EnotfV2\Helpers\EnotfV2Url;
use Plugin\EnotfV2\Policies\EnotfV2Policy;

/**
 * LoginController — Crew-Login, Session-Join und Logout für eNOTF v2.
 *
 * Die DB-Semantik läuft bewusst über den v1-Service
 * Plugin\Enotf\EnotfSession und SessionManager::loginEnotfCrew() —
 * dieselben Tabellen, dieselben $_SESSION-Keys, damit EIN Crew-Login
 * gleichzeitig für v1 und v2 gilt.
 *
 * Abweichung zu v1: Der Logout schreibt NUR auf POST
 * in die DB. GET /enotf-v2/loggedout zeigt eine Bestätigungs- bzw.
 * Abgemeldet-Seite ohne Side-Effects.
 */
class LoginController extends EnotfV2Controller
{
    /**
     * GET /enotf-v2/login — Login-Formular.
     */
    public function form(): void
    {
        $this->bootPage();

        $charLocked       = EnotfV2Policy::charLockEnabled() && !empty($_SESSION['char_name'] ?? '');
        $charName         = (string) ($_SESSION['char_name'] ?? '');
        $jobFilterEnabled = EnotfV2Policy::jobFilterEnabled();
        $charJob          = $_SESSION['char_job'] ?? null;

        // Personal-Liste: lokal + Federation (Namen mit [Instanz]-Suffix)
        $fullnames = [];
        foreach (FederatedPersonnel::getAllNames() as $entry) {
            $label = $entry['fullname'];
            if ($entry['source_name']) {
                $label .= ' [' . $entry['source_name'] . ']';
            }
            $fullnames[] = $label;
        }

        // Quali-Optionen (none=0, abkuerzung gesetzt)
        $qualifikationen = Capsule::table('intra_mitarbeiter_rdquali')
            ->where('none', 0)
            ->whereNotNull('abkuerzung')
            ->orderBy('priority')
            ->select('id', 'name', 'abkuerzung')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        // Fahrzeuge: aktiv, RD-Typ NA/Transport, optional Job-Filter
        $vehiclesQuery = Capsule::table('intra_fahrzeuge')
            ->where('active', 1)
            ->whereIn('rd_type', [1, 2])
            ->orderBy('priority');

        if ($jobFilterEnabled && !empty($charJob)) {
            $vehiclesQuery->where(function ($q) use ($charJob) {
                $q->whereNull('allowed_jobs')
                  ->orWhere('allowed_jobs', '')
                  ->orWhereRaw('FIND_IN_SET(?, allowed_jobs) > 0', [$charJob]);
            });
        }
        $vehicles = $vehiclesQuery->get()->map(fn ($r) => (array) $r)->all();

        // Prefill aus bestehender Crew-Session (?prefill=1)
        $prefill = [];
        if (($_GET['prefill'] ?? null) === '1' && isset($_SESSION['fahrername'])) {
            $prefill = [
                'fahrername'      => $_SESSION['fahrername'] ?? '',
                'fahrerquali'     => $_SESSION['fahrerquali'] ?? '',
                'beifahrername'   => $_SESSION['beifahrername'] ?? '',
                'beifahrerquali'  => $_SESSION['beifahrerquali'] ?? '',
                'praktikantname'  => $_SESSION['praktikantname'] ?? '',
                'praktikantquali' => $_SESSION['praktikantquali'] ?? '',
                'protfzg'         => $_SESSION['protfzg'] ?? '',
            ];
        }

        $this->renderView('login', [
            'charLocked'      => $charLocked,
            'charName'        => $charName,
            'fullnames'       => $fullnames,
            'qualifikationen' => $qualifikationen,
            'vehicles'        => $vehicles,
            'prefill'         => $prefill,
            'loginError'      => (string) ($_GET['error'] ?? ''),
        ]);
    }

    /**
     * POST /enotf-v2/login — Login durchführen (Mode 'new' oder 'join').
     * Semantik identisch zu v1.
     */
    public function login(): void
    {
        $this->bootPage();

        $charLocked = EnotfV2Policy::charLockEnabled() && !empty($_SESSION['char_name'] ?? '');
        $charName   = (string) ($_SESSION['char_name'] ?? '');

        $mode    = $_POST['login_mode'] ?? 'new';
        $vehicle = (string) ($_POST['protfzg'] ?? '');

        $sessionService = new CrewSessionService();

        if ($mode === 'join') {
            $joinPosition = $_POST['join_position'] ?? null;
            $joinName     = $_POST['join_name'] ?? null;
            $joinQuali    = $_POST['join_quali'] ?? null;

            if ($charLocked && $joinName !== $charName) {
                $this->redirectAbsolute(EnotfV2Url::page('login', ['error' => 'char_mismatch']));
            }

            if ($joinPosition && $joinName) {
                $existingSession = $sessionService->findActiveByVehicle($vehicle);
                if ($existingSession) {
                    $result = $sessionService->joinSession(
                        (int) $existingSession['id'],
                        (string) $joinPosition,
                        (string) $joinName,
                        $joinQuali !== null ? (string) $joinQuali : null,
                    );

                    if ($result !== null) {
                        $sessionData = $result['session_data'];
                        SessionManager::loginEnotfCrew(
                            (string) $joinPosition,
                            $result['session_token'],
                            [
                                'fahrer'     => ['name' => $sessionData['fahrername'],     'quali' => $sessionData['fahrerquali']],
                                'beifahrer'  => ['name' => $sessionData['beifahrername'],  'quali' => $sessionData['beifahrerquali']],
                                'praktikant' => ['name' => $sessionData['praktikantname'], 'quali' => $sessionData['praktikantquali']],
                            ],
                            $vehicle,
                        );
                        $this->redirectAbsolute(EnotfV2Url::page('overview'));
                    }
                }
            }
            // Join nicht möglich → Fallback auf normale Anmeldung
            $mode = 'new';
        }

        // Mode 'new'
        if ($charLocked) {
            $submittedNames = [
                'fahrer'     => $_POST['fahrername'] ?? '',
                'beifahrer'  => $_POST['beifahrername'] ?? '',
                'praktikant' => $_POST['praktikantname'] ?? '',
            ];
            if (!in_array($charName, $submittedNames, true)) {
                $this->redirectAbsolute(EnotfV2Url::page('login', ['error' => 'char_mismatch']));
            }
        }

        $crew = [
            'fahrername'      => $_POST['fahrername'] ?? '',
            'fahrerquali'     => $_POST['fahrerquali'] ?? null,
            'beifahrername'   => $_POST['beifahrername'] ?? null,
            'beifahrerquali'  => $_POST['beifahrerquali'] ?? null,
            'praktikantname'  => $_POST['praktikantname'] ?? null,
            'praktikantquali' => $_POST['praktikantquali'] ?? null,
        ];

        // Gültigen eigenen Member-Token wiederverwenden → nur Crew-Update
        $existingToken     = $_SESSION['enotf_session_token'] ?? null;
        $existingSessionId = null;
        if ($existingToken) {
            $existingSessionId = $sessionService->findSessionIdByTokenAndVehicle((string) $existingToken, $vehicle);
        }

        if ($existingSessionId) {
            $sessionService->updateCrew($existingSessionId, $crew);
            SessionManager::loginEnotfCrew(
                (string) ($_SESSION['enotf_position'] ?? ''),
                (string) $existingToken,
                $this->crewArrayToStruct($crew),
                $vehicle,
            );
        } else {
            $result = $sessionService->createSession($vehicle, $crew);
            SessionManager::loginEnotfCrew(
                $result['position'],
                $result['session_token'],
                $this->crewArrayToStruct($crew),
                $vehicle,
            );
        }

        $this->redirectAbsolute(EnotfV2Url::page('overview'));
    }

    /**
     * GET /enotf-v2/loggedout — reine Anzeige, KEIN DB-Write.
     *
     * Mit aktiver Crew-Session: Bestätigungsseite mit den beiden
     * Logout-Optionen (POST mode=self|all). Ohne Session: „Abgemeldet"-
     * Seite mit Login-Link.
     */
    public function loggedOut(): void
    {
        $this->bootPage();

        $this->renderView('loggedout', [
            'hasCrewSession' => EnotfV2Policy::hasCrewSession(),
            'crew'           => $this->crewContext(),
        ]);
    }

    /**
     * POST /enotf-v2/loggedout — Logout ausführen (mode=self|all).
     * DB-Semantik exakt wie v1:
     *   self → eigenen Member entfernen + Position leeren; war es die
     *          letzte besetzte Position, wird die Session deaktiviert.
     *   all  → alle aktiven Sessions des Fahrzeugs deaktivieren.
     */
    public function logout(): void
    {
        $this->bootPage();

        $mode         = $_POST['mode'] ?? 'all';
        $vehicle      = $_SESSION['protfzg'] ?? null;
        $position     = $_SESSION['enotf_position'] ?? null;
        $sessionToken = $_SESSION['enotf_session_token'] ?? null;

        $sessionService = new CrewSessionService();

        if ($mode === 'self' && $vehicle && $position && $sessionToken) {
            $sessionService->removeMember((string) $sessionToken, (string) $position);
        } elseif ($vehicle) {
            $sessionService->deactivateAllForVehicle((string) $vehicle);
        }

        SessionManager::logoutEnotfCrew();

        // Redirect-after-POST auf die Anzeige-Seite (kein Re-Submit bei F5)
        $this->redirectAbsolute(EnotfV2Url::page('loggedout'));
    }

    /**
     * Flaches crew-Array → Struktur für SessionManager::loginEnotfCrew().
     */
    private function crewArrayToStruct(array $crew): array
    {
        return [
            'fahrer'     => ['name' => $crew['fahrername']     ?? '', 'quali' => $crew['fahrerquali']     ?? ''],
            'beifahrer'  => ['name' => $crew['beifahrername']  ?? '', 'quali' => $crew['beifahrerquali']  ?? ''],
            'praktikant' => ['name' => $crew['praktikantname'] ?? '', 'quali' => $crew['praktikantquali'] ?? ''],
        ];
    }
}
