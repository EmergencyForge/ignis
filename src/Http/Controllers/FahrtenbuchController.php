<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Gate;
use App\Exceptions\ValidationException;
use App\Helpers\Flash;
use App\Http\Requests\Fahrtenbuch\CreateFahrtRequest;
use App\Http\Requests\Fahrtenbuch\UpdateFahrtRequest;
use App\Models\Fahrt;
use App\Utils\AuditLogger;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * FahrtenbuchController — Migration des `fahrtenbuch/`-Moduls.
 *
 * URL-Mapping:
 *   GET  /fahrtenbuch/index.php          → index()   (Admin-Liste mit Filter + Stats)
 *   POST /fahrtenbuch/actions.php (create) → store()
 *   POST /fahrtenbuch/actions.php (update) → update()
 *   POST /fahrtenbuch/actions.php (delete) → destroy()
 *
 * Multi-Context-Auth: Die store/update-Methoden akzeptieren BEIDE eingeloggte
 * Admin-User UND eNOTF-Sessions (set $_SESSION['fahrername']) UND FireTab-
 * Sessions (set $_SESSION['einsatz_vehicle_id']). Die Liste in index() ist
 * dagegen nur für Admins mit fahrtenbuch.view Permission.
 */
class FahrtenbuchController extends Controller
{
    /**
     * GET /fahrtenbuch — Admin-Übersicht mit Filter + Stats.
     *
     * Auth + PolicyMiddleware('fahrt.viewList') laufen im Router.
     */
    public function index(): void
    {
        $canManage = Gate::allows('fahrt.delete');

        // Fahrzeuge für Filter und Create-Form (mit Capsule, keine Eloquent-Modell)
        $vehicles = Capsule::table('intra_fahrzeuge')
            ->where('active', 1)
            ->orderBy('priority')
            ->orderBy('name')
            ->select('id', 'name', 'identifier', 'veh_type')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        // Filter-Parameter
        $filterVehicle  = isset($_GET['vehicle']) ? (int) $_GET['vehicle'] : 0;
        $filterFahrttyp = (string) ($_GET['fahrttyp'] ?? '');
        $filterDateFrom = (string) ($_GET['date_from'] ?? '');
        $filterDateTo   = (string) ($_GET['date_to'] ?? '');

        $tableExists = true;
        $entries     = [];
        $stats       = ['total' => 0, 'total_km' => 0];

        try {
            // Stats — direkt via Capsule, weil wir Aggregate brauchen
            $stats = [
                'total'    => (int) Fahrt::query()->count(),
                'total_km' => (float) (Fahrt::query()->sum('kilometer') ?? 0),
            ];

            // Filtered query — joint manuell auf intra_fahrzeuge für vehicle_name
            $query = Capsule::table('intra_fahrtenbuch as fb')
                ->leftJoin('intra_fahrzeuge as f', 'fb.vehicle_id', '=', 'f.id')
                ->select(
                    'fb.*',
                    'f.name as vehicle_name',
                    'f.veh_type'
                );

            if ($filterVehicle > 0) {
                $query->where('fb.vehicle_id', $filterVehicle);
            }
            if ($filterFahrttyp !== '' && isset(Fahrt::FAHRTTYPEN[$filterFahrttyp])) {
                $query->where('fb.fahrttyp', $filterFahrttyp);
            }
            if ($filterDateFrom !== '') {
                $query->where('fb.datum', '>=', $filterDateFrom);
            }
            if ($filterDateTo !== '') {
                $query->where('fb.datum', '<=', $filterDateTo);
            }

            $entries = $query
                ->orderBy('fb.datum', 'desc')
                ->orderBy('fb.abfahrt', 'desc')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        } catch (\Throwable $e) {
            $tableExists = false;
        }

        $this->renderView('fahrtenbuch/index', [
            'entries'        => $entries,
            'vehicles'       => $vehicles,
            'stats'          => $stats,
            'tableExists'    => $tableExists,
            'canManage'      => $canManage,
            'filterVehicle'  => $filterVehicle,
            'filterFahrttyp' => $filterFahrttyp,
            'filterDateFrom' => $filterDateFrom,
            'filterDateTo'   => $filterDateTo,
            'fahrttypen'    => Fahrt::FAHRTTYPEN,
            'fahrttypBadges' => Fahrt::FAHRTTYP_BADGES,
        ]);
    }

