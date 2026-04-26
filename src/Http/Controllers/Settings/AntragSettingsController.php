<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Helpers\Flash;
use App\Auth\Gate;
use App\Http\Controllers\Controller;
use App\Utils\AuditLogger;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * AntragSettingsController — Verwaltung der Antragstypen und ihrer Felder.
 *
 * Heißt bewusst "AntragSettings", um Konflikte mit dem bereits migrierten
 * App\Http\Controllers\AntragController (Antragstellung) zu vermeiden.
 */
class AntragSettingsController extends Controller
{
    public function listAction(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('index.php');

        // Toggle Aktivierungsstatus
        if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
            $id = (int) $_GET['toggle'];
            Capsule::table('intra_antrag_typen')
                ->where('id', $id)
                ->update(['aktiv' => Capsule::raw('NOT aktiv')]);
            Flash::set('success', 'Status erfolgreich geändert');
            $this->redirect('settings/antrag/list.php');
        }

        // Antragstyp löschen
        if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
            $id = (int) $_GET['delete'];
            $count = (int) Capsule::table('intra_antraege')
                ->where('antragstyp_id', $id)
                ->count();

            if ($count > 0) {
                Flash::set('error', 'Dieser Antragstyp kann nicht gelöscht werden, da noch ' . $count . ' Anträge existieren.');
            } else {
                Capsule::table('intra_antrag_typen')->where('id', $id)->delete();
                Flash::set('success', 'Antragstyp erfolgreich gelöscht');
            }
            $this->redirect('settings/antrag/list.php');
        }

        // Sortierung aktualisieren
        if (isset($_POST['update_sortierung'])) {
            $sortierungen = $_POST['sortierung'] ?? [];
            foreach ($sortierungen as $id => $sort) {
                Capsule::table('intra_antrag_typen')
                    ->where('id', (int) $id)
                    ->update(['sortierung' => (int) $sort]);
            }
            Flash::set('success', 'Sortierung aktualisiert');
            $this->redirect('settings/antrag/list.php');
        }

        // Antragstypen mit Aggregaten laden
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

        $this->renderView('settings/antrag/list', ['typen' => $typen]);
    }

    public function createForm(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('index.php');

        if (isset($_POST['submit'])) {
            $this->handleCreate();
            return;
        }

        $defaultSort = ((int) Capsule::table('intra_antrag_typen')->max('sortierung') ?? 0) + 1;

        $this->renderView('settings/antrag/create', [
            'defaultSort' => $defaultSort,
            'errors'      => [],
            'old'         => [],
        ]);
    }

    private function handleCreate(): void
    {
        $name         = trim($_POST['name'] ?? '');
        $beschreibung = trim($_POST['beschreibung'] ?? '');
        $icon         = trim($_POST['icon'] ?? '');
        $aktiv        = isset($_POST['aktiv']) ? 1 : 0;
        $sortierung   = (int) ($_POST['sortierung'] ?? 0);

        if ($name === '') {
            Flash::set('error', 'Bitte geben Sie einen Namen für den Antragstyp an.');
            $defaultSort = ((int) Capsule::table('intra_antrag_typen')->max('sortierung') ?? 0) + 1;
            $this->renderView('settings/antrag/create', [
                'defaultSort' => $defaultSort,
                'old'         => $_POST,
            ]);
            return;
        }

        try {
            $newId = Capsule::table('intra_antrag_typen')->insertGetId([
                'name'         => $name,
                'beschreibung' => $beschreibung,
                'icon'         => $icon,
                'aktiv'        => $aktiv,
                'sortierung'   => $sortierung,
                'erstellt_von' => $_SESSION['userid'] ?? null,
            ]);

            $this->audit('Neuer Antragstyp erstellt', $name . ' [ID: ' . $newId . ']');

            Flash::set('success', 'Antragstyp erfolgreich erstellt. Sie können jetzt Felder hinzufügen.');
            $this->redirect('settings/antrag/edit.php?id=' . $newId);
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Erstellen: ' . $e->getMessage());
            $this->redirect('settings/antrag/create.php');
        }
    }

    public function edit(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('index.php');

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('error', 'Ungültige Antragstyp-ID');
            $this->redirect('settings/antrag/list.php');
        }

        $typ = Capsule::table('intra_antrag_typen')->where('id', $id)->first();
        if (!$typ) {
            Flash::set('error', 'Antragstyp nicht gefunden');
            $this->redirect('settings/antrag/list.php');
        }

        // POST: Antragstyp aktualisieren
        if (isset($_POST['update_typ'])) {
            $this->handleUpdateTyp($id);
            return;
        }

        // POST: Feld hinzufügen
        if (isset($_POST['add_feld'])) {
            $this->handleAddFeld($id);
            return;
        }

        // GET: Feld löschen
        if (isset($_GET['delete_feld'])) {
            $feldId = (int) $_GET['delete_feld'];
            Capsule::table('intra_antrag_felder')
                ->where('id', $feldId)
                ->where('antragstyp_id', $id)
                ->delete();
            Flash::set('success', 'Feld gelöscht');
            $this->redirect('settings/antrag/edit.php?id=' . $id);
        }

        // POST: Felder-Sortierung
        if (isset($_POST['update_felder_sortierung'])) {
            $sortierungen = $_POST['feld_sortierung'] ?? [];
            foreach ($sortierungen as $feldId => $sort) {
                Capsule::table('intra_antrag_felder')
                    ->where('id', (int) $feldId)
                    ->where('antragstyp_id', $id)
                    ->update(['sortierung' => (int) $sort]);
            }
            Flash::set('success', 'Sortierung aktualisiert');
            $this->redirect('settings/antrag/edit.php?id=' . $id);
        }

        $felder = Capsule::table('intra_antrag_felder')
            ->where('antragstyp_id', $id)
            ->orderBy('sortierung')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->renderView('settings/antrag/edit', [
            'id'     => $id,
            'typ'    => (array) $typ,
            'felder' => $felder,
        ]);
    }

    private function handleUpdateTyp(int $id): void
    {
        $name         = trim($_POST['name'] ?? '');
        $beschreibung = trim($_POST['beschreibung'] ?? '');
        $icon         = trim($_POST['icon'] ?? '');
        $aktiv        = isset($_POST['aktiv']) ? 1 : 0;
        $sortierung   = (int) ($_POST['sortierung'] ?? 0);

        if ($name === '') {
            Flash::set('error', 'Bitte geben Sie einen Namen an.');
            $this->redirect('settings/antrag/edit.php?id=' . $id);
        }

        Capsule::table('intra_antrag_typen')->where('id', $id)->update([
            'name'         => $name,
            'beschreibung' => $beschreibung,
            'icon'         => $icon,
            'aktiv'        => $aktiv,
            'sortierung'   => $sortierung,
        ]);

        $this->audit('Antragstyp aktualisiert', $name . ' [ID: ' . $id . ']');

        Flash::set('success', 'Antragstyp erfolgreich aktualisiert');
        $this->redirect('settings/antrag/edit.php?id=' . $id);
    }

    private function handleAddFeld(int $id): void
    {
        $feldname    = trim($_POST['feldname'] ?? '');
        $label       = trim($_POST['label'] ?? '');
        $feldtyp     = $_POST['feldtyp'] ?? 'text';
        $pflichtfeld = isset($_POST['pflichtfeld']) ? 1 : 0;
        $breite      = $_POST['breite'] ?? 'full';
        $platzhalter = trim($_POST['platzhalter'] ?? '');
        $hinweistext = trim($_POST['hinweistext'] ?? '');
        $readonly    = isset($_POST['readonly']) ? 1 : 0;
        $autoFill    = $_POST['auto_fill'] ?: null;
        $optionen    = trim($_POST['optionen'] ?? '');

        if ($feldname === '' || $label === '') {
            Flash::set('error', 'Feldname und Label sind erforderlich');
            $this->redirect('settings/antrag/edit.php?id=' . $id);
        }

        $maxSort = (int) Capsule::table('intra_antrag_felder')
            ->where('antragstyp_id', $id)
            ->max('sortierung');
        $nextSort = $maxSort + 1;

        Capsule::table('intra_antrag_felder')->insert([
            'antragstyp_id' => $id,
            'feldname'      => $feldname,
            'label'         => $label,
            'feldtyp'       => $feldtyp,
            'pflichtfeld'   => $pflichtfeld,
            'breite'        => $breite,
            'platzhalter'   => $platzhalter,
            'hinweistext'   => $hinweistext,
            'readonly'      => $readonly,
            'auto_fill'     => $autoFill,
            'optionen'      => $optionen,
            'sortierung'    => $nextSort,
        ]);

        Flash::set('success', 'Feld erfolgreich hinzugefügt');
        $this->redirect('settings/antrag/edit.php?id=' . $id);
    }

    private function ensureAdmin(string $redirect): void
    {
        if (!Gate::allows('system.admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect($redirect);
        }
    }

    private function audit(string $action, string $details): void
    {
        if (!isset($_SESSION['userid'])) {
            return;
        }
        $logger = new AuditLogger($this->pdo);
        $logger->log($_SESSION['userid'], $action, $details, 'Antragstypen', 1);
    }
}
