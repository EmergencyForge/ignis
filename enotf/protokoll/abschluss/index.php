<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

// Für CitizenFX: Nur Header entfernen, KEINE neuen setzen!
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    // Entferne CSP Header - .htaccess kümmert sich um den Rest
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');
    // KEIN neuer CSP wird gesetzt!
}
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';
require_once __DIR__ . '/../../../assets/functions/enotf/user_auth_middleware.php';
require_once __DIR__ . '/../../../assets/functions/enotf/pin_middleware.php';

use App\Auth\Permissions;

$daten = array();

if (isset($_GET['enr'])) {
    $queryget = "SELECT * FROM intra_edivi WHERE enr = :enr";
    $stmt = $pdo->prepare($queryget);
    $stmt->execute(['enr' => $_GET['enr']]);

    $daten = $stmt->fetch(PDO::FETCH_ASSOC);

    if (count($daten) == 0) {
        header("Location: " . BASE_PATH . "enotf/");
        exit();
    }
} else {
    header("Location: " . BASE_PATH . "enotf/");
    exit();
}

if ($daten['freigegeben'] == 1) {
    $ist_freigegeben = true;
} else {
    $ist_freigegeben = false;
}

$daten['last_edit'] = !empty($daten['last_edit']) ? (new DateTime($daten['last_edit']))->format('d.m.Y H:i') : NULL;

$enr = $daten['enr'];

$prot_url = "https://" . SYSTEM_URL . "/enotf/prot/index.php?enr=" . $enr;

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

$ebesonderheiten = [];
if (!empty($daten['ebesonderheiten'])) {
    $decoded = json_decode($daten['ebesonderheiten'], true);
    if (is_array($decoded)) {
        $ebesonderheiten = array_map('intval', $decoded);
    }
}

$ebesonderheitenLabels = [
    1 => 'keine',
    2 => 'nächste geeignete Klinik nicht aufnahmebereit',
    3 => 'Patient lehnt indizierte Maßnahmen ab',
    4 => 'bewusster Therapieverzicht',
    5 => 'Patient lehnt Transport ab',
    6 => 'Vorsorgliche Bereitstellung',
    7 => 'Zwangsunterbringung',
    8 => 'aufwändige technische Rettung',
    9 => 'Einsatz mit LNA/OrgL',
    10 => 'mehrere Patienten',
    11 => 'MANV',
    12 => 'kein Notarzt in angemessener Zeit verfügbar',
    13 => 'erschwerter Patientenzugang',
    14 => 'verzögerte Patientenübergabe',
    99 => 'Sonstige'
];
$uebergabeortLabels = [
    1 => 'Schockraum',
    2 => 'Praxis',
    3 => 'ZNA / INA',
    4 => 'Stroke Unit',
    5 => 'Intensivstation',
    6 => 'OP direkt',
    7 => 'Hausarzt',
    8 => 'Fachambulanz',
    9 => 'Chest Pain Unit',
    10 => 'Herzkatheterlabor',
    11 => 'Allgemeinstation',
    12 => 'Einsatzstelle',
    99 => 'Sonstige'
];
$uebergabeanLabels = [
    1 => 'Arzt',
    2 => 'Pflegepersonal',
    3 => 'Rettungssanitäter',
    4 => 'Rettungsassistent',
    5 => 'Notfallsanitäter',
    6 => 'Polizei',
    7 => 'Angehörige',
    99 => 'Sonstige'
];

$ebesonderheitenDisplayTexts = [];
if (!empty($ebesonderheiten) && is_array($ebesonderheiten)) {
    foreach ($ebesonderheiten as $value) {
        if (isset($ebesonderheitenLabels[$value])) {
            $ebesonderheitenDisplayTexts[] = $ebesonderheitenLabels[$value];
        }
    }
}

$ebesonderheitenDisplay = !empty($ebesonderheitenDisplayTexts) ? implode(', ', $ebesonderheitenDisplayTexts) : '';

