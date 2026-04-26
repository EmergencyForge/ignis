<?php
/**
 * View: Fahrtenbuch im FireTab-Kontext
 *
 * @var int                            $vehicleId
 * @var string                         $vehicleName
 * @var string                         $fahrerName
 * @var string                         $vehicleIdentifier
 * @var array<string,string>           $fahrttypen
 * @var array<int,array<string,mixed>> $entries
 * @var \PDO                           $pdo
 */

use App\Helpers\Flash;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="fahrtenbuch">
    <div class="flex">
        <?php
        $einsatzActivePage = 'fahrtenbuch';
        $einsatzExtraNav = '';
        include __DIR__ . '/../../assets/components/einsatz-sidebar.php';
        ?>

        <div class="flex-1 overflow-y-auto">
            <div class="container mx-auto my-4">
                <div class="mb-3 flex items-center justify-between">
                    <h1><i class="fa-solid fa-book mr-2"></i>Fahrtenbuch</h1>
                    <button type="button" class="ignis-btn ignis-btn--success ignis-btn--sm" id="toggleCreateForm">
                        <i class="fa-solid fa-plus mr-1"></i>Neuer Eintrag
                    </button>
                </div>

                <?php Flash::render(); ?>

                <!-- Create Form -->
                <div id="createFormWrap" style="display:none;" class="intra__tile p-4 mb-3">
                    <h5 class="mb-3">Neuer Eintrag</h5>
                    <form method="POST" action="<?= BASE_PATH ?>fahrtenbuch/actions.php">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="return_to" value="firetab">
                        <input type="hidden" name="source" value="firetab">

                        <?php
                        $context = 'firetab';
                        $entry = null;
                        include __DIR__ . '/../../assets/components/fahrtenbuch/_form-fields.php';
                        ?>

                        <div class="mt-3 flex gap-2">
                            <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--success"><i class="fa-solid fa-save mr-1"></i>Speichern</button>
                            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost" id="cancelCreateForm">Abbrechen</button>
                        </div>
                    </form>
                </div>

                <!-- Edit Form -->
                <div id="editFormWrap" style="display:none;" class="intra__tile p-4 mb-3">
                    <h5 class="mb-3">Eintrag bearbeiten</h5>
                    <form method="POST" action="<?= BASE_PATH ?>fahrtenbuch/actions.php" id="editForm">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id" value="">
                        <input type="hidden" name="return_to" value="firetab">
                        <input type="hidden" name="source" value="firetab">

                        <?php
                        $context = 'firetab';
                        $entry = null;
                        include __DIR__ . '/../../assets/components/fahrtenbuch/_form-fields.php';
                        ?>

                        <div class="mt-3 flex gap-2">
                            <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--success"><i class="fa-solid fa-save mr-1"></i>Aktualisieren</button>
                            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost" id="cancelEditForm">Abbrechen</button>
                        </div>
                    </form>
                </div>

                <!-- Entries List -->
                <div class="intra__tile p-4">
                    <?php
                    $context = 'firetab';
                    $canEdit = true;
                    $canDelete = false;
                    $actionsUrl = BASE_PATH . 'fahrtenbuch/actions.php';
                    include __DIR__ . '/../../assets/components/fahrtenbuch/_list-table.php';
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var createWrap = document.getElementById('createFormWrap');
        var editWrap = document.getElementById('editFormWrap');
        var toggleBtn = document.getElementById('toggleCreateForm');
        var cancelCreate = document.getElementById('cancelCreateForm');
        var cancelEdit = document.getElementById('cancelEditForm');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                editWrap.style.display = 'none';
                createWrap.style.display = createWrap.style.display === 'none' ? 'block' : 'none';
            });
        }
        if (cancelCreate) {
            cancelCreate.addEventListener('click', function() {
                createWrap.style.display = 'none';
            });
        }
        if (cancelEdit) {
            cancelEdit.addEventListener('click', function() {
                editWrap.style.display = 'none';
            });
        }

        document.querySelectorAll('.fb-edit-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                createWrap.style.display = 'none';
                editWrap.style.display = 'block';

                document.getElementById('edit_id').value = btn.dataset.id;

                var form = document.getElementById('editForm');
                var fields = {
                    'datum': btn.dataset.datum,
                    'abfahrt': btn.dataset.abfahrt,
                    'ankunft': btn.dataset.ankunft || '',
                    'fahrttyp': btn.dataset.fahrttyp,
                    'kilometer': btn.dataset.kilometer || '',
                    'stationierungsort': btn.dataset.stationierungsort || '',
                    'grund': btn.dataset.grund || ''
                };

                for (var key in fields) {
                    var input = form.querySelector('[name="' + key + '"]');
                    if (input) {
                        input.value = fields[key];
                    }
                }

                editWrap.scrollIntoView({ behavior: 'smooth' });
            });
        });
    });
    </script>
</body>

</html>
