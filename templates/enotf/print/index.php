<?php
/**
 * View: eNOTF Print/Detail-Ansicht
 *
 * @var \PDO $pdo
 */

use App\Auth\Permissions;
use App\Helpers\EnotfUrl;
use App\Helpers\Redirects;
use App\Helpers\BloodSugarHelper;

$daten = array();

if (isset($_GET['enr'])) {
    $queryget = "SELECT * FROM intra_edivi WHERE enr = :enr";
    $stmt = $pdo->prepare($queryget);
    $stmt->execute(['enr' => $_GET['enr']]);

    $daten = $stmt->fetch(PDO::FETCH_ASSOC);

    // Zeiten abrufen
    $queryZeiten = "SELECT salarm, s1, s2, s3, s4, spat, s7, s8, sende FROM intra_edivi WHERE enr = :enr";
    $stmtZeiten = $pdo->prepare($queryZeiten);
    $stmtZeiten->execute(['enr' => $_GET['enr']]);
    $zeiten = $stmtZeiten->fetch(PDO::FETCH_ASSOC);

    if (!$daten) {
        header("Location: " . BASE_PATH . "enotf/");
        exit();
    }
} else {
    header("Location: " . BASE_PATH . "enotf/");
    exit();
}

$enr = $daten['enr'];

// Initialize BloodSugarHelper
$bzHelper = new BloodSugarHelper($pdo);
$bzUnit = $bzHelper->getCurrentUnit();

$prot_url = "https://" . SYSTEM_URL . rtrim(EnotfUrl::protokoll($enr), '/');
$defaultUrl = $prot_url;

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

