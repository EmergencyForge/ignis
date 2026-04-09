<?php
/**
 * View: MANV-Lage bearbeiten
 *
 * @var array<string,mixed>             $lage
 * @var array<int,array<string,mixed>>  $users
 * @var string|null                     $success
 * @var string|null                     $error
 * @var \PDO                            $pdo
 */

use App\Helpers\Flash;

$lageId     = (int) $lage['id'];
$SITE_TITLE = 'MANV-Lage bearbeiten - ' . htmlspecialchars($lage['einsatznummer']);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" id="manv-edit" data-page="edivi">
    <?php include __DIR__ . '/../../assets/components/navbar.php'; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row mb-5">
                <div class="col-12">
                    <h1>MANV-Lage bearbeiten</h1>
                    <p class="text-muted"><?= htmlspecialchars($lage['einsatznummer']) ?></p>
                </div>
            </div>

            <?php Flash::render(); ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Grunddaten</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="einsatznummer" class="form-label">Einsatznummer *</label>
                                <input type="text" class="form-control" id="einsatznummer" name="einsatznummer" value="<?= htmlspecialchars($lage['einsatznummer']) ?>" required>
                                <small class="text-muted">z.B. 2025-12345</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="einsatzbeginn" class="form-label">Einsatzbeginn</label>
                                <input type="datetime-local" class="form-control" id="einsatzbeginn" name="einsatzbeginn" value="<?= !empty($lage['einsatzbeginn']) ? date('Y-m-d\TH:i', strtotime($lage['einsatzbeginn'])) : '' ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="einsatzort" class="form-label">Einsatzort *</label>
                            <input type="text" class="form-control" id="einsatzort" name="einsatzort" value="<?= htmlspecialchars($lage['einsatzort']) ?>" required>
                            <small class="text-muted">z.B. Hauptstraße 123, Musterstadt</small>
                        </div>

                        <div class="mb-3">
                            <label for="einsatzanlass" class="form-label">Einsatzanlass / Szenario</label>
                            <textarea class="form-control" id="einsatzanlass" name="einsatzanlass" rows="3"><?= htmlspecialchars($lage['einsatzanlass'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="aktiv" <?= $lage['status'] === 'aktiv' ? 'selected' : '' ?>>Aktiv</option>
                                <option value="abgeschlossen" <?= $lage['status'] === 'abgeschlossen' ? 'selected' : '' ?>>Abgeschlossen</option>
                                <option value="archiviert" <?= $lage['status'] === 'archiviert' ? 'selected' : '' ?>>Archiviert</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Einsatzleitung</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="lna_mitarbeiter_id" class="form-label">Leitender Notarzt (LNA)</label>
                                <select class="form-control" id="lna_mitarbeiter_id" name="lna_mitarbeiter_id">
                                    <option value="">Bitte wählen...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= (int) $user['id'] ?>"
                                            data-name="<?= htmlspecialchars($user['fullname'] ?? '') ?>"
                                            <?= (int) ($lage['lna_mitarbeiter_id'] ?? 0) === (int) $user['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($user['fullname'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" id="lna_name" name="lna_name" value="<?= htmlspecialchars($lage['lna_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="orgl_mitarbeiter_id" class="form-label">Organisatorischer Leiter (OrgL)</label>
                                <select class="form-control" id="orgl_mitarbeiter_id" name="orgl_mitarbeiter_id">
                                    <option value="">Bitte wählen...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= (int) $user['id'] ?>"
                                            data-name="<?= htmlspecialchars($user['fullname'] ?? '') ?>"
                                            <?= (int) ($lage['orgl_mitarbeiter_id'] ?? 0) === (int) $user['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($user['fullname'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" id="orgl_name" name="orgl_name" value="<?= htmlspecialchars($lage['orgl_name'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Notizen</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="notizen" class="form-label">Allgemeine Notizen</label>
                            <textarea class="form-control" id="notizen" name="notizen" rows="4"><?= htmlspecialchars($lage['notizen'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between mb-4">
                    <a href="<?= BASE_PATH ?>manv/board.php?id=<?= $lageId ?>" class="btn btn-ghost">
                        <i class="fas fa-arrow-left me-2"></i>Zurück zum Board
                    </a>
                    <button type="submit" class="btn btn-soft-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Änderungen speichern
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
