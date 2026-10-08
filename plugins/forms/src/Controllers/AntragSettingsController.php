<?php

declare(strict_types=1);

namespace Plugin\Forms\Controllers;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Utils\AuditLogger;
use EmergencyForge\Http\Exceptions\ValidationException;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;
use Plugin\Forms\Requests\AddFormFieldRequest;
use Plugin\Forms\Requests\EditFormActionRequest;
use Plugin\Forms\Requests\FormIdRequest;
use Plugin\Forms\Requests\SaveFormTypeRequest;
use Plugin\Forms\Requests\SortRequest;

/**
 * Die Antragstypen und ihre Felder.
 *
 * Heißt „AntragSettings", damit der Name nicht mit
 * {@see FormsController} kollidiert, der die Antragstellung selbst macht.
 *
 * Zwei Dinge waren hier kaputt, bevor die FormRequests kamen:
 *
 * Aktivieren, Löschen eines Typs und Löschen eines Feldes liefen über
 * `?toggle=`, `?delete=` und `?delete_feld=`: Zustandsänderungen an einer
 * GET-Adresse. Ein `<img src="…?delete=5">` auf irgendeiner Seite reicht
 * dann, und der Vorablade-Mechanismus eines Browsers braucht nicht einmal
 * einen Angreifer. CsrfMiddleware greift dort nicht, sie prüft nur
 * schreibende Methoden. Alle drei sind jetzt eigene POST-Routen.
 *
 * Und zwei Formulare posteten auf eine Adresse, für die nur GET
 * registriert war: das Anlegen eines Antragstyps und die Sortierung der
 * Liste antworteten mit 405. Beides waren tote Knöpfe.
 */
class AntragSettingsController extends Controller
{
    private const LISTE = 'settings/forms/list';

    /**
     * Views liegen im templates/-Verzeichnis des Plugins.
     */
    protected function viewBasePath(): string
    {
        return dirname(__DIR__, 2) . '/templates';
    }

