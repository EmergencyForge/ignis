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

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col">
                    <h1><?= htmlspecialchars($typ->name) ?> stellen</h1>

                    <?php if (!empty($typ->beschreibung)): ?>
                        <p class="text-muted"><?= htmlspecialchars($typ->beschreibung) ?></p>
                    <?php endif; ?>

                    <?php Flash::render(); ?>

                    <div class="row">
                        <div class="col mx-auto">
                            <div class="intra__tile p-4">
                                <form method="post" action="">
                                    <?php
                                    $current_row = [];
                                    foreach ($felder as $index => $feld):
                                        $breite_class    = $feld->breite === 'half' ? 'col-md-6' : 'col-12';
                                        $auto_fill_value = $feld->auto_fill ? $autoFill($feld->auto_fill, $mitarbeiter) : '';

                                        if (empty($current_row)) {
                                            echo '<div class="row">';
                                        }
                                        $current_row[] = $feld->breite;
                                    ?>
                                        <div class="<?= $breite_class ?> mb-3">
                                            <label for="<?= htmlspecialchars($feld->feldname) ?>" class="form-label fw-bold">
                                                <?= htmlspecialchars($feld->label) ?>
                                                <?php if ($feld->pflichtfeld): ?>
                                                    <span class="text-danger">*</span>
                                                <?php endif; ?>
                                            </label>

                                            <?php if ($feld->feldtyp === 'textarea'): ?>
                                                <textarea
                                                    class="form-control"
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
                                                    class="form-control"
                                                    id="<?= htmlspecialchars($feld->feldname) ?>"
                                                    name="<?= htmlspecialchars($feld->feldname) ?>"
                                                    placeholder="<?= htmlspecialchars($feld->platzhalter ?? '') ?>"
                                                    value="<?= htmlspecialchars($auto_fill_value) ?>"
                                                    <?= $feld->pflichtfeld ? 'required' : '' ?>
                                                    <?= $feld->readonly ? 'readonly' : '' ?>>
                                            <?php endif; ?>

                                            <?php if (!empty($feld->hinweistext)): ?>
                                                <small class="text-muted d-block mt-1"><?= htmlspecialchars($feld->hinweistext) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php
                                        $is_last      = $index === count($felder) - 1;
                                        $row_complete = ($feld->breite === 'full') || (count($current_row) >= 2) || $is_last;

                                        if ($row_complete) {
                                            echo '</div>';
                                            $current_row = [];
                                        }
                                    endforeach;
                                    ?>

                                    <hr class="text-light my-4">

                                    <div class="d-flex justify-content-between">
                                        <a href="<?= BASE_PATH ?>index.php" class="btn btn-ghost">
                                            <i class="fa-solid fa-xmark me-2"></i>Abbrechen
                                        </a>
                                        <button type="submit" name="submit_antrag" class="btn btn-success">
                                            <i class="fa-solid fa-paper-plane me-2"></i>Antrag absenden
                                        </button>
                                    </div>
                                </form>
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
