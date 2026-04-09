<?php
/**
 * View: Admin-Detailansicht eines Antrags mit Bearbeitungs-Form
 *
 * @var \App\Models\Antrag                          $antrag
 * @var array<int,\stdClass>                        $felderMitWerten
 * @var array{class:string,text:string,icon:string} $currentStatus
 * @var string                                       $currentUserFullname
 * @var \PDO                                         $pdo
 */

use App\Helpers\Flash;
use App\Models\Antrag;

$caseId     = $antrag->uniqueid;
$createDate = $antrag->time_added;
$SITE_TITLE = htmlspecialchars($antrag->typ->name) . ' bearbeiten [#' . htmlspecialchars($caseId) . ']';
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../../assets/components/_base/admin/head.php'; ?>
    <style>
        .intra__tile {
            padding: 1.5rem;
        }
        .field-label {
            font-weight: 600;
            color: #aaa;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        .field-value {
            background: rgba(0, 0, 0, 0.3);
            padding: 0.75rem;
            border-radius: 0;
            min-height: 2.5rem;
        }
    </style>
</head>

<body data-bs-theme="dark">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col">
                    <h1>
                        <i class="<?= htmlspecialchars($antrag->typ->icon ?? 'fa-solid fa-file') ?> me-2"></i>
                        <?= htmlspecialchars($antrag->typ->name) ?> bearbeiten #<?= htmlspecialchars($caseId) ?>
                    </h1>

                    <?php Flash::render(); ?>

                    <form method="post">
                        <div class="row">
                            <div class="col-lg-8">
                                <!-- Antragsteller -->
                                <div class="intra__tile mb-4">
                                    <h5 class="mb-3"><i class="fa-solid fa-user me-2"></i>Antragsteller</h5>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="field-label">Name und Dienstnummer</div>
                                            <div class="field-value"><?= htmlspecialchars($antrag->name_dn) ?></div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="field-label">Dienstgrad</div>
                                            <div class="field-value"><?= htmlspecialchars($antrag->dienstgrad ?? '') ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Antragsinhalt -->
                                <div class="intra__tile mb-4">
                                    <h5 class="mb-3"><i class="fa-solid fa-file-lines me-2"></i>Antragsinhalt</h5>

                                    <?php
                                    $current_row = [];
                                    foreach ($felderMitWerten as $index => $feld):
                                        $breite_class = $feld->breite === 'half' ? 'col-md-6' : 'col-12';

                                        if (empty($current_row)) {
                                            echo '<div class="row">';
                                        }
                                        $current_row[] = $feld->breite;
                                    ?>
                                        <div class="<?= $breite_class ?> mb-3">
                                            <div class="field-label"><?= htmlspecialchars($feld->label) ?></div>
                                            <div class="field-value">
                                                <?php if ($feld->feldtyp === 'checkbox'): ?>
                                                    <?= $feld->wert ? '<i class="fa-solid fa-square-check text-success"></i> Ja' : '<i class="fa-regular fa-square text-muted"></i> Nein' ?>
                                                <?php elseif (empty($feld->wert)): ?>
                                                    <span class="text-muted"><i>Keine Angabe</i></span>
                                                <?php else: ?>
                                                    <?= nl2br(htmlspecialchars($feld->wert)) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php
                                        $is_last      = $index === count($felderMitWerten) - 1;
                                        $row_complete = ($feld->breite === 'full') || (count($current_row) >= 2) || $is_last;

                                        if ($row_complete) {
                                            echo '</div>';
                                            $current_row = [];
                                        }
                                    endforeach;
                                    ?>
                                </div>

                                <!-- Bearbeitung -->
                                <div class="intra__tile mb-4">
                                    <h5 class="mb-3"><i class="fa-solid fa-clipboard-check me-2"></i>Bearbeitung</h5>

                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Aktueller Bearbeiter</label>
                                        <div class="form-control-plaintext">
                                            <?php if (!empty($antrag->cirs_manager)): ?>
                                                <span class="fw-bold"><?= htmlspecialchars($antrag->cirs_manager) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted"><i>Noch nicht zugewiesen</i></span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">Wird auf "<?= htmlspecialchars($currentUserFullname) ?>" gesetzt beim Speichern</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="cirs_status" class="form-label fw-bold">Status setzen <span class="text-danger">*</span></label>
                                        <select class="form-select" id="cirs_status" name="cirs_status" required>
                                            <?php foreach (Antrag::STATUS_LABELS as $value => $label): ?>
                                                <option value="<?= (int) $value ?>" <?= $antrag->cirs_status === $value ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($label) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="cirs_text" class="form-label fw-bold">Bemerkung durch Bearbeiter</label>
                                        <textarea class="form-control" id="cirs_text" name="cirs_text" rows="5"
                                            placeholder="Fügen Sie hier Ihre Bemerkungen zum Antrag hinzu..."><?= htmlspecialchars($antrag->cirs_text ?? '') ?></textarea>
                                        <small class="text-muted">Diese Bemerkung wird dem Antragsteller angezeigt</small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <!-- Antragsdetails -->
                                <div class="intra__tile mb-4">
                                    <h6 class="mb-3"><i class="fa-solid fa-circle-info me-2"></i>Antragsdetails</h6>
                                    <div class="small">
                                        <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                                            <span class="text-muted">Antragsnummer:</span>
                                            <span class="fw-bold">#<?= htmlspecialchars($caseId) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                                            <span class="text-muted">Typ:</span>
                                            <span><?= htmlspecialchars($antrag->typ->name) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                                            <span class="text-muted">Erstellt am:</span>
                                            <span><?= $createDate ? $createDate->format('d.m.Y H:i') : '' ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                                            <span class="text-muted">Discord-ID:</span>
                                            <span class="font-monospace small"><?= htmlspecialchars($antrag->discordid ?? 'N/A') ?></span>
                                        </div>
                                        <?php if ($antrag->cirs_time): ?>
                                            <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                                                <span class="text-muted">Letzte Bearbeitung:</span>
                                                <span><?= $antrag->cirs_time->format('d.m.Y H:i') ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="d-flex justify-content-between py-2">
                                            <span class="text-muted">Status:</span>
                                            <span class="badge text-bg-<?= $currentStatus['class'] ?>">
                                                <i class="<?= $currentStatus['icon'] ?> me-1"></i>
                                                <?= $currentStatus['text'] ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Aktionen -->
                                <div class="intra__tile">
                                    <h6 class="mb-3"><i class="fa-solid fa-screwdriver-wrench me-2"></i>Aktionen</h6>
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="save" class="btn btn-success">
                                            <i class="fa-solid fa-floppy-disk me-2"></i>Änderungen speichern
                                        </button>
                                        <a href="<?= BASE_PATH ?>antrag/admin/list.php" class="btn btn-ghost">
                                            <i class="fa-solid fa-arrow-left me-2"></i>Zurück zur Übersicht
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>