    /**
     * POST /fahrtenbuch/actions.php (action=create) — Eintrag anlegen.
     *
     * Multi-Context: Akzeptiert Admin/eNOTF/FireTab-Sessions. Auth-Check
     * läuft NICHT über die Standard-`requireAuth()` der Base-Klasse, weil
     * eNOTF/FireTab keine $_SESSION['userid'] haben.
     */
    public function store(): void
    {
        $this->requireAnyContext();
        $this->ensure('fahrt.create', redirectTo: 'index');

        try {
            $data = CreateFahrtRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirectByReturnTo();
        }

        // vehicle_id oder vehicle_identifier auflösen, falls eines fehlt
        $vehicleId         = $data['vehicle_id'];
        $vehicleIdentifier = $data['vehicle_identifier'];

        if ($vehicleId === null && $vehicleIdentifier !== '') {
            $vehicleId = Capsule::table('intra_fahrzeuge')
                ->where('identifier', $vehicleIdentifier)
                ->where('active', 1)
                ->value('id');
        }
        if ($vehicleIdentifier === '' && $vehicleId !== null) {
            $vehicleIdentifier = Capsule::table('intra_fahrzeuge')
                ->where('id', $vehicleId)
                ->value('identifier') ?: '';
        }

        $userId = (int) ($_SESSION['userid'] ?? 0);

        $fahrt = new Fahrt();
        $fahrt->vehicle_id         = $vehicleId;
        $fahrt->vehicle_identifier = $vehicleIdentifier;
        $fahrt->datum              = $data['datum'];
        $fahrt->abfahrt            = $data['abfahrt'];
        $fahrt->ankunft            = $data['ankunft'];
        $fahrt->stationierungsort  = $data['stationierungsort'];
        $fahrt->kilometer          = $data['kilometer'];
        $fahrt->grund              = $data['grund'];
        $fahrt->fahrttyp           = $data['fahrttyp'];
        $fahrt->fahrer_name        = $data['fahrer_name'];
        $fahrt->source             = $data['source'];
        $fahrt->created_by         = $userId > 0 ? $userId : null;
        $fahrt->save();

        if ($userId > 0) {
            (new AuditLogger($this->pdo))->log(
                $userId,
                'Fahrtenbuch-Eintrag erstellt',
                "Fahrzeug: $vehicleIdentifier, Fahrer: {$data['fahrer_name']}, Typ: {$data['fahrttyp']}",
                'Fahrtenbuch',
                1
            );
        }

        Flash::success('Fahrtenbuch-Eintrag erstellt.');
        $this->redirectByReturnTo();
    }

