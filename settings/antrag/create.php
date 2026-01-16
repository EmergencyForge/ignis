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

if (isset($_POST['submit'])) {
    $name = trim($_POST['name']);
    $beschreibung = trim($_POST['beschreibung']);
    $icon = trim($_POST['icon']);
    $aktiv = isset($_POST['aktiv']) ? 1 : 0;
    $sortierung = (int)$_POST['sortierung'];

    if (empty($name)) {
        Flash::set('error', 'Bitte geben Sie einen Namen für den Antragstyp an.');
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO intra_antrag_typen 
                (name, beschreibung, icon, aktiv, sortierung, erstellt_von) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name,
                $beschreibung,
                $icon,
                $aktiv,
                $sortierung,
                $_SESSION['userid']
            ]);

            $new_id = $pdo->lastInsertId();

            // Audit Log
            $auditLogger = new AuditLogger($pdo);
            $auditLogger->log(
                $_SESSION['userid'],
                'Neuer Antragstyp erstellt',
                $name . ' [ID: ' . $new_id . ']',
                'Antragstypen',
                1
            );

            Flash::set('success', 'Antragstyp erfolgreich erstellt. Sie können jetzt Felder hinzufügen.');
            header("Location: " . BASE_PATH . "settings/antrag/edit.php?id=" . $new_id);
            exit();
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Erstellen: ' . $e->getMessage());
        }
    }
}

// Höchste Sortierung ermitteln
$stmt = $pdo->prepare("SELECT MAX(sortierung) FROM intra_antrag_typen");
$stmt->execute();
$max_sort = $stmt->fetchColumn();
$default_sort = ($max_sort ?? 0) + 1;
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Neuer Antragstyp &rsaquo; <?php echo SYSTEM_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/style.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/admin.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/_ext/lineawesome/css/line-awesome.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/mavenpro/css/all.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <script src="<?= BASE_PATH ?>vendor/components/jquery/jquery.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>assets/favicon/favicon-96x96.png" sizes="96x96" />
    <meta name="theme-color" content="<?php echo SYSTEM_COLOR ?>" />
</head>

<body data-bs-theme="dark" data-page="antragstyp-create">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <hr class="text-light my-3">
                    <h1><i class="fa-solid fa-circle-plus me-2"></i>Neuen Antragstyp erstellen</h1>

                    <?php Flash::render(); ?>

                    <hr class="text-light my-3">

                    <div class="intra__tile p-4">
                        <form method="post" action="">
                            <div class="mb-4">
                                <label for="name" class="form-label fw-bold">
                                    Name des Antragstyps <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                    class="form-control"
                                    id="name"
                                    name="name"
                                    placeholder="z.B. Urlaubsantrag, Versetzungsantrag, ..."
                                    required
                                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                                <small class="text-muted">Dieser Name wird Benutzern angezeigt</small>
                            </div>

                            <div class="mb-4">
                                <label for="beschreibung" class="form-label fw-bold">
                                    Beschreibung
                                </label>
                                <textarea class="form-control"
                                    id="beschreibung"
                                    name="beschreibung"
                                    rows="3"
                                    placeholder="Kurze Erklärung, wofür dieser Antrag verwendet wird"><?= htmlspecialchars($_POST['beschreibung'] ?? '') ?></textarea>
                                <small class="text-muted">Optional: Hilft Benutzern zu verstehen, wann sie diesen Antrag nutzen sollten</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="icon" class="form-label fw-bold">
                                        Icon
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i id="icon-preview" class="<?= htmlspecialchars($_POST['icon'] ?? 'fa-solid fa-file-lines') ?> fs-4"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control"
                                            id="icon"
                                            name="icon"
                                            placeholder="fa-solid fa-file-lines"
                                            value="<?= htmlspecialchars($_POST['icon'] ?? 'fa-solid fa-file-lines') ?>">
                                    </div>
                                    <small class="text-muted">
                                        Font Awesome Icon-Klasse
                                        <a href="https://fontawesome.com/search?o=r&m=free" target="_blank" class="text-info">
                                            (Icons durchsuchen)
                                        </a>
                                    </small>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <label for="sortierung" class="form-label fw-bold">
                                        Sortierung
                                    </label>
                                    <input type="number"
                                        class="form-control"
                                        id="sortierung"
                                        name="sortierung"
                                        value="<?= $_POST['sortierung'] ?? $default_sort ?>"
                                        min="0">
                                    <small class="text-muted">Niedrigere Zahlen erscheinen zuerst</small>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input"
                                        type="checkbox"
                                        id="aktiv"
                                        name="aktiv"
                                        <?= isset($_POST['aktiv']) || !isset($_POST['submit']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="aktiv">
                                        <strong>Antragstyp sofort aktivieren</strong>
                                        <br>
                                        <small class="text-muted">Wenn deaktiviert, können Benutzer diesen Antragstyp nicht sehen</small>
                                    </label>
                                </div>
                            </div>

                            <hr class="text-light my-4">

                            <div class="alert alert-info">
                                <i class="fa-solid fa-circle-info me-2"></i>
                                <strong>Hinweis:</strong> Nach dem Erstellen können Sie Formularfelder für diesen Antragstyp hinzufügen.
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="<?= BASE_PATH ?>settings/antrag/list.php" class="btn btn-secondary">
                                    <i class="fa-solid fa-xmark me-2"></i>Abbrechen
                                </a>
                                <button type="submit" name="submit" class="btn btn-success">
                                    <i class="fa-solid fa-floppy-disk me-2"></i>Antragstyp erstellen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Live Icon-Vorschau
        $('#icon').on('input', function() {
            const iconClass = $(this).val() || 'fa-solid fa-file-lines';
            $('#icon-preview').attr('class', iconClass + ' fs-4');
        });
    </script>

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>