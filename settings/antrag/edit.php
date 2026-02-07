<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;

if (!Permissions::check(['admin'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    Flash::set('error', 'Ungültige Antragstyp-ID');
    header("Location: " . BASE_PATH . "settings/antrag/list.php");
    exit();
}

// Antragstyp laden
$stmt = $pdo->prepare("SELECT * FROM intra_antrag_typen WHERE id = ?");
$stmt->execute([$id]);
$typ = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$typ) {
    Flash::set('error', 'Antragstyp nicht gefunden');
    header("Location: " . BASE_PATH . "settings/antrag/list.php");
    exit();
}

// Antragstyp aktualisieren
if (isset($_POST['update_typ'])) {
    $name = trim($_POST['name']);
    $beschreibung = trim($_POST['beschreibung']);
    $icon = trim($_POST['icon']);
    $aktiv = isset($_POST['aktiv']) ? 1 : 0;
    $sortierung = (int)$_POST['sortierung'];

    if (empty($name)) {
        Flash::set('error', 'Bitte geben Sie einen Namen an.');
    } else {
        $stmt = $pdo->prepare("
            UPDATE intra_antrag_typen 
            SET name = ?, beschreibung = ?, icon = ?, aktiv = ?, sortierung = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $beschreibung, $icon, $aktiv, $sortierung, $id]);

        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Antragstyp aktualisiert', $name . ' [ID: ' . $id . ']', 'Antragstypen', 1);

        Flash::set('success', 'Antragstyp erfolgreich aktualisiert');
        header("Location: " . BASE_PATH . "settings/antrag/edit.php?id=" . $id);
        exit();
    }
}

// Feld hinzufügen
if (isset($_POST['add_feld'])) {
    $feldname = trim($_POST['feldname']);
    $label = trim($_POST['label']);
    $feldtyp = $_POST['feldtyp'];
    $pflichtfeld = isset($_POST['pflichtfeld']) ? 1 : 0;
    $breite = $_POST['breite'];
    $platzhalter = trim($_POST['platzhalter']);
    $hinweistext = trim($_POST['hinweistext']);
    $readonly = isset($_POST['readonly']) ? 1 : 0;
    $auto_fill = $_POST['auto_fill'] ?: null;
    $optionen = trim($_POST['optionen']);

    // Höchste Sortierung ermitteln
    $stmt = $pdo->prepare("SELECT MAX(sortierung) FROM intra_antrag_felder WHERE antragstyp_id = ?");
    $stmt->execute([$id]);
    $max_sort = $stmt->fetchColumn();
    $next_sort = ($max_sort ?? 0) + 1;

    if (empty($feldname) || empty($label)) {
        Flash::set('error', 'Feldname und Label sind erforderlich');
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO intra_antrag_felder 
            (antragstyp_id, feldname, label, feldtyp, pflichtfeld, breite, platzhalter, 
             hinweistext, readonly, auto_fill, optionen, sortierung) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id,
            $feldname,
            $label,
            $feldtyp,
            $pflichtfeld,
            $breite,
            $platzhalter,
            $hinweistext,
            $readonly,
            $auto_fill,
            $optionen,
            $next_sort
        ]);

        Flash::set('success', 'Feld erfolgreich hinzugefügt');
        header("Location: " . BASE_PATH . "settings/antrag/edit.php?id=" . $id);
        exit();
    }
}

// Feld löschen
if (isset($_GET['delete_feld'])) {
    $feld_id = (int)$_GET['delete_feld'];
    $stmt = $pdo->prepare("DELETE FROM intra_antrag_felder WHERE id = ? AND antragstyp_id = ?");
    $stmt->execute([$feld_id, $id]);
    Flash::set('success', 'Feld gelöscht');
    header("Location: " . BASE_PATH . "settings/antrag/edit.php?id=" . $id);
    exit();
}

// Felder-Sortierung aktualisieren
if (isset($_POST['update_felder_sortierung'])) {
    $sortierungen = $_POST['feld_sortierung'] ?? [];
    foreach ($sortierungen as $feld_id => $sort) {
        $stmt = $pdo->prepare("UPDATE intra_antrag_felder SET sortierung = ? WHERE id = ? AND antragstyp_id = ?");
        $stmt->execute([$sort, $feld_id, $id]);
    }
    Flash::set('success', 'Sortierung aktualisiert');
    header("Location: " . BASE_PATH . "settings/antrag/edit.php?id=" . $id);
    exit();
}

