<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Http\Requests\Personnel\SaveFireSkillRequest;
use App\Http\Requests\Personnel\SaveMedicSkillRequest;
use App\Http\Requests\Personnel\SaveRankRequest;
use App\Http\Requests\Personnel\SaveSpecialtyRequest;
use App\Utils\AuditLogger;
use EmergencyForge\Http\Exceptions\ValidationException;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * Die vier Stammdaten-Kataloge des Personals: Dienstgrade,
 * Feuerwehr-Qualifikationen, Rettungsdienst-Qualifikationen, Fachdienste.
 *
 * Alle vier sind dasselbe: eine Liste mit Modal, dazu drei POST-Ziele für
 * Anlegen, Ändern und Löschen. Deshalb steht das Gemeinsame in
 * {@see store()}, {@see update()} und {@see destroy()}, und die zwölf
 * öffentlichen Methoden sagen nur noch, um welchen Katalog es geht — die
 * Routen sind fest verdrahtet, sonst wären es drei Methoden mit einem
 * Parameter.
 *
 * Geprüft wird über FormRequests. Vorher las jede Methode ihre Felder
 * einzeln aus `$_POST` und fragte, ob drei davon nicht leer sind; die
 * Länge fragte niemand. Ein Name über 255 Zeichen lief damit in eine
 * PDOException, die als „exception" im Hinweis landete — oder, je nach
 * SQL-Modus, wurde stumm abgeschnitten.
 */
class PersonalController extends Controller
{
    /**
     * Die vier Kataloge: Tabelle, Regelmenge, Spalte für die Sortierung,
     * Seite, Modul im Prüfprotokoll und die Bezeichnung für dessen Text.
     *
     * @var array<string,array{
     *     table: string,
     *     request: class-string<\App\Http\Requests\FormRequest>,
     *     order: string,
     *     view: string,
     *     path: string,
     *     module: string,
     *     label: string,
     *     flash: string,
     * }>
     *
     * `flash` ist der Schlüssel in der Meldungstabelle von
     * {@see \App\Helpers\Flash}. Die drei Qualifikationskataloge standen
     * dort auf `quali` — ein Schlüssel, den die Tabelle nicht kennt, und
     * `Flash::set()` gibt bei einem unbekannten stillschweigend auf.
     * Anlegen und Löschen meldeten deshalb nie etwas, weder Erfolg noch
     * Fehler. Der richtige Schlüssel heißt `qualification`.
     */
    private const KATALOGE = [
        'ranks' => [
            'table'   => 'intra_mitarbeiter_dienstgrade',
            'request' => SaveRankRequest::class,
            'order'   => 'priority',
            'view'    => 'settings/personnel/ranks',
            'path'    => 'settings/personnel/ranks',
            'module'  => 'Dienstgrade',
            'label'   => 'Rank',
            'flash'   => 'rank',
        ],
        'fireSkills' => [
            'table'   => 'intra_mitarbeiter_fwquali',
            'request' => SaveFireSkillRequest::class,
            'order'   => 'priority',
            'view'    => 'settings/personnel/fdskills',
            'path'    => 'settings/personnel/fdskills',
            'module'  => 'FW-Qualifikationen',
            'label'   => 'FW-Qualifikation',
            'flash'   => 'qualification',
        ],
        'medicSkills' => [
            'table'   => 'intra_mitarbeiter_rdquali',
            'request' => SaveMedicSkillRequest::class,
            'order'   => 'priority',
            'view'    => 'settings/personnel/ambskills',
            'path'    => 'settings/personnel/ambskills',
            'module'  => 'RD-Qualifikationen',
            'label'   => 'RD-Qualifikation',
            'flash'   => 'qualification',
        ],
        'specialties' => [
            'table'   => 'intra_mitarbeiter_fdquali',
            'request' => SaveSpecialtyRequest::class,
            'order'   => 'sgnr',
            'view'    => 'settings/personnel/specialties',
            'path'    => 'settings/personnel/specialties',
            'module'  => 'Fachdienste',
            'label'   => 'Fachdienst',
            'flash'   => 'qualification',
        ],
    ];

    // ── Dienstgrade ─────────────────────────────────────────────

    public function dienstgradeIndex(): void
    {
        $this->index('ranks', 'ranks');
    }

    public function dienstgradStore(): void
    {
        $this->store('ranks');
    }

    public function dienstgradUpdate(): void
    {
        $this->update('ranks');
    }

    public function dienstgradDelete(): void
    {
        $this->destroy('ranks');
    }

    // ── FW-Qualifikationen ──────────────────────────────────────

    public function fwQualiIndex(): void
    {
        $this->index('fireSkills', 'qualis');
    }

    public function fwQualiStore(): void
    {
        $this->store('fireSkills');
    }

    public function fwQualiUpdate(): void
    {
        $this->update('fireSkills');
    }

    public function fwQualiDelete(): void
    {
        $this->destroy('fireSkills');
    }

    // ── RD-Qualifikationen ──────────────────────────────────────

    public function rdQualiIndex(): void
    {
        $this->index('medicSkills', 'qualis');
    }

    public function rdQualiStore(): void
    {
        $this->store('medicSkills');
    }

    public function rdQualiUpdate(): void
    {
        $this->update('medicSkills');
    }

    public function rdQualiDelete(): void
    {
        $this->destroy('medicSkills');
    }

    // ── Fachdienste (FD) ────────────────────────────────────────

