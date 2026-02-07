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

if (!Permissions::check(['admin'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

// Toggle Aktivierungsstatus
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE intra_antrag_typen SET aktiv = NOT aktiv WHERE id = ?");
    $stmt->execute([$id]);
    Flash::set('success', 'Status erfolgreich geändert');
    header("Location: " . BASE_PATH . "settings/antrag/list.php");
    exit();
}

// Antragstyp löschen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Prüfen ob Anträge existieren
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM intra_antraege WHERE antragstyp_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        Flash::set('error', 'Dieser Antragstyp kann nicht gelöscht werden, da noch ' . $count . ' Anträge existieren.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM intra_antrag_typen WHERE id = ?");
        $stmt->execute([$id]);
        Flash::set('success', 'Antragstyp erfolgreich gelöscht');
    }

    header("Location: " . BASE_PATH . "settings/antrag/list.php");
    exit();
}

// Sortierung aktualisieren
if (isset($_POST['update_sortierung'])) {
    $sortierungen = $_POST['sortierung'] ?? [];
    foreach ($sortierungen as $id => $sort) {
        $stmt = $pdo->prepare("UPDATE intra_antrag_typen SET sortierung = ? WHERE id = ?");
        $stmt->execute([$sort, $id]);
    }
    Flash::set('success', 'Sortierung aktualisiert');
    header("Location: " . BASE_PATH . "settings/antrag/list.php");
    exit();
}

// Antragstypen laden
$stmt = $pdo->prepare("
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
$stmt->execute();
$typen = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . '/../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="antragstypen">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col">
                    <hr class="text-light my-3">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1>Antragstypen verwalten</h1>
                        <a href="<?= BASE_PATH ?>settings/antrag/create.php" class="btn btn-success">
                            <i class="fa-solid fa-plus me-2"></i>Neuer Antragstyp
                        </a>
                    </div>

                    <?php Flash::render(); ?>

                    <hr class="text-light my-3">

                    <?php if (empty($typen)): ?>
                        <div class="alert alert-info">
                            <i class="fa-solid fa-info-circle me-2"></i>
                            Noch keine Antragstypen vorhanden. Erstellen Sie jetzt Ihren ersten Antragstyp!
                        </div>
                    <?php else: ?>
                        <form method="post" action="">
                            <div class="intra__tile p-3">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th style="width: 60px;">Sort.</th>
                                                <th style="width: 60px;">Icon</th>
                                                <th>Name</th>
                                                <th>Beschreibung</th>
                                                <th style="width: 100px;" class="text-center">Felder</th>
                                                <th style="width: 100px;" class="text-center">Anträge</th>
                                                <th style="width: 100px;" class="text-center">Status</th>
                                                <th style="width: 200px;" class="text-end">Aktionen</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($typen as $typ): ?>
                                                <tr>
                                                    <td>
                                                        <input type="number"
                                                            name="sortierung[<?= $typ['id'] ?>]"
                                                            value="<?= $typ['sortierung'] ?>"
                                                            class="form-control form-control-sm"
                                                            style="width: 60px;">
                                                    </td>
                                                    <td class="text-center">
                                                        <i class="<?= htmlspecialchars($typ['icon']) ?> fs-4"></i>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($typ['name']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars(substr($typ['beschreibung'] ?? '', 0, 80)) ?>
                                                            <?= strlen($typ['beschreibung'] ?? '') > 80 ? '...' : '' ?>
                                                        </small>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info"><?= $typ['anzahl_felder'] ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary"><?= $typ['anzahl_antraege'] ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($typ['aktiv']): ?>
                                                            <span class="badge-status status-success"><span class="status-dot"></span>Aktiv</span>
                                                        <?php else: ?>
                                                            <span class="badge-status status-muted"><span class="status-dot"></span>Inaktiv</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="<?= BASE_PATH ?>settings/antrag/edit.php?id=<?= $typ['id'] ?>"
                                                                class="btn btn-soft-primary btn-icon mx-1"
                                                                title="Bearbeiten">
                                                                <i class="fa-solid fa-edit"></i>
                                                            </a>
                                                            <a href="?toggle=<?= $typ['id'] ?>"
                                                                class="btn btn-soft-warning btn-icon mx-1"
                                                                title="<?= $typ['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                                                <i class="fa-solid fa-power-off"></i>
                                                            </a>
                                                            <?php if ($typ['anzahl_antraege'] == 0): ?>
                                                                <a href="?delete=<?= $typ['id'] ?>"
                                                                    class="btn btn-outline-danger mx-1"
                                                                    title="Löschen"
                                                                    onclick="event.preventDefault(); showConfirm('Antragstyp wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Antragstyp löschen'}).then(result => { if(result) window.location.href = this.href; });">
                                                                    <i class="fa-solid fa-trash"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" name="update_sortierung" class="btn btn-soft-primary">
                                        <i class="fa-solid fa-save me-2"></i>Sortierung speichern
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>

                    <div class="mt-4">
                        <a href="<?= BASE_PATH ?>antrag/admin/list.php" class="btn btn-ghost">
                            <i class="fa-solid fa-arrow-left me-2"></i>Zurück zur Antragsübersicht
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>