// Felder laden
$stmt = $pdo->prepare("SELECT * FROM intra_antrag_felder WHERE antragstyp_id = ? ORDER BY sortierung ASC");
$stmt->execute([$id]);
$felder = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . '/../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col">
                    <hr class="text-light my-3">
                    <h1><?= htmlspecialchars($typ['name']) ?> bearbeiten</h1>

                    <?php Flash::render(); ?>

                    <hr class="text-light my-3">

                    <!-- Grundeinstellungen -->
                    <div class="intra__tile p-4 mb-4">
                        <h4 class="mb-3">Grundeinstellungen</h4>
                        <form method="post">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label fw-bold">Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name"
                                        value="<?= htmlspecialchars($typ['name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="sortierung" class="form-label fw-bold">Sortierung</label>
                                    <input type="number" class="form-control" id="sortierung" name="sortierung"
                                        value="<?= $typ['sortierung'] ?>" min="0">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="beschreibung" class="form-label fw-bold">Beschreibung</label>
                                <textarea class="form-control" id="beschreibung" name="beschreibung"
                                    rows="2"><?= htmlspecialchars($typ['beschreibung']) ?></textarea>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="aktiv" name="aktiv"
                                        <?= $typ['aktiv'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="aktiv">
                                        <strong>Antragstyp aktiviert</strong>
                                    </label>
                                </div>
                            </div>

                            <button type="submit" name="update_typ" class="btn btn-soft-primary">
                                <i class="fa-solid fa-save me-2"></i>Speichern
                            </button>
                        </form>
                    </div>

                    <!-- Felder Verwaltung -->
                    <div class="intra__tile p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4><i class="fa-solid fa-list me-2"></i>Formularfelder (<?= count($felder) ?>)</h4>
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addFeldModal">
                                <i class="fa-solid fa-plus me-1"></i>Feld hinzufügen
                            </button>
                        </div>

                        <?php if (empty($felder)): ?>
                            <div class="alert alert-info">
                                <i class="fa-solid fa-info-circle me-2"></i>
                                Noch keine Felder definiert. Fügen Sie jetzt Ihr erstes Feld hinzu!
                            </div>
                        <?php else: ?>
                            <form method="post">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th style="width: 60px;">Sort.</th>
                                                <th>Feldname</th>
                                                <th>Label</th>
                                                <th>Typ</th>
                                                <th class="text-center">Breite</th>
                                                <th class="text-center">Pflicht</th>
                                                <th class="text-center">Readonly</th>
                                                <th style="width: 100px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($felder as $feld): ?>
                                                <tr>
                                                    <td>
                                                        <input type="number"
                                                            name="feld_sortierung[<?= $feld['id'] ?>]"
                                                            value="<?= $feld['sortierung'] ?>"
                                                            class="form-control form-control-sm"
                                                            style="width: 60px;">
                                                    </td>
                                                    <td><code><?= htmlspecialchars($feld['feldname']) ?></code></td>
                                                    <td><?= htmlspecialchars($feld['label']) ?></td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?= htmlspecialchars($feld['feldtyp']) ?></span>
                                                        <?php if ($feld['auto_fill']): ?>
                                                            <span class="badge bg-info" title="Auto-Fill">
                                                                <i class="fa-solid fa-magic"></i>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-<?= $feld['breite'] === 'full' ? 'primary' : 'warning' ?>">
                                                            <?= $feld['breite'] === 'full' ? 'Voll' : 'Halb' ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?= $feld['pflichtfeld'] ? '<i class="fa-solid fa-check text-success"></i>' : '<i class="fa-solid fa-times text-muted"></i>' ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?= $feld['readonly'] ? '<i class="fa-solid fa-lock text-warning"></i>' : '<i class="fa-solid fa-unlock text-muted"></i>' ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <a href="?id=<?= $id ?>&delete_feld=<?= $feld['id'] ?>"
                                                            class="btn btn-outline-danger btn-sm btn-icon"
                                                            onclick="event.preventDefault(); showConfirm('Feld wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Feld löschen'}).then(result => { if(result) window.location.href = this.href; });">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" name="update_felder_sortierung" class="btn btn-soft-primary mt-2">
                                    <i class="fa-solid fa-save me-2"></i>Sortierung speichern
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <a href="<?= BASE_PATH ?>settings/antrag/list.php" class="btn btn-ghost mb-5">
                        <i class="fa-solid fa-arrow-left me-2"></i>Zurück zur Übersicht
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Feld hinzufügen -->
    <div class="modal fade" id="addFeldModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fa-solid fa-plus me-2"></i>Neues Feld hinzufügen</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="feldname" class="form-label fw-bold">Feldname (technisch) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="feldname" name="feldname"
                                    placeholder="z.B. von_datum, grund" required>
                                <small class="text-muted">Nur Kleinbuchstaben, Zahlen und Unterstriche</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="label" class="form-label fw-bold">Label (Anzeige) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="label" name="label"
                                    placeholder="z.B. Urlaub von" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="feldtyp" class="form-label fw-bold">Feldtyp <span class="text-danger">*</span></label>
                                <select class="form-select" id="feldtyp" name="feldtyp" required>
                                    <option value="text">Text (einzeilig)</option>
                                    <option value="textarea">Textarea (mehrzeilig)</option>
                                    <option value="number">Zahl</option>
                                    <option value="date">Datum</option>
                                    <option value="time">Uhrzeit</option>
                                    <option value="email">E-Mail</option>
                                    <option value="tel">Telefon</option>
                                    <option value="select">Auswahlfeld</option>
                                    <option value="checkbox">Checkbox</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="breite" class="form-label fw-bold">Feldbreite</label>
                                <select class="form-select" id="breite" name="breite">
                                    <option value="full">Volle Breite</option>
                                    <option value="half">Halbe Breite</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="auto_fill" class="form-label fw-bold">Auto-Fill</label>
                                <select class="form-select" id="auto_fill" name="auto_fill">
                                    <option value="">Kein Auto-Fill</option>
                                    <option value="fullname_dienstnr">Name + Dienstnr.</option>
                                    <option value="fullname">Name</option>
                                    <option value="dienstnr">Dienstnummer</option>
                                    <option value="dienstgrad">Dienstgrad</option>
                                    <option value="discordtag">Discord-Tag</option>
                                </select>
                                <small class="text-muted">Automatisch ausfüllen</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="platzhalter" class="form-label fw-bold">Platzhalter-Text</label>
                            <input type="text" class="form-control" id="platzhalter" name="platzhalter"
                                placeholder="z.B. TT.MM.JJJJ">
                        </div>

                        <div class="mb-3" id="optionen-container" style="display: none;">
                            <label for="optionen" class="form-label fw-bold">Optionen (für Select)</label>
                            <textarea class="form-control" id="optionen" name="optionen" rows="3"
                                placeholder="Eine Option pro Zeile"></textarea>
                            <small class="text-muted">Jede Zeile wird zu einer Auswahloption</small>
                        </div>

                        <div class="mb-3">
                            <label for="hinweistext" class="form-label fw-bold">Hinweistext</label>
                            <textarea class="form-control" id="hinweistext" name="hinweistext" rows="2"
                                placeholder="Optionaler Hinweis, der unter dem Feld angezeigt wird"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="pflichtfeld" name="pflichtfeld">
                                    <label class="form-check-label" for="pflichtfeld">
                                        <strong>Pflichtfeld</strong>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="readonly" name="readonly">
                                    <label class="form-check-label" for="readonly">
                                        <strong>Nur lesbar (Readonly)</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" name="add_feld" class="btn btn-success">
                            <i class="fa-solid fa-plus me-2"></i>Feld hinzufügen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Icon Preview
        $('#icon').on('input', function() {
            const iconClass = $(this).val() || 'fa-solid fa-file-alt';
            $('#icon-preview').attr('class', iconClass + ' fs-4');
        });

        // Optionen-Feld nur bei Select-Typ anzeigen
        $('#feldtyp').on('change', function() {
            if ($(this).val() === 'select') {
                $('#optionen-container').show();
            } else {
                $('#optionen-container').hide();
            }
        });
    </script>

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>