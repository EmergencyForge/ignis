<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Helpers\Flash;

$mitarbeiter = null;
if (isset($_SESSION['discordtag']) && !empty($_SESSION['discordtag'])) {
    $stmt = $pdo->prepare("
        SELECT 
            m.fullname, 
            m.dienstnr, 
            CASE 
                WHEN m.geschlecht = 1 THEN dg.name_m 
                ELSE dg.name_w 
            END as dienstgrad_name,
            m.geschlecht,
            m.discordtag
        FROM intra_mitarbeiter m 
        LEFT JOIN intra_mitarbeiter_dienstgrade dg ON m.dienstgrad = dg.id 
        WHERE m.discordtag = ? AND dg.archive = 0
    ");
    $stmt->execute([$_SESSION['discordtag']]);
    $mitarbeiter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mitarbeiter) {
        Flash::set('error', 'Kein Mitarbeiterprofil für Ihre Discord-ID gefunden.');
        header("Location: " . BASE_PATH . "index.php");
        exit();
    }
}

// Antragstyp-ID aus URL
$typ_id = $_GET['typ'] ?? null;

if (!$typ_id || !is_numeric($typ_id)) {
    Flash::set('error', 'Kein Antragstyp ausgewählt.');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

// Antragstyp laden
$stmt = $pdo->prepare("SELECT * FROM intra_antrag_typen WHERE id = ? AND aktiv = 1");
$stmt->execute([$typ_id]);
$typ = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$typ) {
    Flash::set('error', 'Antragstyp nicht gefunden oder nicht aktiv.');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

// Felder für diesen Typ laden
$stmt = $pdo->prepare("SELECT * FROM intra_antrag_felder WHERE antragstyp_id = ? ORDER BY sortierung ASC");
$stmt->execute([$typ_id]);
$felder = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Formular absenden
if (isset($_POST['submit_antrag'])) {
    try {
        $pdo->beginTransaction();

        // Unique ID generieren
        do {
            $random_number = mt_rand(100000, 999999);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM intra_antraege WHERE uniqueid = ?");
            $stmt->execute([$random_number]);
            $count = $stmt->fetchColumn();
        } while ($count > 0);

        // Hauptantrag speichern
        $name_dn = $mitarbeiter['fullname'] . ' (' . $mitarbeiter['dienstnr'] . ')';

        // Alle Anträge werden jetzt in der neuen Struktur gespeichert
        $stmt = $pdo->prepare("
            INSERT INTO intra_antraege 
            (uniqueid, antragstyp_id, name_dn, dienstgrad, discordid) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $random_number,
            $typ_id,
            $name_dn,
            $mitarbeiter['dienstgrad_name'],
            $_SESSION['discordtag']
        ]);

        $antrag_id = $pdo->lastInsertId();

        // Felddaten speichern
        foreach ($felder as $feld) {
            $wert = $_POST[$feld['feldname']] ?? '';

            $stmt = $pdo->prepare("
                INSERT INTO intra_antraege_daten (antrag_id, feldname, wert) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$antrag_id, $feld['feldname'], $wert]);
        }

        $pdo->commit();

        Flash::set('success', 'Antrag erfolgreich eingereicht!');
        header("Location: " . BASE_PATH . "antrag/view.php?antrag=" . $random_number);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        Flash::set('error', 'Fehler beim Speichern: ' . $e->getMessage());
    }
}

/**
 * Funktion zum Generieren von Auto-Fill Werten
 *
 * @param string $auto_fill
 * @param array<string, string> $mitarbeiter
 * @return string
 */
function getAutoFillValue(string $auto_fill, array $mitarbeiter): string
{
    switch ($auto_fill) {
        case 'fullname_dienstnr':
            return $mitarbeiter['fullname'] . ' (' . $mitarbeiter['dienstnr'] . ')';
        case 'fullname':
            return $mitarbeiter['fullname'];
        case 'dienstnr':
            return $mitarbeiter['dienstnr'];
        case 'dienstgrad':
            return $mitarbeiter['dienstgrad_name'];
        case 'discordtag':
            return $mitarbeiter['discordtag'];
        default:
            return '';
    }
}
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    $SITE_TITLE = htmlspecialchars($typ['name']) . ' stellen';
    include __DIR__ . "/../assets/components/_base/admin/head.php";
    ?>
</head>

