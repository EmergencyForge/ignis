<?php
/**
 * View: Antragstypen verwalten
 *
 * @var array<int,array<string,mixed>> $typen
 * @var \PDO                           $pdo
 */

use App\Helpers\Flash;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="antragstypen">
    <?php include __DIR__ . '/../../../assets/components/navbar.php'; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-4 flex items-center justify-between">
                <h1>Antragstypen verwalten</h1>
                <a href="<?= BASE_PATH ?>settings/antrag/create.php" class="btn btn-success no-underline hover:no-underline">
                    <i class="fa-solid fa-plus mr-2"></i>Neuer Antragstyp
                </a>
            </div>

            <?php Flash::render(); ?>

            <?php if (empty($typen)): ?>
                <div class="alert alert-info">
                    <i class="fa-solid fa-info-circle mr-2"></i>
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
                                        <th style="width: 200px;" class="text-right">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($typen as $typ): ?>
                                        <tr>
                                            <td>
                                                <input type="number"
                                                    name="sortierung[<?= (int)$typ['id'] ?>]"
                                                    value="<?= (int)$typ['sortierung'] ?>"
                                                    class="form-control form-control-sm"
                                                    style="width: 60px;">
                                            </td>
                                            <td class="text-center">
                                                <i class="<?= htmlspecialchars($typ['icon']) ?> text-xl"></i>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($typ['name']) ?></strong>
                                            </td>
                                            <td>
                                                <small class="text-gray-400">
                                                    <?= htmlspecialchars(substr($typ['beschreibung'] ?? '', 0, 80)) ?>
                                                    <?= strlen($typ['beschreibung'] ?? '') > 80 ? '...' : '' ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info"><?= (int)$typ['anzahl_felder'] ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary"><?= (int)$typ['anzahl_antraege'] ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($typ['aktiv']): ?>
                                                    <span class="badge-status status-success"><span class="status-dot"></span>Aktiv</span>
                                                <?php else: ?>
                                                    <span class="badge-status status-muted"><span class="status-dot"></span>Inaktiv</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-right">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?= BASE_PATH ?>settings/antrag/edit.php?id=<?= (int)$typ['id'] ?>" class="btn btn-soft-primary btn-icon mx-1 no-underline hover:no-underline" title="Bearbeiten">
                                                        <i class="fa-solid fa-edit"></i>
                                                    </a>
                                                    <a href="?toggle=<?= (int)$typ['id'] ?>" class="btn btn-soft-warning btn-icon mx-1 no-underline hover:no-underline" title="<?= $typ['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                                        <i class="fa-solid fa-power-off"></i>
                                                    </a>
                                                    <?php if ((int)$typ['anzahl_antraege'] === 0): ?>
                                                        <a href="?delete=<?= (int)$typ['id'] ?>" class="btn btn-outline-danger mx-1 no-underline hover:no-underline" title="Löschen"
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

                        <div class="mt-4">
                            <button type="submit" name="update_sortierung" class="btn btn-soft-primary">
                                <i class="fa-solid fa-save mr-2"></i>Sortierung speichern
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <div class="mt-4">
                <a href="<?= BASE_PATH ?>antrag/admin/list.php" class="btn btn-ghost no-underline hover:no-underline">
                    <i class="fa-solid fa-arrow-left mr-2"></i>Zurück zur Antragsübersicht
                </a>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
