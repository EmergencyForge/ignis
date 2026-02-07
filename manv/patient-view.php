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
use App\MANV\MANVLog;
use App\Auth\Permissions;

$patientId = $_GET['id'] ?? null;
if (!$patientId) {
    header('Location: index.php');
    exit;
}

if (!Permissions::check(['admin', 'manv.manage'])) {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$manvPatient = new MANVPatient($pdo);
$manvLage = new MANVLage($pdo);
$manvLog = new MANVLog($pdo);

$patient = $manvPatient->getById((int)$patientId);
if (!$patient) {
    header('Location: index.php');
    exit;
}

$lage = $manvLage->getById((int)$patient['manv_lage_id']);

// Lade verfügbare Fahrzeuge aus Ressourcen (nur nicht zugewiesene + aktuelles Fahrzeug)
// JOIN mit intra_fahrzeuge um rd_type zu laden (2 = Transportmittel)
$verfuegbareFahrzeugeStmt = $pdo->prepare("
    SELECT r.*, f.rd_type 
    FROM intra_manv_ressourcen r
    LEFT JOIN intra_fahrzeuge f ON r.bezeichnung = f.name
    WHERE r.manv_lage_id = ? 
    AND r.typ = 'fahrzeug'
    AND (
        r.bezeichnung NOT IN (
            SELECT transportmittel_rufname 
            FROM intra_manv_patienten 
            WHERE manv_lage_id = ? 
            AND transportmittel_rufname IS NOT NULL 
            AND transport_abfahrt IS NULL
            AND id != ?
        )
        OR r.bezeichnung = ?
    )
    ORDER BY r.bezeichnung ASC
");
$verfuegbareFahrzeugeStmt->execute([
    (int)$patient['manv_lage_id'],
    (int)$patient['manv_lage_id'],
    (int)$patientId,
    $patient['transportmittel_rufname'] ?? ''
]);
$verfuegbareFahrzeuge = $verfuegbareFahrzeugeStmt->fetchAll(PDO::FETCH_ASSOC);

// Lade Krankenhäuser für Transportziel
$krankenhausStmt = $pdo->prepare("SELECT id, name, ort FROM intra_edivi_pois WHERE typ = 'Krankenhaus' AND active = 1 ORDER BY name ASC");
$krankenhausStmt->execute();
$krankenhaeuser = $krankenhausStmt->fetchAll(PDO::FETCH_ASSOC);

// Schnelle Sichtungskategorie-Änderung via GET
if (isset($_GET['quick_sk']) && in_array($_GET['quick_sk'], ['SK1', 'SK2', 'SK3', 'SK4', 'SK5', 'SK6', 'tot'])) {
    $manvPatient->updateSichtung((int)$patientId, $_GET['quick_sk'], $_SESSION['user_id'] ?? null);
    $manvLog->log(
        (int)$patient['manv_lage_id'],
        'sichtung_geaendert',
        'Sichtungskategorie geändert zu ' . $_GET['quick_sk'],
        $_SESSION['user_id'] ?? null,
        $_SESSION['username'] ?? null,
        'patient',
        (int)$patientId
    );
    header('Location: patient-view.php?id=' . $patientId);
    exit;
}

// Update via POST
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
                AND id != ?
            ");
            $checkStmt->execute([(int)$patient['manv_lage_id'], $fahrzeug['bezeichnung'], (int)$patientId]);
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

    $updateData = [
        'name' => $_POST['name'] ?? null,
        'vorname' => $_POST['vorname'] ?? null,
        'geburtsdatum' => !empty($_POST['geburtsdatum']) ? $_POST['geburtsdatum'] : null,
        'geschlecht' => $_POST['geschlecht'] ?? 'unbekannt',
        'transportmittel' => $transportmittel,
        'transportmittel_rufname' => $transportmittel_rufname,
        'fahrzeug_lokalisation' => $fahrzeug_lokalisation,
        'transportziel' => $_POST['transportziel'] ?? null,
        'verletzungen' => $_POST['verletzungen'] ?? null,
        'notizen' => $_POST['notizen'] ?? null,
        'geaendert_von' => $_SESSION['user_id'] ?? null
    ];

    // Sichtungskategorie separat behandeln
    if (isset($_POST['sichtungskategorie']) && $_POST['sichtungskategorie'] !== $patient['sichtungskategorie']) {
        $manvPatient->updateSichtung((int)$patientId, $_POST['sichtungskategorie'], $_SESSION['user_id'] ?? null);
        $manvLog->log(
            (int)$patient['manv_lage_id'],
            'sichtung_geaendert',
            'Sichtungskategorie geändert von ' . ($patient['sichtungskategorie'] ?? 'ungesichtet') . ' zu ' . $_POST['sichtungskategorie'],
            $_SESSION['user_id'] ?? null,
            $_SESSION['username'] ?? null,
            'patient',
            (int)$patientId
        );
    }

    if (!isset($error)) {
        try {
            $manvPatient->update((int)$patientId, $updateData);
            $manvLog->log(
                (int)$patient['manv_lage_id'],
                'patient_aktualisiert',
                'Patientendaten wurden aktualisiert',
                $_SESSION['user_id'] ?? null,
                $_SESSION['username'] ?? null,
                'patient',
                (int)$patientId
            );
            $success = 'Patient erfolgreich aktualisiert.';
            $patient = $manvPatient->getById((int)$patientId); // Refresh
        } catch (Exception $e) {
            $error = 'Fehler beim Aktualisieren: ' . $e->getMessage();
        }
    }
}

// Farbe für Sichtungskategorie
$skColors = [
    'SK1' => 'danger',
    'SK2' => 'warning',
    'SK3' => 'success',
    'SK4' => 'info',
    'SK5' => 'sk5',
    'SK6' => 'sk6',
    'tot' => 'dark'
];
$skColor = $skColors[$patient['sichtungskategorie']] ?? 'secondary';
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = 'Patient ' . htmlspecialchars($patient['patienten_nummer']);
    include __DIR__ . '/../assets/components/_base/admin/head.php';
    ?>
    <style>
        .quick-action-btn {
            margin: 0.25rem;
        }

        .bg-sk5 {
            background-color: #000 !important;
            color: #fff !important;
        }

        .bg-sk6 {
            background-color: #9b59b6 !important;
            color: #fff !important;
        }

        .patient-header {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-box {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>

<body data-bs-theme="dark" id="patient-view" data-page="edivi">
    <?php include __DIR__ . '/../assets/components/navbar.php'; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <hr class="text-light my-3">
            <!-- Patient Header -->
            <div class="patient-header mb-5">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1 class="mb-2">
                            <?= htmlspecialchars($patient['patienten_nummer']) ?>
                        </h1>
                        <h4>
                            <?= htmlspecialchars($patient['vorname'] ?? '') ?> <?= htmlspecialchars($patient['name'] ?? 'Unbekannt') ?>
                        </h4>
                        <p class="text-muted mb-0">
                            MANV-Lage: <?= htmlspecialchars($lage['einsatznummer']) ?>
                        </p>
                    </div>
                    <div class="col-md-6 text-end">
                        <h2>
                            <span class="badge bg-<?= $skColor ?> fs-4">
                                <?= htmlspecialchars($patient['sichtungskategorie'] ?? 'Ungesichtet') ?>
                            </span>
                        </h2>
                        <small class="text-muted">
                            <?php if ($patient['sichtungskategorie_zeit']): ?>
                                Gesichtet: <?= date('d.m.Y H:i', strtotime($patient['sichtungskategorie_zeit'])) ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Schnellaktionen -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Schnell-Sichtung</h5>
                </div>
                <div class="card-body text-center">
                    <a href="?id=<?= $patientId ?>&quick_sk=SK1" class="btn btn-danger quick-action-btn">
                        <i class="fas fa-circle me-1"></i>SK1 - Rot
                    </a>
                    <a href="?id=<?= $patientId ?>&quick_sk=SK2" class="btn btn-warning quick-action-btn">
                        <i class="fas fa-circle me-1"></i>SK2 - Gelb
                    </a>
                    <a href="?id=<?= $patientId ?>&quick_sk=SK3" class="btn btn-success quick-action-btn">
                        <i class="fas fa-circle me-1"></i>SK3 - Grün
                    </a>
                    <a href="?id=<?= $patientId ?>&quick_sk=SK4" class="btn btn-info quick-action-btn">
                        <i class="fas fa-circle me-1"></i>SK4 - Blau
                    </a>
                    <a href="?id=<?= $patientId ?>&quick_sk=SK5" class="btn quick-action-btn" style="background-color: #000; color: #fff; border-color: #fff;">
                        <i class="fas fa-circle me-1"></i>SK5 - Schwarz
                    </a>
                    <a href="?id=<?= $patientId ?>&quick_sk=SK6" class="btn quick-action-btn" style="background-color: #9b59b6; color: #fff;">
                        <i class="fas fa-circle me-1"></i>SK6 - Lila
                    </a>
                </div>
            </div>

            <form method="POST" action="">
                <div class="row">
                    <!-- Linke Spalte -->
                    <div class="col-md-6">
                        <!-- Personalien -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Personalien</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($patient['name'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="vorname" class="form-label">Vorname</label>
                                    <input type="text" class="form-control" id="vorname" name="vorname" value="<?= htmlspecialchars($patient['vorname'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="geburtsdatum" class="form-label">Geburtsdatum</label>
                                    <input type="date" class="form-control" id="geburtsdatum" name="geburtsdatum" value="<?= htmlspecialchars($patient['geburtsdatum'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="geschlecht" class="form-label">Geschlecht</label>
                                    <select class="form-control" id="geschlecht" name="geschlecht">
                                        <option value="unbekannt" <?= $patient['geschlecht'] === 'unbekannt' ? 'selected' : '' ?>>Unbekannt</option>
                                        <option value="m" <?= $patient['geschlecht'] === 'm' ? 'selected' : '' ?>>Männlich</option>
                                        <option value="w" <?= $patient['geschlecht'] === 'w' ? 'selected' : '' ?>>Weiblich</option>
                                        <option value="d" <?= $patient['geschlecht'] === 'd' ? 'selected' : '' ?>>Divers</option>
                                    </select>
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
                                    <label for="sichtungskategorie" class="form-label">Kategorie</label>
                                    <select class="form-control form-control-lg" id="sichtungskategorie" name="sichtungskategorie">
                                        <option value="SK1" <?= $patient['sichtungskategorie'] === 'SK1' ? 'selected' : '' ?> class="text-danger">SK1 - Rot</option>
                                        <option value="SK2" <?= $patient['sichtungskategorie'] === 'SK2' ? 'selected' : '' ?> class="text-warning">SK2 - Gelb</option>
                                        <option value="SK3" <?= $patient['sichtungskategorie'] === 'SK3' ? 'selected' : '' ?> class="text-success">SK3 - Grün</option>
                                        <option value="SK4" <?= $patient['sichtungskategorie'] === 'SK4' ? 'selected' : '' ?> class="text-info">SK4 - Blau</option>
                                        <option value="SK5" <?= $patient['sichtungskategorie'] === 'SK5' ? 'selected' : '' ?> style="background-color: #000; color: #fff;">SK5 - Schwarz (Tot)</option>
                                        <option value="SK6" <?= $patient['sichtungskategorie'] === 'SK6' ? 'selected' : '' ?> style="color: #9b59b6;">SK6 - Lila</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rechte Spalte -->
                    <div class="col-md-6">
                        <!-- Transport / Fahrzeugzuweisung -->
                        <?php
                        // SK4, SK5, SK6 und Tot können nicht transportiert werden, aber Fahrzeuge zugewiesen bekommen
                        $canTransport = !in_array($patient['sichtungskategorie'], ['SK4', 'SK5', 'SK6', 'tot']);
                        ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><?= $canTransport ? 'Transport' : 'Fahrzeugzuweisung' ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="transportmittel_id" class="form-label">Zugewiesenes Fahrzeug</label>
                                    <select class="form-control" id="transportmittel_id" name="transportmittel_id">
                                        <option value="" data-rdtype="">Noch nicht zugewiesen</option>
                                        <?php foreach ($verfuegbareFahrzeuge as $fzg):
                                            $selected = ($patient['transportmittel_rufname'] === $fzg['bezeichnung']) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $fzg['id'] ?>" <?= $selected ?>
                                                data-bezeichnung="<?= htmlspecialchars($fzg['bezeichnung']) ?>"
                                                data-fahrzeugtyp="<?= htmlspecialchars($fzg['fahrzeugtyp'] ?? '') ?>"
                                                data-lokalisation="<?= htmlspecialchars($fzg['lokalisation'] ?? '') ?>"
                                                data-rdtype="<?= isset($fzg['rd_type']) ? (int)$fzg['rd_type'] : '' ?>">
                                                <?= htmlspecialchars($fzg['bezeichnung']) ?> - <?= htmlspecialchars($fzg['fahrzeugtyp'] ?? 'Unbekannt') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="display_fahrzeugtyp" class="form-label">Art</label>
                                    <input type="text" class="form-control" id="display_fahrzeugtyp" value="<?= htmlspecialchars($patient['transportmittel'] ?? '') ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="display_rufname" class="form-label">Rufname / Kennung</label>
                                    <input type="text" class="form-control" id="display_rufname" value="<?= htmlspecialchars($patient['transportmittel_rufname'] ?? '') ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="display_lokalisation" class="form-label">Fahrzeug-Lokalisation</label>
                                    <input type="text" class="form-control" id="display_lokalisation" value="<?= htmlspecialchars($patient['fahrzeug_lokalisation'] ?? '') ?>" readonly>
                                </div>
                                <?php if ($canTransport): ?>
                                    <div class="mb-3">
                                        <label for="transportziel" class="form-label">Transportziel</label>
                                        <select class="form-control" id="transportziel" name="transportziel">
                                            <option value="">Bitte wählen...</option>
                                            <option value="Kein Transport" <?= ($patient['transportziel'] === 'Kein Transport') ? 'selected' : '' ?>>Kein Transport</option>
                                            <?php foreach ($krankenhaeuser as $kh): ?>
                                                <option value="<?= htmlspecialchars($kh['name']) ?>" <?= ($patient['transportziel'] === $kh['name']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($kh['name']) ?><?= !empty($kh['ort']) ? ' (' . htmlspecialchars($kh['ort']) . ')' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <?php if ($canTransport && $patient['transport_abfahrt']): ?>
                                    <div class="info-box">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            Abfahrt: <?= date('d.m.Y H:i', strtotime($patient['transport_abfahrt'])) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Medizinische Informationen -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Medizinische Informationen</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="verletzungen" class="form-label">Verletzungen</label>
                                    <textarea class="form-control" id="verletzungen" name="verletzungen" rows="3"><?= htmlspecialchars($patient['verletzungen'] ?? '') ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="notizen" class="form-label">Notizen</label>
                                    <textarea class="form-control" id="notizen" name="notizen" rows="2"><?= htmlspecialchars($patient['notizen'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between mb-4">
                    <div>
                        <a href="<?= BASE_PATH ?>manv/board.php?id=<?= $patient['manv_lage_id'] ?>" class="btn btn-ghost">
                            <i class="fas fa-arrow-left me-2"></i>Zurück zum Board
                        </a>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-soft-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Änderungen speichern
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../assets/components/footer.php'; ?>

    <script>
        // Auto-update readonly fields and transportziel visibility when vehicle is selected
        document.getElementById('transportmittel_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const transportzielGroup = document.getElementById('transportziel')?.closest('.mb-3');

            if (selectedOption.value) {
                document.getElementById('display_rufname').value = selectedOption.dataset.bezeichnung || '';
                document.getElementById('display_fahrzeugtyp').value = selectedOption.dataset.fahrzeugtyp || '';
                document.getElementById('display_lokalisation').value = selectedOption.dataset.lokalisation || '';

                // Zeige/verstecke Transportziel nur wenn Fahrzeug ein Rettungsdienstfahrzeug ist (rd_type >= 1)
                const rdType = parseInt(selectedOption.dataset.rdtype);
                if (transportzielGroup) {
                    if (rdType >= 1) {
                        transportzielGroup.style.display = 'block';
                    } else {
                        transportzielGroup.style.display = 'none';
                        document.getElementById('transportziel').value = 'Kein Transport';
                    }
                }
            } else {
                document.getElementById('display_rufname').value = '';
                document.getElementById('display_fahrzeugtyp').value = '';
                document.getElementById('display_lokalisation').value = '';

                // Zeige Transportziel wenn kein Fahrzeug ausgewählt
                if (transportzielGroup) {
                    transportzielGroup.style.display = 'block';
                }
            }
        });

        // Initial check on page load
        document.addEventListener('DOMContentLoaded', function() {
            const selectElement = document.getElementById('transportmittel_id');
            if (selectElement) {
                selectElement.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>

</html>