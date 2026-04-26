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

    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="flex items-center justify-between">
                <h1>
                    <?= htmlspecialchars($antrag->typ->name) ?> #<?= htmlspecialchars($caseId) ?>
                </h1>
            </div>
            <?php Flash::render(); ?>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <!-- Haupt-Spalte (lg: 2/3 Breite) -->
                <div class="lg:col-span-2">
                    <!-- Antragsteller -->
                    <div class="intra__tile mb-4 p-3">
                        <h5 class="mb-4">Antragsteller</h5>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="form-label text-xs text-gray-400">Name und Dienstnummer</label>
                                <div class="form-control-plaintext font-bold">
                                    <?= htmlspecialchars($antrag->name_dn) ?>
                                </div>
                            </div>
                            <div>
                                <label class="form-label text-xs text-gray-400">Aktueller Dienstgrad</label>
                                <div class="form-control-plaintext font-bold">
                                    <?= htmlspecialchars($antrag->dienstgrad ?? '') ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Antragsinhalt -->
                    <div class="intra__tile mb-4 p-3">
                        <h5 class="mb-4">Antragsinhalt</h5>

                        <?php if (!empty($felderMitWerten)): ?>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <?php foreach ($felderMitWerten as $feld):
                                    // Textareas immer full-width, sonst DB-`breite` respektieren.
                                    $isFullWidth = $feld->feldtyp === 'textarea' || $feld->breite !== 'half';
                                    $spanClass   = $isFullWidth ? 'md:col-span-2' : '';
                                ?>
                                    <div class="<?= $spanClass ?>">
                                        <div class="field-label"><?= htmlspecialchars($feld->label) ?></div>
                                        <div class="field-value">
                                            <?php if ($feld->feldtyp === 'checkbox'): ?>
                                                <?= $feld->wert ? '<i class="fa-solid fa-square-check text-success"></i> Ja' : '<i class="fa-regular fa-square text-gray-400"></i> Nein' ?>
                                            <?php elseif (empty($feld->wert)): ?>
                                                <span class="text-gray-400"><i>Keine Angabe</i></span>
                                            <?php else: ?>
                                                <?= nl2br(htmlspecialchars($feld->wert)) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="rounded bg-black/30 p-3">
                                <p class="mb-0 text-gray-400"><i>Keine Felddaten vorhanden</i></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Bearbeitung -->
                    <div class="intra__tile mb-6 p-3">
                        <h5 class="mb-4">Bearbeitung</h5>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="form-label text-xs text-gray-400">Bearbeiter</label>
                                <div class="form-control-plaintext">
                                    <?php if (!empty($antrag->cirs_manager)): ?>
                                        <span class="font-bold"><?= htmlspecialchars($antrag->cirs_manager) ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400"><i>Noch nicht zugewiesen</i></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <label class="form-label text-xs text-gray-400">Status</label>
                                <div class="form-control-plaintext">
                                    <span class="badge text-bg-<?= $currentStatus['class'] ?>">
                                        <i class="<?= $currentStatus['icon'] ?> mr-1"></i>
                                        <?= $currentStatus['text'] ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($antrag->cirs_text)): ?>
                            <div class="mt-4">
                                <label class="form-label text-xs text-gray-400">Bemerkung</label>
                                <div class="rounded bg-black/30 p-3">
                                    <p class="mb-0" style="white-space: pre-line;"><?= htmlspecialchars($antrag->cirs_text) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar (lg: 1/3 Breite) -->
                <div class="lg:col-span-1">
                    <!-- Antragsdetails -->
                    <div class="intra__tile mb-4 p-3">
                        <h6 class="mb-4">Antragsdetails</h6>
                        <div class="text-sm">
                            <div class="flex justify-between border-b border-white/10 py-2">
                                <span class="text-gray-400">Antragsnummer:</span>
                                <span class="font-bold">#<?= htmlspecialchars($caseId) ?></span>
                            </div>
                            <div class="flex justify-between border-b border-white/10 py-2">
                                <span class="text-gray-400">Typ:</span>
                                <span><?= htmlspecialchars($antrag->typ->name) ?></span>
                            </div>
                            <div class="flex justify-between border-b border-white/10 py-2">
                                <span class="text-gray-400">Erstellt am:</span>
                                <span><?= $createDate ? $createDate->format('d.m.Y H:i') : '' ?></span>
                            </div>
                            <?php if ($antrag->cirs_time): ?>
                                <div class="flex justify-between border-b border-white/10 py-2">
                                    <span class="text-gray-400">Bearbeitet am:</span>
                                    <span><?= $antrag->cirs_time->format('d.m.Y H:i') ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="flex justify-between py-2">
                                <span class="text-gray-400">Status:</span>
                                <span class="badge text-bg-<?= $currentStatus['class'] ?>">
                                    <i class="<?= $currentStatus['icon'] ?> mr-1"></i>
                                    <?= $currentStatus['text'] ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Aktionen -->
                    <div class="intra__tile p-3">
                        <h6 class="mb-4"><i class="fa-solid fa-screwdriver-wrench mr-2"></i>Aktionen</h6>
                        <div class="flex flex-col gap-2">
                            <a href="<?= BASE_PATH ?>index.php" class="ignis-btn ignis-btn--ghost no-underline hover:no-underline">
                                <i class="fas fa-arrow-left mr-2"></i>Zurück zum Dashboard
                            </a>
                            <?php if (Gate::allows('antrag.decide', $antrag)): ?>
                                <a href="<?= BASE_PATH ?>antrag/admin/view.php?antrag=<?= htmlspecialchars($caseId) ?>" class="ignis-btn ignis-btn--soft-primary no-underline hover:no-underline">
                                    <i class="fas fa-edit mr-2"></i>Bearbeiten
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>
