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
    <?php include dirname(__DIR__, 4) . '/assets/components/_base/admin/head.php'; ?>
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
    <div class="flex">
        <?php $einsatzActivePage = 'create'; include dirname(__DIR__, 4) . '/assets/components/firetab-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <div class="container mx-auto my-4">
                <h1>Neuen Einsatz anlegen</h1>
                <?php Flash::render(); ?>
                <?php if (!empty($errors)): ?>
                    <div class="ignis-alert ignis-alert--danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" class="intra__tile p-3">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                        <div class="md:col-span-6">
                            <label class="ignis-field__label">Einsatznummer*</label>
                            <input type="text" name="incident_number" class="ignis-input" required>
                        </div>
                        <div class="md:col-span-6">
                            <label class="ignis-field__label">Einsatzort*</label>
                            <input type="text" name="location" class="ignis-input" required>
                        </div>
                        <div class="md:col-span-6">
                            <label class="ignis-field__label">Einsatzstichwort*</label>
                            <input type="text" name="keyword" class="ignis-input" required>
                        </div>
                        <div class="md:col-span-3">
                            <label class="ignis-field__label">Datum*</label>
                            <input type="date" name="date" class="ignis-input" value="<?= (new DateTime('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d') ?>" required>
                        </div>
                        <div class="md:col-span-3">
                            <label class="ignis-field__label">Uhrzeit*</label>
                            <input type="time" name="time" class="ignis-input" required>
                        </div>
                        <div class="md:col-span-6">
                            <label class="ignis-field__label">Einsatzleiter*</label>
                            <select name="leader_id" class="form-select" required data-custom-dropdown="true" data-search-threshold="5">
                                <option value="">Bitte wählen...</option>
                                <?php foreach ($leaders as $l): ?>
                                    <option value="<?= htmlspecialchars((string)$l['id']) ?>"><?= htmlspecialchars($l['fullname']) ?><?= $l['source_name'] ? ' [' . htmlspecialchars($l['source_name']) . ']' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-12">
                            <hr>
                        </div>
                        <div class="md:col-span-6">
                            <label class="ignis-field__label">Melder – Name</label>
                            <input type="text" name="caller_name" class="ignis-input">
                        </div>
                        <div class="md:col-span-6">
                            <label class="ignis-field__label">Melder – Kontakt</label>
                            <input type="text" name="caller_contact" class="ignis-input">
                        </div>
                        <div class="md:col-span-12">
                            <hr>
                        </div>
                        <div class="md:col-span-6">
                            <label class="ignis-field__label">Geschädigter/Eigentümer/Halter – Name</label>
                            <input type="text" name="owner_name" class="ignis-input">
                        </div>
                        <div class="md:col-span-6">
                            <label class="ignis-field__label">Geschädigter/Eigentümer/Halter – Kontakt</label>
                            <input type="text" name="owner_contact" class="ignis-input">
                        </div>
                        <div class="md:col-span-12">
                            <small class="text-gray-400">Optional: Angaben zum Geschädigten, Eigentümer oder Halter (Name/Kontakt).</small>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="ignis-btn ignis-btn--primary">Einsatz erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