    public function fdQualiIndex(): void
    {
        $this->index('specialties', 'qualis');
    }

    public function fdQualiStore(): void
    {
        $this->store('specialties');
    }

    public function fdQualiUpdate(): void
    {
        $this->update('specialties');
    }

    public function fdQualiDelete(): void
    {
        $this->destroy('specialties');
    }

    // ── Das Gemeinsame ──────────────────────────────────────────

    /** @param non-empty-string $variable Name der Liste in der Ansicht */
    private function index(string $katalog, string $variable): void
    {
        $this->requireAuth();
        $this->ensureView();

        $k    = self::KATALOGE[$katalog];
        $rows = Capsule::table($k['table'])
            ->orderBy($k['order'])
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->renderView($k['view'], [$variable => $rows]);
    }

    private function store(string $katalog): void
    {
        $k    = self::KATALOGE[$katalog];
        $data = $this->validated($k);
        unset($data['id']);

        $this->write(
            $k,
            static fn () => Capsule::table($k['table'])->insert($data),
            $k['flash'],
            'created',
            $k['label'] . ' erstellt',
            $this->beschreibung($data),
        );
    }

    private function update(string $katalog): void
    {
        $k    = self::KATALOGE[$katalog];
        $data = $this->validated($k);
        $id   = (int) $data['id'];
        unset($data['id']);

        if ($id <= 0) {
            Flash::set('error', 'missing-fields');
            $this->redirect($k['path'] . '/index');
        }

        // „updated" steht in der Meldungstabelle unter `success`, nicht
        // unter dem Katalog.
        $this->write(
            $k,
            static fn () => Capsule::table($k['table'])->where('id', $id)->update($data),
            'success',
            'updated',
            $k['label'] . ' aktualisiert [ID: ' . $id . ']',
            null,
            $id,
        );
    }

    private function destroy(string $katalog): void
    {
        $k = self::KATALOGE[$katalog];
        $this->requireAuth();
        $this->ensureAdmin($k['path'] . '/index.php');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::set($k['flash'], 'invalid-id');
            $this->redirect($k['path'] . '/index');
        }

        // Der Dienstgrad meldete „nicht gefunden", die drei anderen
        // löschten stillschweigend nichts. Jetzt alle gleich.
        if (!Capsule::table($k['table'])->where('id', $id)->exists()) {
            Flash::set($k['flash'], 'not-found');
            $this->redirect($k['path'] . '/index');
        }

        $this->write(
            $k,
            static fn () => Capsule::table($k['table'])->where('id', $id)->delete(),
            $k['flash'],
            'deleted',
            $k['label'] . ' gelöscht [ID: ' . $id . ']',
            null,
            $id,
        );
    }

    /**
     * Rechte prüfen und den Post durch die Regelmenge des Katalogs
     * schicken. Beides gehört zusammen: ohne Rechte wird gar nicht erst
     * gelesen.
     *
     * @param  array<string,mixed> $k
     * @return array<string,mixed>
     */
    private function validated(array $k): array
    {
        $this->requireAuth();
        $this->ensureAdmin($k['path'] . '/index.php');

        /** @var class-string<\App\Http\Requests\FormRequest> $request */
        $request = $k['request'];

        try {
            return $request::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect($k['path'] . '/index');
        }
    }

    /**
     * Schreiben, melden, protokollieren — und bei einem Datenbankfehler
     * dasselbe wie vorher: ins Fehlerprotokoll, „exception" für den Nutzer.
     *
     * @param array<string,mixed> $k
     * @param callable():mixed    $schreiben
     */
    private function write(
        array $k,
        callable $schreiben,
        string $flashTyp,
        string $flashSchluessel,
        string $aktion,
        ?string $details = null,
        ?int $id = null,
    ): void {
        try {
            $schreiben();
            Flash::set($flashTyp, $flashSchluessel);
            $this->audit($aktion, $details, $k['module'], $id);
        } catch (PDOException $e) {
            error_log('PDO Error (' . $k['table'] . '): ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect($k['path'] . '/index');
    }

    /**
     * Die Zeile im Prüfprotokoll: der Name, wenn der Katalog einen hat,
     * sonst nichts.
     *
     * @param array<string,mixed> $data
     */
    private function beschreibung(array $data): ?string
    {
        $name = $data['name'] ?? $data['sgname'] ?? null;

        return is_string($name) ? 'Name: ' . $name : null;
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * View-Guard: erlaubt admin oder personnel.view.
     * Redirect zur Index, falls keine Berechtigung.
     */
    private function ensureView(): void
    {
        if (!Gate::allows('personnel.viewList')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }
    }

    /**
     * Admin-only Guard. Bei Denial: Flash + Redirect zur angegebenen Seite.
     */
    private function ensureAdmin(string $redirect): void
    {
        if (!Gate::allows('system.admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect($redirect);
        }
    }

    /**
     * Schreibt ins Prüfprotokoll, sofern jemand angemeldet ist.
     *
     * Die Kennung geht als `context.id` mit — darüber findet
     * {@see \App\Support\Activity} den Eintrag, ohne sie aus dem
     * Meldungstext klauben zu müssen.
     */
    private function audit(string $action, ?string $details, string $category, ?int $id = null): void
    {
        if (!isset($_SESSION['userid'])) {
            return;
        }

        (new AuditLogger())->log(
            $_SESSION['userid'],
            $action,
            $details,
            $category,
            1,
            $id === null ? [] : ['id' => $id],
        );
    }
}