    /**
     * POST /fahrtenbuch/actions.php (action=update) — bestehenden Eintrag ändern.
     */
    public function update(): void
    {
        $this->requireAnyContext();

        try {
            $data = UpdateFahrtRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirectByReturnTo();
        }

        /** @var Fahrt|null $entry */
        $entry = Fahrt::find($data['id']);
        if ($entry === null) {
            Flash::error('Eintrag nicht gefunden.');
            $this->redirectByReturnTo();
        }

        // Multi-Context-Authorization via Policy
        if (Gate::denies('fahrt.update', $entry)) {
            Flash::error('Keine Berechtigung zum Bearbeiten.');
            $this->redirectByReturnTo();
        }

        // Felder updaten — leere Strings für vehicle/fahrer_name überschreiben
        // den bestehenden Wert nicht.
        $vehicleId         = $entry->vehicle_id;
        $vehicleIdentifier = $entry->vehicle_identifier;

        $isAdmin = isset($_SESSION['userid']);
        if ($isAdmin && $data['vehicle_id'] !== null) {
            $vehicleId         = $data['vehicle_id'];
            $vehicleIdentifier = Capsule::table('intra_fahrzeuge')
                ->where('id', $vehicleId)
                ->value('identifier') ?: $vehicleIdentifier;
        }

        $entry->datum              = $data['datum'];
        $entry->abfahrt            = $data['abfahrt'];
        $entry->ankunft            = $data['ankunft'];
        $entry->stationierungsort  = $data['stationierungsort'];
        $entry->kilometer          = $data['kilometer'];
        $entry->grund              = $data['grund'];
        $entry->fahrttyp           = $data['fahrttyp'];
        $entry->fahrer_name        = $data['fahrer_name'] !== '' ? $data['fahrer_name'] : $entry->fahrer_name;
        $entry->vehicle_id         = $vehicleId;
        $entry->vehicle_identifier = $vehicleIdentifier;
        $entry->save();

        $userId = (int) ($_SESSION['userid'] ?? 0);
        if ($userId > 0) {
            (new AuditLogger($this->pdo))->log(
                $userId,
                'Fahrtenbuch-Eintrag bearbeitet',
                "ID: {$data['id']}, Fahrzeug: $vehicleIdentifier",
                'Fahrtenbuch',
                1
            );
        }

        Flash::success('Eintrag aktualisiert.');
        $this->redirectByReturnTo();
    }

    /**
     * POST /fahrtenbuch/actions.php (action=delete) — Eintrag löschen.
     * Nur Admin mit `fahrt.delete`. eNOTF/FireTab dürfen nicht löschen —
     * deshalb `requireAuth()` (nicht `requireAnyContext()`) + inline Gate-Check,
     * weil der Dispatcher selber multi-context ist.
     */
    public function destroy(): void
    {
        $this->requireAuth();
        Gate::authorize('fahrt.delete');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::error('Ungültige ID.');
            $this->redirectByReturnTo();
        }

        Fahrt::query()->where('id', $id)->delete();

        (new AuditLogger($this->pdo))->log(
            (int) $_SESSION['userid'],
            'Fahrtenbuch-Eintrag gelöscht',
            'ID: ' . $id,
            'Fahrtenbuch',
            1
        );

        Flash::success('Eintrag gelöscht.');
        $this->redirectByReturnTo();
    }

    // -----------------------------------------------------------------------
    //  Multi-Context-spezifische Helpers
    // -----------------------------------------------------------------------

    /**
     * Wie requireAuth(), aber akzeptiert auch eNOTF/FireTab-Sessions.
     * Wenn keiner der drei Kontexte gegeben ist → Login-Redirect.
     */
    private function requireAnyContext(): void
    {
        $isAdmin   = isset($_SESSION['userid']);
        $isEnotf   = isset($_SESSION['fahrername']) && isset($_SESSION['protfzg']);
        $isFiretab = isset($_SESSION['einsatz_vehicle_id']);

        if (!$isAdmin && !$isEnotf && !$isFiretab) {
            Flash::error('Nicht authentifiziert.');
            $this->redirect('login');
        }
    }

    /**
     * Redirect basierend auf POST['return_to'] — die actions.php wird aus
     * 3 verschiedenen Kontexten aufgerufen (admin/enotf/firetab) und muss
     * jeweils zur richtigen Page zurückspringen.
     */
    private function redirectByReturnTo(): never
    {
        $returnTo = (string) ($_POST['return_to'] ?? 'admin');
        $target = match ($returnTo) {
            'enotf'   => 'enotf/fahrtenbuch.php',
            'firetab' => 'einsatz/fahrtenbuch.php',
            default   => 'fahrtenbuch/index.php',
        };
        $this->redirect($target);
    }
}
