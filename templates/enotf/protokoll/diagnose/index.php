<?php
/**
 * View: enotf/protokoll/diagnose/index.php
 *
 * @var \PDO $pdo
 */


use App\Auth\Permissions;

use App\Helpers\EnotfUrl;
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

$prot_url = "https://" . SYSTEM_URL . rtrim(EnotfUrl::protokoll($enr), '/');

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';

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
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="diagnose" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include __DIR__ . '/../../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <?php if (!$ist_freigegeben) : ?>
                            <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '1') ?>" data-requires="diagnose_haupt">
                                    <span>Diagnose (führend)</span>
                                </a>
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2') ?>">
                                    <span>Diagnose (weitere)</span>
                                </a>
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '3') ?>">
                                    <span>Diagnose Text</span>
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="col edivi__overview-container">
                            <div class="row">
                                <div class="col">
                                    <div class="row edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '1') ?>" style="cursor:pointer">
                                        <h5 class="text-light px-2 py-1 edivi__group-check">Diagnose (führend) <i id="icon-diagnose_fuehrend" class="fa-solid fa-circle-exclamation" style="color:#d91425; margin-left:4px; display:none;"></i></h5>
                                        <div class="col">
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="diagnose_fuehrend" class="edivi__description" style="display: none;">Diagnose (führend)</label>
                                                    <input type="text" name="diagnose_fuehrend" id="diagnose_fuehrend" class="w-100 form-control edivi__input-check" value="<?= !empty($diagnose_haupt_text) ? $diagnose_haupt_text : '' ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="row edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2') ?>" style="cursor:pointer">
                                        <h5 class="text-light px-2 py-1">Diagnose (weitere)</h5>
                                        <div class="col">
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="diagnose_weitere" class="edivi__description" style="display: none;">Diagnose (weitere)</label>
                                                    <input type="text" name="diagnose_weitere" id="diagnose_weitere" class="w-100 form-control" value="<?= !empty($diagnose_weitere_text) ? $diagnose_weitere_text : '' ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <div class="row edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '3') ?>" style="cursor:pointer">
                                        <h5 class="text-light px-2 py-1">Diagnose Text</h5>
                                        <div class="col">
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="diagnose_text" class="edivi__description" style="display: none;">Diagnose Text</label>
                                                    <textarea name="diagnose_text" id="diagnose_text" rows="5" class="w-100 form-control" style="resize: none" readonly><?= $daten['diagnose'] ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </form>
    <?php
    include __DIR__ . '/../../../../assets/functions/enotf/notify.php';
    include __DIR__ . '/../../../../assets/functions/enotf/field_checks.php';
    include __DIR__ . '/../../../../assets/functions/enotf/clock.php';
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
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>