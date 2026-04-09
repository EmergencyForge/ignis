<?php
/**
 * View: Antrag-Detailansicht (read-only)
 *
 * @var \App\Models\Antrag                  $antrag
 * @var array<int,\stdClass>                $felderMitWerten
 * @var array{class:string,text:string,icon:string} $currentStatus
 * @var \PDO                                $pdo
 */

use App\Auth\Gate;
use App\Helpers\Flash;

$caseId    = $antrag->uniqueid;
$createDate = $antrag->time_added;
$SITE_TITLE = "Antrag [#" . htmlspecialchars($caseId) . "] anzeigen";
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . "/../../assets/components/_base/admin/head.php"; ?>
    <style>
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

        .form-control-plaintext {
            border-bottom: 1px solid var(--bs-border-color);
            padding-bottom: 0.375rem;
            min-height: calc(1.5em + 0.75rem + 2px);
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="antrag-view">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1>
                            <?= htmlspecialchars($antrag->typ->name) ?> #<?= htmlspecialchars($caseId) ?>
                        </h1>
                    </div>
                    <?php Flash::render(); ?>

                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Antragsteller -->
                            <div class="intra__tile p-2 mb-4">
                                <h5 class="mb-3">Antragsteller</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-muted small">Name und Dienstnummer</label>
                                        <div class="form-control-plaintext fw-bold">
                                            <?= htmlspecialchars($antrag->name_dn) ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-muted small">Aktueller Dienstgrad</label>
                                        <div class="form-control-plaintext fw-bold">
                                            <?= htmlspecialchars($antrag->dienstgrad ?? '') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Antragsinhalt -->
                            <div class="intra__tile p-2 mb-4">
                                <h5 class="mb-3">Antragsinhalt</h5>

                                <?php if (!empty($felderMitWerten)): ?>
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
                                <?php else: ?>
                                    <div class="bg-dark rounded p-3">
                                        <p class="text-muted mb-0"><i>Keine Felddaten vorhanden</i></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Bearbeitung -->
                            <div class="intra__tile p-2 mb-5">
                                <h5 class="mb-3">Bearbeitung</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-muted small">Bearbeiter</label>
                                        <div class="form-control-plaintext">
                                            <?php if (!empty($antrag->cirs_manager)): ?>
                                                <span class="fw-bold"><?= htmlspecialchars($antrag->cirs_manager) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted"><i>Noch nicht zugewiesen</i></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-muted small">Status</label>
                                        <div class="form-control-plaintext">
                                            <span class="badge text-bg-<?= $currentStatus['class'] ?>">
                                                <i class="<?= $currentStatus['icon'] ?> me-1"></i>
                                                <?= $currentStatus['text'] ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($antrag->cirs_text)): ?>
                                    <div class="mt-3">
                                        <label class="form-label text-muted small">Bemerkung</label>
                                        <div class="bg-dark rounded p-3">
                                            <p class="mb-0" style="white-space: pre-line;"><?= htmlspecialchars($antrag->cirs_text) ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Antragsdetails -->
                            <div class="intra__tile p-2 mb-4">
                                <h6 class="mb-3">Antragsdetails</h6>
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
                                    <?php if ($antrag->cirs_time): ?>
                                        <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                                            <span class="text-muted">Bearbeitet am:</span>
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
                            <div class="intra__tile p-2">
                                <h6 class="mb-3"><i class="fa-solid fa-screwdriver-wrench me-2"></i>Aktionen</h6>
                                <div class="d-grid gap-2">
                                    <a href="<?= BASE_PATH ?>index.php" class="btn btn-ghost">
                                        <i class="fas fa-arrow-left me-2"></i>Zurück zum Dashboard
                                    </a>
                                    <?php if (Gate::allows('antrag.decide', $antrag)): ?>
                                        <a href="<?= BASE_PATH ?>antrag/admin/view.php?antrag=<?= htmlspecialchars($caseId) ?>" class="btn btn-soft-primary">
                                            <i class="fas fa-edit me-2"></i>Bearbeiten
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>
