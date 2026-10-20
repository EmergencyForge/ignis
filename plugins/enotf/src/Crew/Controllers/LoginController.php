<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Controllers;

use App\Federation\FederatedPersonnel;
use App\Session\SessionManager;
use EmergencyForge\Http\Exceptions\ValidationException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Enotf\EnotfSession as CrewSessionService;
use Plugin\Enotf\Helpers\EnotfUrl;
use Plugin\Enotf\Crew\Policies\CrewPolicy;
use Plugin\Enotf\Crew\Requests\CrewLoginRequest;
use Plugin\Enotf\Crew\Requests\CrewLogoutRequest;
use Plugin\Enotf\Crew\Support\CrewSessionGuard;

/**
 * LoginController: Crew-Login, Session-Join und Logout für eNOTF v2.
 *
 * Die DB-Semantik läuft bewusst über den v1-Service
 * Plugin\Enotf\EnotfSession und SessionManager::loginEnotfCrew(),
 * dieselben Tabellen, dieselben $_SESSION-Keys, damit EIN Crew-Login
 * gleichzeitig für v1 und v2 gilt.
 *
 * Abweichung zu v1: Der Logout schreibt NUR auf POST
 * in die DB. GET /enotf/loggedout zeigt eine Bestätigungs- bzw.
 * Abgemeldet-Seite ohne Side-Effects.
 */