// Einsatzort aus JSON dekodieren für Anzeige im Format: Straße HNR, Ort-Ortsteil
$einsatzort_display = '';
if (!empty($daten['transp_poi'])) {
    $einsatzort_display = $daten['transp_poi'];
}
if (!empty($daten['transp_adresse'])) {
    $parts = [];
    $decoded = json_decode($daten['transp_adresse'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        // Straße + HNR zusammen
        $strasseHnr = [];
        if (!empty($decoded['strasse'])) $strasseHnr[] = $decoded['strasse'];
        if (!empty($decoded['hnr'])) $strasseHnr[] = $decoded['hnr'];
        if (!empty($strasseHnr)) {
            $parts[] = implode(' ', $strasseHnr);
        }

        // Ort-Ortsteil
        $ortOrtsteil = [];
        if (!empty($decoded['ort'])) $ortOrtsteil[] = $decoded['ort'];
        if (!empty($decoded['ortsteil'])) $ortOrtsteil[] = $decoded['ortsteil'];
        if (!empty($ortOrtsteil)) {
            $parts[] = implode('-', $ortOrtsteil);
        }
    }
    $adresse_text = implode(', ', $parts);
    if (!empty($einsatzort_display) && !empty($adresse_text)) {
        $einsatzort_display .= ', ' . $adresse_text;
    } elseif (!empty($adresse_text)) {
        $einsatzort_display = $adresse_text;
    }
}

// Transportziel aus JSON dekodieren für Anzeige
$transportziel_display = '';
if (!empty($daten['ziel_poi'])) {
    $transportziel_display = $daten['ziel_poi'];
}
if (!empty($daten['ziel_adresse'])) {
    $parts = [];
    $decoded = json_decode($daten['ziel_adresse'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        // Straße + HNR zusammen
        $strasseHnr = [];
        if (!empty($decoded['strasse'])) $strasseHnr[] = $decoded['strasse'];
        if (!empty($decoded['hnr'])) $strasseHnr[] = $decoded['hnr'];
        if (!empty($strasseHnr)) {
            $parts[] = implode(' ', $strasseHnr);
        }

        // Ort-Ortsteil
        $ortOrtsteil = [];
        if (!empty($decoded['ort'])) $ortOrtsteil[] = $decoded['ort'];
        if (!empty($decoded['ortsteil'])) $ortOrtsteil[] = $decoded['ortsteil'];
        if (!empty($ortOrtsteil)) {
            $parts[] = implode('-', $ortOrtsteil);
        }
    }
    $adresse_text = implode(', ', $parts);
    if (!empty($transportziel_display) && !empty($adresse_text)) {
        $transportziel_display .= ', ' . $adresse_text;
    } elseif (!empty($adresse_text)) {
        $transportziel_display = $adresse_text;
    }
}

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>[#<?= $daten['enr'] ?>] &rsaquo; eNOTF &rsaquo; <?php echo SYSTEM_NAME ?></title>
    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/print.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/rubik/css/all.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/freehand/css/all.min.css" />
    <!-- Bootstrap -->
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>assets/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="<?= BASE_PATH ?>assets/favicon/favicon.svg" />
    <link rel="shortcut icon" href="<?= BASE_PATH ?>assets/favicon/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_PATH ?>assets/favicon/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="<?php echo SYSTEM_NAME ?>" />
    <link rel="manifest" href="<?= BASE_PATH ?>assets/favicon/site.webmanifest" />
    <!-- Metas -->
    <meta name="theme-color" content="#ffaf2f" />
    <meta property="og:site_name" content="<?php echo SERVER_NAME ?>" />
    <meta property="og:url" content="<?= $prot_url ?>" />
    <meta property="og:title" content="[#<?= $daten['enr'] ?>] &rsaquo; eNOTF &rsaquo; <?php echo SYSTEM_NAME ?>" />
    <meta property="og:image" content="https://<?php echo SYSTEM_URL ?>/assets/img/aelrd.png" />
    <meta property="og:description" content="Verwaltungsportal der <?php echo RP_ORGTYPE . " " .  SERVER_CITY ?>" />
</head>

<body>
    <div id="topbar" class="container-fluid" data-pin-enabled="<?= $pinEnabled ?>">
        <div class="row">
            <div class="col">
                <?php if (!isset($_SESSION['klinik_access_enr'])): ?>
                    <a href="<?= Redirects::getRedirectUrl($defaultUrl); ?>" class="topbar-ignis-btn">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                <?php endif; ?>
            </div>
            <div class="col text-end">
                <button type="button" class="topbar-ignis-btn" onclick="zoomOut()" title="Verkleinern">
                    <i class="fa-solid fa-minus"></i>
                </button>
                <button type="button" class="topbar-ignis-btn" onclick="zoomIn()" title="Vergrößern">
                    <i class="fa-solid fa-plus"></i>
                </button>
                <button type="button" class="topbar-ignis-btn" onclick="window.print()" title="Drucken">
                    <i class="fa-solid fa-print"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="print__paper">
        <div class="row">
            <div class="col-5">
                <div class="row border border-dark">
                    <div class="col">
                        <h6 class="print__heading">Patientendaten</h6>
                        <?php $printFullname = trim(($daten['pat_nachname'] ?? '') . (!empty($daten['pat_nachname']) && !empty($daten['pat_vorname']) ? ', ' : '') . ($daten['pat_vorname'] ?? '')); ?>
                        <div class="print__field-wrapper" data-field-name="Name">
                            <input type="text" class="w-100 print__field" value="<?= htmlspecialchars($printFullname) ?>" readonly>
                        </div>
                        <div class="print__field-wrapper" data-field-name="Geburtsdatum">
                            <input type="text" class="w-100 print__field" id="patgebdat" data-date="<?= $daten['patgebdat'] ?>" value="<?= !empty($daten['patgebdat']) ? date('d.m.Y', strtotime($daten['patgebdat'])) : '' ?>" readonly>
                        </div>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>Geschlecht</td>
                                <td>
                                    <?php if ($daten['patsex'] === 0): ?>
                                        <input type="radio" name="männlich" checked disabled />
                                        <label for="männlich">männlich</label>
                                    <?php else : ?>
                                        <input type="radio" name="männlich" disabled />
                                        <label for="männlich">männlich</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if ($daten['patsex'] == 1): ?>
                                        <input type="radio" name="weiblich" checked disabled />
                                        <label for="weiblich">weiblich</label>
                                    <?php else : ?>
                                        <input type="radio" name="weiblich" disabled />
                                        <label for="weiblich">weiblich</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['patsex'] == 2): ?>
                                        <input type="radio" name="divers" checked disabled />
                                        <label for="divers">divers</label>
                                    <?php else : ?>
                                        <input type="radio" name="divers" disabled />
                                        <label for="divers">divers</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-5">
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>
                                    <div class="print__field-wrapper" data-field-name="Alter">
                                        <input type="text" class="w-100 print__field" id="_AGE_" readonly>
                                    </div>
                                </td>
                                <td>Jahre</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <?php if ($daten['prot_by'] === 0) : ?>
                            <h1 class="print__heading-main">Einsatzprotokoll</h1>
                        <?php else : ?>
                            <h1 class="print__heading-main">Notarztprotokoll</h1>
                        <?php endif; ?>
                        <p class="print__text-small">Automatisch erstellt mit ıgnıs</p>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col-5">
                        <div class="print__field-wrapper" data-field-name="Einsatz-Nr">
                            <input type="text" class="w-100 print__field" value="<?= $daten['enr'] ?>" readonly>
                        </div>
                    </div>
                    <div class="col">
                        <?php
                        $fahrzeugname = '';
                        if ($daten['prot_by'] === 0) {
                            if (!empty($daten['fzg_transp'])) {
                                $stmt = $pdo->prepare("SELECT name FROM intra_fahrzeuge WHERE identifier = :id LIMIT 1");
                                $stmt->execute(['id' => $daten['fzg_transp']]);
                                $fahrzeugname = $stmt->fetchColumn();
                            }
                        } else {
                            if (!empty($daten['fzg_na'])) {
                                $stmt = $pdo->prepare("SELECT name FROM intra_fahrzeuge WHERE identifier = :id LIMIT 1");
                                $stmt->execute(['id' => $daten['fzg_na']]);
                                $fahrzeugname = $stmt->fetchColumn();
                            }
                        }
                        ?>
                        <div class="print__field-wrapper" data-field-name="Rufname">
                            <input type="text" class="w-100 print__field" value="<?= $fahrzeugname ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="row border border-dark border-start-0 print__full-height">
                    <div class="col">
                        <h6 class="print__heading">Einsatztechnische Daten</h6>
                        <div class="row">
                            <div class="col">
                                <div class="print__field-wrapper" data-field-name="Einsatz-Datum/Zeit">
                                    <input type="text" class="w-100 print__field" value="<?= !empty($daten['edatum']) ? date('d.m.Y', strtotime($daten['edatum'])) . " " . $daten['ezeit'] : '' . " " . $daten['ezeit'] ?>" readonly>
                                </div>
                                <div class="print__field-wrapper" data-field-name="Einsatz-Ort">
                                    <input type="text" class="w-100 print__field" value="<?= $einsatzort_display ?>" readonly>
                                </div>
                                <div class="print__field-wrapper" data-field-name="Transp.-Ziel">
                                    <input type="text" class="w-100 print__field" value="<?= $transportziel_display ?>" readonly>
                                </div>
                                <?php
                                $versorgungsarten = [
                                    1  => 'ambulante Versorgung vor Ort',
                                    2  => 'Transport ohne NA (oder mit TNA)',
                                    21 => 'Transport mit NA (bodengebunden)',
                                    22 => 'Transport mit NA (RTH)',
                                    3  => 'Übergabe anderes Rettungsmittel',
                                    4  => 'Fehleinsatz - kein Patient',
                                    5  => 'Patient nicht transportfähig',
                                    6  => 'primäre Todesfeststellung (ohne CPR)',
                                    99 => 'Sonstige',
                                ];

                                $versorgung_text = $versorgungsarten[$daten['transportziel'] ?? ''] ?? '';
                                ?>
                                <div class="print__field-wrapper" data-field-name="Versorgung">
                                    <input type="text" class="w-100 print__field" value="<?= $versorgung_text ?>" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <?php if ($daten['prot_by'] === 0) : ?>
                                <div class="col">
                                    <div class="print__field-wrapper" data-field-name="Fahrer">
                                        <input type="text" class="w-100 print__field" value="<?= $daten['fzg_transp_perso'] ?>" readonly>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="print__field-wrapper" data-field-name="Beifahrer">
                                        <input type="text" class="w-100 print__field" value="<?= $daten['fzg_transp_perso_2'] ?>" readonly>
                                    </div>
                                </div>
                            <?php else : ?>
                                <div class="col">
                                    <div class="print__field-wrapper" data-field-name="Fahrer">
                                        <input type="text" class="w-100 print__field" value="<?= $daten['fzg_na_perso'] ?>" readonly>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="print__field-wrapper" data-field-name="Beifahrer">
                                        <input type="text" class="w-100 print__field" value="<?= $daten['fzg_na_perso_2'] ?>" readonly>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row border border-dark border-top-0">
            <?php
            $naca_print_labels = [
                0 => '0',
                1 => 'I',
                2 => 'II',
                3 => 'III',
                4 => 'IV',
                5 => 'V',
                6 => 'VI',
                7 => 'VII'
            ];
            $naca_initial_display = ($daten['naca_initial'] !== null && isset($naca_print_labels[$daten['naca_initial']])) ? $naca_print_labels[$daten['naca_initial']] : '';
            $naca_uebergabe_display = ($daten['naca_uebergabe'] !== null && isset($naca_print_labels[$daten['naca_uebergabe']])) ? $naca_print_labels[$daten['naca_uebergabe']] : '';
            $sb_parts = [];
            if (!empty($daten['symptombeginn_datum'])) $sb_parts[] = date('d.m.Y', strtotime($daten['symptombeginn_datum']));
            if (!empty($daten['symptombeginn_zeit'])) $sb_parts[] = $daten['symptombeginn_zeit'];
            $sb_display = implode(' ', $sb_parts);
            ?>
            <div class="col">
                <h6 class="print__heading">Symptombeginn</h6>
                <div style="display: flex; align-items: flex-start; gap: 6pt; padding: 2pt 0;">
                    <strong style="font-size: 9pt;"><?= $sb_display ?></strong>
                    <div style="font-size: 7pt; line-height: 1.4;">
                        <div>
                            <?php if (!empty($daten['symptombeginn_geschaetzt'])) : ?>
                                <input type="radio" checked disabled style="width: 7px; height: 7px; margin: 0;" />
                            <?php else : ?>
                                <input type="radio" disabled style="width: 7px; height: 7px; margin: 0;" />
                            <?php endif; ?>
                            geschätzt
                        </div>
                        <div>
                            <?php if (!empty($daten['symptombeginn_nf'])) : ?>
                                <input type="radio" checked disabled style="width: 7px; height: 7px; margin: 0;" />
                            <?php else : ?>
                                <input type="radio" disabled style="width: 7px; height: 7px; margin: 0;" />
                            <?php endif; ?>
                            nicht feststellbar
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <h6 class="print__heading">NACA</h6>
                <div style="padding: 2pt 0;">
                    <span style="font-size: 9pt;">initial</span>
                    <strong style="font-size: 11pt; margin-left: 4pt;"><?= $naca_initial_display ?></strong>
                    <span style="font-size: 9pt; margin-left: 8pt;">Übergabe</span>
                    <strong style="font-size: 11pt; margin-left: 4pt;"><?= $naca_uebergabe_display ?></strong>
                </div>
            </div>
        </div>
        <div class="row border border-dark border-top-0">
            <div class="col">
                <h6 class="print__heading">Notfallgeschehen, Anamnese, Erstebefund, Vormedikation, Vorbehandlung</h6>
                <div class="row">
                    <div class="col">
                        <div class="print__field-wrapper">
                            <textarea rows="22" style="resize:none" class="w-100 print__textbox" readonly><?= $daten['anmerkungen'] ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <h6 class="print__heading">Erstbefund</h6>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <h6 class="print__heading">Atemwege</h6>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>
                                    <?php if ($daten['awfrei_1'] == 1): ?>
                                        <input type="radio" name="awfrei" checked disabled />
                                        <label for="awfrei">frei</label>
                                    <?php else : ?>
                                        <input type="radio" name="awfrei" disabled />
                                        <label for="awfrei">frei</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['awfrei_1'] == 2): ?>
                                        <input type="radio" name="awgefährdet" checked disabled />
                                        <label for="awgefährdet">gefährdet</label>
                                    <?php else : ?>
                                        <input type="radio" name="awgefährdet" disabled />
                                        <label for="awgefährdet">gefährdet</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <?php if ($daten['awfrei_1'] == 3): ?>
                                        <input type="radio" name="awverlegt" checked disabled />
                                        <label for="awverlegt">verlegt</label>
                                    <?php else : ?>
                                        <input type="radio" name="awverlegt" disabled />
                                        <label for="awverlegt">verlegt</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col border-start border-dark">
                        <h6 class="print__heading">Zyanose</h6>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>
                                    <?php if ($daten['zyanose_1'] == 1): ?>
                                        <input type="radio" name="zyanosenein" checked disabled />
                                        <label for="zyanosenein">Nein</label>
                                    <?php else : ?>
                                        <input type="radio" name="zyanosenein" disabled />
                                        <label for="zyanosenein">Nein</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['zyanose_1'] == 2): ?>
                                        <input type="radio" name="zyanoseja" checked disabled />
                                        <label for="zyanoseja">Ja</label>
                                    <?php else : ?>
                                        <input type="radio" name="zyanoseja" disabled />
                                        <label for="zyanoseja">Ja</label>
                                    <?php endif; ?>
                                </td>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <div class="row">
                            <div class="col">
                                <h6 class="print__heading">Atmung</h6>
                            </div>
                            <div class="col print__text-small text-end">
                                <?php if ($daten['b_symptome'] == 99): ?>
                                    <input type="radio" name="nicht_untersucht_atmung" checked disabled />
                                    <label for="nicht_untersucht_atmung">nicht untersucht</label>
                                <?php else : ?>
                                    <input type="radio" name="nicht_untersucht_atmung" disabled />
                                    <label for="nicht_untersucht_atmung">nicht untersucht</label>
                                <?php endif; ?>
                            </div>
                        </div>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>
                                    <?php if ($daten['b_symptome'] === 0): ?>
                                        <input type="radio" name="unauffällig_atmung" checked disabled />
                                        <label for="unauffällig_atmung">unauffällig</label>
                                    <?php else : ?>
                                        <input type="radio" name="unauffällig_atmung" disabled />
                                        <label for="unauffällig_atmung">unauffällig</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_symptome'] == 1): ?>
                                        <input type="radio" name="dyspnoe" checked disabled />
                                        <label for="dyspnoe">Dyspnoe</label>
                                    <?php else : ?>
                                        <input type="radio" name="dyspnoe" disabled />
                                        <label for="dyspnoe">Dyspnoe</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_symptome'] == 2): ?>
                                        <input type="radio" name="apnoe" checked disabled />
                                        <label for="apnoe">Apnoe</label>
                                    <?php else : ?>
                                        <input type="radio" name="apnoe" disabled />
                                        <label for="apnoe">Apnoe</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_symptome'] == 5): ?>
                                        <input type="radio" name="beatmung_atmung" checked disabled />
                                        <label for="beatmung_atmung">Beatmung</label>
                                    <?php else : ?>
                                        <input type="radio" name="beatmung_atmung" disabled />
                                        <label for="beatmung_atmung">Beatmung</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <?php if ($daten['b_symptome'] == 4): ?>
                                        <input type="radio" name="sonstigepatho_atmung" checked disabled />
                                        <label for="sonstigepatho_atmung">sonstige path. Atemmuster</label>
                                    <?php else : ?>
                                        <input type="radio" name="sonstigepatho_atmung" disabled />
                                        <label for="sonstigepatho_atmung">sonstige path. Atemmuster</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_symptome'] == 3): ?>
                                        <input type="radio" name="schnappatmung" checked disabled />
                                        <label for="schnappatmung">Schnappatmung</label>
                                    <?php else : ?>
                                        <input type="radio" name="schnappatmung" disabled />
                                        <label for="schnappatmung">Schnappatmung</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_symptome'] == 6): ?>
                                        <input type="radio" name="hyperventilation" checked disabled />
                                        <label for="hyperventilation">Hyperventilation</label>
                                    <?php else : ?>
                                        <input type="radio" name="hyperventilation" disabled />
                                        <label for="hyperventilation">Hyperventilation</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <div class="row">
                            <div class="col">
                                <h5 class="print__heading">Auskultation</h5>
                            </div>
                            <div class="col print__text-small text-end">
                                <?php if ($daten['b_auskult'] == 99): ?>
                                    <input type="radio" name="nicht_untersucht_auskult" checked disabled />
                                    <label for="nicht_untersucht_auskult">nicht untersucht</label>
                                <?php else : ?>
                                    <input type="radio" name="nicht_untersucht_auskult" disabled />
                                    <label for="nicht_untersucht_auskult">nicht untersucht</label>
                                <?php endif; ?>
                            </div>
                        </div>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>
                                    <?php if ($daten['b_auskult'] === 0): ?>
                                        <input type="radio" name="unauffällig_auskult" checked disabled />
                                        <label for="unauffällig_auskult">unauffällig</label>
                                    <?php else : ?>
                                        <input type="radio" name="nicht_untersucht_auskult" disabled />
                                        <label for="unauffällig_auskult">unauffällig</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_auskult'] == 1): ?>
                                        <input type="radio" name="spastik" checked disabled />
                                        <label for="spastik">Spastik</label>
                                    <?php else : ?>
                                        <input type="radio" name="spastik" disabled />
                                        <label for="spastik">Spastik</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_auskult'] == 2): ?>
                                        <input type="radio" name="stridor" checked disabled />
                                        <label for="stridor">Stridor</label>
                                    <?php else : ?>
                                        <input type="radio" name="stridor" disabled />
                                        <label for="stridor">Stridor</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_auskult'] == 4): ?>
                                        <input type="radio" name="andere_pathologien_auskult" checked disabled />
                                        <label for="andere_pathologien_auskult">Andere Pathologien</label>
                                    <?php else : ?>
                                        <input type="radio" name="andere_pathologien_auskult" disabled />
                                        <label for="andere_pathologien_auskult">Andere Pathologien</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <?php if ($daten['b_auskult'] == 98): ?>
                                        <input type="radio" name="nicht_beurtielbar_auskult" checked disabled />
                                        <label for="nicht_beurtielbar_auskult">nicht beurteilbar</label>
                                    <?php else : ?>
                                        <input type="radio" name="nicht_beurtielbar_auskult" disabled />
                                        <label for="nicht_beurtielbar_auskult">nicht beurteilbar</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_auskult'] == 3): ?>
                                        <input type="radio" name="rasselgeräusche" checked disabled />
                                        <label for="rasselgeräusche">Rasselgeräusche</label>
                                    <?php else : ?>
                                        <input type="radio" name="rasselgeräusche" disabled />
                                        <label for="rasselgeräusche">Rasselgeräusche</label>
                                    <?php endif; ?>
                                </td>
                                <td></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <div class="row">
                            <div class="col">
                                <h6 class="print__heading">Kreislauf</h6>
                            </div>
                            <div class="col print__text-small text-end">
                                <?php if ($daten['c_rekap'] == 2): ?>
                                    <input type="radio" name="rekap_verlängert" checked disabled />
                                    <label for="rekap_verlängert">Rekap. &gt; 2 s</label>
                                <?php else : ?>
                                    <input type="radio" name="rekap_verlängert" disabled />
                                    <label for="rekap_verlängert">Rekap. &gt; 2 s</label>
                                <?php endif; ?>
                            </div>
                            <div class="col print__text-small text-end">
                                <?php if ($daten['c_blutung'] == 2): ?>
                                    <input type="radio" name="starke_blutung" checked disabled />
                                    <label for="starke_blutung">starke Blutung</label>
                                <?php else : ?>
                                    <input type="radio" name="starke_blutung" disabled />
                                    <label for="starke_blutung">starke Blutung</label>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                        $c_kreislauf_labels = [
                            1 => 'stabil',
                            2 => 'instabil',
                            3 => 'Nicht beurteilbar'
                        ];
                        ?>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>Zustand<br><span class="fw-bold"><?= $c_kreislauf_labels[$daten['c_kreislauf'] ?? ''] ?? '' ?></span></td>
                                <td>
                                    Puls
                                    <br>
                                    <?php if ($daten['c_puls_reg'] == 1): ?>
                                        <input type="radio" name="puls_regelmässig" checked disabled />
                                        <label for="puls_regelmässig">regelmäßig</label>
                                    <?php else : ?>
                                        <input type="radio" name="puls_regelmässig" disabled />
                                        <label for="puls_regelmässig">regelmäßig</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    Radialispuls
                                    <br>
                                    <?php if ($daten['c_puls_rad'] == 1): ?>
                                        <input type="radio" name="radialispuls_tastbar" checked disabled />
                                        <label for="radialispuls_tastbar">tastbar</label>
                                    <?php else : ?>
                                        <input type="radio" name="radialispuls_tastbar" disabled />
                                        <label for="radialispuls_tastbar">tastbar</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <?php if ($daten['c_puls_reg'] == 2): ?>
                                        <input type="radio" name="puls_arrhythmisch" checked disabled />
                                        <label for="puls_arrhythmisch">arrhythmisch</label>
                                    <?php else : ?>
                                        <input type="radio" name="puls_arrhythmisch" disabled />
                                        <label for="puls_arrhythmisch">arrhythmisch</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_puls_rad'] == 2): ?>
                                        <input type="radio" name="radialispuls_nicht_tastbar" checked disabled />
                                        <label for="radialispuls_nicht_tastbar">nicht tastbar</label>
                                    <?php else : ?>
                                        <input type="radio" name="radialispuls_nicht_tastbar" disabled />
                                        <label for="radialispuls_nicht_tastbar">nicht tastbar</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <div class="row">
                            <div class="col">
                                <h6 class="print__heading">EKG</h6>
                            </div>
                            <div class="col print__text-small text-end">
                                <?php if ($daten['c_ekg'] == 99): ?>
                                    <input type="radio" name="kein_ekg" checked disabled />
                                    <label for="kein_ekg">Kein EKG</label>
                                <?php else : ?>
                                    <input type="radio" name="kein_ekg" disabled />
                                    <label for="kein_ekg">Kein EKG</label>
                                <?php endif; ?>
                            </div>
                        </div>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>
                                    <?php if ($daten['c_ekg'] == 1): ?>
                                        <input type="radio" name="sinusrhythmus" checked disabled />
                                        <label for="sinusrhythmus">Sinusrhythmus</label>
                                    <?php else : ?>
                                        <input type="radio" name="sinusrhythmus" disabled />
                                        <label for="sinusrhythmus">Sinusrhythmus</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 4): ?>
                                        <input type="radio" name="kammerflimmern" checked disabled />
                                        <label for="kammerflimmern">VF</label>
                                    <?php else : ?>
                                        <input type="radio" name="kammerflimmern" disabled />
                                        <label for="kammerflimmern">VF</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 2): ?>
                                        <input type="radio" name="infarkt_ekg" checked disabled />
                                        <label for="infarkt_ekg">STEMI</label>
                                    <?php else : ?>
                                        <input type="radio" name="infarkt_ekg" disabled />
                                        <label for="infarkt_ekg">STEMI</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 5): ?>
                                        <input type="radio" name="avblockii" checked disabled />
                                        <label for="avblockii">AV-Block II</label>
                                    <?php else : ?>
                                        <input type="radio" name="avblockii" disabled />
                                        <label for="avblockii">AV-Block II</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 97): ?>
                                        <input type="radio" name="sonstige_ekg" checked disabled />
                                        <label for="sonstige_ekg">Sonstige</label>
                                    <?php else : ?>
                                        <input type="radio" name="sonstige_ekg" disabled />
                                        <label for="sonstige_ekg">Sonstige</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if ($daten['c_ekg'] == 3): ?>
                                        <input type="radio" name="absolute_arrhythmie" checked disabled />
                                        <label for="absolute_arrhythmie">Abs. Arrhythmie</label>
                                    <?php else : ?>
                                        <input type="radio" name="absolute_arrhythmie" disabled />
                                        <label for="absolute_arrhythmie">Abs. Arrhythmie</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 41): ?>
                                        <input type="radio" name="pulslose_elektrische_aktivität" checked disabled />
                                        <label for="pulslose_elektrische_aktivität">PEA</label>
                                    <?php else : ?>
                                        <input type="radio" name="pulslose_elektrische_aktivität" disabled />
                                        <label for="pulslose_elektrische_aktivität">PEA</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 7): ?>
                                        <input type="radio" name="linksschenkelblock" checked disabled />
                                        <label for="linksschenkelblock">LSB</label>
                                    <?php else : ?>
                                        <input type="radio" name="linksschenkelblock" disabled />
                                        <label for="linksschenkelblock">LSB</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 51): ?>
                                        <input type="radio" name="avblockiii" checked disabled />
                                        <label for="avblockiii">AV-Block III</label>
                                    <?php else : ?>
                                        <input type="radio" name="avblockiii" disabled />
                                        <label for="avblockiii">AV-Block III</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 98): ?>
                                        <input type="radio" name="nicht_beurteilbar_ekg" checked disabled />
                                        <label for="nicht_beurteilbar_ekg">nicht beurteilbar</label>
                                    <?php else : ?>
                                        <input type="radio" name="nicht_beurteilbar_ekg" disabled />
                                        <label for="nicht_beurteilbar_ekg">nicht beurteilbar</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if ($daten['c_ekg'] == 21): ?>
                                        <input type="radio" name="schrittmacherrhytmus" checked disabled />
                                        <label for="schrittmacherrhytmus">Schrittmacherrhytmus</label>
                                    <?php else : ?>
                                        <input type="radio" name="schrittmacherrhytmus" disabled />
                                        <label for="schrittmacherrhytmus">Schrittmacherrhytmus</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 6): ?>
                                        <input type="radio" name="asystolie" checked disabled />
                                        <label for="asystolie">Asystolie</label>
                                    <?php else : ?>
                                        <input type="radio" name="asystolie" disabled />
                                        <label for="asystolie">Asystolie</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 71): ?>
                                        <input type="radio" name="rechtsschenkelblock" checked disabled />
                                        <label for="rechtsschenkelblock">RSB</label>
                                    <?php else : ?>
                                        <input type="radio" name="rechtsschenkelblock" disabled />
                                        <label for="rechtsschenkelblock">RSB</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <h6 class="print__heading">Messwerte initial</h6>
                        <div class="row">
                            <div class="col">
                                <div class="print__field-wrapper" data-field-name="/Min" data-vp-name="AF">
                                    <input type="text" class="w-100 print__field-vitals" value="<?= $daten['atemfreq'] ?>" readonly>
                                </div>
                            </div>
                            <div class="col">
                                <div class="print__field-wrapper" data-field-name="%" data-vp-name="SpO2">
                                    <input type="text" class="w-100 print__field-vitals" value="<?= $daten['spo2'] ?>" readonly>
                                </div>
                            </div>
                            <div class="col">
                                <div class="print__field-wrapper" data-field-name="/Min" data-vp-name="HF">
                                    <input type="text" class="w-100 print__field-vitals" value="<?= $daten['herzfreq'] ?>" readonly>
                                </div>
                            </div>
                            <div class="col">
                                <div class="print__field-wrapper" data-field-name="mmHg" data-vp-name="etCO2">
                                    <input type="text" class="w-100 print__field-vitals" value="<?= $daten['etco2'] ?>" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="print__field-wrapper" data-field-name="mmHg" data-vp-name="RR">
                                    <input type="text" class="w-100 print__field-vitals" value="<?= $daten['rrsys'] ?><?= !empty($daten['rrdias']) ? '/' . $daten['rrdias'] : '' ?>" readonly>
                                </div>
                            </div>
                            <div class="col">
                                <div class="print__field-wrapper" data-field-name="<?= htmlspecialchars($bzUnit) ?>" data-vp-name="BZ">
                                    <input type="text" class="w-100 print__field-vitals" value="<?= $daten['bz'] ?>" readonly>
                                </div>
                            </div>
                            <div class="col">
                                <div class="print__field-wrapper" data-field-name="°C" data-vp-name="Temp">
                                    <input type="text" class="w-100 print__field-vitals" value="<?= $daten['temp'] ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <h6 class="print__heading">ERKRANKUNGEN</h6>
                        <?php
                        $diagnose_labels = [
                            // ZNS (1-9)
                            1 => 'Schlaganfall / TIA / ICB',
                            2 => 'ICB (klin. Diagn.)',
                            3 => 'SAB (klin. Diagn.)',
                            4 => 'Krampfanfall',
                            5 => 'Status Epilepticus',
                            6 => 'Meningitis / Encephalitis',
                            9 => 'sonstige Erkrankung ZNS',

                            // Herz-Kreislauf (11-29)
                            11 => 'ACS / NSTEMI',
                            12 => 'ACS / STEMI',
                            13 => 'Kardiogener Schock',
                            14 => 'tachykarde Arrhythmie',
                            15 => 'bradykarde Arrhythmie',
                            16 => 'Schrittmacher-/ICD Fehlfunktion',
                            17 => 'Lungenembolie',
                            18 => 'Lungenödem',
                            19 => 'hypertensiver Notfall',
                            20 => 'Aortenaneurysma',
                            21 => 'Hypotonie',
                            22 => 'Synkope',
                            23 => 'Thrombose / art. Verschluss',
                            24 => 'Herz-Kreislauf-Stillstand',
                            25 => 'Schock unklarer Genese',
                            26 => 'unklarer Thoraxschmerz',
                            27 => 'orthostatische Fehlregulation',
                            28 => 'hypertensive Krise / Entgleisung',
                            29 => 'sonstige Erkrankung Herz-Kreislauf',

                            // Atemwege (31-49)
                            31 => 'Asthma (Anfall)',
                            32 => 'Status Asthmaticus',
                            33 => 'exacerbierte COPD',
                            34 => 'Aspiration',
                            35 => 'Pneumonie / Bronchitis',
                            36 => 'Hyperventilation',
                            37 => 'Spontanpneumothorax',
                            38 => 'Hämoptysis',
                            39 => 'Dyspnoe unklarer Ursache',
                            49 => 'sonstige Erkrankung Atmung',

                            // Abdomen (51-59)
                            51 => 'akutes Abdomen',
                            52 => 'obere GI-Blutung',
                            53 => 'untere GI-Blutung',
                            54 => 'Gallenkolik',
                            55 => 'Nierenkolik',
                            56 => 'Kolik allgemein',
                            59 => 'sonstige Erkrankung Abdomen',

                            // Psychiatrie (61-69)
                            61 => 'psychischer Ausnahmezustand',
                            62 => 'Depression',
                            63 => 'Manie',
                            64 => 'Intoxikation',
                            65 => 'Entzug, Delir',
                            66 => 'Suizidalität',
                            67 => 'psychosoziale Krise',
                            69 => 'sonstige Erkrankung Psychiatrie',

                            // Stoffwechsel (71-79)
                            71 => 'Hypoglykämie',
                            72 => 'Hyperglykämie',
                            73 => 'Urämie / ANV',
                            74 => 'Exsikkose',
                            75 => 'bek. Dialysepflicht',
                            79 => 'sonstige Erkrankung Stoffwechsel',

                            // Sonstige (81-99)
                            81 => 'Anaphylaxie Grad 1/2',
                            82 => 'Anaphylaxie Grad 3/4',
                            83 => 'Infekt / Sepsis / Schock',
                            84 => 'Hitzeerschöpfung / Hitzschlag',
                            85 => 'Unterkühlung / Erfrierung',
                            86 => 'akute Lumbago',
                            87 => 'Palliative Situation',
                            88 => 'medizinische Behandlungskompl.',
                            89 => 'Epistaxis / HNO-Erkrankung',
                            91 => 'urologische Erkrankung',
                            92 => 'unklare Erkrankung',
                            93 => 'akzidentelle Intoxikation',
                            94 => 'Augenerkrankung',
                            99 => 'Keine Erkrankung / Verletzung feststellbar',

                            // Trauma - Schädel-Hirn (101-104)
                            101 => 'Trauma Schädel-Hirn leicht',
                            102 => 'Trauma Schädel-Hirn mittel',
                            103 => 'Trauma Schädel-Hirn schwer',
                            104 => 'Trauma Schädel-Hirn tödlich',

                            // Trauma - Gesicht (111-114)
                            111 => 'Trauma Gesicht leicht',
                            112 => 'Trauma Gesicht mittel',
                            113 => 'Trauma Gesicht schwer',
                            114 => 'Trauma Gesicht tödlich',

                            // Trauma - HWS (121-124)
                            121 => 'Trauma HWS leicht',
                            122 => 'Trauma HWS mittel',
                            123 => 'Trauma HWS schwer',
                            124 => 'Trauma HWS tödlich',

                            // Trauma - Thorax (131-134)
                            131 => 'Trauma Thorax leicht',
                            132 => 'Trauma Thorax mittel',
                            133 => 'Trauma Thorax schwer',
                            134 => 'Trauma Thorax tödlich',

                            // Trauma - Abdomen (141-144)
                            141 => 'Trauma Abdomen leicht',
                            142 => 'Trauma Abdomen mittel',
                            143 => 'Trauma Abdomen schwer',
                            144 => 'Trauma Abdomen tödlich',

                            // Trauma - BWS / LWS (151-153)
                            151 => 'Trauma BWS / LWS leicht',
                            152 => 'Trauma BWS / LWS mittel',
                            153 => 'Trauma BWS / LWS schwer',

                            // Trauma - Becken (161-163)
                            161 => 'Trauma Becken leicht',
                            162 => 'Trauma Becken mittel',
                            163 => 'Trauma Becken schwer',

                            // Trauma - obere Extremitäten (171-173)
                            171 => 'Trauma obere Extremitäten leicht',
                            172 => 'Trauma obere Extremitäten mittel',
                            173 => 'Trauma obere Extremitäten schwer',

                            // Trauma - untere Extremitäten (181-183)
                            181 => 'Trauma untere Extremitäten leicht',
                            182 => 'Trauma untere Extremitäten mittel',
                            183 => 'Trauma untere Extremitäten schwer',

                            // Trauma - Weichteile (191-193)
                            191 => 'Trauma Weichteile leicht',
                            192 => 'Trauma Weichteile mittel',
                            193 => 'Trauma Weichteile schwer',

                            // Trauma - spezielle (201-209)
                            201 => 'Verbrennung / Verbrühung',
                            202 => 'Inhalationstrauma',
                            203 => 'Elektrounfall',
                            204 => '(beinahe-) Ertrinken',
                            205 => 'Tauchunfall',
                            206 => 'Verätzung',
                            209 => 'Sonstige',
                        ];

                        $diagnose_haupt_text = '';
                        if (isset($daten['diagnose_haupt']) && !empty($daten['diagnose_haupt'])) {
                            $diagnose_haupt_text = $diagnose_labels[$daten['diagnose_haupt'] ?? ''] ?? 'Unbekannte Diagnose';
                        }

                        $diagnose_weitere_array = [];
                        if (!empty($daten['diagnose_weitere'])) {
                            $decoded = json_decode($daten['diagnose_weitere'], true);
                            if (is_array($decoded)) {
                                $diagnose_weitere_array = $decoded;
                            }
                        }

                        $diagnose_weitere_text = '';
                        if (!empty($diagnose_weitere_array)) {
                            $diagnose_weitere_labels = [];
                            foreach ($diagnose_weitere_array as $diagnose_id) {
                                if (isset($diagnose_labels[$diagnose_id])) {
                                    $diagnose_weitere_labels[] = $diagnose_labels[$diagnose_id];
                                }
                            }
                            $diagnose_weitere_text = implode(', ', $diagnose_weitere_labels);
                        }

                        $rettungstechnik = [];
                        if (!empty($daten['rettungstechnik'])) {
                            $decoded = json_decode($daten['rettungstechnik'], true);
                            if (is_array($decoded)) {
                                $rettungstechnik = array_map('intval', $decoded);
                            }
                        }
                        ?>
                        <div class="print__field-wrapper" data-field-name="führende Diagnose">
                            <input type="text" class="w-100 print__field" value="<?= !empty($diagnose_haupt_text) ? $diagnose_haupt_text : '' ?>" readonly>
                        </div>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <h5 class="print__heading">weitere Diagnosen</h5>
                        <div class="print__field-wrapper">
                            <textarea rows="3" style="resize:none;font-size:11pt" class="w-100 print__textbox" readonly><?= !empty($diagnose_weitere_text) ? $diagnose_weitere_text : '' ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="row border border-dark border-top-0 border-start-0">
                    <div class="col">
                        <h6 class="print__heading">Neurologie</h6>
                    </div>
                    <div class="w-8/12 border-start border-dark px-3">
                        <?php
                        $d_bewusstsein_labels = [
                            1 => 'wach',
                            2 => 'analgosediert / Narkose',
                            3 => 'reagiert auf Ansprache',
                            4 => 'reagiert auf Schmerzreiz',
                            5 => 'bewusstlos',
                            97 => 'Sonstige',
                            98 => 'nicht beurteilbar',
                            99 => 'nicht untersucht'
                        ];
                        ?>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td colspan="4">
                                    Bewusstsein <span class="fw-bold"><?= $d_bewusstsein_labels[$daten['d_bewusstsein'] ?? ''] ?? '' ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php
                                    // GCS-Berechnung
                                    $gcs_augen = isset($daten['d_gcs_1']) ? (4 - $daten['d_gcs_1']) : 0;
                                    $gcs_verbal = isset($daten['d_gcs_2']) ? (5 - $daten['d_gcs_2']) : 0;
                                    $gcs_motorik = isset($daten['d_gcs_3']) ? (6 - $daten['d_gcs_3']) : 0;
                                    $gcs_total = $gcs_augen + $gcs_verbal + $gcs_motorik;
                                    ?>
                                    Augen<br><span class="fw-bold"><?= $gcs_augen ?></span>
                                </td>
                                <td>
                                    Verbal<br><span class="fw-bold"><?= $gcs_verbal ?></span>
                                </td>
                                <td>
                                    Motorik<br><span class="fw-bold"><?= $gcs_motorik ?></span>
                                </td>
                                <td>
                                    Summe<br><span class="fw-bold" style="font-size: 8pt"><?= $gcs_total ?> GCS</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0 border-start-0">
                    <div class="col">
                        <?php
                        $d_pupillenw_1_labels = [
                            1 => 'weit',
                            2 => 'mittel',
                            3 => 'eng',
                            4 => 'entrundet',
                            99 => 'nicht untersucht'
                        ];
                        $d_lichtreakt_1_labels = [
                            1 => 'prompt',
                            2 => 'träge',
                            3 => 'keine',
                            99 => 'nicht untersucht'
                        ];
                        ?>
                        <h5 class="print__heading">Pupillenstatus</h5>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>rechts</td>
                                <td>Weite<br><span class="fw-bold"><?= $d_pupillenw_1_labels[$daten['d_pupillenw_2'] ?? ''] ?? '' ?></span></td>
                                <td>Lichtreaktion<br><span class="fw-bold"><?= $d_lichtreakt_1_labels[$daten['d_lichtreakt_2'] ?? ''] ?? '' ?></span></td>
                                <td>links</td>
                                <td>Weite<br><span class="fw-bold"><?= $d_pupillenw_1_labels[$daten['d_pupillenw_1'] ?? ''] ?? '' ?></span></td>
                                <td>Lichtreaktion<br><span class="fw-bold"><?= $d_lichtreakt_1_labels[$daten['d_lichtreakt_1'] ?? ''] ?? '' ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0 border-start-0">
                    <div class="col print__text-small">
                        <?php
                        $d_ex_1_labels = [
                            1 => 'uneingeschränkt',
                            2 => 'leicht eingeschränkt',
                            3 => 'stark eingeschränkt',
                            99 => 'Nicht beurteilbar'
                        ];
                        ?>
                        Extremitätenbewegung<br><span class="fw-bold"><?= $d_ex_1_labels[$daten['d_ex_1'] ?? ''] ?? '' ?></span>
                    </div>
                    <div class="col border-start border-dark">
                        <?php
                        $sz_nrs_labels = [
                            0 => 'keine',
                            1 => '1',
                            2 => '2',
                            3 => '3',
                            4 => '4',
                            5 => '5',
                            6 => '6',
                            7 => '7',
                            8 => '8',
                            9 => '9',
                            10 => '10',
                            98 => 'Nicht erhoben',
                            99 => 'Nicht beurteilbar'
                        ];
                        ?>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td rowspan="2">Schmerzen<br><span class="fw-bold">NRS <?= $sz_nrs_labels[$daten['sz_nrs'] ?? ''] ?? '' ?></span></td>
                                <td>
                                    <?php if ($daten['sz_toleranz_1'] == 1): ?>
                                        <input type="radio" name="tolerabel" checked disabled />
                                        <label for="tolerabel">Tolerabel</label>
                                    <?php else : ?>
                                        <input type="radio" name="tolerabel" disabled />
                                        <label for="tolerabel">Tolerabel</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if ($daten['sz_toleranz_1'] == 2): ?>
                                        <input type="radio" name="ntolerabel" checked disabled />
                                        <label for="ntolerabel">Nicht tolerabel</label>
                                    <?php else : ?>
                                        <input type="radio" name="ntolerabel" disabled />
                                        <label for="ntolerabel">Nicht tolerabel</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0 border-start-0">
                    <div class="col">
                        <?php
                        $psych = [];
                        if (!empty($daten['psych'])) {
                            $decoded = json_decode($daten['psych'], true);
                            if (is_array($decoded)) {
                                $psych = array_map('intval', $decoded);
                            }
                        }
                        ?>
                        <h6 class="print__heading">Psyche</h6>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td></td>
                                <td>
                                    <?php if (in_array(2, $psych)): ?>
                                        <input type="radio" name="aggressiv" checked disabled />
                                        <label for="aggressiv">aggressiv</label>
                                    <?php else : ?>
                                        <input type="radio" name="aggressiv" disabled />
                                        <label for="aggressiv">aggressiv</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(5, $psych)): ?>
                                        <input type="radio" name="verwirrt" checked disabled />
                                        <label for="verwirrt">verwirrt</label>
                                    <?php else : ?>
                                        <input type="radio" name="verwirrt" disabled />
                                        <label for="verwirrt">verwirrt</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(8, $psych)): ?>
                                        <input type="radio" name="erregt" checked disabled />
                                        <label for="erregt">erregt</label>
                                    <?php else : ?>
                                        <input type="radio" name="erregt" disabled />
                                        <label for="erregt">erregt</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(10, $psych)): ?>
                                        <input type="radio" name="suizidal" checked disabled />
                                        <label for="suizidal">suizidal</label>
                                    <?php else : ?>
                                        <input type="radio" name="suizidal" disabled />
                                        <label for="suizidal">suizidal</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(98, $psych)): ?>
                                        <input type="radio" name="nicht_beurteilbar_psych" checked disabled />
                                        <label for="nicht_beurteilbar_psych">Nicht beurteilbar</label>
                                    <?php else : ?>
                                        <input type="radio" name="nicht_beurteilbar_psych" disabled />
                                        <label for="nicht_beurteilbar_psych">Nicht beurteilbar</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if (in_array(1, $psych)): ?>
                                        <input type="radio" name="unauffällig_psych" checked disabled />
                                        <label for="unauffällig_psych">unauffällig</label>
                                    <?php else : ?>
                                        <input type="radio" name="unauffällig_psych" disabled />
                                        <label for="unauffällig_psych">unauffällig</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(3, $psych)): ?>
                                        <input type="radio" name="depressiv" checked disabled />
                                        <label for="depressiv">depressiv</label>
                                    <?php else : ?>
                                        <input type="radio" name="depressiv" disabled />
                                        <label for="depressiv">depressiv</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(6, $psych)): ?>
                                        <input type="radio" name="verlangsamt" checked disabled />
                                        <label for="verlangsamt">verlangsamt</label>
                                    <?php else : ?>
                                        <input type="radio" name="verlangsamt" disabled />
                                        <label for="verlangsamt">verlangsamt</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(9, $psych)): ?>
                                        <input type="radio" name="ängstlich" checked disabled />
                                        <label for="ängstlich">ängstlich</label>
                                    <?php else : ?>
                                        <input type="radio" name="ängstlich" disabled />
                                        <label for="ängstlich">ängstlich</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(12, $psych)): ?>
                                        <input type="radio" name="sonstige_psych" checked disabled />
                                        <label for="sonstige_psych">Sonstige</label>
                                    <?php else : ?>
                                        <input type="radio" name="sonstige_psych" disabled />
                                        <label for="sonstige_psych">Sonstige</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(99, $psych)): ?>
                                        <input type="radio" name="nicht_untersucht_psych" checked disabled />
                                        <label for="nicht_untersucht_psych">nicht untersucht</label>
                                    <?php else : ?>
                                        <input type="radio" name="nicht_untersucht_psych" disabled />
                                        <label for="nicht_untersucht_psych">nicht untersucht</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <?php if (in_array(4, $psych)): ?>
                                        <input type="radio" name="wahnhaft" checked disabled />
                                        <label for="wahnhaft">wahnhaft</label>
                                    <?php else : ?>
                                        <input type="radio" name="wahnhaft" disabled />
                                        <label for="wahnhaft">wahnhaft</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(7, $psych)): ?>
                                        <input type="radio" name="euphorisch" checked disabled />
                                        <label for="euphorisch">euphorisch</label>
                                    <?php else : ?>
                                        <input type="radio" name="euphorisch" disabled />
                                        <label for="euphorisch">euphorisch</label>
                                    <?php endif; ?>
                                </td>
                                <td colspan="2">
                                    <?php if (in_array(11, $psych)): ?>
                                        <input type="radio" name="motorisch_unruhig" checked disabled />
                                        <label for="motorisch_unruhig">motorisch unruhig</label>
                                    <?php else : ?>
                                        <input type="radio" name="motorisch_unruhig" disabled />
                                        <label for="motorisch_unruhig">motorisch unruhig</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0 border-start-0">
                    <div class="col">
                        <h6 class="print__heading">Verletzungen</h6>
                        <?php
                        $v_muster_k_labels = [
                            1 => 'keine',
                            2 => 'leicht',
                            3 => 'mittel',
                            4 => 'schwer',
                            99 => 'Nicht untersucht'
                        ];
                        $v_muster_k1_labels = [
                            1 => 'offen',
                            2 => 'geschlossen'
                        ];
                        ?>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>Schädel-Hirn</td>
                                <td class="fw-bold"><?= $v_muster_k_labels[$daten['v_muster_k'] ?? ''] ?? '' ?></td>
                                <td class="fw-bold"> <?= $v_muster_k1_labels[$daten['v_muster_k1'] ?? ''] ?? '' ?></td>
                            </tr>
                            <tr>
                                <td>Wirbelsäule</td>
                                <td class="fw-bold"><?= $v_muster_k_labels[$daten['v_muster_w'] ?? ''] ?? '' ?></td>
                                <td class="fw-bold"> <?= $v_muster_k1_labels[$daten['v_muster_w1'] ?? ''] ?? '' ?></td>
                            </tr>
                            <tr>
                                <td>Thorax</td>
                                <td class="fw-bold"><?= $v_muster_k_labels[$daten['v_muster_t'] ?? ''] ?? '' ?></td>
                                <td class="fw-bold"> <?= $v_muster_k1_labels[$daten['v_muster_t1'] ?? ''] ?? '' ?></td>
                            </tr>
                            <tr>
                                <td>Abdomen</td>
                                <td class="fw-bold"><?= $v_muster_k_labels[$daten['v_muster_a'] ?? ''] ?? '' ?></td>
                                <td class="fw-bold"> <?= $v_muster_k1_labels[$daten['v_muster_a1'] ?? ''] ?? '' ?></td>
                            </tr>
                            <tr>
                                <td>Obere Extremitäten</td>
                                <td class="fw-bold"><?= $v_muster_k_labels[$daten['v_muster_al'] ?? ''] ?? '' ?></td>
                                <td class="fw-bold"> <?= $v_muster_k1_labels[$daten['v_muster_al1'] ?? ''] ?? '' ?></td>
                            </tr>
                            <tr>
                                <td>Untere Extremitäten</td>
                                <td class="fw-bold"><?= $v_muster_k_labels[$daten['v_muster_bl'] ?? ''] ?? '' ?></td>
                                <td class="fw-bold"> <?= $v_muster_k1_labels[$daten['v_muster_bl1'] ?? ''] ?? '' ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0 border-start-0">
                    <div class="col text-end fw-bold print__text-small">
                        erstellt mit ıgnıs von EmergencyForge
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="print__paper">
        <div class="row">
            <div class="col-8">
                <div class="row border border-dark">
                    <div class="col">
                        <h6 class="print__heading">Verlaufsbeschreibung</h6>
                    </div>
                </div>
                <?php
                // Vitalparameter aus Datenbank laden
                $queryVitals = "SELECT * FROM intra_edivi_vitalparameter_einzelwerte 
                            WHERE enr = :enr AND geloescht = 0 
                            ORDER BY zeitpunkt ASC";
                $stmtVitals = $pdo->prepare($queryVitals);
                $stmtVitals->execute(['enr' => $enr]);
                $vitalsRaw = $stmtVitals->fetchAll(PDO::FETCH_ASSOC);

                // Nach Zeitpunkt gruppieren
                $groupedVitals = [];
                foreach ($vitalsRaw as $vital) {
                    $zeitpunkt = $vital['zeitpunkt'];
                    if (!isset($groupedVitals[$zeitpunkt])) {
                        $groupedVitals[$zeitpunkt] = [];
                    }
                    $groupedVitals[$zeitpunkt][$vital['parameter_name']] = $vital['parameter_wert'];
                }
                ksort($groupedVitals);

                // Daten für Chart vorbereiten
                $chartLabels = [];
                $chartData = [
                    'spo2' => [],
                    'herzfreq' => [],
                    'rrsys' => [],
                    'rrdias' => [],
                    'atemfreq' => [],
                    'temp' => [],
                    'bz' => [],
                    'etco2' => []
                ];

                foreach ($groupedVitals as $zeitpunkt => $werte) {
                    $chartLabels[] = (new DateTime($zeitpunkt))->format('H:i');

                    $chartData['spo2'][] = isset($werte['SpO₂']) && $werte['SpO₂'] !== '' ? floatval(str_replace(',', '.', $werte['SpO₂'])) : null;
                    $chartData['herzfreq'][] = isset($werte['Herzfrequenz']) && $werte['Herzfrequenz'] !== '' ? floatval(str_replace(',', '.', $werte['Herzfrequenz'])) : null;
                    $chartData['rrsys'][] = isset($werte['RR systolisch']) && $werte['RR systolisch'] !== '' ? floatval(str_replace(',', '.', $werte['RR systolisch'])) : null;
                    $chartData['rrdias'][] = isset($werte['RR diastolisch']) && $werte['RR diastolisch'] !== '' ? floatval(str_replace(',', '.', $werte['RR diastolisch'])) : null;
                    $chartData['atemfreq'][] = isset($werte['Atemfrequenz']) && $werte['Atemfrequenz'] !== '' ? floatval(str_replace(',', '.', $werte['Atemfrequenz'])) : null;
                    $chartData['temp'][] = isset($werte['Temperatur']) && $werte['Temperatur'] !== '' ? floatval(str_replace(',', '.', $werte['Temperatur'])) : null;
                    // Blutzucker: Werte sind in mg/dl gespeichert, konvertiere zur Anzeige-Einheit
                    $bzValue = isset($werte['Blutzucker']) && $werte['Blutzucker'] !== '' ? floatval(str_replace(',', '.', $werte['Blutzucker'])) : null;
                    $chartData['bz'][] = $bzValue !== null ? $bzHelper->toDisplayUnit($bzValue) : null;
                    $chartData['etco2'][] = isset($werte['etCO₂']) && $werte['etCO₂'] !== '' ? floatval(str_replace(',', '.', $werte['etCO₂'])) : null;
                }
                ?>

                <?php if (!empty($groupedVitals)): ?>
                    <div class="row border border-dark border-top-0">
                        <div class="col p-3" style="background: white;">
                            <canvas id="vitalChart" width="700" height="400"></canvas>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row border border-dark border-top-0">
                        <div class="col p-3 text-center print__text-small">
                            Keine Vitalparameter dokumentiert
                        </div>
                    </div>
                <?php endif; ?>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <div class="row">
                            <div class="col">
                                <h6 class="print__heading">Medikation</h6>
                            </div>
                            <div class="col print__text-small text-end">
                                <?php if ($daten['medis'] == 1 || $daten['medis'] === 0): ?>
                                    <input type="radio" name="keine_medis" checked disabled />
                                    <label for="keine_medis">Keine Medikamente</label>
                                <?php else : ?>
                                    <input type="radio" name="keine_medis" disabled />
                                    <label for="keine_medis">Keine Medikamente</label>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                        /**
                         * @return array<int, array<string, mixed>>
                         */
                        function getCurrentMedikamente(?string $medikamenteJson): array
                        {
                            if (empty($medikamenteJson)) {
                                return [];
                            }

                            $decoded = json_decode($medikamenteJson, true);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                return [];
                            }

                            if (is_array($decoded)) {
                                return $decoded;
                            }

                            return [];
                        }

                        function displayAllMedikamente(?string $medikamenteJson): string
                        {
                            if ($medikamenteJson === null) {
                                return '';
                            }

                            if ($medikamenteJson === '0') {
                                return '';
                            }

                            $medikamente = getCurrentMedikamente($medikamenteJson);

                            if (empty($medikamente)) {
                                return '';
                            }

                            usort($medikamente, function ($a, $b) {
                                return strcmp($a['zeit'], $b['zeit']);
                            });

                            $displays = [];
                            foreach ($medikamente as $med) {
                                $displayEinheit = $med['einheit'];
                                if ($displayEinheit === 'mcg') {
                                    $displayEinheit = '&micro;g';
                                } else if ($displayEinheit === 'IE') {
                                    $displayEinheit = 'I.E.';
                                }

                                $displays[] = sprintf(
                                    '%s: %s %s %s %s',
                                    $med['zeit'],
                                    $med['wirkstoff'],
                                    $med['dosierung'],
                                    $displayEinheit,
                                    $med['applikation']
                                );
                            }

                            return implode("\n", $displays);
                        }

                        function hasAnyMedikamente(?string $medikamenteJson): bool
                        {
                            $medikamente = getCurrentMedikamente($medikamenteJson);
                            return !empty($medikamente);
                        }
                        ?>
                        <div class="print__field-wrapper">
                            <textarea rows="18" style="resize:none" class="w-100 print__textbox" readonly><?= displayAllMedikamente($daten['medis'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <h6 class="print__heading">Zeiten</h6>
                        <div class="row">
                            <div class="col-3">
                                <div class="print__field-wrapper" data-field-name="Alarm">
                                    <input type="text" class="w-100 print__field" value="<?= !empty($zeiten['salarm']) ? date('H:i', strtotime($zeiten['salarm'])) : '' ?>" readonly>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="print__field-wrapper" data-field-name="aus (3)">
                                    <input type="text" class="w-100 print__field" value="<?= !empty($zeiten['s3']) ? date('H:i', strtotime($zeiten['s3'])) : '' ?>" readonly>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="print__field-wrapper" data-field-name="E.-an (4)">
                                    <input type="text" class="w-100 print__field" value="<?= !empty($zeiten['s4']) ? date('H:i', strtotime($zeiten['s4'])) : '' ?>" readonly>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="print__field-wrapper" data-field-name="Pat.-an">
                                    <input type="text" class="w-100 print__field" value="<?= !empty($zeiten['spat']) ? date('H:i', strtotime($zeiten['spat'])) : '' ?>" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-3">
                                <div class="print__field-wrapper" data-field-name="E.-ab (7)">
                                    <input type="text" class="w-100 print__field" value="<?= !empty($zeiten['s7']) ? date('H:i', strtotime($zeiten['s7'])) : '' ?>" readonly>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="print__field-wrapper" data-field-name="KH an (8)">
                                    <input type="text" class="w-100 print__field" value="<?= !empty($zeiten['s8']) ? date('H:i', strtotime($zeiten['s8'])) : '' ?>" readonly>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="print__field-wrapper" data-field-name="frei (1)">
                                    <input type="text" class="w-100 print__field" value="<?= !empty($zeiten['s1']) ? date('H:i', strtotime($zeiten['s1'])) : '' ?>" readonly>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="print__field-wrapper" data-field-name="Wache (2)">
                                    <input type="text" class="w-100 print__field" value="<?= !empty($zeiten['s2']) ? date('H:i', strtotime($zeiten['s2'])) : '' ?>" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-3">
                                <div class="print__field-wrapper" data-field-name="Ende">
                                    <input type="text" class="w-100 print__field" value="<?= !empty($zeiten['sende']) ? date('H:i', strtotime($zeiten['sende'])) : '' ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="row border border-dark">
                    <div class="col">
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>Patient Name<br><span style="font-weight: 600;font-size:11pt"><?= htmlspecialchars($printFullname) ?></span></td>
                                <td>Geb.Dat.<br><span style="font-weight: 600;font-size:11pt"><?= !empty($daten['patgebdat']) ? date('d.m.Y', strtotime($daten['patgebdat'])) : '' ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <h5 class="print__heading">Maßnahmen</h5>
                        <h6 class="print__heading">Zugänge</h6>
                        <?php
                        /**
                         * @return array<int, array<string, mixed>>
                         */
                        function getCurrentZugaenge(?string $zugangJson): array
                        {
                            if (empty($zugangJson)) {
                                return [];
                            }

                            $decoded = json_decode($zugangJson, true);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                return [];
                            }

                            if (isset($decoded['art'])) {
                                return [$decoded];
                            } else if (is_array($decoded)) {
                                return $decoded;
                            }

                            return [];
                        }

                        /**
                         * @param array<int, array<string, mixed>> $zugaenge
                         */
                        function hasZugangAtLocation(array $zugaenge, string $art, string $ort, string $seite): bool
                        {
                            foreach ($zugaenge as $zugang) {
                                if (
                                    $zugang['art'] === $art &&
                                    $zugang['ort'] === $ort &&
                                    $zugang['seite'] === $seite
                                ) {
                                    return true;
                                }
                            }
                            return false;
                        }

                        /**
                         * @param array<int, array<string, mixed>> $zugaenge
                         * @param array<string, mixed> $newZugang
                         * @return array<int, array<string, mixed>>
                         */
                        function addZugang(array $zugaenge, array $newZugang): array
                        {
                            foreach ($zugaenge as $index => $zugang) {
                                if (
                                    $zugang['art'] === $newZugang['art'] &&
                                    $zugang['ort'] === $newZugang['ort'] &&
                                    $zugang['seite'] === $newZugang['seite']
                                ) {
                                    $zugaenge[$index] = $newZugang;
                                    return $zugaenge;
                                }
                            }

                            $zugaenge[] = $newZugang;
                            return $zugaenge;
                        }

                        /**
                         * @param array<int, array<string, mixed>> $zugaenge
                         * @return array<int, array<string, mixed>>
                         */
                        function removeZugang(array $zugaenge, string $art, string $ort, string $seite): array
                        {
                            return array_values(array_filter($zugaenge, function ($zugang) use ($art, $ort, $seite) {
                                return !($zugang['art'] === $art &&
                                    $zugang['ort'] === $ort &&
                                    $zugang['seite'] === $seite);
                            }));
                        }

                        $currentZugaenge = getCurrentZugaenge($daten['c_zugang'] ?? '');

                        function normalize_groesse_pretty(string $raw): string
                        {
                            $v = strtoupper(str_replace(' ', '', trim($raw)));
                            $suffixKurz = false;

                            if (str_ends_with($v, '_KURZ')) {
                                $suffixKurz = true;
                                $v = substr($v, 0, -5);
                            }

                            if (preg_match('/^(\d{2})G$/', $v, $m)) {
                                $core = $m[1] . 'G';
                            } elseif (preg_match('/^G(\d{2})$/', $v, $m)) {
                                $core = $m[1] . 'G';
                            } elseif (preg_match('/^(15|25|45)MM$/', $v, $m)) {
                                return $m[1] . ' mm';
                            } else {
                                $core = $v;
                            }

                            $labels = [
                                '24G' => '24 G',
                                '22G' => '22 G',
                                '20G' => '20 G',
                                '18G' => '18 G',
                                '17G' => '17 G',
                                '16G' => '16 G',
                                '14G' => '14 G',
                            ];

                            $pretty = $labels[$core] ?? $raw;

                            if ($suffixKurz) {
                                $pretty .= ' kurz';
                            }
                            return $pretty;
                        }

                        function displayAllZugaenge(?string $zugangJson): string
                        {
                            if ($zugangJson === null) {
                                return '';
                            }
                            if ($zugangJson === '0') {
                                return 'Kein Zugang';
                            }

                            $zugaenge = getCurrentZugaenge($zugangJson);
                            if (empty($zugaenge)) {
                                return '';
                            }

                            usort($zugaenge, function ($a, $b) {
                                return [$a['art'], $a['ort'], $a['seite']] <=> [$b['art'], $b['ort'], $b['seite']];
                            });

                            $artNames = ['pvk' => 'PVK', 'zvk' => 'ZVK', 'io' => 'intraossär'];
                            $displays = [];

                            foreach ($zugaenge as $zugang) {
                                $artName   = $artNames[$zugang['art'] ?? ''] ?? ($zugang['art'] ?? '');
                                $groesse   = normalize_groesse_pretty($zugang['groesse'] ?? '');
                                $ort       = $zugang['ort']   ?? '';
                                $seite     = $zugang['seite'] ?? '';

                                $displays[] = sprintf('%s %s %s %s', $artName, $groesse, $ort, $seite);
                            }

                            return implode('<br>', $displays);
                        }

                        function displayAllZugaengeText(?string $zugangJson): string
                        {
                            if ($zugangJson === null) {
                                return '';
                            }
                            if ($zugangJson === '0') {
                                return 'Kein Zugang';
                            }

                            $zugaenge = getCurrentZugaenge($zugangJson);
                            if (empty($zugaenge)) {
                                return '';
                            }

                            usort($zugaenge, function ($a, $b) {
                                return [$a['art'], $a['ort'], $a['seite']] <=> [$b['art'], $b['ort'], $b['seite']];
                            });

                            $artNames = ['pvk' => 'PVK', 'zvk' => 'ZVK', 'io' => 'intraossär'];
                            $displays = [];

                            foreach ($zugaenge as $zugang) {
                                $artName   = $artNames[$zugang['art'] ?? ''] ?? ($zugang['art'] ?? '');
                                $groesse   = normalize_groesse_pretty($zugang['groesse'] ?? '');
                                $ort       = $zugang['ort']   ?? '';
                                $seite     = $zugang['seite'] ?? '';

                                $displays[] = sprintf('%s %s %s %s', $artName, $groesse, $ort, $seite);
                            }

                            return implode("\n", $displays);
                        }

                        function displayZugaengeByArt(?string $zugangJson, ?string $filterArt = null): string
                        {
                            if ($zugangJson === null) {
                                return '';
                            }
                            if ($zugangJson === '0') {
                                return 'Kein Zugang';
                            }

                            $zugaenge = getCurrentZugaenge($zugangJson);
                            if (empty($zugaenge)) {
                                return '';
                            }

                            // Filtern nach Art, wenn angegeben
                            if ($filterArt !== null) {
                                $zugaenge = array_filter($zugaenge, function ($zugang) use ($filterArt) {
                                    return $zugang['art'] === $filterArt;
                                });

                                if (empty($zugaenge)) {
                                    return '<em>Keine Zugänge dieser Art</em>';
                                }
                            }

                            usort($zugaenge, function ($a, $b) {
                                return [$a['art'], $a['ort'], $a['seite']] <=> [$b['art'], $b['ort'], $b['seite']];
                            });

                            $artNames = ['pvk' => 'PVK', 'zvk' => 'ZVK', 'io' => 'intraossär'];
                            $displays = [];

                            foreach ($zugaenge as $zugang) {
                                $artName   = $artNames[$zugang['art'] ?? ''] ?? ($zugang['art'] ?? '');
                                $groesse   = normalize_groesse_pretty($zugang['groesse'] ?? '');
                                $ort       = $zugang['ort']   ?? '';
                                $seite     = $zugang['seite'] ?? '';

                                $displays[] = sprintf('%s %s %s %s', $artName, $groesse, $ort, $seite);
                            }

                            return implode('<br>', $displays);
                        }

                        // Oder als Text-Version für Textareas:
                        function displayZugaengeByArtText(?string $zugangJson, ?string $filterArt = null): string
                        {
                            if ($zugangJson === null) {
                                return '';
                            }
                            if ($zugangJson === '0') {
                                return 'Kein Zugang';
                            }

                            $zugaenge = getCurrentZugaenge($zugangJson);
                            if (empty($zugaenge)) {
                                return '';
                            }

                            // Filtern nach Art, wenn angegeben
                            if ($filterArt !== null) {
                                $zugaenge = array_filter($zugaenge, function ($zugang) use ($filterArt) {
                                    return $zugang['art'] === $filterArt;
                                });

                                if (empty($zugaenge)) {
                                    return 'Keine Zugänge dieser Art';
                                }
                            }

                            usort($zugaenge, function ($a, $b) {
                                return [$a['art'], $a['ort'], $a['seite']] <=> [$b['art'], $b['ort'], $b['seite']];
                            });

                            $artNames = ['pvk' => 'PVK', 'zvk' => 'ZVK', 'io' => 'intraossär'];
                            $displays = [];

                            foreach ($zugaenge as $zugang) {
                                $artName   = $artNames[$zugang['art'] ?? ''] ?? ($zugang['art'] ?? '');
                                $groesse   = normalize_groesse_pretty($zugang['groesse'] ?? '');
                                $ort       = $zugang['ort']   ?? '';
                                $seite     = $zugang['seite'] ?? '';

                                $displays[] = sprintf('%s %s %s %s', $artName, $groesse, $ort, $seite);
                            }

                            return implode("\n", $displays);
                        }

                        function hasAnyZugang(?string $zugangJson): bool
                        {
                            $zugaenge = getCurrentZugaenge($zugangJson);
                            return !empty($zugaenge);
                        }
                        ?>
                        <div class="print__field-wrapper">
                            <textarea rows="8" style="resize:none" class="w-100 print__textbox" readonly><?= displayAllZugaengeText($daten['c_zugang'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <h6 class="print__heading">Atemwege</h6>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>
                                    <?php if ($daten['awsicherung_1'] == 1): ?>
                                        <input type="radio" name="atemwege_freimachen" checked disabled />
                                        <label for="atemwege_freimachen">Atemwege freimachen</label>
                                    <?php else : ?>
                                        <input type="radio" name="atemwege_freimachen" disabled />
                                        <label for="atemwege_freimachen">Atemwege freimachen</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['awsicherung_2'] == 1): ?>
                                        <input type="radio" name="absaugen" checked disabled />
                                        <label for="absaugen">Absaugen</label>
                                    <?php else : ?>
                                        <input type="radio" name="absaugen" disabled />
                                        <label for="absaugen">Absaugen</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if ($daten['entlastungspunktion'] == 1): ?>
                                        <input type="radio" name="entlastungspunktion" checked disabled />
                                        <label for="entlastungspunktion">Entlastungspunktion</label>
                                    <?php else : ?>
                                        <input type="radio" name="entlastungspunktion" disabled />
                                        <label for="entlastungspunktion">Entlastungspunktion</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['hws_immo'] == 1): ?>
                                        <input type="radio" name="hws_immo" checked disabled />
                                        <label for="hws_immo">HWS-Immobilisation</label>
                                    <?php else : ?>
                                        <input type="radio" name="hws_immo" disabled />
                                        <label for="hws_immo">HWS-Immobilisation</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <?php
                        $awsicherung_neu_labels = [
                            1 => 'keine',
                            2 => 'Endotrachealtubus',
                            3 => 'Larynxmaske',
                            4 => 'Guedeltubus',
                            5 => 'Larynxtubus',
                            6 => 'Wendl-Tubus',
                            99 => 'Sonstige'
                        ];
                        $b_beatmung_labels = [
                            1 => 'Spontanatmung',
                            2 => 'Assistierte Beatmung',
                            3 => 'Kontrollierte Beatmung',
                            4 => 'Maschinelle Beatmung',
                            5 => 'keine'
                        ];
                        ?>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>
                                    Intubation<br><span style="font-weight:600;font-size:11pt"><?= $awsicherung_neu_labels[$daten['awsicherung_neu'] ?? ''] ?? '' ?></span>
                                </td>
                                <td>
                                    Beatmung<br><span style="font-weight:600;font-size:11pt"><?= $b_beatmung_labels[$daten['b_beatmung'] ?? ''] ?? '' ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    O2-Gabe L/min<br><span style="font-weight:600;font-size:11pt"><?= $daten['o2gabe'] ?? '' ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <h6 class="print__heading">Weitere</h6>
                        <?php
                        $lagerung_labels = [
                            1 => 'OK Hochlagerung',
                            2 => 'Flachlagerung',
                            3 => 'Schocklagerung',
                            4 => 'stabile Seitenlage',
                            5 => 'sitzender Transport',
                            6 => 'keine',
                            99 => 'sonstige Lagerung'
                        ];
                        ?>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td colspan="2">
                                    Lagerung<br><span style="font-weight:600;font-size:11pt"><?= $lagerung_labels[$daten['lagerung'] ?? ''] ?? '' ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if ($daten['waerme_passiv'] == 1): ?>
                                        <input type="radio" name="waerme_passiv" checked disabled />
                                        <label for="waerme_passiv">passiver Wärmeerhalt</label>
                                    <?php else : ?>
                                        <input type="radio" name="waerme_passiv" disabled />
                                        <label for="waerme_passiv">passiver Wärmeerhalt</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['waerme_aktiv'] == 1): ?>
                                        <input type="radio" name="waerme_aktiv" checked disabled />
                                        <label for="waerme_aktiv">aktiver Wärmeerhalt</label>
                                    <?php else : ?>
                                        <input type="radio" name="waerme_aktiv" disabled />
                                        <label for="waerme_aktiv">aktiver Wärmeerhalt</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if ($daten['e_reposition'] == 1): ?>
                                        <input type="radio" name="e_reposition" checked disabled />
                                        <label for="e_reposition">Reposition</label>
                                    <?php else : ?>
                                        <input type="radio" name="e_reposition" disabled />
                                        <label for="e_reposition">Reposition</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['e_verband'] == 1): ?>
                                        <input type="radio" name="e_verband" checked disabled />
                                        <label for="e_verband">Verband</label>
                                    <?php else : ?>
                                        <input type="radio" name="e_verband" disabled />
                                        <label for="e_verband">Verband</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if ($daten['e_kuehlung'] == 1): ?>
                                        <input type="radio" name="e_kuehlung" checked disabled />
                                        <label for="e_kuehlung">Kühlung</label>
                                    <?php else : ?>
                                        <input type="radio" name="e_kuehlung" disabled />
                                        <label for="e_kuehlung">Kühlung</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['e_tourniquet'] == 1): ?>
                                        <input type="radio" name="e_tourniquet" checked disabled />
                                        <label for="e_tourniquet">Tourniquet</label>
                                    <?php else : ?>
                                        <input type="radio" name="e_tourniquet" disabled />
                                        <label for="e_tourniquet">Tourniquet</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <table class="w-100 print__text-small">
                            <tr>
                                <td colspan="2">
                                    <?php if ($daten['e_krintervention'] == 1): ?>
                                        <input type="radio" name="e_krintervention" checked disabled />
                                        <label for="e_krintervention">Krisenintervention</label>
                                    <?php else : ?>
                                        <input type="radio" name="e_krintervention" disabled />
                                        <label for="e_krintervention">Krisenintervention</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if ($daten['e_narkose'] == 1): ?>
                                        <input type="radio" name="e_narkose" checked disabled />
                                        <label for="e_narkose">Notfallnarkose</label>
                                    <?php else : ?>
                                        <input type="radio" name="e_narkose" disabled />
                                        <label for="e_narkose">Notfallnarkose</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['e_cpr'] == 1): ?>
                                        <input type="radio" name="e_cpr" checked disabled />
                                        <label for="e_cpr">CPR / HLW</label>
                                    <?php else : ?>
                                        <input type="radio" name="e_cpr" disabled />
                                        <label for="e_cpr">CPR / HLW</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <h6 class="print__heading">Rettungstechnik</h6>
                        <?php
                        $rettungstechnikLabels = [
                            1 => 'Spineboard',
                            2 => 'KED-System',
                            3 => 'Beckenschlinge',
                            4 => 'Schaufeltrage',
                            5 => 'Vakuummatratze',
                            6 => 'SAMsplint',
                            99 => 'sonstige Immobilisation'
                        ];

                        $rettungstechnikDisplayTexts = [];
                        if (!empty($rettungstechnik)) {
                            foreach ($rettungstechnik as $value) {
                                if (isset($rettungstechnikLabels[$value])) {
                                    $rettungstechnikDisplayTexts[] = $rettungstechnikLabels[$value];
                                }
                            }
                        }

                        $rettungstechnikDisplay = !empty($rettungstechnikDisplayTexts) ? implode(', ', $rettungstechnikDisplayTexts) : 'keine';
                        ?>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>
                                    <?= $rettungstechnikDisplay ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php if ($daten['prot_by'] != 1): ?>
                    <div class="row border border-dark border-top-0">
                        <div class="col">
                            <h6 class="print__heading">Nachforderung Notarzt</h6>
                            <table class="w-100 print__text-small">
                                <tr>
                                    <td>
                                        <?php if ($daten['na_nachf'] == 1): ?>
                                            <input type="radio" name="na_nachf_nein" checked disabled />
                                            <label for="na_nachf_nein">nein</label>
                                        <?php else : ?>
                                            <input type="radio" name="na_nachf_nein" disabled />
                                            <label for="na_nachf_nein">nein</label>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($daten['na_nachf'] == 2): ?>
                                            <input type="radio" name="na_nachf_ja" checked disabled />
                                            <label for="na_nachf_ja">ja</label>
                                        <?php else : ?>
                                            <input type="radio" name="na_nachf_ja" disabled />
                                            <label for="na_nachf_ja">ja</label>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <h6 class="print__heading">Beteiligte Einsatzmittel</h6>
                        <div class="print__field-wrapper" data-field-name="Bet. EM">
                            <input type="text" class="w-100 print__field" value="<?= $daten['fzg_sonst'] ?>" readonly>
                        </div>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <?php
                        $uebergabe_ort_labels = [
                            1 => 'Schockraum',
                            2 => 'Praxis',
                            3 => 'ZNA / INA',
                            4 => 'Stroke Unit',
                            5 => 'Intensivstation',
                            6 => 'OP direkt',
                            7 => 'Hausarzt',
                            8 => 'Fachambulanz',
                            9 => 'Chest Pain Unit',
                            10 => 'HKL',
                            11 => 'Allgemeinstation',
                            12 => 'Einsatzstelle',
                            99 => 'Sonstige'
                        ];

                        $uebergabe_an_labels = [
                            1 => 'Arzt',
                            2 => 'Pflegepersonal',
                            3 => 'Rettungssanitäter',
                            4 => 'Rettungsassistent',
                            5 => 'Notfallsanitäter',
                            6 => 'Polizei',
                            7 => 'Angehörige',
                            99 => 'Sonstige'
                        ];

                        $uebergabe_an_text = '';
                        if (isset($daten['uebergabe_an']) && !empty($daten['uebergabe_an'])) {
                            $uebergabe_an_text = $uebergabe_an_labels[$daten['uebergabe_an'] ?? ''] ?? '&nbsp';
                        }

                        $uebergabe_ort_text = '';
                        if (isset($daten['uebergabe_ort']) && !empty($daten['uebergabe_ort'])) {
                            $uebergabe_ort_text = $uebergabe_ort_labels[$daten['uebergabe_ort'] ?? ''] ?? '&nbsp';
                        }
                        ?>
                        <h6 class="print__heading">Übergabe an</h6>
                        <span style="font-weight:600;font-size:11pt"><?= $uebergabe_an_text ?></span>
                        <h6 class="print__heading">Übergabeort</h6>
                        <span style="font-weight:600;font-size:11pt"><?= $uebergabe_ort_text ?></span>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <h6 class="print__heading">Protokollant / Unterschrift</h6>
                        <span style="font-size:11pt;font-family: 'Freehand', sans-serif"><?= $daten['pfname'] ?? '&nbsp' ?></span>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col text-end fw-bold print__text-small">
                        erstellt mit ıgnıs von EmergencyForge
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($groupedVitals)): ?>
        <script>
            // Chart nach dem Laden der Seite erstellen
            window.addEventListener('load', function() {
                const ctx = document.getElementById('vitalChart');
                if (!ctx) return;

                const chartLabels = <?= json_encode($chartLabels) ?>;
                const chartData = <?= json_encode($chartData) ?>;

                // Custom Point Styles Plugin
                const customPointStyles = {
                    id: 'customPointStyles',
                    afterDatasetsDraw(chart) {
                        const ctx = chart.ctx;

                        chart.data.datasets.forEach((dataset, datasetIndex) => {
                            const meta = chart.getDatasetMeta(datasetIndex);

                            meta.data.forEach((point, index) => {
                                if (dataset.data[index] === null) return;

                                const x = point.x;
                                const y = point.y;
                                const size = 6;

                                ctx.save();
                                ctx.fillStyle = 'black';
                                ctx.strokeStyle = 'black';
                                ctx.lineWidth = 2;

                                // Verschiedene Symbole für verschiedene Parameter
                                switch (dataset.label) {
                                    case 'SpO₂': // Kreis gefüllt
                                        ctx.beginPath();
                                        ctx.arc(x, y, size, 0, Math.PI * 2);
                                        ctx.fill();
                                        break;

                                    case 'HF': // Quadrat gefüllt
                                        ctx.fillRect(x - size, y - size, size * 2, size * 2);
                                        break;

                                    case 'RRsys': // Dreieck gefüllt
                                        ctx.beginPath();
                                        ctx.moveTo(x, y - size);
                                        ctx.lineTo(x - size, y + size);
                                        ctx.lineTo(x + size, y + size);
                                        ctx.closePath();
                                        ctx.fill();
                                        break;

                                    case 'RRdia':
                                        ctx.beginPath();
                                        ctx.moveTo(x, y + size);
                                        ctx.lineTo(x - size, y - size);
                                        ctx.lineTo(x + size, y - size);
                                        ctx.closePath();
                                        ctx.fill();
                                        break;

                                    case 'AF': // Raute gefüllt
                                        ctx.beginPath();
                                        ctx.moveTo(x, y - size);
                                        ctx.lineTo(x + size, y);
                                        ctx.lineTo(x, y + size);
                                        ctx.lineTo(x - size, y);
                                        ctx.closePath();
                                        ctx.fill();
                                        break;

                                    case 'Temp': // Kreis leer
                                        ctx.beginPath();
                                        ctx.arc(x, y, size, 0, Math.PI * 2);
                                        ctx.stroke();
                                        break;

                                    case 'BZ': // Stern
                                        const spikes = 5;
                                        const outerRadius = size;
                                        const innerRadius = size / 2;

                                        ctx.beginPath();
                                        for (let i = 0; i < spikes * 2; i++) {
                                            const radius = i % 2 === 0 ? outerRadius : innerRadius;
                                            const angle = (Math.PI / spikes) * i - Math.PI / 2;
                                            const px = x + Math.cos(angle) * radius;
                                            const py = y + Math.sin(angle) * radius;

                                            if (i === 0) {
                                                ctx.moveTo(px, py);
                                            } else {
                                                ctx.lineTo(px, py);
                                            }
                                        }
                                        ctx.closePath();
                                        ctx.fill();
                                        break;

                                    case 'etCO₂': // Plus
                                        ctx.beginPath();
                                        ctx.moveTo(x - size, y);
                                        ctx.lineTo(x + size, y);
                                        ctx.moveTo(x, y - size);
                                        ctx.lineTo(x, y + size);
                                        ctx.stroke();
                                        break;
                                }

                                ctx.restore();
                            });
                        });
                    }
                };

                new Chart(ctx, {
                    type: 'line',
                    plugins: [customPointStyles],
                    data: {
                        labels: chartLabels,
                        datasets: [{
                                label: 'SpO₂',
                                data: chartData.spo2,
                                borderColor: 'black',
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                borderDash: [],
                                yAxisID: 'y',
                                tension: 0.3,
                                pointStyle: false,
                                spanGaps: true
                            },
                            {
                                label: 'HF',
                                data: chartData.herzfreq,
                                borderColor: 'black',
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                yAxisID: 'y1',
                                tension: 0.3,
                                pointStyle: false,
                                spanGaps: true
                            },
                            {
                                label: 'RRsys',
                                data: chartData.rrsys,
                                borderColor: 'black',
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                borderDash: [10, 2, 2, 2],
                                yAxisID: 'y1',
                                tension: 0.3,
                                pointStyle: false,
                                spanGaps: true
                            },
                            {
                                label: 'RRdia',
                                data: chartData.rrdias,
                                borderColor: 'black',
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                borderDash: [2, 2],
                                yAxisID: 'y1',
                                tension: 0.3,
                                pointStyle: false,
                                spanGaps: true
                            },
                            {
                                label: 'AF',
                                data: chartData.atemfreq,
                                borderColor: 'black',
                                backgroundColor: 'transparent',
                                borderWidth: 1.5,
                                borderDash: [],
                                yAxisID: 'y',
                                tension: 0.3,
                                pointStyle: false,
                                spanGaps: true
                            },
                            {
                                label: 'Temp',
                                data: chartData.temp,
                                borderColor: 'black',
                                backgroundColor: 'transparent',
                                borderWidth: 2.5,
                                borderDash: [8, 4],
                                yAxisID: 'y',
                                tension: 0.3,
                                pointStyle: false,
                                spanGaps: true
                            },
                            {
                                label: 'BZ',
                                data: chartData.bz,
                                borderColor: 'black',
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                borderDash: [15, 3, 3, 3],
                                yAxisID: 'y1',
                                tension: 0.3,
                                pointStyle: false,
                                spanGaps: true
                            },
                            {
                                label: 'etCO₂',
                                data: chartData.etco2,
                                borderColor: 'black',
                                backgroundColor: 'transparent',
                                borderWidth: 1.5,
                                borderDash: [3, 3],
                                yAxisID: 'y',
                                tension: 0.3,
                                pointStyle: false,
                                spanGaps: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        aspectRatio: 1.75,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: {
                                    color: 'black',
                                    font: {
                                        size: 9
                                    },
                                    padding: 15,
                                    boxWidth: 40,
                                    boxHeight: 2,
                                    usePointStyle: false,
                                    generateLabels: function(chart) {
                                        const datasets = chart.data.datasets;
                                        return datasets.map((dataset, i) => {
                                            // Symbol-Text für jeden Parameter
                                            const symbols = {
                                                'SpO₂': '●',
                                                'HF': '■',
                                                'RRsys': '▲',
                                                'RRdia': '▼',
                                                'AF': '◆',
                                                'Temp': '○',
                                                'BZ': '★',
                                                'etCO₂': '+'
                                            };

                                            // Einheit für jeden Parameter
                                            const units = {
                                                'SpO₂': '%',
                                                'HF': '/min',
                                                'RRsys': 'mmHg',
                                                'RRdia': 'mmHg',
                                                'AF': '/min',
                                                'Temp': '°C',
                                                'BZ': <?= json_encode($bzUnit) ?>,
                                                'etCO₂': 'mmHg'
                                            };

                                            // Linienstil-Beschreibung
                                            const lineStyles = {
                                                'SpO₂': 'durchgezogen',
                                                'HF': 'gestrichelt',
                                                'RRsys': 'Strich-Punkt',
                                                'RRdia': 'gepunktet',
                                                'AF': 'dünn',
                                                'Temp': 'gestrichelt dick',
                                                'BZ': 'lang-kurz',
                                                'etCO₂': 'klein gepunktet'
                                            };

                                            const label = dataset.label;
                                            const symbol = symbols[label] || '●';
                                            const unit = units[label] || '';
                                            const lineStyle = lineStyles[label] || '';

                                            return {
                                                text: `${symbol} ${label} (${unit})`,
                                                fillStyle: 'transparent',
                                                strokeStyle: 'black',
                                                lineWidth: dataset.borderWidth || 2,
                                                lineDash: dataset.borderDash || [],
                                                hidden: false,
                                                index: i
                                            };
                                        });
                                    }
                                }
                            },
                            tooltip: {
                                enabled: false
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    color: 'black',
                                    font: {
                                        size: 9
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)',
                                    drawBorder: true,
                                    borderColor: 'black',
                                    borderWidth: 2
                                },
                                title: {
                                    display: true,
                                    text: 'Zeit (Uhrzeit)',
                                    color: 'black',
                                    font: {
                                        size: 11,
                                        weight: 'bold'
                                    }
                                }
                            },
                            y: {
                                type: 'linear',
                                position: 'left',
                                min: 0,
                                max: 100,
                                ticks: {
                                    color: 'black',
                                    font: {
                                        size: 9,
                                        weight: 'bold'
                                    },
                                    stepSize: 10
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)',
                                    drawBorder: true,
                                    borderColor: 'black',
                                    borderWidth: 2
                                },
                                title: {
                                    display: true,
                                    text: 'SpO₂ / AF / etCO₂ / Temp (0-100)',
                                    color: 'black',
                                    font: {
                                        size: 10,
                                        weight: 'bold'
                                    }
                                }
                            },
                            y1: {
                                type: 'linear',
                                position: 'right',
                                min: 0,
                                max: <?= $bzUnit === 'mmol/l' ? 16.65 : 300 ?>,
                                ticks: {
                                    color: 'black',
                                    font: {
                                        size: 9,
                                        weight: 'bold'
                                    },
                                    stepSize: <?= $bzUnit === 'mmol/l' ? 1.665 : 30 ?>
                                },
                                grid: {
                                    display: false
                                },
                                title: {
                                    display: true,
                                    text: 'RR / HF / BZ (0-<?= $bzUnit === 'mmol/l' ? '16.65' : '300' ?>)',
                                    color: 'black',
                                    font: {
                                        size: 10,
                                        weight: 'bold'
                                    }
                                }
                            }
                        }
                    }
                });
            });
        </script>
    <?php endif; ?>
    <script>
        function calculateAge(birthDateString) {
            const birthDate = new Date(birthDateString);
            const today = new Date();

            if (isNaN(birthDate)) return 0;

            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();

            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }

            return age >= 0 ? age : 0;
        }

        function updateAge() {
            const birthDateValue = document.getElementById('patgebdat').dataset.date;
            const age = calculateAge(birthDateValue);
            document.getElementById('_AGE_').value = age;
        }

        document.addEventListener('DOMContentLoaded', updateAge);
    </script>
    <script>
        let currentZoom = 1;

        function zoomIn() {
            if (currentZoom < 2) {
                currentZoom += 0.1;
                applyZoom();
            }
        }

        function zoomOut() {
            if (currentZoom > 0.5) {
                currentZoom -= 0.1;
                applyZoom();
            }
        }

        function applyZoom() {
            const papers = document.querySelectorAll('.print__paper');

            papers.forEach(paper => {
                let wrapper = paper.parentElement;
                if (!wrapper.classList.contains('zoom-wrapper')) {
                    wrapper = document.createElement('div');
                    wrapper.className = 'zoom-wrapper';
                    paper.parentNode.insertBefore(wrapper, paper);
                    wrapper.appendChild(paper);
                }

                paper.style.transform = `scale(${currentZoom})`;
                paper.style.transformOrigin = 'top center';

                const naturalHeight = 297; // mm
                const scaledHeight = naturalHeight * currentZoom;

                wrapper.style.height = `${scaledHeight}mm`;
                wrapper.style.marginBottom = '20px';
                wrapper.style.overflow = 'visible';
                wrapper.style.display = 'flex';
                wrapper.style.justifyContent = 'center';
            });

            document.body.style.overflowX = currentZoom > 1 ? 'auto' : 'visible';
        }

        function isInIframe() {
            try {
                return window.self !== window.top;
            } catch (e) {
                return true;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (isInIframe()) {
                const printButtons = document.querySelectorAll('.topbar-ignis-btn[onclick*="print"]');
                printButtons.forEach(ignis-btn => {
                    ignis-btn.style.display = 'none';
                });
            }

            applyZoom();
        });

        window.addEventListener('beforeprint', function() {
            const papers = document.querySelectorAll('.print__paper');
            papers.forEach(paper => {
                const wrapper = paper.parentElement;
                if (wrapper && wrapper.classList.contains('zoom-wrapper')) {
                    wrapper.parentNode.insertBefore(paper, wrapper);
                    wrapper.remove();
                }
                paper.style.transform = '';
                paper.style.transformOrigin = '';
            });
            document.body.style.overflowX = '';
        });
        window.addEventListener('afterprint', function() {
            applyZoom();
        });
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>