    public function listAction(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('index');

        $typen = Capsule::connection()->select("
            SELECT
                at.*,
                COUNT(DISTINCT af.id) as anzahl_felder,
                COUNT(DISTINCT a.uniqueid) as anzahl_antraege
            FROM intra_antrag_typen at
            LEFT JOIN intra_antrag_felder af ON at.id = af.antragstyp_id
            LEFT JOIN intra_antraege a ON at.id = a.antragstyp_id
            GROUP BY at.id
            ORDER BY at.sortierung ASC, at.name ASC
        ");
        $typen = array_map(fn ($r) => (array) $r, $typen);

        $this->renderView('settings/forms/list', ['typen' => $typen]);
    }

    /** POST: Antragstyp aktivieren oder stilllegen. */
    public function toggle(): void
    {
        $id = $this->postedId(self::LISTE);

        Capsule::table('intra_antrag_typen')
            ->where('id', $id)
            ->update(['aktiv' => Capsule::connection()->raw('NOT aktiv')]);

        $this->audit('Antragstyp umgeschaltet', '[ID: ' . $id . ']', $id);
        Flash::set('success', 'Status erfolgreich geändert');
        $this->redirect(self::LISTE);
    }

    /** POST: Antragstyp löschen, sofern kein Antrag daran hängt. */
    public function destroy(): void
    {
        $id = $this->postedId(self::LISTE);

        $anzahl = (int) Capsule::table('intra_antraege')->where('antragstyp_id', $id)->count();
        if ($anzahl > 0) {
            Flash::set('error', 'Dieser Antragstyp kann nicht gelöscht werden, da noch ' . $anzahl . ' Anträge existieren.');
            $this->redirect(self::LISTE);
        }

        Capsule::table('intra_antrag_typen')->where('id', $id)->delete();

        $this->audit('Antragstyp gelöscht', '[ID: ' . $id . ']', $id);
        Flash::set('success', 'Antragstyp erfolgreich gelöscht');
        $this->redirect(self::LISTE);
    }

    /** POST: die Reihenfolge der Liste. */
    public function sort(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('index');

        foreach ($this->sortierung(self::LISTE)['sortierung'] as $id => $sortierung) {
            Capsule::table('intra_antrag_typen')->where('id', $id)->update(['sortierung' => $sortierung]);
        }

        Flash::set('success', 'Sortierung aktualisiert');
        $this->redirect(self::LISTE);
    }

    public function createForm(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('index');

        $this->renderView('settings/forms/create', [
            'defaultSort' => $this->naechsteSortierung(),
            'errors'      => [],
            'old'         => [],
        ]);
    }

    /** POST: einen Antragstyp anlegen. */
    public function store(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('index');

        try {
            $data = SaveFormTypeRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('settings/forms/create');
        }

        try {
            $newId = Capsule::table('intra_antrag_typen')->insertGetId(
                $data + ['erstellt_von' => $_SESSION['userid'] ?? null],
            );
        } catch (PDOException $e) {
            // Die Meldung der Datenbank gehört ins Protokoll, nicht in die
            // Hinweisblase: sie nennt Tabellen- und Spaltennamen.
            error_log('Antragstyp anlegen fehlgeschlagen: ' . $e->getMessage());
            Flash::set('error', 'Der Antragstyp konnte nicht angelegt werden.');
            $this->redirect('settings/forms/create');
        }

        $this->audit('Neuer Antragstyp erstellt', $data['name'] . ' [ID: ' . $newId . ']', (int) $newId);
        Flash::set('success', 'Antragstyp erfolgreich erstellt. Du kannst jetzt Felder hinzufügen.');
        $this->redirect('settings/forms/edit?id=' . $newId);
    }

    public function edit(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('index');

        $id  = (int) ($_GET['id'] ?? 0);
        $typ = $this->typOderZurueck($id);

        // Nur beim Post: validate() räumt die alte Eingabe weg, die das
        // Formular nach einem Fehler gerade wieder anzeigen soll.
        $aktion = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
            ? EditFormActionRequest::validate($_POST)['aktion']
            : null;

        if ($aktion === EditFormActionRequest::TYP) {
            $this->updateTyp($id);
        }

        if ($aktion === EditFormActionRequest::FELD) {
            $this->addFeld($id);
        }

        if ($aktion === EditFormActionRequest::SORTIERUNG) {
            foreach ($this->sortierung('settings/forms/edit?id=' . $id)['feld_sortierung'] as $feldId => $sortierung) {
                Capsule::table('intra_antrag_felder')
                    ->where('id', $feldId)
                    ->where('antragstyp_id', $id)
                    ->update(['sortierung' => $sortierung]);
            }
            Flash::set('success', 'Sortierung aktualisiert');
            $this->redirect('settings/forms/edit?id=' . $id);
        }

        $felder = Capsule::table('intra_antrag_felder')
            ->where('antragstyp_id', $id)
            ->orderBy('sortierung')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->renderView('settings/forms/edit', [
            'id'     => $id,
            'typ'    => (array) $typ,
            'felder' => $felder,
        ]);
    }

    /** POST: ein Feld löschen. */
    public function destroyField(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('index');

        $ids   = $this->kennungen(self::LISTE);
        $typId = $ids['antragstyp_id'];
        $this->typOderZurueck($typId);

        $feldId = $ids['id'];
        if ($feldId <= 0) {
            Flash::set('error', 'Ungültige Feld-ID');
            $this->redirect('settings/forms/edit?id=' . $typId);
        }

        Capsule::table('intra_antrag_felder')
            ->where('id', $feldId)
            ->where('antragstyp_id', $typId)
            ->delete();

        $this->audit('Antragsfeld gelöscht', '[Feld: ' . $feldId . ']', $typId);
        Flash::set('success', 'Feld gelöscht');
        $this->redirect('settings/forms/edit?id=' . $typId);
    }

    // ── Innereien ───────────────────────────────────────────────

    private function updateTyp(int $id): never
    {
        try {
            $data = SaveFormTypeRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('settings/forms/edit?id=' . $id);
        }

        Capsule::table('intra_antrag_typen')->where('id', $id)->update($data);

        $this->audit('Antragstyp aktualisiert', $data['name'] . ' [ID: ' . $id . ']', $id);
        Flash::set('success', 'Antragstyp erfolgreich aktualisiert');
        $this->redirect('settings/forms/edit?id=' . $id);
    }

    private function addFeld(int $id): never
    {
        try {
            $data = AddFormFieldRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('settings/forms/edit?id=' . $id);
        }

        $maxSort = (int) Capsule::table('intra_antrag_felder')
            ->where('antragstyp_id', $id)
            ->max('sortierung');

        Capsule::table('intra_antrag_felder')->insert(
            $data + ['antragstyp_id' => $id, 'sortierung' => $maxSort + 1],
        );

        $this->audit('Antragsfeld angelegt', $data['feldname'] . ' [ID: ' . $id . ']', $id);
        Flash::set('success', 'Feld erfolgreich hinzugefügt');
        $this->redirect('settings/forms/edit?id=' . $id);
    }

    /** Die Kennung aus dem Post, oder zurück zur Liste. */
    private function postedId(string $zurueck): int
    {
        $this->requireAuth();
        $this->ensureAdmin('index');

        $id = $this->kennungen($zurueck)['id'];
        if ($id <= 0) {
            Flash::set('error', 'Ungültige Antragstyp-ID');
            $this->redirect($zurueck);
        }

        return $id;
    }

    /**
     * @return array<string,mixed>
     */
    private function kennungen(string $zurueck): array
    {
        try {
            return FormIdRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::set('error', $e->firstError() ?? 'Ungültige Antragstyp-ID');
            $this->redirect($zurueck);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function sortierung(string $zurueck): array
    {
        try {
            return SortRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::set('error', $e->firstError() ?? 'Ungültige Sortierung.');
            $this->redirect($zurueck);
        }
    }

    /** Den Antragstyp holen, oder zurück zur Liste. */
    private function typOderZurueck(int $id): \stdClass
    {
        if ($id <= 0) {
            Flash::set('error', 'Ungültige Antragstyp-ID');
            $this->redirect(self::LISTE);
        }

        $typ = Capsule::table('intra_antrag_typen')->where('id', $id)->first();
        if (!$typ instanceof \stdClass) {
            Flash::set('error', 'Antragstyp nicht gefunden');
            $this->redirect(self::LISTE);
        }

        return $typ;
    }

    private function naechsteSortierung(): int
    {
        return (int) Capsule::table('intra_antrag_typen')->max('sortierung') + 1;
    }

    private function ensureAdmin(string $redirect): void
    {
        if (!Gate::allows('system.admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect($redirect);
        }
    }

    /**
     * Schreibt ins Prüfprotokoll. Die Kennung des Antragstyps geht als
     * `context.id` mit, damit {@see \App\Support\Activity} sie nicht aus
     * dem Meldungstext klauben muss.
     */
    private function audit(string $action, string $details, ?int $id = null): void
    {
        if (!isset($_SESSION['userid'])) {
            return;
        }

        (new AuditLogger())->log(
            $_SESSION['userid'],
            $action,
            $details,
            'Antragstypen',
            1,
            $id === null ? [] : ['id' => $id],
        );
    }
}
