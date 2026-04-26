<?php
/**
 * View: enotf/protokoll/erstbefund/index.php
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

    if (!$daten) {
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

$psych = [];
if (!empty($daten['psych'])) {
    $decoded = json_decode($daten['psych'], true);
    if (is_array($decoded)) {
        $psych = array_map('intval', $decoded);
    }
}

$prot_url = "https://" . SYSTEM_URL . rtrim(EnotfUrl::protokoll($enr), '/');

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="erstbefund" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include __DIR__ . '/../../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="w-full" id="edivi__container">
            <div class="flex flex-wrap -mx-3 h-full">
                <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
                <div class="flex-1 px-3" id="edivi__content" style="padding-left: 0">
                    <div class="flex flex-wrap -mx-3" style="margin-left: 0">
                        <?php if (!$ist_freigegeben) : ?>
                            <div class="w-2/12 flex flex-col edivi__interactbutton-more px-3">
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'atemwege') ?>" data-requires="awfrei_1,zyanose_1">
                                    <span>Atemwege</span>
                                </a>
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'atmung') ?>" data-requires="b_symptome,b_auskult">
                                    <span>Atmung</span>
                                </a>
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'kreislauf') ?>" data-requires="c_kreislauf,c_puls_rad,c_puls_reg">
                                    <span>Kreislauf</span>
                                </a>
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'neurologie') ?>" data-requires="d_bewusstsein,d_ex_1,d_pupillenw_1,d_pupillenw_2,d_lichtreakt_1,d_lichtreakt_2,d_gcs_1,d_gcs_2,d_gcs_3">
                                    <span>Neurologie</span>
                                </a>
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'erweitern') ?>">
                                    <span>Erweitern</span>
                                </a>
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'ekg') ?>" data-requires="c_ekg">
                                    <span>EKG-Befund</span>
                                </a>
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'psychisch') ?>" data-requires="psych">
                                    <span>psych. Zustand</span>
                                </a>
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'messwerte') ?>" data-requires="spo2,atemfreq,rrsys,herzfreq,bz">
                                    <span>Messwerte</span>
                                </a>
                            </div>
                        <?php endif;
                        $awfrei_labels = [
                            1 => 'frei',
                            2 => 'gefährdet',
                            3 => 'verlegt'
                        ];
                        $zyanose_labels = [
                            1 => 'Nein',
                            2 => 'Ja'
                        ];
                        $b_symptome_labels = [
                            0 => 'unauffällig',
                            1 => 'Dyspnoe',
                            2 => 'Apnoe',
                            3 => 'Schnappatmung',
                            4 => 'sonstige path. Atemmuster',
                            5 => 'Beatmung',
                            6 => 'Hyperventilation',
                            99 => 'nicht untersucht'
                        ];
                        $b_auskult_labels = [
                            0 => 'unauffällig',
                            1 => 'Spastik',
                            2 => 'Stridor',
                            3 => 'Rasselgeräusche',
                            4 => 'Andere Pathologien',
                            98 => 'nicht beurteilbar',
                            99 => 'nicht untersucht'
                        ];
                        $c_kreislauf_labels = [
                            1 => 'stabil',
                            2 => 'instabil',
                            3 => 'Nicht beurteilbar'
                        ];
                        $c_ekg_labels = [
                            1 => 'Sinusrhythmus',
                            2 => 'Infarkt EKG / STEMI',
                            21 => 'Schrittmacherrhythmus',
                            3 => 'Absolute Arrhythmie',
                            4 => 'Kammerflimmern,-flattern',
                            41 => 'Pulslose elektrische Aktivität',
                            5 => 'AV-Block II',
                            51 => 'AV-Block III',
                            6 => 'Astystolie',
                            7 => 'Linksschenkelblock',
                            71 => 'Rechtsschenkelblock',
                            97 => 'Sonstige',
                            98 => 'Nicht beurteilbar',
                            99 => 'Kein EKG'
                        ];
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
                        $d_ex_1_labels = [
                            1 => 'uneingeschränkt',
                            2 => 'leicht eingeschränkt',
                            3 => 'stark eingeschränkt',
                            99 => 'Nicht beurteilbar'
                        ];
                        $d_pupillenw_1_labels = [
                            1 => 'weit',
                            2 => 'mittel',
                            3 => 'eng',
                            4 => 'entrundet',
                            99 => 'Nicht untersucht'
                        ];
                        $d_lichtreakt_1_labels = [
                            1 => 'prompt',
                            2 => 'träge',
                            3 => 'keine',
                            99 => 'Nicht untersucht'
                        ];
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
                        $sz_toleranz_1_labels = [
                            1 => 'Tolerabel',
                            2 => 'Nicht tolerabel'
                        ];
                        $psychLabels = [
                            1 => 'unauffällig',
                            2 => 'aggressiv',
                            3 => 'depressiv',
                            4 => 'wahnhaft',
                            5 => 'verwirrt',
                            6 => 'verlangsamt',
                            7 => 'euphorisch',
                            8 => 'erregt',
                            9 => 'ängstlich',
                            10 => 'suizidal',
                            11 => 'motorisch unruhig',
                            12 => 'Sonstige',
                            98 => 'nicht beurteilbar',
                            99 => 'nicht untersucht'
                        ];
                        $c_puls_reg_labels = [
                            1 => 'regelmäßig',
                            2 => 'arrhythmisch'
                        ];
                        $c_puls_rad_labels = [
                            1 => 'tastbar',
                            2 => 'nicht tastbar'
                        ];
                        $c_rekap_labels = [
                            1 => '< 2 s',
                            2 => '> 2 s'
                        ];
                        $c_blutung_labels = [
                            1 => 'nein',
                            2 => 'ja'
                        ];

                        $psychDisplayTexts = [];
                        if (!empty($psych) && is_array($psych)) {
                            foreach ($psych as $value) {
                                if (isset($psychLabels[$value])) {
                                    $psychDisplayTexts[] = $psychLabels[$value];
                                }
                            }
                        }

                        $psychDisplay = !empty($psychDisplayTexts) ? implode(', ', $psychDisplayTexts) : '';
                        ?>
                        <div class="flex-1 edivi__overview-container px-3">
                            <div class="flex flex-wrap -mx-3">
                                <div class="flex-1 px-3">
                                    <div class="flex flex-wrap -mx-3 edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'atemwege') ?>" style="cursor:pointer">
                                        <h5 class="text-white px-2 py-1 edivi__group-check">Atemwege</h5>
                                        <div class="flex-1 px-3">
                                            <div class="flex flex-wrap -mx-3 my-2">
                                                <div class="flex-1 px-3">
                                                    <label for="atemwegszustand" class="edivi__description">Atemwegszustand</label>
                                                    <input type="text" name="atemwegszustand" id="atemwegszustand" class="w-full form-control edivi__input-check" value="<?= $awfrei_labels[$daten['awfrei_1'] ?? ''] ?? '' ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap -mx-3 my-2">
                                                <div class="flex-1 px-3">
                                                    <label for="zyanose" class="edivi__description">Zyanose</label>
                                                    <input type="text" name="zyanose" id="zyanose" class="w-full form-control edivi__input-check" value="<?= $zyanose_labels[$daten['zyanose_1'] ?? ''] ?? '' ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex-1 px-3">
                                    <div class="flex flex-wrap -mx-3 edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'atmung') ?>" style="cursor:pointer">
                                        <h5 class="text-white px-2 py-1 edivi__group-check">Atmung</h5>
                                        <div class="flex-1 px-3">
                                            <div class="flex flex-wrap -mx-3 my-2">
                                                <div class="flex-1 px-3">
                                                    <label for="beurteilung_atmung" class="edivi__description">Beurteilung Atmung</label>
                                                    <input type="text" name="beurteilung_atmung" id="beurteilung_atmung" class="w-full form-control edivi__input-check" value="<?= $b_symptome_labels[$daten['b_symptome'] ?? ''] ?? '' ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap -mx-3 my-2">
                                                <div class="flex-1 px-3">
                                                    <label for="auskultation" class="edivi__description">Auskultation</label>
                                                    <input type="text" name="auskultation" id="auskultation" class="w-full form-control edivi__input-check" value="<?= $b_auskult_labels[$daten['b_auskult'] ?? ''] ?? '' ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-wrap -mx-3">
                                <div class="flex-1 px-3">
                                    <div class="flex flex-wrap -mx-3 edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'kreislauf') ?>" style="cursor:pointer">
                                        <h5 class="text-white px-2 py-1 edivi__group-check">Kreislauf</h5>
                                        <div class="flex-1 px-3">
                                            <div class="flex flex-wrap -mx-3">
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="patientenzustand" class="edivi__description">Patientenzustand</label>
                                                            <input type="text" name="patientenzustand" id="patientenzustand" class="w-full form-control edivi__input-check" value="<?= $c_kreislauf_labels[$daten['c_kreislauf'] ?? ''] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="rekap" class="edivi__description">Rekap. Zeit</label>
                                                            <input type="text" name="rekap" id="rekap" class="w-full form-control edivi__input-check" value="<?= $c_rekap_labels[$daten['c_rekap'] ?? ''] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="ekgbefund" class="edivi__description">EKG-Befund</label>
                                                            <input type="text" name="ekgbefund" id="ekgbefund" class="w-full form-control edivi__input-check" value="<?= $c_ekg_labels[$daten['c_ekg'] ?? ''] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap -mx-3">
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="pulsregelmaessig" class="edivi__description">Pulsqualität</label>
                                                            <input type="text" name="pulsregelmaessig" id="pulsregelmaessig" class="w-full form-control edivi__input-check" value="<?= $c_puls_reg_labels[$daten['c_puls_reg'] ?? ''] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="radialispuls" class="edivi__description">Radialispuls</label>
                                                            <input type="text" name="radialispuls" id="radialispuls" class="w-full form-control edivi__input-check" value="<?= $c_puls_rad_labels[$daten['c_puls_rad'] ?? ''] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="blutung" class="edivi__description">starke Blutung</label>
                                                            <input type="text" name="blutung" id="blutung" class="w-full form-control edivi__input-check" value="<?= $c_blutung_labels[$daten['c_blutung'] ?? ''] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex-1 px-3">
                                    <div class="flex flex-wrap -mx-3 edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'neurologie') ?>" style="cursor:pointer">
                                        <h5 class="text-white px-2 py-1 edivi__group-check">Neurologie</h5>
                                        <div class="flex-1 px-3">
                                            <div class="flex flex-wrap -mx-3">
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="bewusstseinslage" class="edivi__description">Bewusstseinslage</label>
                                                            <input type="text" name="bewusstseinslage" id="bewusstseinslage" class="w-full form-control edivi__input-check" value="<?= $d_bewusstsein_labels[$daten['d_bewusstsein'] ?? ''] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="extremitaetenbewegung" class="edivi__description">Extremitätenbewegung</label>
                                                            <input type="text" name="extremitaetenbewegung" id="extremitaetenbewegung" class="w-full form-control edivi__input-check" value="<?= $d_ex_1_labels[$daten['d_ex_1'] ?? ''] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="w-4/12 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <?php
                                                            // GCS-Berechnung: nur anzeigen wenn alle drei Werte gesetzt sind
                                                            $gcs_value = '--';
                                                            if (
                                                                isset($daten['d_gcs_1']) && isset($daten['d_gcs_2']) && isset($daten['d_gcs_3']) &&
                                                                $daten['d_gcs_1'] !== null && $daten['d_gcs_2'] !== null && $daten['d_gcs_3'] !== null
                                                            ) {
                                                                $gcs_augen = 4 - $daten['d_gcs_1'];
                                                                $gcs_verbal = 5 - $daten['d_gcs_2'];
                                                                $gcs_motorik = 6 - $daten['d_gcs_3'];
                                                                $gcs_value = $gcs_augen + $gcs_verbal + $gcs_motorik;
                                                            }
                                                            ?>
                                                            <label for="_GCS_" class="edivi__description">GCS</label>
                                                            <input type="text" name="_GCS_" id="_GCS_" class="w-full form-control" value="<?= $gcs_value ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap -mx-3">
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="pupillenweite_li" class="edivi__description">Pupillenweite li</label>
                                                            <input type="text" name="pupillenweite_li" id="pupillenweite_li" class="w-full form-control edivi__input-check" value="<?= $d_pupillenw_1_labels[$daten['d_pupillenw_1'] ?? ''] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="pupillenweite_re" class="edivi__description">re</label>
                                                            <input type="text" name="pupillenweite_re" id="pupillenweite_re" class="w-full form-control edivi__input-check" value="<?= $d_pupillenw_1_labels[$daten['d_pupillenw_2'] ?? ''] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="lichtreaktion_li" class="edivi__description">Lichtreaktion li</label>
                                                            <input type="text" name="lichtreaktion_li" id="lichtreaktion_li" class="w-full form-control edivi__input-check" value="<?= $d_lichtreakt_1_labels[$daten['d_lichtreakt_1'] ?? ''] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="lichtreaktion_re" class="edivi__description">re</label>
                                                            <input type="text" name="lichtreaktion_re" id="lichtreaktion_re" class="w-full form-control edivi__input-check" value="<?= $d_lichtreakt_1_labels[$daten['d_lichtreakt_2'] ?? ''] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-wrap -mx-3">
                                <div class="w-8/12 px-3">
                                    <div class="flex flex-wrap -mx-3 edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'erweitern') ?>" style="cursor:pointer">
                                        <h5 class="text-white px-2 py-1 edivi__group-check">Verletzungen</h5>
                                        <div class="flex-1 px-3">
                                            <div class="flex flex-wrap -mx-3">
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="schädel_hirn" class="edivi__description">Schädel-Hirn</label>
                                                            <input type="text" name="schädel_hirn" id="schädel_hirn" class="w-full form-control edivi__input-check" value="<?= ($v_muster_k_labels[$daten['v_muster_k'] ?? ''] ?? '') . (!empty($daten['v_muster_k1']) ? ' / ' . ($v_muster_k1_labels[$daten['v_muster_k1'] ?? ''] ?? '') : '') ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="wirbelsaeule" class="edivi__description">Wirbelsäule</label>
                                                            <input type="text" name="wirbelsaeule" id="wirbelsaeule" class="w-full form-control edivi__input-check" value="<?= ($v_muster_k_labels[$daten['v_muster_w'] ?? ''] ?? '') . (!empty($daten['v_musterw1']) ? ' / ' . ($v_muster_k1_labels[$daten['v_muster_w1'] ?? ''] ?? '') : '') ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="thorax" class="edivi__description">Thorax</label>
                                                            <input type="text" name="thorax" id="thorax" class="w-full form-control edivi__input-check" value="<?= ($v_muster_k_labels[$daten['v_muster_t'] ?? ''] ?? '') . (!empty($daten['v_muster_t1']) ? ' / ' . ($v_muster_k1_labels[$daten['v_muster_t1'] ?? ''] ?? '') : '') ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="abdomen" class="edivi__description">Abdomen</label>
                                                            <input type="text" name="abdomen" id="abdomen" class="w-full form-control edivi__input-check" value="<?= ($v_muster_k_labels[$daten['v_muster_a'] ?? ''] ?? '') . (!empty($daten['v_muster_a1']) ? ' / ' . ($v_muster_k1_labels[$daten['v_muster_a1'] ?? ''] ?? '') : '') ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap -mx-3">
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="obere_extremitaeten" class="edivi__description">Obere Extremitäten</label>
                                                            <input type="text" name="obere_extremitaeten" id="obere_extremitaeten" class="w-full form-control edivi__input-check" value="<?= ($v_muster_k_labels[$daten['v_muster_al'] ?? ''] ?? '') . (!empty($daten['v_muster_al1']) ? ' / ' . ($v_muster_k1_labels[$daten['v_muster_al1'] ?? ''] ?? '') : '') ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="untere_extremitaeten" class="edivi__description">Untere Extremitäten</label>
                                                            <input type="text" name="untere_extremitaeten" id="untere_extremitaeten" class="w-full form-control edivi__input-check" value="<?= ($v_muster_k_labels[$daten['v_muster_bl'] ?? ''] ?? '') . (!empty($daten['v_musterbl1']) ? ' / ' . ($v_muster_k1_labels[$daten['v_muster_bl1'] ?? ''] ?? '') : '') ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex-1 px-3">
                                    <div class="flex flex-wrap -mx-3 edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'erweitern') ?>" style="cursor:pointer">
                                        <h5 class="text-white px-2 py-1">Schmerzen</h5>
                                        <div class="flex-1 px-3">
                                            <div class="flex flex-wrap -mx-3 my-2">
                                                <div class="flex-1 px-3">
                                                    <label for="intensitaet" class="edivi__description">Intensität</label>
                                                    <input type="text" name="intensitaet" id="intensitaet" class="w-full form-control" value="<?= $sz_nrs_labels[$daten['sz_nrs'] ?? ''] ?? '' ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap -mx-3 my-2">
                                                <div class="flex-1 px-3">
                                                    <label for="toleranz" class="edivi__description">Toleranz</label>
                                                    <input type="text" name="ekgbefund" id="ekgbefund" class="w-full form-control" value="<?= $sz_toleranz_1_labels[$daten['sz_toleranz_1'] ?? ''] ?? '' ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-wrap -mx-3">
                                <div class="w-4/12 px-3">
                                    <div class="flex flex-wrap -mx-3 edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'psychisch') ?>" style="cursor:pointer">
                                        <h5 class="text-white px-2 py-1 edivi__group-check">Psyche</h5>
                                        <div class="flex-1 px-3">
                                            <div class="flex flex-wrap -mx-3 my-2">
                                                <div class="flex-1 px-3">
                                                    <label for="psychischer_zustand" class="edivi__description">Psychischer Zustand</label>
                                                    <input type="text" name="psychischer_zustand" id="psychischer_zustand" class="w-full form-control edivi__input-check" value="<?= !empty($psychDisplay) ? htmlspecialchars($psychDisplay) : '' ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex-1 px-3">
                                    <div class="flex flex-wrap -mx-3 edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'messwerte') ?>" style="cursor:pointer">
                                        <h5 class="text-white px-2 py-1">Vitalparameter</h5>
                                        <div class="flex-1 px-3">
                                            <div class="flex flex-wrap -mx-3">
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="spo2" class="edivi__description">SpO<sub>2</sub></label>
                                                            <input type="text" name="spo2" id="spo2" class="w-full form-control edivi__input-check" value="<?= $daten['spo2'] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="af" class="edivi__description">AF</label>
                                                            <input type="text" name="af" id="af" class="w-full form-control edivi__input-check" value="<?= $daten['atemfreq'] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="etco2" class="edivi__description">etCO<sub>2</sub></label>
                                                            <input type="text" name="etco2" id="etco2" class="w-full form-control" value="<?= $daten['etco2'] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="hf" class="edivi__description">HF</label>
                                                            <input type="text" name="hf" id="hf" class="w-full form-control edivi__input-check" value="<?= $daten['herzfreq'] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="rrsys" class="edivi__description">RR<sub>sys</sub></label>
                                                            <input type="text" name="rrsys" id="rrsys" class="w-full form-control edivi__input-check" value="<?= $daten['rrsys'] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="rrdia" class="edivi__description">RR<sub>dia</sub></label>
                                                            <input type="text" name="rrdia" id="rrdia" class="w-full form-control" value="<?= $daten['rrdias'] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="bz" class="edivi__description">BZ</label>
                                                            <input type="text" name="bz" id="bz" class="w-full form-control edivi__input-check" value="<?= $daten['bz'] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1 px-3">
                                                    <div class="flex flex-wrap -mx-3 my-2">
                                                        <div class="flex-1 px-3">
                                                            <label for="temp" class="edivi__description">Temp</label>
                                                            <input type="text" name="temp" id="temp" class="w-full form-control" value="<?= $daten['temp'] ?? '' ?>" readonly>
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