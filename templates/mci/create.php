<?php
/**
 * View: Neue MANV-Lage anlegen
 *
 * @var array<int,array<string,mixed>> $users
 * @var string|null                     $error
 * @var \PDO                            $pdo
 */

use App\Helpers\Flash;

$SITE_TITLE = 'Neue MANV-Lage anlegen';
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" id="manv-create" data-page="edivi">
    <?php include __DIR__ . '/../../assets/components/navbar.php'; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-4">
                <h1><i class="fas fa-plus-circle mr-2"></i>Neue MANV-Lage anlegen</h1>
                <p class="text-gray-400">Erfassung einer neuen MANV-Lage (Massenanfall von Verletzten)</p>
            </div>

            <?php Flash::render(); ?>

            <?php if (!empty($error)): ?>
                <div class="ignis-alert ignis-alert--danger">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="ignis-card mb-4">
                    <div class="ignis-card__header">
                        <h5 class="mb-0">Grunddaten</h5>
                    </div>
                    <div class="ignis-card__body">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="einsatznummer" class="ignis-field__label">Einsatznummer *</label>
                                <input type="text" class="ignis-input" id="einsatznummer" name="einsatznummer" required>
                                <small class="mt-1 block text-xs text-gray-400">z.B. 2025-12345</small>
                            </div>
                            <div>
                                <label for="einsatzbeginn" class="ignis-field__label">Einsatzbeginn</label>
                                <div data-ignis-datetimepicker
                                     data-name="einsatzbeginn"
                                     data-value="<?= date('Y-m-d\TH:i') ?>"
                                     class="ignis-datetimepicker"></div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="einsatzort" class="ignis-field__label">Einsatzort *</label>
                            <input type="text" class="ignis-input" id="einsatzort" name="einsatzort" required>
                            <small class="mt-1 block text-xs text-gray-400">z.B. Hauptstraße 123, Musterstadt</small>
                        </div>

                        <div class="mt-4">
                            <label for="einsatzanlass" class="ignis-field__label">Einsatzanlass / Szenario</label>
                            <textarea class="ignis-input" id="einsatzanlass" name="einsatzanlass" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="ignis-card mb-4">
                    <div class="ignis-card__header">
                        <h5 class="mb-0">Einsatzleitung</h5>
                    </div>
                    <div class="ignis-card__body">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="lna_mitarbeiter_id" class="ignis-field__label">Leitender Notarzt (LNA)</label>
                                <select class="ignis-input" id="lna_mitarbeiter_id" name="lna_mitarbeiter_id">
                                    <option value="">Bitte wählen...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= (int) $user['id'] ?>" data-name="<?= htmlspecialchars($user['fullname']) ?>">
                                            <?= htmlspecialchars($user['fullname']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" id="lna_name" name="lna_name">
                            </div>
                            <div>
                                <label for="orgl_mitarbeiter_id" class="ignis-field__label">Organisatorischer Leiter (OrgL)</label>
                                <select class="ignis-input" id="orgl_mitarbeiter_id" name="orgl_mitarbeiter_id">
                                    <option value="">Bitte wählen...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= (int) $user['id'] ?>" data-name="<?= htmlspecialchars($user['fullname']) ?>">
                                            <?= htmlspecialchars($user['fullname']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" id="orgl_name" name="orgl_name">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ignis-card mb-4">
                    <div class="ignis-card__header">
                        <h5 class="mb-0">Notizen</h5>
                    </div>
                    <div class="ignis-card__body">
                        <div>
                            <label for="notizen" class="ignis-field__label">Allgemeine Notizen</label>
                            <textarea class="ignis-input" id="notizen" name="notizen" rows="4"></textarea>
                        </div>
                    </div>
                </div>

                <div class="mb-4 flex items-center justify-between">
                    <a href="<?= BASE_PATH ?>mci/index" class="ignis-btn ignis-btn--ghost no-underline hover:no-underline">
                        <i class="fas fa-arrow-left mr-2"></i>Zurück
                    </a>
                    <button type="submit" class="ignis-btn ignis-btn--soft-primary ignis-btn--lg">
                        <i class="fas fa-save mr-2"></i>MANV-Lage anlegen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../../assets/components/footer.php'; ?>

    <script>
        document.getElementById('lna_mitarbeiter_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            document.getElementById('lna_name').value = selectedOption.value ? selectedOption.dataset.name : '';
        });

        document.getElementById('orgl_mitarbeiter_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            document.getElementById('orgl_name').value = selectedOption.value ? selectedOption.dataset.name : '';
        });
    </script>
</body>

</html>
