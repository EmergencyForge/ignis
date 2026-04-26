<?php
/**
 * View: Antrag stellen (Form)
 *
 * @var \App\Models\AntragTyp                                                  $typ
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\AntragField> $felder
 * @var \stdClass                                                              $mitarbeiter
 * @var \PDO                                                                   $pdo
 */

use App\Helpers\Flash;

$SITE_TITLE = htmlspecialchars($typ->name) . ' stellen';

/**
 * Mappt einen AntragField-Auto-Fill-Key auf den entsprechenden Mitarbeiter-Wert.
 */
$autoFill = function (string $key, \stdClass $mitarbeiter): string {
    return match ($key) {
        'fullname_dienstnr' => $mitarbeiter->fullname . ' (' . $mitarbeiter->dienstnr . ')',
        'fullname'          => (string) $mitarbeiter->fullname,
        'dienstnr'          => (string) $mitarbeiter->dienstnr,
        'dienstgrad'        => (string) ($mitarbeiter->dienstgrad_name ?? ''),
        'discordtag'        => (string) $mitarbeiter->discordtag,
        default             => '',
    };
};
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . "/../../assets/components/_base/admin/head.php"; ?>
</head>

<body data-bs-theme="dark" data-page="antrag-create">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>

    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <h1><?= htmlspecialchars($typ->name) ?> stellen</h1>

            <?php if (!empty($typ->beschreibung)): ?>
                <p class="text-gray-400"><?= htmlspecialchars($typ->beschreibung) ?></p>
            <?php endif; ?>

            <?php Flash::render(); ?>

            <div class="intra__tile p-6">
                <form method="post" action="">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <?php foreach ($felder as $feld):
                            // Textareas sind immer full-width, unabhängig von breite in der DB —
                            // halbe Textareas wären zu schmal zum Sinnvoll-Schreiben.
                            $isFullWidth     = $feld->feldtyp === 'textarea' || $feld->breite !== 'half';
                            $spanClass       = $isFullWidth ? 'md:col-span-2' : '';
                            $auto_fill_value = $feld->auto_fill ? $autoFill($feld->auto_fill, $mitarbeiter) : '';
                        ?>
                            <div class="<?= $spanClass ?>">
                                <label for="<?= htmlspecialchars($feld->feldname) ?>" class="ignis-field__label font-bold">
                                    <?= htmlspecialchars($feld->label) ?>
                                    <?php if ($feld->pflichtfeld): ?>
                                        <span class="text-red-500">*</span>
                                    <?php endif; ?>
                                </label>

                                <?php if ($feld->feldtyp === 'textarea'): ?>
                                    <textarea
                                        class="ignis-input"
                                        id="<?= htmlspecialchars($feld->feldname) ?>"
                                        name="<?= htmlspecialchars($feld->feldname) ?>"
                                        rows="5"
                                        placeholder="<?= htmlspecialchars($feld->platzhalter ?? '') ?>"
                                        <?= $feld->pflichtfeld ? 'required' : '' ?>
                                        <?= $feld->readonly ? 'readonly' : '' ?>><?= htmlspecialchars($auto_fill_value) ?></textarea>

                                <?php elseif ($feld->feldtyp === 'select'): ?>
                                    <select
                                        class="form-select"
                                        id="<?= htmlspecialchars($feld->feldname) ?>"
                                        name="<?= htmlspecialchars($feld->feldname) ?>"
                                        <?= $feld->pflichtfeld ? 'required' : '' ?>
                                        <?= $feld->readonly ? 'disabled' : '' ?>>
                                        <option value="">Bitte wählen...</option>
                                        <?php foreach ($feld->selectOptions() as $option): ?>
                                            <option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                <?php elseif ($feld->feldtyp === 'checkbox'): ?>
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="<?= htmlspecialchars($feld->feldname) ?>"
                                            name="<?= htmlspecialchars($feld->feldname) ?>"
                                            value="1"
                                            <?= $feld->readonly ? 'disabled' : '' ?>>
                                        <label class="form-check-label" for="<?= htmlspecialchars($feld->feldname) ?>">
                                            <?= htmlspecialchars($feld->platzhalter ?? '') ?>
                                        </label>
                                    </div>

                                <?php else: ?>
                                    <input
                                        type="<?= htmlspecialchars($feld->feldtyp) ?>"
                                        class="ignis-input"
                                        id="<?= htmlspecialchars($feld->feldname) ?>"
                                        name="<?= htmlspecialchars($feld->feldname) ?>"
                                        placeholder="<?= htmlspecialchars($feld->platzhalter ?? '') ?>"
                                        value="<?= htmlspecialchars($auto_fill_value) ?>"
                                        <?= $feld->pflichtfeld ? 'required' : '' ?>
                                        <?= $feld->readonly ? 'readonly' : '' ?>>
                                <?php endif; ?>

                                <?php if (!empty($feld->hinweistext)): ?>
                                    <small class="mt-1 block text-xs text-gray-400"><?= htmlspecialchars($feld->hinweistext) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr class="my-6 border-white/20">

                    <div class="flex justify-between">
                        <a href="<?= BASE_PATH ?>index.php" class="ignis-btn ignis-btn--ghost">
                            <i class="fa-solid fa-xmark mr-2"></i>Abbrechen
                        </a>
                        <button type="submit" name="submit_antrag" class="ignis-btn ignis-btn--success">
                            <i class="fa-solid fa-paper-plane mr-2"></i>Antrag absenden
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>
