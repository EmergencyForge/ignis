<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Http\Requests\Antraege\AddFormFieldRequest;
use App\Http\Requests\Antraege\SaveFormTypeRequest;
use App\Utils\AuditLogger;
use EmergencyForge\Http\Exceptions\ValidationException;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * Die Antragstypen und ihre Felder.
 *
 * Heißt „AntragSettings", damit der Name nicht mit
 * {@see \App\Http\Controllers\FormsController} kollidiert, der die
 * Antragstellung selbst macht.
 *
 * Zwei Dinge waren hier kaputt, bevor die FormRequests kamen:
 *
 * Aktivieren, Löschen eines Typs und Löschen eines Feldes liefen über
 * `?toggle=`, `?delete=` und `?delete_feld=` — Zustandsänderungen an einer
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

    public function listAction(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('index.php');

        $typen = Capsule::select("
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

    /** POST — Antragstyp aktivieren oder stilllegen. */
    public function toggle(): void
    {
        $id = $this->postedId(self::LISTE);

        Capsule::table('intra_antrag_typen')
            ->where('id', $id)
            ->update(['aktiv' => Capsule::raw('NOT aktiv')]);

        $this->audit('Antragstyp umgeschaltet', '[ID: ' . $id . ']', $id);
        Flash::set('success', 'Status erfolgreich geändert');
        $this->redirect(self::LISTE);
    }

    /** POST — Antragstyp löschen, sofern kein Antrag daran hängt. */
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

    /** POST — die Reihenfolge der Liste. */
    public function sort(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('index.php');

        foreach ($this->sortierungen($_POST['sortierung'] ?? null) as $id => $sortierung) {
            Capsule::table('intra_antrag_typen')->where('id', $id)->update(['sortierung' => $sortierung]);
        }

        Flash::set('success', 'Sortierung aktualisiert');
        $this->redirect(self::LISTE);
    }

    public function createForm(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('index.php');

        $this->renderView('settings/forms/create', [
            'defaultSort' => $this->naechsteSortierung(),
            'errors'      => [],
            'old'         => [],
        ]);
    }

    /** POST — einen Antragstyp anlegen. */
    public function store(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('index.php');

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
        $this->ensureAdmin('index.php');

        $id  = (int) ($_GET['id'] ?? 0);
        $typ = $this->typOderZurueck($id);

        if (isset($_POST['update_typ'])) {
            $this->updateTyp($id);
        }

        if (isset($_POST['add_feld'])) {
            $this->addFeld($id);
        }

        if (isset($_POST['update_felder_sortierung'])) {
            foreach ($this->sortierungen($_POST['feld_sortierung'] ?? null) as $feldId => $sortierung) {
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

    /** POST — ein Feld löschen. */
    public function destroyField(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('index.php');

        $typId = (int) ($_POST['antragstyp_id'] ?? 0);
        $this->typOderZurueck($typId);

        $feldId = (int) ($_POST['id'] ?? 0);
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
        $this->ensureAdmin('index.php');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('error', 'Ungültige Antragstyp-ID');
            $this->redirect($zurueck);
        }

        return $id;
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

    /**
     * Die Sortierungstabelle eines Formulars als Kennung => Zahl.
     *
     * Der Post bringt sie als `sortierung[12]=3`. Beides muss eine Zahl
     * sein, sonst landet ein Schlüssel wie `sortierung[abc]` als
     * `WHERE id = 0` in der Abfrage.
     *
     * @return array<int,int>
     */
    private function sortierungen(mixed $roh): array
    {
        if (!is_array($roh)) {
            return [];
        }

        $out = [];
        foreach ($roh as $id => $sortierung) {
            if (!is_numeric($id) || !is_numeric($sortierung)) {
                continue;
            }
            if ((int) $id > 0) {
                $out[(int) $id] = (int) $sortierung;
            }
        }

        return $out;
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
