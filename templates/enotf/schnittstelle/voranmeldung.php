<?php
/**
 * View: eNOTF Voranmeldung
 *
 * PIN-Lockscreen wird vom Controller geprüft.
 *
 * @var \PDO $pdo
 */

use App\Auth\Permissions;
use App\Helpers\EnotfUrl;
use App\Helpers\Redirects;
use App\Integrations\DiscordWebhook;

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

if ($ist_freigegeben || ENOTF_PREREG === false) {
    header("Location: " . EnotfUrl::protokoll($daten['enr']));
    exit();
}

$daten['last_edit'] = !empty($daten['last_edit']) ? (new DateTime($daten['last_edit']))->format('d.m.Y H:i') : NULL;

$enr = $daten['enr'];

$prot_url = "https://" . SYSTEM_URL . rtrim(EnotfUrl::protokoll($enr), '/');
$defaultUrl = EnotfUrl::protokoll($daten['enr']);

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('Y-m-d');

// LOGGING: Prüfe ob POST-Request
$logFile = __DIR__ . '/php_errors.log';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] POST-Request erhalten\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new']) && $_POST['new'] == "1") {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Verarbeite neue Voranmeldung...\n", FILE_APPEND);

    $requiredFields = [
        'priority',
        'arrival_date',
        'arrival_time',
        'fahrzeug',
        'diagnose',
        'kreislauf',
        'intubiert',
        'ziel'
    ];

    $allFilled = true;
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '' || $_POST[$field] === 'NULL') {
            $allFilled = false;
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Fehlendes Pflichtfeld: {$field}\n", FILE_APPEND);
            break;
        }
    }

    if ($allFilled) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Alle Pflichtfelder gefüllt, führe INSERT aus...\n", FILE_APPEND);
        // Datum normalisieren: DD.MM.YYYY → YYYY-MM-DD (falls Browser kein natives date-input hat)
        $rawDate = $_POST['arrival_date'];
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $rawDate, $m)) {
            $rawDate = $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }
        $arrivalDateTime = $rawDate . ' ' . $_POST['arrival_time'] . ':00';
        $stmt = $pdo->prepare("
            INSERT INTO intra_edivi_prereg (
                priority,
                arrival,
                fahrzeug,
                diagnose,
                geschlecht,
                `alter`,
                text,
                kreislauf,
                gcs,
                intubiert,
                ziel
            ) VALUES (
                :priority,
                :arrival,
                :fahrzeug,
                :diagnose,
                :geschlecht,
                :alter,
                :text,
                :kreislauf,
                :gcs,
                :intubiert,
                :ziel
            )
        ");

        $stmt->execute([
            'priority' => $_POST['priority'],
            'arrival' => $arrivalDateTime,
            'fahrzeug' => $_POST['fahrzeug'],
            'diagnose' => $_POST['diagnose'],
            'geschlecht' => (isset($daten['patsex']) && in_array($daten['patsex'], [0, 1, 2], false)) ? $daten['patsex'] : 9,
            'alter' => $_POST['_AGE_'] ?? NULL,
            'text' => $_POST['text'] ?? NULL,
            'kreislauf' => $_POST['kreislauf'],
            'gcs' => $_POST['_GCS_'] ?? NULL,
            'intubiert' => $_POST['intubiert'],
            'ziel' => $_POST['ziel']
        ]);

        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] INSERT ausgeführt\n", FILE_APPEND);

        // LOGGING: Voranmeldung wurde gespeichert
        $logFile = __DIR__ . '/../schnittstelle/php_errors.log';
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Voranmeldung gespeichert, ID: " . $pdo->lastInsertId() . "\n", FILE_APPEND);

        // Discord Webhook Benachrichtigung senden
        try {
            $preregId = $pdo->lastInsertId();

            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Discord Webhook wird vorbereitet...\n", FILE_APPEND);

            // Diagnose-Label holen
            $diagnose_labels = [
                1 => 'Schlaganfall / TIA / ICB',
                2 => 'ICB (klin. Diagn.)',
                3 => 'SAB (klin. Diagn.)',
                4 => 'Krampfanfall',
                5 => 'Status Epilepticus',
                6 => 'Meningitis / Encephalitis',
                9 => 'sonstige Erkrankung ZNS',
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
                51 => 'akutes Abdomen',
                52 => 'obere GI-Blutung',
                53 => 'untere GI-Blutung',
                54 => 'Gallenkolik',
                55 => 'Nierenkolik',
                56 => 'Kolik allgemein',
                59 => 'sonstige Erkrankung Abdomen',
                61 => 'psychischer Ausnahmezustand',
                62 => 'Depression',
                63 => 'Manie',
                64 => 'Intoxikation',
                65 => 'Entzug, Delir',
                66 => 'Suizidalität',
                67 => 'psychosoziale Krise',
                69 => 'sonstige Erkrankung Psychiatrie',
                71 => 'Hypoglykämie',
                72 => 'Hyperglykämie',
                73 => 'Urämie / ANV',
                74 => 'Exsikkose',
                75 => 'Hypothermie',
                76 => 'Hyperthermie',
                79 => 'sonstige Erkrankung Stoffwechsel',
                81 => 'Verbrennung',
                82 => 'Erfrierung',
                83 => 'Verätzung',
                84 => 'Inhalationstrauma',
                85 => 'Stromunfall',
                86 => 'Ertrinkungsunfall',
                87 => 'Tauchunfall',
                88 => 'Strangulation',
                89 => 'sonstige Vergiftung / Umwelt',
                91 => 'SHT',
                92 => 'Polytrauma',
                93 => 'Thoraxtrauma',
                94 => 'Bauchtrauma',
                95 => 'Extremitätentrauma',
                96 => 'Wirbelsäulentrauma',
                97 => 'Beckenfraktur',
                98 => 'andere traumatologische Verletzung',
                99 => 'sonstiges Trauma',
                101 => 'Wochenbett',
                102 => 'Schwangerschaft',
                103 => 'Geburt',
                104 => 'Fehlgeburt',
                105 => 'gynäkologische Blutung',
                109 => 'sonstige gynäkologische Erkrankung',
                111 => 'Neugeborenes (U1)',
                112 => 'Frühgeburt',
                113 => 'Fieberkrampf',
                114 => 'Pseudokrupp',
                115 => 'Epiglottitis',
                116 => 'Fremdkörperaspiration',
                119 => 'sonstige pädiatrische Erkrankung',
                999 => 'Sonstiges'
            ];

            // Diagnose: Wenn bereits Text, direkt verwenden, sonst mapping
            if (is_numeric($_POST['diagnose'])) {
                $diagnoseText = $diagnose_labels[$_POST['diagnose']] ?? 'Code ' . $_POST['diagnose'];
            } else {
                $diagnoseText = $_POST['diagnose'];
            }

            // Zielklinik auflösen
            $zielText = $_POST['ziel'];
            if (strpos($_POST['ziel'], 'poi_') === 0) {
                $poiId = str_replace('poi_', '', $_POST['ziel']);
                $poiStmt = $pdo->prepare("SELECT name FROM intra_edivi_pois WHERE id = ?");
                $poiStmt->execute([$poiId]);
                $poiData = $poiStmt->fetch();
                if ($poiData) {
                    $zielText = $poiData['name'];
                }
            }

            $preregData = [
                'id' => $preregId,
                'enr' => $daten['enr'],
                'priority' => $_POST['priority'],
                'arrival' => $arrivalDateTime,
                'fahrzeug' => $_POST['fahrzeug'],
                'diagnose' => $diagnoseText,
                'ziel' => $zielText,
                'intubiert' => $_POST['intubiert'],
                'kreislauf' => $_POST['kreislauf']
            ];

            // Domain-Event feuern — Listener dispatchen Discord-Webhook-Job
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Firing EnotfPreregistered event...\n", FILE_APPEND);
            app(\App\Events\EventDispatcher::class)->fire(
                new \App\Events\EnotfPreregistered($preregData)
            );
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Event fired\n", FILE_APPEND);
        } catch (\Throwable $e) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            \App\Logging\Logger::error('EnotfPreregistered: Event-Fire Fehler', [
                'error' => $e->getMessage(),
            ]);
        }

        Redirects::redirect($defaultUrl, []);
        exit();
    } else {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Pflichtfelder nicht alle gefüllt\n", FILE_APPEND);
        $formError = "Bitte füllen Sie alle Pflichtfelder korrekt aus.";
    }
}

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

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <div class="col" id="edivi__content">
                    <h2 class="text-center my-3">Klinik-Voranmeldung</h2>
                    <div class="row">
                        <div class="col">
                            <div class="row edivi__box p-2">
                                <div class="col">
                                    <div class="row">
                                        <div class="col">
                                            <label for="ziel" class="edivi__description">Zielklinik</label>
                                            <select name="ziel" id="ziel" class="w-100 form-select" required data-custom-dropdown="true" data-search-threshold="5">
                                                <option disabled hidden selected value="NULL">---</option>
                                                <?php
                                                require __DIR__ . '/../../../assets/config/database.php';

                                                // Nur POIs mit Typ "Krankenhaus" laden
                                                $stmt = $pdo->prepare("SELECT id, name FROM intra_edivi_pois WHERE typ = 'Krankenhaus' AND active = 1 ORDER BY name ASC");
                                                $stmt->execute();
                                                $krankenhaeuserPois = $stmt->fetchAll();

                                                // POI-Krankenhäuser mit poi_ prefix ausgeben
                                                foreach ($krankenhaeuserPois as $row) {
                                                    echo '<option value="poi_' . $row['id'] . '">' . htmlspecialchars($row['name']) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col">
                                            <div class="col">
                                                <label for="fahrzeug" class="edivi__description">Fahrzeug Transport</label>
                                                <?php
                                                require __DIR__ . '/../../../assets/config/database.php';

                                                $selectedFzg = $daten['fzg_transp'] ?? $daten['fzg_na'] ?? 'NULL';

                                                $stmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge ORDER BY priority ASC");
                                                $stmt->execute();
                                                $fahrzeuge = $stmt->fetchAll();
                                                ?>

                                                <select name="fahrzeug" id="fahrzeug" class="w-100 form-select" required data-custom-dropdown="true" data-search-threshold="5">
                                                    <option value="NULL" <?= $selectedFzg === 'NULL' ? 'selected' : '' ?>>Fzg. Transp.</option>
                                                    <?php foreach ($fahrzeuge as $row): ?>
                                                        <?php
                                                        $isSelected = $row['identifier'] === $selectedFzg;
                                                        $isActive = $row['active'] == 1;
                                                        ?>
                                                        <option value="<?= htmlspecialchars($row['name']) ?>"
                                                            <?= $isSelected ? 'selected' : '' ?>
                                                            <?= !$isActive ? 'disabled' : '' ?>>
                                                            <?= htmlspecialchars($row['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row edivi__box p-2 mt-2">
                                <div class="col">
                                    <div class="row">
                                        <div class="col">
                                            <label for="diagnose" class="edivi__description">Diagnose</label>
                                            <input type="text" name="diagnose" id="diagnose" class="w-100 form-control" maxlength="255" placeholder="..." value="<?= !empty($diagnose_haupt_text) ? $diagnose_haupt_text : '' ?>" readonly required>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col">
                                            <label for="text" class="edivi__description">Anmeldetext</label>
                                            <textarea name="text" id="text" rows="3" class="w-100 form-control" style="resize: none" placeholder="..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="row edivi__box p-2">
                                <div class="col">
                                    <div class="row">
                                        <div class="col">
                                            <label for="geschlecht" class="edivi__description">Geschlecht</label>
                                            <select name="geschlecht" id="geschlecht" class="w-100 form-select" readonly autocomplete="off" data-custom-dropdown="true">
                                                <option disabled hidden selected>---</option>
                                                <option value="0" <?php echo (isset($daten['patsex']) && $daten['patsex'] == 0 ? 'selected' : '') ?>>männlich</option>
                                                <option value="1" <?php echo ($daten['patsex'] == 1 ? 'selected' : '') ?>>weiblich</option>
                                                <option value="2" <?php echo ($daten['patsex'] == 2 ? 'selected' : '') ?>>divers</option>
                                            </select>
                                        </div>
                                        <div class="col">
                                            <label for="_AGE_" class="edivi__description">Alter</label>
                                            <input type="text" name="_AGE_" id="_AGE_" class="w-100 form-control" value="0" readonly>
                                            <input type="hidden" name="patgebdat" id="patgebdat" value="<?= $daten['patgebdat'] ?>">
                                        </div>
                                        <div class="col">
                                            <label for="_GCS_" class="edivi__description">GCS</label>
                                            <?php
                                            // GCS-Berechnung: nur anzeigen wenn alle drei Werte gesetzt sind
                                            $gcs_total = '';
                                            if (
                                                isset($daten['d_gcs_1']) && isset($daten['d_gcs_2']) && isset($daten['d_gcs_3']) &&
                                                $daten['d_gcs_1'] !== null && $daten['d_gcs_2'] !== null && $daten['d_gcs_3'] !== null
                                            ) {
                                                $gcs_augen = 4 - $daten['d_gcs_1'];
                                                $gcs_verbal = 5 - $daten['d_gcs_2'];
                                                $gcs_motorik = 6 - $daten['d_gcs_3'];
                                                $gcs_total = $gcs_augen + $gcs_verbal + $gcs_motorik;
                                            }
                                            ?>
                                            <input type="text" class="form-control" id="_GCS_" name="_GCS_" placeholder="--" value="<?= $gcs_total ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col">
                                            <label for="kreislauf" class="edivi__description">Kreislauf</label>
                                            <select name="kreislauf" id="kreislauf" class="w-100 form-select" required autocomplete="off" data-custom-dropdown="true">
                                                <option disabled hidden selected>---</option>
                                                <option value="1">stabil</option>
                                                <option value="0">instabil</option>
                                            </select>
                                        </div>
                                        <div class="col">
                                            <label for="intubiert" class="edivi__description">Intubiert</label>
                                            <select name="intubiert" id="intubiert" class="w-100 form-select" required autocomplete="off" data-custom-dropdown="true">
                                                <option disabled hidden selected>---</option>
                                                <option value="0">nein</option>
                                                <option value="1">ja</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row edivi__box p-2">
                                <div class="col">
                                    <div class="row">
                                        <div class="col">
                                            <?php
                                            $currentDate = date('Y-m-d');
                                            ?>
                                            <label for="arrival_date" class="edivi__description">Ankunftsdatum</label>
                                            <div class="input-group mb-2">
                                                <input type="date" name="arrival_date" id="arrival_date" class="form-control" value="<?= $currentDate ?>" required>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <label for="arrival_time" class="edivi__description">Ankunftszeit</label>
                                            <div class="input-group">
                                                <input type="time" name="arrival_time" id="arrival_time" class="form-control" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col">
                                            <label for="priority" class="edivi__description">Priorität</label>
                                            <select name="priority" id="priority" class="w-100 form-select" required autocomplete="off" data-custom-dropdown="true">
                                                <option disabled hidden selected>---</option>
                                                <option value="0">Nicht dringlich</option>
                                                <option value="1">Dringlich</option>
                                                <option value="2">Sofort</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="edivi__freigabe-buttons">
                        <div class="row">
                            <div class="col">
                                <a href="<?= Redirects::getRedirectUrl($defaultUrl); ?>">zurück</a>
                            </div>
                            <div class="col">
                                <button type="submit">versenden</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </form>
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
            const birthDateValue = document.getElementById('patgebdat').value;
            const age = calculateAge(birthDateValue);
            document.getElementById('_AGE_').value = age;
        }

        document.addEventListener('DOMContentLoaded', updateAge);
        document.getElementById('patgebdat').addEventListener('input', updateAge);
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>