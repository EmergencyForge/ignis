<?php
/**
 * View: POI-Verwaltung
 *
 * @var array<int,array<string,mixed>> $pois
 * @var \PDO                           $pdo
 */

use App\Auth\Permissions;
use App\Helpers\Flash;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . '/../../../assets/components/navbar.php'; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 mb-5 px-3">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item is-active">POIs</span></nav>
                    <div class="page-header mb-4">
                        <h1>POI-Verwaltung</h1>
                        <div class="header-actions">
                            <?php if (Permissions::check(['admin', 'pois.manage'])) : ?>
                                <div class="flex gap-2">
                                    <a href="<?= BASE_PATH ?>settings/pois/access-codes" class="ignis-btn ignis-btn--soft-warning">
                                        <i class="fa-solid fa-key"></i> Krankenhaus-Zugänge
                                    </a>
                                    <button type="button" class="ignis-btn ignis-btn--success" data-bs-toggle="modal" data-bs-target="#createPoiModal">
                                        <i class="fa-solid fa-plus"></i> POI erstellen
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php Flash::render(); ?>
                    <div class="mb-3">
                        <div class="btn-toolbar-group" id="statusFilter">
                            <button class="btn active" data-filter="">Alle</button>
                            <button class="btn" data-filter="Ja">Aktiv</button>
                            <button class="btn" data-filter="Nein">Inaktiv</button>
                        </div>
                    </div>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-pois">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Straße</th>
                                    <th scope="col">HNR</th>
                                    <th scope="col">Ort</th>
                                    <th scope="col">Ortsteil</th>
                                    <th scope="col">Typ</th>
                                    <th scope="col">Aktiv?</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pois as $row):
                                    $dimmed = '';
                                    if ((int)$row['active'] === 0) {
                                        $poiActive = "<span class='badge-status status-danger'><span class='status-dot'></span>Nein</span>";
                                        $dimmed = "style='color:var(--tag-color)'";
                                    } else {
                                        $poiActive = "<span class='badge-status status-success'><span class='status-dot'></span>Ja</span>";
                                    }
                                    $strasse = htmlspecialchars($row['strasse'] ?? '-');
                                    $hnr = htmlspecialchars($row['hnr'] ?? '-');
                                    $ortsteil = htmlspecialchars($row['ortsteil'] ?? '-');
                                    $typ = htmlspecialchars($row['typ'] ?? '-');

                                    $actions = '';
                                    if (Permissions::check(['admin', 'pois.manage'])) {
                                        if ($row['typ'] === 'Krankenhaus' || $row['typ'] === 'Klinik') {
                                            $actions .= "<a title='Fachrichtungen verwalten' href='" . BASE_PATH . "settings/pois/departments.php?poi_id={$row['id']}' class='btn btn-sm btn-outline-secondary btn-icon mr-1'><i class='fa-solid fa-hospital'></i></a>";
                                        }
                                        $actions .= "<a title='POI bearbeiten' href='#' class='btn btn-sm btn-soft-primary btn-icon edit-btn' data-bs-toggle='modal' data-bs-target='#editPoiModal' data-id='{$row['id']}' data-name='" . htmlspecialchars($row['name']) . "' data-strasse='" . htmlspecialchars($row['strasse'] ?? '') . "' data-hnr='" . htmlspecialchars($row['hnr'] ?? '') . "' data-ort='" . htmlspecialchars($row['ort']) . "' data-ortsteil='" . htmlspecialchars($row['ortsteil'] ?? '') . "' data-typ='" . htmlspecialchars($row['typ'] ?? '') . "' data-active='{$row['active']}'><i class='fa-solid fa-pen'></i></a>";
                                    }
                                ?>
                                    <tr>
                                        <td <?= $dimmed ?>><?= htmlspecialchars($row['name']) ?></td>
                                        <td <?= $dimmed ?>><?= $strasse ?></td>
                                        <td <?= $dimmed ?>><?= $hnr ?></td>
                                        <td <?= $dimmed ?>><?= htmlspecialchars($row['ort']) ?></td>
                                        <td <?= $dimmed ?>><?= $ortsteil ?></td>
                                        <td <?= $dimmed ?>><?= $typ ?></td>
                                        <td><?= $poiActive ?></td>
                                        <td><?= $actions ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (Permissions::check('admin')) : ?>
        <!-- Edit Modal -->
        <div class="modal fade" id="editPoiModal" tabindex="-1" aria-labelledby="editPoiModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/pois/update" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editPoiModalLabel">POI bearbeiten <small class="text-[var(--text-dimmed,#818189)]" id="poi-id-display"></small></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="poi-id">
                            <div class="mb-3"><label for="poi-name" class="ignis-field__label">Name / Objekt / Einrichtung *</label><input type="text" class="ignis-input" name="name" id="poi-name" required></div>
                            <div class="mb-3"><label for="poi-strasse" class="ignis-field__label">Straße</label><input type="text" class="ignis-input" name="strasse" id="poi-strasse"></div>
                            <div class="mb-3"><label for="poi-hnr" class="ignis-field__label">Hausnummer / Postal</label><input type="text" class="ignis-input" name="hnr" id="poi-hnr"></div>
                            <div class="mb-3"><label for="poi-ort" class="ignis-field__label">Ort *</label><input type="text" class="ignis-input" name="ort" id="poi-ort" required></div>
                            <div class="mb-3"><label for="poi-ortsteil" class="ignis-field__label">Ortsteil</label><input type="text" class="ignis-input" name="ortsteil" id="poi-ortsteil"></div>
                            <div class="mb-3">
                                <label for="poi-typ" class="ignis-field__label">Typ</label>
                                <select class="form-select" name="typ" id="poi-typ" data-custom-dropdown="true">
                                    <option value="">--- Kein Typ ---</option>
                                    <option value="Polizeiwache">Polizeiwache</option>
                                    <option value="Rettungswache">Rettungswache</option>
                                    <option value="Feuerwache">Feuerwache</option>
                                    <option value="Krankenhaus">Krankenhaus</option>
                                    <option value="Klinik">Ärztliche Praxis / Klinik</option>
                                    <option value="Behörde">Behörde</option>
                                    <option value="Schule">Schule / Bildungseinrichtung</option>
                                    <option value="Sonstiges">Sonstiges</option>
                                </select>
                            </div>
                            <label class="ignis-checkbox" for="poi-active"><input type="checkbox" name="active" id="poi-active"><span>Aktiv?</span></label>
                        </div>
                        <div class="modal-footer flex justify-between">
                            <button type="button" class="ignis-btn ignis-btn--ghost-danger" id="delete-poi-btn">Löschen</button>
                            <div>
                                <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                                <button type="submit" class="ignis-btn ignis-btn--soft-primary">Speichern</button>
                            </div>
                        </div>
                    </form>
                    <form id="delete-poi-form" action="<?= BASE_PATH ?>settings/pois/delete" method="POST" style="display:none;">
                        <input type="hidden" name="id" id="poi-delete-id">
                    </form>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div class="modal fade" id="createPoiModal" tabindex="-1" aria-labelledby="createPoiModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/pois/create" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createPoiModalLabel">Neuen POI anlegen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3"><label for="new-poi-name" class="ignis-field__label">Name / Objekt / Einrichtung *</label><input type="text" class="ignis-input" name="name" id="new-poi-name" required></div>
                            <div class="mb-3"><label for="new-poi-strasse" class="ignis-field__label">Straße</label><input type="text" class="ignis-input" name="strasse" id="new-poi-strasse"></div>
                            <div class="mb-3"><label for="new-poi-hnr" class="ignis-field__label">Hausnummer / Postal</label><input type="text" class="ignis-input" name="hnr" id="new-poi-hnr"></div>
                            <div class="mb-3"><label for="new-poi-ort" class="ignis-field__label">Ort *</label><input type="text" class="ignis-input" name="ort" id="new-poi-ort" required></div>
                            <div class="mb-3"><label for="new-poi-ortsteil" class="ignis-field__label">Ortsteil</label><input type="text" class="ignis-input" name="ortsteil" id="new-poi-ortsteil"></div>
                            <div class="mb-3">
                                <label for="new-poi-typ" class="ignis-field__label">Typ</label>
                                <select class="form-select" name="typ" id="new-poi-typ" data-custom-dropdown="true">
                                    <option value="">--- Kein Typ ---</option>
                                    <option value="Polizeiwache">Polizeiwache</option>
                                    <option value="Rettungswache">Rettungswache</option>
                                    <option value="Feuerwache">Feuerwache</option>
                                    <option value="Krankenhaus">Krankenhaus</option>
                                    <option value="Klinik">Ärztliche Praxis / Klinik</option>
                                    <option value="Behörde">Behörde</option>
                                    <option value="Schule">Schule / Bildungseinrichtung</option>
                                    <option value="Sonstiges">Sonstiges</option>
                                </select>
                            </div>
                            <label class="ignis-checkbox" for="new-poi-active"><input type="checkbox" name="active" id="new-poi-active" checked><span>Aktiv?</span></label>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                            <button type="submit" class="ignis-btn ignis-btn--success">Erstellen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        $(document).ready(function() {
            var table = $('#table-pois').DataTable({
                stateSave: true, paging: true, lengthMenu: [10, 20, 50], pageLength: 20,
                order: [[0, 'asc']], columnDefs: [{ orderable: false, targets: -1 }],
                language: window.IgnisDataTableLang('POIs')
            });

            document.querySelectorAll('#statusFilter .btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('#statusFilter .btn').forEach(function(b) { b.classList.remove('active'); });
                    this.classList.add('active');
                    table.column(6).search(this.dataset.filter).draw();
                });
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    document.getElementById('poi-id').value = id;
                    document.getElementById('poi-id-display').textContent = '(ID: ' + id + ')';
                    document.getElementById('poi-name').value = this.dataset.name;
                    document.getElementById('poi-strasse').value = this.dataset.strasse || '';
                    document.getElementById('poi-hnr').value = this.dataset.hnr || '';
                    document.getElementById('poi-ort').value = this.dataset.ort;
                    document.getElementById('poi-ortsteil').value = this.dataset.ortsteil || '';
                    document.getElementById('poi-typ').value = this.dataset.typ || '';
                    document.getElementById('poi-active').checked = this.dataset.active == 1;
                    document.getElementById('poi-delete-id').value = id;
                });
            });

            const delBtn = document.getElementById('delete-poi-btn');
            if (delBtn) {
                delBtn.addEventListener('click', function() {
                    showConfirm('Möchtest du diesen POI wirklich löschen?', { danger: true, confirmText: 'Löschen', title: 'POI löschen' }).then(result => {
                        if (result) document.getElementById('delete-poi-form').submit();
                    });
                });
            }
        });
    </script>
    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
