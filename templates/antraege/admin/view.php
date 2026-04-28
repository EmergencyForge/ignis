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

<body data-bs-theme="dark" data-page="antrag-admin-view">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>

    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <h1>
                <i class="<?= htmlspecialchars($antrag->typ->icon ?? 'fa-solid fa-file') ?> mr-2"></i>
                <?= htmlspecialchars($antrag->typ->name) ?> bearbeiten #<?= htmlspecialchars($caseId) ?>
            </h1>

            <?php Flash::render(); ?>

            <form method="post">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <!-- Haupt-Spalte (lg: 2/3 Breite) -->
                    <div class="lg:col-span-2">
                        <!-- Antragsteller -->
                        <div class="intra__tile mb-4 p-3">
                            <h5 class="mb-4"><i class="fa-solid fa-user mr-2"></i>Antragsteller</h5>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <div class="field-label">Name und Dienstnummer</div>
                                    <div class="field-value"><?= htmlspecialchars($antrag->name_dn) ?></div>
                                </div>
                                <div>
                                    <div class="field-label">Dienstgrad</div>
                                    <div class="field-value"><?= htmlspecialchars($antrag->dienstgrad ?? '') ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Antragsinhalt -->
                        <div class="intra__tile mb-4 p-3">
                            <h5 class="mb-4"><i class="fa-solid fa-file-lines mr-2"></i>Antragsinhalt</h5>

                            <?php if (!empty($felderMitWerten)): ?>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <?php foreach ($felderMitWerten as $feld):
                                        $isFullWidth = $feld->feldtyp === 'textarea' || $feld->breite !== 'half';
                                        $spanClass   = $isFullWidth ? 'md:col-span-2' : '';
                                    ?>
                                        <div class="<?= $spanClass ?>">
                                            <div class="field-label"><?= htmlspecialchars($feld->label) ?></div>
                                            <div class="field-value">
                                                <?php if ($feld->feldtyp === 'checkbox'): ?>
                                                    <?= $feld->wert ? '<i class="fa-solid fa-square-check text-[#6abf76]"></i> Ja' : '<i class="fa-regular fa-square text-gray-400"></i> Nein' ?>
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
                            <h5 class="mb-4"><i class="fa-solid fa-clipboard-check mr-2"></i>Bearbeitung</h5>

                            <div class="mb-4">
                                <label class="ignis-field__label text-xs text-gray-400">Aktueller Bearbeiter</label>
                                <div class="form-control-plaintext">
                                    <?php if (!empty($antrag->cirs_manager)): ?>
                                        <span class="font-bold"><?= htmlspecialchars($antrag->cirs_manager) ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400"><i>Noch nicht zugewiesen</i></span>
                                    <?php endif; ?>
                                </div>
                                <small class="mt-1 block text-xs text-gray-400">Wird auf "<?= htmlspecialchars($currentUserFullname) ?>" gesetzt beim Speichern</small>
                            </div>

                            <div class="mb-4">
                                <label for="cirs_status" class="ignis-field__label font-bold">Status setzen <span class="text-red-500">*</span></label>
                                <select class="form-select" id="cirs_status" name="cirs_status" required>
                                    <?php foreach (Antrag::STATUS_LABELS as $value => $label): ?>
                                        <option value="<?= (int) $value ?>" <?= $antrag->cirs_status === $value ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label for="cirs_text" class="ignis-field__label font-bold">Bemerkung durch Bearbeiter</label>
                                <textarea class="ignis-input" id="cirs_text" name="cirs_text" rows="5"
                                    placeholder="Fügen Sie hier Ihre Bemerkungen zum Antrag hinzu..."><?= htmlspecialchars($antrag->cirs_text ?? '') ?></textarea>
                                <small class="mt-1 block text-xs text-gray-400">Diese Bemerkung wird dem Antragsteller angezeigt</small>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar (lg: 1/3 Breite) -->
                    <div class="lg:col-span-1">
                        <!-- Antragsdetails -->
                        <div class="intra__tile mb-4 p-3">
                            <h6 class="mb-4"><i class="fa-solid fa-circle-info mr-2"></i>Antragsdetails</h6>
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
                                <div class="flex justify-between border-b border-white/10 py-2">
                                    <span class="text-gray-400">Discord-ID:</span>
                                    <span class="font-mono text-xs"><?= htmlspecialchars($antrag->discordid ?? 'N/A') ?></span>
                                </div>
                                <?php if ($antrag->cirs_time): ?>
                                    <div class="flex justify-between border-b border-white/10 py-2">
                                        <span class="text-gray-400">Letzte Bearbeitung:</span>
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
                                <button type="submit" name="save" class="ignis-ignis-btn ignis-btn--success">
                                    <i class="fa-solid fa-floppy-disk mr-2"></i>Änderungen speichern
                                </button>
                                <a href="<?= BASE_PATH ?>antrag/admin/list" class="ignis-ignis-btn ignis-btn--ghost no-underline hover:no-underline">
                                    <i class="fa-solid fa-arrow-left mr-2"></i>Zurück zur Übersicht
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>
