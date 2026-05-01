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
                                    <button type="button" class="ignis-btn ignis-btn--success" onclick="openCreatePoiModal()">
                                        <i class="fa-solid fa-plus"></i> POI erstellen
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php Flash::render(); ?>
                    <div class="mb-3">
                        <div class="btn-toolbar-group" id="statusFilter">
                            <button class="ignis-btn active" data-filter="">Alle</button>
                            <button class="ignis-btn" data-filter="Ja">Aktiv</button>
                            <button class="ignis-btn" data-filter="Nein">Inaktiv</button>
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
                                            $actions .= "<a title='Fachrichtungen verwalten' href='" . BASE_PATH . "settings/pois/departments?poi_id={$row['id']}' class='ignis-btn ignis-btn--sm ignis-btn--outline-secondary ignis-btn--icon mr-1'><i class='fa-solid fa-hospital'></i></a>";
                                        }
                                        $actions .= "<button type='button' title='POI bearbeiten' class='ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon' onclick='openEditPoiModal(this)' data-id='{$row['id']}' data-name='" . htmlspecialchars($row['name']) . "' data-strasse='" . htmlspecialchars($row['strasse'] ?? '') . "' data-hnr='" . htmlspecialchars($row['hnr'] ?? '') . "' data-ort='" . htmlspecialchars($row['ort']) . "' data-ortsteil='" . htmlspecialchars($row['ortsteil'] ?? '') . "' data-typ='" . htmlspecialchars($row['typ'] ?? '') . "' data-active='{$row['active']}'><i class='fa-solid fa-pen'></i></button>";
                                    }
                                ?>
                                    <tr>
                                        <td <?= $dimmed ?>>
                                            <span data-poi-card="<?= (int) $row['id'] ?>" style="cursor:help;">
                                                <?= htmlspecialchars($row['name']) ?>
                                            </span>
                                        </td>
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
        <template id="poiFormTemplate">
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
        </template>

        <form id="delete-poi-form" action="<?= BASE_PATH ?>settings/pois/delete" method="POST" style="display:none;">
            <input type="hidden" name="id" id="poi-delete-id">
        </form>
    <?php endif; ?>

    <script>
        $(document).ready(function() {
            var table = $('#table-pois').DataTable({
                stateSave: true, paging: true, lengthMenu: [10, 20, 50], pageLength: 20,
                order: [[0, 'asc']], columnDefs: [{ orderable: false, targets: -1 }],
                language: window.IgnisDataTableLang('POIs')
            });

            document.querySelectorAll('#statusFilter .ignis-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('#statusFilter .ignis-btn').forEach(function(b) { b.classList.remove('active'); });
                    this.classList.add('active');
                    table.column(6).search(this.dataset.filter).draw();
                });
            });
        });

        function openCreatePoiModal() {
            Dialog.form({
                title:        'Neuen POI anlegen',
                template:     'poiFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/pois/create',
                submitLabel:  'Erstellen',
                submitVariant:'success',
                onOpen: function (dlg) {
                    $(dlg.element).find('#poi-active').prop('checked', true);
                },
            });
        }

        function openEditPoiModal(btn) {
            var data = btn.dataset;
            document.getElementById('poi-delete-id').value = data.id;

            Dialog.form({
                title:        'POI bearbeiten (ID: ' + data.id + ')',
                template:     'poiFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/pois/update',
                hiddenFields: { id: data.id },
                submitLabel:  'Speichern',
                submitVariant:'soft-primary',
                dangerAction: {
                    label:   'Löschen',
                    onClick: function () {
                        showConfirm('Möchtest du diesen POI wirklich löschen?', {
                            danger:      true,
                            confirmText: 'Löschen',
                            title:       'POI löschen',
                        }).then(function (ok) {
                            if (ok) document.getElementById('delete-poi-form').submit();
                        });
                    },
                },
                onOpen: function (dlg) {
                    var $body = $(dlg.element);
                    $body.find('#poi-name').val(data.name);
                    $body.find('#poi-strasse').val(data.strasse || '');
                    $body.find('#poi-hnr').val(data.hnr || '');
                    $body.find('#poi-ort').val(data.ort);
                    $body.find('#poi-ortsteil').val(data.ortsteil || '');
                    $body.find('#poi-typ').val(data.typ || '');
                    $body.find('#poi-active').prop('checked', data.active == 1);
                },
            });
        }
    </script>
    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
