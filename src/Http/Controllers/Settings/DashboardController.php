<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveDashboardCategoryRequest;
use App\Http\Requests\Settings\SaveDashboardTileRequest;
use App\Utils\AuditLogger;
use EmergencyForge\Http\Exceptions\ValidationException;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * Die Dashboard-Konfiguration: Kategorien und die Verlinkungen darin.
 *
 * Beide sind derselbe Ablauf, deshalb steht das Gemeinsame in
 * {@see store()}, {@see update()} und {@see destroy()}.
 *
 * Geprüft wird über FormRequests. Am Ziel einer Verlinkung hängt mehr
 * daran, als es aussieht — siehe {@see SaveDashboardTileRequest}: es
 * landet auf dem Dashboard in einem `href`, und bis hierher nahm der
 * Controller jeden Wert, `javascript:` eingeschlossen.
 */
class DashboardController extends Controller
{
    private const SEITE = 'settings/dashboard/index';

    /**
     * @var array<string,array{
     *     table: string,
     *     request: class-string<\App\Http\Requests\FormRequest>,
     *     flash: string,
     *     label: string,
     * }>
     */
    private const BEREICHE = [
        'category' => [
            'table'   => 'intra_dashboard_categories',
            'request' => SaveDashboardCategoryRequest::class,
            'flash'   => 'dashboard.category',
            'label'   => 'Kategorie',
        ],
        'tile' => [
            'table'   => 'intra_dashboard_tiles',
            'request' => SaveDashboardTileRequest::class,
            'flash'   => 'dashboard.tile',
            'label'   => 'Verlinkung',
        ],
    ];

    public function index(): void
    {
        $this->requireAuth();
        $this->ensureManage('index.php');

        $categories = Capsule::table('intra_dashboard_categories')
            ->orderBy('priority')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $tiles = Capsule::table('intra_dashboard_tiles')
            ->orderBy('priority')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $tilesByCategory = [];
        foreach ($tiles as $tile) {
            $tilesByCategory[(int) $tile['category']][] = $tile;
        }

        $this->renderView('settings/dashboard/index', [
            'categories'      => $categories,
            'tilesByCategory' => $tilesByCategory,
        ]);
    }

    // ── Kategorien ─────────────────────────────────────────

    public function categoryStore(): void
    {
        $this->store('category');
    }

    public function categoryUpdate(): void
    {
        $this->update('category');
    }

    public function categoryDestroy(): void
    {
        $this->destroy('category');
    }

    // ── Verlinkungen ───────────────────────────────────────

    public function tileStore(): void
    {
        $this->store('tile');
    }

    public function tileUpdate(): void
    {
        $this->update('tile');
    }

    public function tileDestroy(): void
    {
        $this->destroy('tile');
    }

    // ── Das Gemeinsame ─────────────────────────────────────

    private function store(string $bereich): void
    {
        $b    = self::BEREICHE[$bereich];
        $data = $this->validated($b);
        unset($data['id']);

        $this->write(
            $b,
            static fn () => Capsule::table($b['table'])->insert($data),
            $b['flash'],
            'created',
            $b['label'] . ' erstellt',
            'Titel: ' . $data['title'],
        );
    }

    private function update(string $bereich): void
    {
        $b    = self::BEREICHE[$bereich];
        $data = $this->validated($b);
        $id   = (int) $data['id'];
        unset($data['id']);

        if ($id <= 0) {
            Flash::set('error', 'missing-fields');
            $this->redirect(self::SEITE);
        }

        // „updated" steht in der Meldungstabelle unter `success`.
        $this->write(
            $b,
            static fn () => Capsule::table($b['table'])->where('id', $id)->update($data),
            'success',
            'updated',
            $b['label'] . ' aktualisiert [ID: ' . $id . ']',
            null,
            $id,
        );
    }

    private function destroy(string $bereich): void
    {
        $b = self::BEREICHE[$bereich];
        $this->ensureManage(self::SEITE . '.php');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::set($b['flash'], 'invalid-id');
            $this->redirect(self::SEITE);
        }

        if (!Capsule::table($b['table'])->where('id', $id)->exists()) {
            Flash::set($b['flash'], 'not-found');
            $this->redirect(self::SEITE);
        }

        $this->write(
            $b,
            static fn () => Capsule::table($b['table'])->where('id', $id)->delete(),
            $b['flash'],
            'deleted',
            $b['label'] . ' gelöscht [ID: ' . $id . ']',
            null,
            $id,
        );
    }

    /**
     * @param  array<string,mixed> $b
     * @return array<string,mixed>
     */
    private function validated(array $b): array
    {
        $this->ensureManage(self::SEITE . '.php');

        /** @var class-string<\App\Http\Requests\FormRequest> $request */
        $request = $b['request'];

        try {
            return $request::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect(self::SEITE);
        }
    }

    /**
     * @param array<string,mixed> $b
     * @param callable():mixed    $schreiben
     */
    private function write(
        array $b,
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
            $this->audit($aktion, $details, $id);
        } catch (PDOException $e) {
            error_log('PDO Error (' . $b['table'] . '): ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect(self::SEITE);
    }

    private function ensureManage(string $redirect): void
    {
        $this->requireAuth();
        if (!Gate::allows('system.manageDashboard')) {
            Flash::set('error', 'no-permissions');
            $this->redirect($redirect);
        }
    }

    /**
     * Schreibt ins Prüfprotokoll. Die Kennung geht als `context.id` mit,
     * damit {@see \App\Support\Activity} sie nicht aus dem Meldungstext
     * klauben muss.
     */
    private function audit(string $action, ?string $details, ?int $id = null): void
    {
        if (!isset($_SESSION['userid'])) {
            return;
        }

        (new AuditLogger())->log(
            $_SESSION['userid'],
            $action,
            $details,
            'Dashboard',
            1,
            $id === null ? [] : ['id' => $id],
        );
    }
}