<body data-bs-theme="dark" data-page="antrag-create">
    <?php include __DIR__ . "/../assets/components/navbar.php"; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col">
                    <h1>
                        <?= htmlspecialchars($typ['name']) ?> stellen
                    </h1>

                    <?php if (!empty($typ['beschreibung'])): ?>
                        <p class="text-muted"><?= htmlspecialchars($typ['beschreibung']) ?></p>
                    <?php endif; ?>

                    <?php Flash::render(); ?>


                    <div class="row">
                        <div class="col mx-auto">
                            <div class="intra__tile p-4">
                                <form method="post" action="">
                                    <?php
                                    $current_row = [];
                                    foreach ($felder as $index => $feld):
                                        $breite_class = $feld['breite'] === 'half' ? 'col-md-6' : 'col-12';
                                        $auto_fill_value = $feld['auto_fill'] ? getAutoFillValue($feld['auto_fill'], $mitarbeiter) : '';

                                        // Zeile beginnen
                                        if (empty($current_row)) {
                                            echo '<div class="row">';
                                        }

                                        $current_row[] = $feld['breite'];
                                    ?>
                                        <div class="<?= $breite_class ?> mb-3">
                                            <label for="<?= htmlspecialchars($feld['feldname']) ?>" class="form-label fw-bold">
                                                <?= htmlspecialchars($feld['label']) ?>
                                                <?php if ($feld['pflichtfeld']): ?>
                                                    <span class="text-danger">*</span>
                                                <?php endif; ?>
                                            </label>

                                            <?php if ($feld['feldtyp'] === 'textarea'): ?>
                                                <textarea
                                                    class="form-control"
                                                    id="<?= htmlspecialchars($feld['feldname']) ?>"
                                                    name="<?= htmlspecialchars($feld['feldname']) ?>"
                                                    rows="5"
                                                    placeholder="<?= htmlspecialchars($feld['platzhalter'] ?? '') ?>"
                                                    <?= $feld['pflichtfeld'] ? 'required' : '' ?>
                                                    <?= $feld['readonly'] ? 'readonly' : '' ?>><?= htmlspecialchars($auto_fill_value) ?></textarea>

                                            <?php elseif ($feld['feldtyp'] === 'select'): ?>
                                                <select
                                                    class="form-select"
                                                    id="<?= htmlspecialchars($feld['feldname']) ?>"
                                                    name="<?= htmlspecialchars($feld['feldname']) ?>"
                                                    <?= $feld['pflichtfeld'] ? 'required' : '' ?>
                                                    <?= $feld['readonly'] ? 'disabled' : '' ?>>
                                                    <option value="">Bitte wählen...</option>
                                                    <?php
                                                    $optionen = explode("\n", $feld['optionen']);
                                                    foreach ($optionen as $option):
                                                        $option = trim($option);
                                                        if (!empty($option)):
                                                    ?>
                                                            <option value="<?= htmlspecialchars($option) ?>">
                                                                <?= htmlspecialchars($option) ?>
                                                            </option>
                                                    <?php
                                                        endif;
                                                    endforeach;
                                                    ?>
                                                </select>

                                            <?php elseif ($feld['feldtyp'] === 'checkbox'): ?>
                                                <div class="form-check">
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        id="<?= htmlspecialchars($feld['feldname']) ?>"
                                                        name="<?= htmlspecialchars($feld['feldname']) ?>"
                                                        value="1"
                                                        <?= $feld['readonly'] ? 'disabled' : '' ?>>
                                                    <label class="form-check-label" for="<?= htmlspecialchars($feld['feldname']) ?>">
                                                        <?= htmlspecialchars($feld['platzhalter'] ?? '') ?>
                                                    </label>
                                                </div>

                                            <?php else: ?>
                                                <input
                                                    type="<?= htmlspecialchars($feld['feldtyp']) ?>"
                                                    class="form-control"
                                                    id="<?= htmlspecialchars($feld['feldname']) ?>"
                                                    name="<?= htmlspecialchars($feld['feldname']) ?>"
                                                    placeholder="<?= htmlspecialchars($feld['platzhalter'] ?? '') ?>"
                                                    value="<?= htmlspecialchars($auto_fill_value) ?>"
                                                    <?= $feld['pflichtfeld'] ? 'required' : '' ?>
                                                    <?= $feld['readonly'] ? 'readonly' : '' ?>>
                                            <?php endif; ?>

                                            <?php if (!empty($feld['hinweistext'])): ?>
                                                <small class="text-muted d-block mt-1">
                                                    <?= htmlspecialchars($feld['hinweistext']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php
                                        $is_last = $index === count($felder) - 1;
                                        $row_complete = ($feld['breite'] === 'full') || (count($current_row) >= 2) || $is_last;

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

    <?php include __DIR__ . "/../assets/components/footer.php"; ?>
</body>

</html>