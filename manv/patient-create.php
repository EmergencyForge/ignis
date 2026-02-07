<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\MANV\MANVPatient;
use App\MANV\MANVLage;
use App\MANV\MANVRessource;
use App\MANV\MANVLog;
use App\Auth\Permissions;

$lageId = $_GET['lage_id'] ?? null;
if (!$lageId) {
    header('Location: index.php');
    exit;
}

if (!Permissions::check(['admin', 'manv.manage'])) {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$manvPatient = new MANVPatient($pdo);
$manvLage = new MANVLage($pdo);
$manvRessource = new MANVRessource($pdo);
$manvLog = new MANVLog($pdo);

$lage = $manvLage->getById((int)$lageId);
if (!$lage) {
    header('Location: index.php');
    exit;
}

// Verfügbare Ressourcen abrufen (nur nicht zugewiesene Fahrzeuge)

// Fahrzeuge nur anzeigen, wenn bezeichnung (nicht rufname!) nicht bereits als transportmittel_rufname aktiv ist
$fahrzeugeStmt = $pdo->prepare("
    SELECT r.* 
    FROM intra_manv_ressourcen r
    WHERE r.manv_lage_id = ? 
    AND r.typ = 'fahrzeug'
    AND r.bezeichnung NOT IN (
        SELECT transportmittel_rufname 
        FROM intra_manv_patienten 
        WHERE manv_lage_id = ? 
        AND transportmittel_rufname IS NOT NULL 
        AND transport_abfahrt IS NULL
    )
    ORDER BY r.bezeichnung ASC
");
$fahrzeugeStmt->execute([(int)$lageId, (int)$lageId]);
$fahrzeuge = $fahrzeugeStmt->fetchAll(PDO::FETCH_ASSOC);

// Lade Krankenhäuser für Transportziel
$krankenhausStmt = $pdo->prepare("SELECT id, name, ort FROM intra_edivi_pois WHERE typ = 'Krankenhaus' AND active = 1 ORDER BY name ASC");
$krankenhausStmt->execute();
$krankenhaeuser = $krankenhausStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Hole Fahrzeugdetails wenn transportmittel_id gesetzt
    $transportmittel = null;
    $transportmittel_rufname = null;
    $fahrzeug_lokalisation = null;

    if (!empty($_POST['transportmittel_id'])) {
        $fahrzeugStmt = $pdo->prepare("SELECT bezeichnung, rufname, fahrzeugtyp, lokalisation FROM intra_manv_ressourcen WHERE id = ?");
        $fahrzeugStmt->execute([(int)$_POST['transportmittel_id']]);
        $fahrzeug = $fahrzeugStmt->fetch(PDO::FETCH_ASSOC);
        if ($fahrzeug) {
            // Prüfe ob Fahrzeug bereits einem anderen Patienten zugewiesen ist
            $checkStmt = $pdo->prepare("
                SELECT id, patienten_nummer 
                FROM intra_manv_patienten 
                WHERE manv_lage_id = ? 
                AND transportmittel_rufname = ? 
                AND transport_abfahrt IS NULL
            ");
            $checkStmt->execute([(int)$lageId, $fahrzeug['bezeichnung']]);
            $existingPatient = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingPatient) {
                $error = 'Das Fahrzeug ' . htmlspecialchars($fahrzeug['bezeichnung']) . ' ist bereits Patient ' . htmlspecialchars($existingPatient['patienten_nummer']) . ' zugewiesen.';
            } else {
                $transportmittel = $fahrzeug['fahrzeugtyp'];
                $transportmittel_rufname = $fahrzeug['bezeichnung'];
                $fahrzeug_lokalisation = $fahrzeug['lokalisation'];
            }
        }
    }

    $data = [
        'manv_lage_id' => (int)$lageId,
        'patienten_nummer' => $manvPatient->generateNextPatientNumber((int)$lageId),
        'name' => $_POST['name'] ?? null,
        'vorname' => $_POST['vorname'] ?? null,
        'geburtsdatum' => !empty($_POST['geburtsdatum']) ? $_POST['geburtsdatum'] : null,
        'geschlecht' => $_POST['geschlecht'] ?? 'unbekannt',
        'sichtungskategorie' => $_POST['sichtungskategorie'] ?? null,
        'transportmittel' => $transportmittel,
        'transportmittel_rufname' => $transportmittel_rufname,
        'fahrzeug_lokalisation' => $fahrzeug_lokalisation,
        'transportziel' => $_POST['transportziel'] ?? null,
        'verletzungen' => $_POST['verletzungen'] ?? null,
        'massnahmen' => $_POST['massnahmen'] ?? null,
        'notizen' => $_POST['notizen'] ?? null,
        'erstellt_von' => $_SESSION['user_id'] ?? null,
        'sichtungskategorie_geaendert_von' => !empty($_POST['sichtungskategorie']) ? ($_SESSION['user_id'] ?? null) : null
    ];

    if (!isset($error)) {
        try {
            $patientId = $manvPatient->create($data);

            // Log-Eintrag erstellen
            $manvLog->log(
                (int)$lageId,
                'patient_erstellt',
                'Patient ' . $data['patienten_nummer'] . ' wurde erstellt',
                $_SESSION['user_id'] ?? null,
                $_SESSION['username'] ?? null,
                'patient',
                $patientId
            );

            header('Location: patient-view.php?id=' . $patientId);
            exit;
        } catch (Exception $e) {
            $error = 'Fehler beim Erstellen des Patienten: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = 'Neuer Patient - ' . htmlspecialchars($lage['einsatznummer']);
    include __DIR__ . '/../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" id="patient-create" data-page="edivi">
    <?php include __DIR__ . '/../assets/components/navbar.php'; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <hr class="text-light my-3">
            <div class="row mb-5">
                <div class="col-12">
                    <h1>Neuer Patient</h1>
                    <p class="text-muted">MANV-Lage: <?= htmlspecialchars($lage['einsatznummer']) ?> - <?= htmlspecialchars($lage['einsatzort']) ?></p>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Personalien -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Personalien</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="vorname" class="form-label">Vorname</label>
                                <input type="text" class="form-control" id="vorname" name="vorname">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="geburtsdatum" class="form-label">Geburtsdatum</label>
                                <input type="date" class="form-control" id="geburtsdatum" name="geburtsdatum">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="geschlecht" class="form-label">Geschlecht</label>
                                <select class="form-control" id="geschlecht" name="geschlecht">
                                    <option value="unbekannt">Unbekannt</option>
                                    <option value="m">Männlich</option>
                                    <option value="w">Weiblich</option>
                                    <option value="d">Divers</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sichtung -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Sichtungskategorie</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="sichtungskategorie" class="form-label">Kategorie *</label>
                            <select class="form-control form-control-lg" id="sichtungskategorie" name="sichtungskategorie" required>
                                <option value="">Bitte wählen...</option>
                                <option value="SK1" class="text-danger">SK1 - Rot (Akute Lebensgefahr)</option>
                                <option value="SK2" class="text-warning">SK2 - Gelb (Nicht auszuschließende schwere Folgeschäden)</option>
                                <option value="SK3" class="text-success">SK3 - Grün (Spätere Behandlung)</option>
                                <option value="SK4" class="text-info">SK4 - Blau (Akute Lebensgefahr ohne zeitnahe Versorgung)</option>
                                <option value="SK5" style="background-color: #000; color: #fff;">SK5 - Schwarz (Tot)</option>
                                <option value="SK6" style="color: #9b59b6;">SK6 - Lila (Beteiligter ohne Verletzung)</option>
                            </select>
                        </div>

                        <div class="alert alert-info">
                            <small>
                                <strong>SK1 (Rot):</strong> Akute Lebensgefahr - Sofortbehandlung und sofortiger Transport nach Stabilisierung, wenn keine Transportkapazität vorhanden dann → SK4<br>
                                <strong>SK2 (Gelb):</strong> Nicht auszuschließende schwere Folgeschäden oder akutes, nicht lebensbedrohliches Problem - Behandlung und Transport nach individueller Dringlichkeit<br>
                                <strong>SK3 (Grün):</strong> Spätere Behandlung bei nicht akuten Problemen ohne erwartbare Folgeschäden - Transport nach Verfügbarkeiten<br>
                                <strong>SK4 (Blau):</strong> Akute Lebensgefahr ohne Möglichkeit der zeitnahen Versorgung (prä-)klinisch oder keine erwartbare Überlebenschance - Betreuung und ggf. Sedierung<br>
                                <strong>SK5 (Schwarz):</strong> Verstorbene Person - Keine medizinische Maßnahme erforderlich, Leichnam sichern und dokumentieren<br>
                                <strong>SK6 (Lila):</strong> Beteiligter / Betroffener ohne Verletzung / Erkrankung - ggf. Betreuung oder Unterbringung
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Transport -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Transport</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="transportmittel_id" class="form-label">Zugewiesenes Fahrzeug</label>
                            <select class="form-control" id="transportmittel_id" name="transportmittel_id">
                                <option value="">Noch nicht zugewiesen</option>
                                <?php foreach ($fahrzeuge as $fzg): ?>
                                    <option value="<?= $fzg['id'] ?>"
                                        data-bezeichnung="<?= htmlspecialchars($fzg['bezeichnung']) ?>"
                                        data-fahrzeugtyp="<?= htmlspecialchars($fzg['fahrzeugtyp'] ?? '') ?>"
                                        data-lokalisation="<?= htmlspecialchars($fzg['lokalisation'] ?? '') ?>">
                                        <?= htmlspecialchars($fzg['bezeichnung']) ?> - <?= htmlspecialchars($fzg['fahrzeugtyp'] ?? 'Unbekannt') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="display_fahrzeugtyp" class="form-label">Art</label>
                            <input type="text" class="form-control" id="display_fahrzeugtyp" readonly>
                        </div>

                        <div class="mb-3">
                            <label for="display_rufname" class="form-label">Rufname / Kennung</label>
                            <input type="text" class="form-control" id="display_rufname" readonly>
                        </div>

                        <div class="mb-3">
                            <label for="display_lokalisation" class="form-label">Fahrzeug-Lokalisation</label>
                            <input type="text" class="form-control" id="display_lokalisation" readonly>
                        </div>

                        <div class="mb-3">
                            <label for="transportziel" class="form-label">Transportziel</label>
                            <select class="form-control" id="transportziel" name="transportziel">
                                <option value="">Bitte wählen...</option>
                                <option value="Kein Transport">Kein Transport</option>
                                <?php foreach ($krankenhaeuser as $kh): ?>
                                    <option value="<?= htmlspecialchars($kh['name']) ?>">
                                        <?= htmlspecialchars($kh['name']) ?><?= !empty($kh['ort']) ? ' (' . htmlspecialchars($kh['ort']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Medizinische Daten -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Medizinische Informationen</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="verletzungen" class="form-label">Verletzungen / Diagnose</label>
                            <textarea class="form-control" id="verletzungen" name="verletzungen" rows="3" placeholder="Beschreibung der Verletzungen..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="notizen" class="form-label">Notizen</label>
                            <textarea class="form-control" id="notizen" name="notizen" rows="2" placeholder="Zusätzliche Notizen..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between mb-4">
                    <a href="<?= BASE_PATH ?>manv/board.php?id=<?= $lageId ?>" class="btn btn-ghost">
                        <i class="fas fa-arrow-left me-2"></i>Zurück zum Board
                    </a>
                    <button type="submit" class="btn btn-soft-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Patient anlegen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../assets/components/footer.php'; ?>

    <script>
        // Auto-update readonly fields when vehicle is selected
        document.getElementById('transportmittel_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                document.getElementById('display_rufname').value = selectedOption.dataset.bezeichnung || '';
                document.getElementById('display_fahrzeugtyp').value = selectedOption.dataset.fahrzeugtyp || '';
                document.getElementById('display_lokalisation').value = selectedOption.dataset.lokalisation || '';
            } else {
                document.getElementById('display_rufname').value = '';
                document.getElementById('display_fahrzeugtyp').value = '';
                document.getElementById('display_lokalisation').value = '';
            }
        });
    </script>
</body>

</html>