// Felder basierend auf Protokollart leeren
// Bei Rettungsdienst-Protokoll (prot_by = 0): Notarzt-Felder leeren
if ($daten['prot_by'] == 0) {
    $daten['fzg_na_perso'] = NULL;
    $daten['fzg_na_perso_2'] = NULL;
}
// Bei Notarzt-Protokoll (prot_by = 1): Transportmittel-Felder leeren
elseif ($daten['prot_by'] == 1) {
    $daten['fzg_transp_perso'] = NULL;
    $daten['fzg_transp_perso_2'] = NULL;
}

// Automatisches Ausfüllen der Personalfelder basierend auf Session-Daten und Protokollart
// Nur wenn die Felder leer sind und die entsprechende Session-Variable gesetzt ist
if (isset($_SESSION['fahrername'])) {
    // Fahrer-Name mit Qualifikation
    $fahrerName = $_SESSION['fahrername'];
    if (isset($_SESSION['fahrerquali']) && !empty($_SESSION['fahrerquali'])) {
        $fahrerName .= ' (' . $_SESSION['fahrerquali'] . ')';
    }

    // Beifahrer-Name mit Qualifikation
    $beifahrerName = '';
    if (isset($_SESSION['beifahrername']) && !empty($_SESSION['beifahrername'])) {
        $beifahrerName = $_SESSION['beifahrername'];
        if (isset($_SESSION['beifahrerquali']) && !empty($_SESSION['beifahrerquali'])) {
            $beifahrerName .= ' (' . $_SESSION['beifahrerquali'] . ')';
        }
    }

    // Bei Rettungsdienst-Protokoll (prot_by = 0): Transportmittel-Personal
    // fzg_transp_perso = Fahrer, fzg_transp_perso_2 = Beifahrer
    if ($daten['prot_by'] == 0) {
        if (empty($daten['fzg_transp_perso'])) {
            $daten['fzg_transp_perso'] = $fahrerName;
        }
        if (empty($daten['fzg_transp_perso_2']) && !empty($beifahrerName)) {
            $daten['fzg_transp_perso_2'] = $beifahrerName;
        }
    }
    // Bei Notarzt-Protokoll (prot_by = 1): Notarzt-Personal
    // fzg_na_perso = Fahrer, fzg_na_perso_2 = Beifahrer
    elseif ($daten['prot_by'] == 1) {
        if (empty($daten['fzg_na_perso'])) {
            $daten['fzg_na_perso'] = $fahrerName;
        }
        if (empty($daten['fzg_na_perso_2']) && !empty($beifahrerName)) {
            $daten['fzg_na_perso_2'] = $beifahrerName;
        }
    }
}

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="abschluss" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include __DIR__ . '/../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <?php include __DIR__ . '/../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <?php if (!$ist_freigegeben) : ?>
                            <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                                <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/1.php?enr=<?= $daten['enr'] ?>" data-requires="ebesonderheiten">
                                    <span>Einsatzverlauf Besonderheiten</span>
                                </a>
                                <?php if ($daten['prot_by'] != 1) : ?>
                                    <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/2.php?enr=<?= $daten['enr'] ?>" data-requires="na_nachf">
                                        <span>Nachforderung NA</span>
                                    </a>
                                <?php endif; ?>
                                <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/3.php?enr=<?= $daten['enr'] ?>">
                                    <span>Übergabe</span>
                                </a>
                                <a href="#" onclick="sendPatientToDispatch(event)" id="btn-send-patient">
                                    <span>An Leitstelle senden</span>
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="col edivi__overview-container">
                            <div class="row">
                                <div class="col">
                                    <div class="row edivi__box">
                                        <h5 class="text-light px-2 py-1">Transportdaten</h5>
                                        <div class="col">
                                            <div class="row mt-2" id="fzg_transp_row">
                                                <div class="col-5">
                                                    <label for="fzg_transp" class="edivi__description">Fahrzeug Transport</label>
                                                    <?php if ($daten['fzg_transp'] === NULL) : ?>
                                                        <select name="fzg_transp" id="fzg_transp" class="w-100 form-select" data-custom-dropdown="true" data-search-threshold="5">
                                                            <option selected value="NULL">Fzg. Transp.</option>
                                                            <?php
                                                            require __DIR__ . '/../../../assets/config/database.php';
                                                            require_once __DIR__ . '/../../../assets/functions/enotf/pin_middleware.php';

                                                            $stmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge WHERE rd_type = 2 AND active = 1 ORDER BY priority ASC");
                                                            $stmt->execute();
                                                            $fahrzeuge = $stmt->fetchAll();
                                                            foreach ($fahrzeuge as $row) {
                                                                echo '<option value="' . $row['identifier'] . '">' . $row['name'] . '</option>';
                                                            }
                                                            ?>
                                                        </select>
                                                    <?php else : ?>
                                                        <select name="fzg_transp" id="fzg_transp" class="w-100 form-select" data-custom-dropdown="true" data-search-threshold="5">
                                                            <option selected value="NULL">Fzg. Transp.</option>
                                                            <?php
                                                            require __DIR__ . '/../../../assets/config/database.php';
                                                            require_once __DIR__ . '/../../../assets/functions/enotf/pin_middleware.php';

                                                            $stmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge WHERE rd_type = 2 ORDER BY priority ASC");
                                                            $stmt->execute();
                                                            $fahrzeuge = $stmt->fetchAll();

                                                            foreach ($fahrzeuge as $row) {
                                                                if ($row['identifier'] == $daten['fzg_transp'] && $row['active'] == 1) {
                                                                    echo '<option value="' . $row['identifier'] . '" selected>' . $row['name'] . '</option>';
                                                                } elseif ($row['identifier'] == $daten['fzg_transp'] && $row['active'] == 0) {
                                                                    echo '<option value="' . $row['identifier'] . '" selected disabled>' . $row['name'] . '</option>';
                                                                } else {
                                                                    echo '<option value="' . $row['identifier'] . '">' . $row['name'] . '</option>';
                                                                }
                                                            }
                                                            ?>
                                                        </select>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col">
                                                    <label for="fzg_transp_perso" class="edivi__description">Besatzung Transpormittel</label>
                                                    <input type="text" name="fzg_transp_perso" id="fzg_transp_perso" class="w-100 form-control" placeholder="Transportführer RTW/KTW" value="<?= $daten['fzg_transp_perso'] ?>">
                                                </div>
                                            </div>
                                            <div class="row mb-2" id="fzg_transp_row_2">
                                                <div class="col-5">
                                                </div>
                                                <div class="col">
                                                    <input type="text" name="fzg_transp_perso_2" id="fzg_transp_perso_2" class="w-100 form-control" placeholder="Fahrzeugführer RTW/KTW" value="<?= $daten['fzg_transp_perso_2'] ?>">
                                                </div>
                                            </div>
                                            <div class="row mt-2" id="fzg_na_row">
                                                <div class="col-5">
                                                    <label for="fzg_na" class="edivi__description">Fahrzeug Notarzt</label>
                                                    <?php if ($daten['fzg_na'] === NULL) : ?>
                                                        <select name="fzg_na" id="fzg_na" class="w-100 form-select" data-custom-dropdown="true" data-search-threshold="5">
                                                            <option selected value="NULL">Fzg. NA</option>
                                                            <?php
                                                            require __DIR__ . '/../../../assets/config/database.php';
                                                            require_once __DIR__ . '/../../../assets/functions/enotf/pin_middleware.php';

                                                            $stmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge WHERE rd_type = 1 AND active = 1 ORDER BY priority ASC");
                                                            $stmt->execute();
                                                            $fahrzeuge = $stmt->fetchAll();
                                                            foreach ($fahrzeuge as $row) {
                                                                echo '<option value="' . $row['identifier'] . '">' . $row['name'] . '</option>';
                                                            }
                                                            ?>
                                                        </select>
                                                    <?php else : ?>
                                                        <select name="fzg_na" id="fzg_na" class="w-100 form-select" data-custom-dropdown="true" data-search-threshold="5">
                                                            <option selected value="NULL">Fzg. NA</option>
                                                            <?php
                                                            require __DIR__ . '/../../../assets/config/database.php';
                                                            require_once __DIR__ . '/../../../assets/functions/enotf/pin_middleware.php';

                                                            $stmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge WHERE rd_type = 1 ORDER BY priority ASC");
                                                            $stmt->execute();
                                                            $fahrzeuge = $stmt->fetchAll();

                                                            foreach ($fahrzeuge as $row) {
                                                                if ($row['identifier'] == $daten['fzg_na'] && $row['active'] == 1) {
                                                                    echo '<option value="' . $row['identifier'] . '" selected>' . $row['name'] . '</option>';
                                                                } elseif ($row['identifier'] == $daten['fzg_na'] && $row['active'] == 0) {
                                                                    echo '<option value="' . $row['identifier'] . '" selected disabled>' . $row['name'] . '</option>';
                                                                } else {
                                                                    echo '<option value="' . $row['identifier'] . '">' . $row['name'] . '</option>';
                                                                }
                                                            }
                                                            ?>
                                                        </select>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col">
                                                    <label for="fzg_na_perso" class="edivi__description">Besatzung Notarztzubringer</label>
                                                    <input type="text" name="fzg_na_perso" id="fzg_na_perso" class="w-100 form-control" placeholder="Notarzt" value="<?= $daten['fzg_na_perso'] ?>">
                                                </div>
                                            </div>
                                            <div class="row mb-2" id="fzg_na_row_2">
                                                <div class="col-5">
                                                </div>
                                                <div class="col">
                                                    <input type="text" name="fzg_na_perso_2" id="fzg_na_perso_2" class="w-100 form-control" placeholder="Fahrzeugführer NEF/HEMS-TC" value="<?= $daten['fzg_na_perso_2'] ?>">
                                                </div>
                                            </div>
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="fzg_sonst" class="edivi__description">Sonstige Fahrzeuge</label>
                                                    <input type="text" name="fzg_sonst" id="fzg_sonst" class="w-100 form-control" placeholder="Weitere Rettungsmittel" value="<?= $daten['fzg_sonst'] ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="row edivi__box">
                                        <h5 class="text-light px-2 py-1 edivi__group-check">Protokolldaten</h5>
                                        <div class="col">
                                            <?php
                                            $stmtfn = $pdo->query("SELECT fullname FROM intra_mitarbeiter ORDER BY fullname ASC");
                                            $fullnames = $stmtfn->fetchAll(PDO::FETCH_COLUMN);
                                            $currentValue = $daten['pfname'] ?? '';
                                            ?>
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="pfname" class="edivi__description">Protokollant</label>
                                                    <div class="name-autocomplete-wrapper" style="position: relative;">
                                                        <input type="text" class="w-100 form-control edivi__input-check" name="pfname" id="pfname" value="<?= htmlspecialchars($currentValue) ?>" required autocomplete="off" />
                                                        <div class="name-dropdown" id="pfname-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; background-color: #444; border: 1px solid #555; border-radius: 4px; max-height: 200px; overflow-y: auto; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="prot_by" class="edivi__description">Protokoll durch</label>
                                                    <select name="prot_by" id="prot_by" class="w-100 form-select edivi__input-check" readonly required autocomplete="off">
                                                        <option disabled hidden <?php echo (empty($daten['prot_by']) && $daten['prot_by'] !== 0 ? 'selected' : '') ?>>---</option>
                                                        <option value="0" <?php echo ($daten['prot_by'] == 0 ? 'selected' : '') ?>>Transportmittel</option>
                                                        <option value="1" <?php echo ($daten['prot_by'] == 1 ? 'selected' : '') ?>>Notarzt</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/abschluss/3.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                        <h5 class="text-light px-2 py-1">Übergabe</h5>
                                        <div class="col">
                                            <?php
                                            $stmtfn = $pdo->query("SELECT fullname FROM intra_mitarbeiter ORDER BY fullname ASC");
                                            $fullnames = $stmtfn->fetchAll(PDO::FETCH_COLUMN);
                                            ?>
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="uebergabeort" class="edivi__description">Übergabe-Ort</label>
                                                    <input type="text" name="uebergabeort" id="uebergabeort" class="w-100 form-control" value="<?= $uebergabeortLabels[$daten['uebergabe_ort'] ?? ''] ?? '' ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="uebergabean" class="edivi__description">Übergabe an</label>
                                                    <input type="text" name="uebergabean" id="uebergabean" class="w-100 form-control" value="<?= $uebergabeanLabels[$daten['uebergabe_an'] ?? ''] ?? '' ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/abschluss/1.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                        <h5 class="text-light px-2 py-1 edivi__group-check">Einsatzverlauf</h5>
                                        <div class="col">
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="einsatzverlauf_besonderheiten" class="edivi__description">Besonderheiten</label>
                                                    <input type="text" name="einsatzverlauf_besonderheiten" id="einsatzverlauf_besonderheiten" class="w-100 form-control edivi__input-check" value="<?= !empty($ebesonderheitenDisplay) ? htmlspecialchars($ebesonderheitenDisplay) : '' ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (!$ist_freigegeben) : ?>
                        <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/freigabe.php?enr=<?= $daten['enr'] ?>" id="abschluss__btn">Abschließen</a>
                    <?php endif; ?>
                </div>
            </div>
    </form>
    <?php
    include __DIR__ . '/../../../assets/functions/enotf/notify.php';
    include __DIR__ . '/../../../assets/functions/enotf/field_checks.php';
    include __DIR__ . '/../../../assets/functions/enotf/clock.php';
    ?>
    <?php if ($ist_freigegeben) : ?>
        <script>
            var formElements = document.querySelectorAll('input, textarea');
            var selectElements2 = document.querySelectorAll('select');
            var inputElements2 = document.querySelectorAll('.btn-check');
            var inputElements3 = document.querySelectorAll('.form-check-input');

            formElements.forEach(function(element) {
                element.setAttribute('readonly', 'readonly');
            });

            selectElements2.forEach(function(element) {
                element.setAttribute('disabled', 'disabled');
            });

            inputElements2.forEach(function(element) {
                element.setAttribute('disabled', 'disabled');
            });

            inputElements3.forEach(function(element) {
                element.setAttribute('disabled', 'disabled');
            });
        </script>
    <?php endif; ?>
    <script>
        var modalCloseButton = document.querySelector('#myModal4 .btn-close');
        var freigeberInput = document.getElementById('freigeber');

        if (modalCloseButton && freigeberInput) {
            modalCloseButton.addEventListener('click', function() {
                freigeberInput.value = '';
            });
        }

        // Felder basierend auf Protokollart aktivieren/deaktivieren
        document.addEventListener('DOMContentLoaded', function() {
            const protBy = <?= json_encode($daten['prot_by']) ?>;
            const istFreigegeben = <?= json_encode($ist_freigegeben) ?>;

            console.log('Protokollart (prot_by):', protBy);
            console.log('Ist freigegeben:', istFreigegeben);

            const fzgTranspRow = document.getElementById('fzg_transp_row');
            const fzgTranspRow2 = document.getElementById('fzg_transp_row_2');
            const fzgNaRow = document.getElementById('fzg_na_row');
            const fzgNaRow2 = document.getElementById('fzg_na_row_2');

            console.log('Elemente gefunden:', {
                fzgTranspRow: !!fzgTranspRow,
                fzgTranspRow2: !!fzgTranspRow2,
                fzgNaRow: !!fzgNaRow,
                fzgNaRow2: !!fzgNaRow2
            });

            function updateFieldStates() {
                // Bei Rettungsdienst-Protokoll (prot_by = 0)
                if (protBy == 0) {
                    console.log('Rettungsdienst-Protokoll: Notarzt-Felder verstecken');
                    // Transportmittel-Zeilen anzeigen
                    if (fzgTranspRow) fzgTranspRow.style.display = '';
                    if (fzgTranspRow2) fzgTranspRow2.style.display = '';

                    // Notarzt-Zeilen verstecken
                    if (fzgNaRow) fzgNaRow.style.display = 'none';
                    if (fzgNaRow2) fzgNaRow2.style.display = 'none';
                }
                // Bei Notarzt-Protokoll (prot_by = 1)
                else if (protBy == 1) {
                    console.log('Notarzt-Protokoll: Transportmittel-Felder verstecken');
                    // Notarzt-Zeilen anzeigen
                    if (fzgNaRow) fzgNaRow.style.display = '';
                    if (fzgNaRow2) fzgNaRow2.style.display = '';

                    // Transportmittel-Zeilen verstecken
                    if (fzgTranspRow) fzgTranspRow.style.display = 'none';
                    if (fzgTranspRow2) fzgTranspRow2.style.display = 'none';
                }
            }

            // Immer ausführen, nicht nur wenn nicht freigegeben
            updateFieldStates();
        });

        // Setup custom name autocomplete for Protokollant
        const pfnameSuggestions = <?= json_encode($fullnames, JSON_UNESCAPED_UNICODE) ?>;

        function setupNameAutocomplete(inputId, dropdownId, suggestions) {
            const input = document.getElementById(inputId);
            const dropdown = document.getElementById(dropdownId);

            if (!input || !dropdown) return;

            // Populate dropdown
            function populateDropdown(filterValue = '') {
                dropdown.innerHTML = '';
                const filteredNames = suggestions.filter(name =>
                    name.toLowerCase().includes(filterValue.toLowerCase())
                );

                filteredNames.forEach(name => {
                    const item = document.createElement('div');
                    item.className = 'name-item';
                    item.style.cssText = 'padding: 8px 12px; cursor: pointer; color: white; border-bottom: 1px solid #555;';
                    item.textContent = name;
                    item.addEventListener('mouseenter', function() {
                        this.style.backgroundColor = '#555';
                    });
                    item.addEventListener('mouseleave', function() {
                        this.style.backgroundColor = '';
                    });
                    item.addEventListener('click', function() {
                        input.value = name;
                        dropdown.style.display = 'none';
                        // Trigger change event for auto-save
                        const changeEvent = new Event('change', {
                            bubbles: true
                        });
                        input.dispatchEvent(changeEvent);
                    });
                    dropdown.appendChild(item);
                });

                return filteredNames.length > 0;
            }

            // Show dropdown on focus
            input.addEventListener('focus', function() {
                if (populateDropdown(this.value)) {
                    dropdown.style.display = 'block';
                }
            });

            // Filter dropdown on input
            input.addEventListener('input', function() {
                if (populateDropdown(this.value)) {
                    dropdown.style.display = 'block';
                } else {
                    dropdown.style.display = 'none';
                }
            });

            // Hide dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.name-autocomplete-wrapper') ||
                    (e.target.closest('.name-autocomplete-wrapper') &&
                        e.target.closest('.name-autocomplete-wrapper').querySelector('input') !== input)) {
                    dropdown.style.display = 'none';
                }
            });
        }

        setupNameAutocomplete('pfname', 'pfname-dropdown', pfnameSuggestions);

        // Patient an Leitstelle senden
        function sendPatientToDispatch(e) {
            e.preventDefault();
            const syncIcon = document.getElementById('pat-sync-icon');
            const syncIconEl = syncIcon ? syncIcon.querySelector('i') : null;

            // Sofort auf Gelb setzen (Senden läuft)
            if (syncIconEl) syncIconEl.style.color = '#f0ad4e';

            fetch('<?= BASE_PATH ?>api/enotf-patient-sync.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enr: '<?= $enr ?>' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (syncIconEl) syncIconEl.style.color = '#f0ad4e';
                } else {
                    if (syncIconEl) syncIconEl.style.color = '#dc3545';
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            })
            .catch(() => {
                if (syncIconEl) syncIconEl.style.color = '#dc3545';
                alert('Verbindungsfehler beim Senden.');
            });
        }
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>