class LoginController extends CrewController
{
    /**
     * GET /enotf/login: Login-Formular.
     */
    public function form(): void
    {
        $this->bootPage();

        $charLocked       = CrewPolicy::charLockEnabled() && !empty($_SESSION['char_name'] ?? '');
        $charName         = (string) ($_SESSION['char_name'] ?? '');

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

        $vehicles = $this->selectableVehicles()->orderBy('priority')->get()->map(fn ($r) => (array) $r)->all();

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
            'personnelQuali'  => FederatedPersonnel::rdQualiByName(),
            'qualifikationen' => $qualifikationen,
            'vehicles'        => $vehicles,
            'prefill'         => $prefill,
            'loginError'      => (string) ($_GET['error'] ?? ''),
        ]);
    }

    /**
     * POST /enotf/login: Login durchführen (Mode 'new' oder 'join').
     * Semantik identisch zu v1.
     */
    public function login(): void
    {
        $this->bootPage();

        $charLocked = CrewPolicy::charLockEnabled() && !empty($_SESSION['char_name'] ?? '');
        $charName   = (string) ($_SESSION['char_name'] ?? '');

        try {
            $input = CrewLoginRequest::validate($_POST);
        } catch (ValidationException) {
            $this->redirectAbsolute(EnotfUrl::page('login'));
        }

        $mode    = $input['login_mode'];
        $vehicle = (string) $input['protfzg'];

        // Dieselbe Auswahl wie im Formular: aktiv, RD-Fahrzeug, Job-Filter.
        // Ein selbst gebauter POST kommt sonst auf jedes Fahrzeug.
        if (!$this->selectableVehicles()->where('identifier', $vehicle)->exists()) {
            $this->redirectAbsolute(EnotfUrl::page('login', ['error' => 'vehicle']));
        }

        $sessionService = new CrewSessionService();

        if ($mode === 'join') {
            $joinPosition = $input['join_position'];
            $joinName     = $input['join_name'];
            $joinQuali    = $input['join_quali'];

            if ($charLocked && $joinName !== $charName) {
                $this->redirectAbsolute(EnotfUrl::page('login', ['error' => 'char_mismatch']));
            }

            if ($joinPosition && $joinName) {
                $existingSession = $sessionService->findActiveByVehicle($vehicle);
                if ($existingSession) {
                    $this->leaveOtherVehicle($sessionService, $vehicle);

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
                        $this->redirectAbsolute(EnotfUrl::page('overview'));
                    }

                    // Position inzwischen besetzt: nicht still eine neue
                    // Besatzung anlegen, das würde die bestehende abmelden
                    $this->redirectAbsolute(EnotfUrl::page('login', ['error' => 'position_taken']));
                }
            }
            // Keine Session mehr zum Beitreten → normale Anmeldung
            $mode = 'new';
        }

        // Mode 'new'
        if ($charLocked) {
            $submittedNames = [
                'fahrer'     => $input['fahrername'] ?? '',
                'beifahrer'  => $input['beifahrername'] ?? '',
                'praktikant' => $input['praktikantname'] ?? '',
            ];
            if (!in_array($charName, $submittedNames, true)) {
                $this->redirectAbsolute(EnotfUrl::page('login', ['error' => 'char_mismatch']));
            }
        }

        $crew = [
            'fahrername'      => $input['fahrername'] ?? '',
            'fahrerquali'     => $input['fahrerquali'],
            'beifahrername'   => $input['beifahrername'],
            'beifahrerquali'  => $input['beifahrerquali'],
            'praktikantname'  => $input['praktikantname'],
            'praktikantquali' => $input['praktikantquali'],
        ];

        // Wechsel auf ein anderes Fahrzeug: die Position auf dem alten
        // Fahrzeug freigeben, sonst bleibt dort eine verwaiste Session aktiv
        $this->leaveOtherVehicle($sessionService, $vehicle);

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

        $this->redirectAbsolute(EnotfUrl::page('overview'));
    }

    /**
     * GET /enotf/loggedout: reine Anzeige, KEIN DB-Write.
     *
     * Mit aktiver Crew-Session: Bestätigungsseite mit den beiden
     * Logout-Optionen (POST mode=self|all). Ohne Session: „Abgemeldet"-
     * Seite mit Login-Link.
     */
    public function loggedOut(): void
    {
        $this->bootPage();

        $this->renderView('loggedout', [
            'hasCrewSession' => CrewPolicy::hasCrewSession(),
            'crew'           => $this->crewContext(),
            'ausserDienst'   => ($_GET['grund'] ?? '') === 'ausser-dienst',
            'endedText'      => CrewSessionGuard::message((string) ($_GET['grund'] ?? '')),
        ]);
    }

    /**
     * POST /enotf/loggedout: Logout ausführen (mode=self|all).
     * DB-Semantik exakt wie v1:
     *   self → eigenen Member entfernen + Position leeren; war es die
     *          letzte besetzte Position, wird die Session deaktiviert.
     *   all  → alle aktiven Sessions des Fahrzeugs deaktivieren.
     */
    public function logout(): void
    {
        $this->bootPage();

        try {
            $mode = CrewLogoutRequest::validate($_POST)['mode'];
        } catch (ValidationException) {
            $this->redirectAbsolute(EnotfUrl::page('loggedout'));
        }

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
        $this->redirectAbsolute(EnotfUrl::page('loggedout'));
    }

    /**
     * Fahrzeuge, auf denen sich eine Crew anmelden darf: aktiv, RD-Typ
     * NA/Transport, mit Job-Filter nur die für den Job des Charakters.
     */
    private function selectableVehicles(): \Illuminate\Database\Query\Builder
    {
        $query = Capsule::table('intra_fahrzeuge')
            ->where('active', 1)
            ->whereIn('rd_type', [1, 2]);

        $charJob = $_SESSION['char_job'] ?? null;
        if (CrewPolicy::jobFilterEnabled() && !empty($charJob)) {
            $query->where(function ($q) use ($charJob) {
                $q->whereNull('allowed_jobs')
                  ->orWhere('allowed_jobs', '')
                  ->orWhereRaw('FIND_IN_SET(?, allowed_jobs) > 0', [$charJob]);
            });
        }

        return $query;
    }

    /**
     * Ist dieser Browser auf einem anderen Fahrzeug angemeldet, gibt er
     * dort seine Position frei (wie „Nur mich abmelden“).
     */
    private function leaveOtherVehicle(CrewSessionService $sessionService, string $vehicle): void
    {
        $token = (string) ($_SESSION['enotf_session_token'] ?? '');
        $own   = $sessionService->findByToken($token);
        if ($own === null || (string) $own['vehicle_identifier'] === $vehicle) {
            return;
        }

        $sessionService->removeMember($token, (string) $own['member_position']);
        SessionManager::logoutEnotfCrew();
    }

    /**
     * Flaches crew-Array → Struktur für SessionManager::loginEnotfCrew().
     *
     * @param array<string, mixed> $crew
     * @return array{fahrer: array{name: mixed, quali: mixed}, beifahrer: array{name: mixed, quali: mixed}, praktikant: array{name: mixed, quali: mixed}}
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
