<?php
/**
 * View: Neuen Einsatz anlegen (Formular)
 *
 * @var array<int,array<string,mixed>> $leaders
 * @var array<string>                  $errors
 * @var \PDO                           $pdo
 */

use App\Helpers\Flash;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/enotf-custom-dropdown.css">
    <style>
        .enotf-dropdown-container.form-select {
            padding: .375rem .75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: var(--bs-body-color);
            background-color: var(--bs-body-bg);
            background-clip: padding-box;
            border: var(--bs-border-width) solid var(--bs-border-color);
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="protokolle">
    <div class="d-flex">
        <?php $einsatzActivePage = 'create'; include __DIR__ . '/../../assets/components/einsatz-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-grow-1" style="overflow-y: auto;">
            <div class="container my-4">
                <h1>Neuen Einsatz anlegen</h1>
                <?php Flash::render(); ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" class="intra__tile p-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Einsatznummer*</label>
                            <input type="text" name="incident_number" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Einsatzort*</label>
                            <input type="text" name="location" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Einsatzstichwort*</label>
                            <input type="text" name="keyword" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Datum*</label>
                            <input type="date" name="date" class="form-control" value="<?= (new DateTime('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Uhrzeit*</label>
                            <input type="time" name="time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Einsatzleiter*</label>
                            <select name="leader_id" class="form-select" required data-custom-dropdown="true" data-search-threshold="5">
                                <option value="">Bitte wählen...</option>
                                <?php foreach ($leaders as $l): ?>
                                    <option value="<?= htmlspecialchars((string)$l['id']) ?>"><?= htmlspecialchars($l['fullname']) ?><?= $l['source_name'] ? ' [' . htmlspecialchars($l['source_name']) . ']' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <hr>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Melder – Name</label>
                            <input type="text" name="caller_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Melder – Kontakt</label>
                            <input type="text" name="caller_contact" class="form-control">
                        </div>
                        <div class="col-12">
                            <hr>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Geschädigter/Eigentümer/Halter – Name</label>
                            <input type="text" name="owner_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Geschädigter/Eigentümer/Halter – Kontakt</label>
                            <input type="text" name="owner_contact" class="form-control">
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Optional: Angaben zum Geschädigten, Eigentümer oder Halter (Name/Kontakt).</small>
                        </div>
                    </div>
                    <div class="mt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Einsatz erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?= BASE_PATH ?>assets/js/enotf-custom-dropdown.js"></script>
    <script>
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                eNOTFCustomDropdown.init();
            });
        } else {
            eNOTFCustomDropdown.init();
        }
    </script>
</body>

</html>
