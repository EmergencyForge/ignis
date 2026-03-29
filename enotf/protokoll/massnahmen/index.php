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
require_once __DIR__ . '/../../../assets/functions/enotf/modular_redirect.php';
enotf_modular_redirect('massnahmen');
require_once __DIR__ . '/../../../assets/functions/enotf/zugang_helpers.php';

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

$prot_url = "https://" . SYSTEM_URL . "/enotf/protokoll/index.php?enr=" . $enr;

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';

$currentZugaenge = getCurrentZugaenge($daten['c_zugang'] ?? '');

// Local display functions with specific formatting for this page
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

function displayAllZugaenge($zugangJson)
{
    if (!isset($zugangJson) || $zugangJson === null) {
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

function displayAllZugaengeText($zugangJson)
{
    if (!isset($zugangJson) || $zugangJson === null) {
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

function displayZugaengeByArt($zugangJson, $filterArt = null)
{
    if (!isset($zugangJson) || $zugangJson === null) {
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
function displayZugaengeByArtText($zugangJson, $filterArt = null)
{
    if (!isset($zugangJson) || $zugangJson === null) {
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

function getCurrentMedikamente($medikamenteJson)
{
    if (empty($medikamenteJson) || $medikamenteJson === '0') {
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

function displayAllMedikamente($medikamenteJson)
{
    if (!isset($medikamenteJson) || $medikamenteJson === null) {
        return '';
    }

    if ($medikamenteJson === '0' || $medikamenteJson == '1') {
        return 'Keine Medikamente';
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

function hasAnyMedikamente($medikamenteJson)
{
    $medikamente = getCurrentMedikamente($medikamenteJson);
    return !empty($medikamente);
}

$rettungstechnik = [];
if (!empty($daten['rettungstechnik'])) {
    $decoded = json_decode($daten['rettungstechnik'], true);
    if (is_array($decoded)) {
        $rettungstechnik = array_map('intval', $decoded);
    }
}
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="massnahmen" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
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
                                <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/atemwege/index.php?enr=<?= $daten['enr'] ?>" data-requires="awsicherung_neu">
                                    <span>Atemwege</span>
                                </a>
                                <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/atmung/index.php?enr=<?= $daten['enr'] ?>" data-requires="b_beatmung">
                                    <span>Atmung</span>
                                </a>
                                <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/index.php?enr=<?= $daten['enr'] ?>" data-requires="c_zugang">
                                    <span>Zugang</span>
                                </a>
                                <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/medikamente/index.php?enr=<?= $daten['enr'] ?>" data-requires="medis">
                                    <span>Medikamente</span>
                                </a>
                                <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/weitere/index.php?enr=<?= $daten['enr'] ?>">
                                    <span>Weitere</span>
                                </a>
                            </div>
                        <?php endif;
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
                        $o2gabe_labels = [
                            0 => 'keine',
                            1 => '1 L/min',
                            2 => '2 L/min',
                            3 => '3 L/min',
                            4 => '4 L/min',
                            5 => '5 L/min',
                            6 => '6 L/min',
                            7 => '7 L/min',
                            8 => '8 L/min',
                            9 => '9 L/min',
                            10 => '10 L/min',
                            11 => '11 L/min',
                            12 => '12 L/min',
                            13 => '13 L/min',
                            14 => '14 L/min',
                            15 => '15 L/min'
                        ];
                        $lagerung_labels = [
                            1 => 'OK Hochlagerung',
                            2 => 'Flachlagerung',
                            3 => 'Schocklagerung',
                            4 => 'stabile Seitenlage',
                            5 => 'sitzender Transport',
                            6 => 'keine',
                            99 => 'sonstige Lagerung'
                        ];
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
                        if (!empty($rettungstechnik) && is_array($rettungstechnik)) {
                            foreach ($rettungstechnik as $value) {
                                if (isset($rettungstechnikLabels[$value])) {
                                    $rettungstechnikDisplayTexts[] = $rettungstechnikLabels[$value];
                                }
                            }
                        }

                        $rettungstechnikDisplay = !empty($rettungstechnikDisplayTexts) ? implode(', ', $rettungstechnikDisplayTexts) : '';
                        ?>
                        <div class="col edivi__overview-container">
                            <div class="row">
                                <div class="col">
                                    <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/atemwege/index.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                        <h5 class="text-light px-2 py-1 edivi__group-check">Atemwege</h5>
                                        <div class="col">
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="atemwegssicherung" class="edivi__description">Atemwegssicherung</label>
                                                    <input type="text" name="atemwegssicherung" id="atemwegssicherung" class="w-100 form-control edivi__input-check" value="<?= $awsicherung_neu_labels[$daten['awsicherung_neu'] ?? ''] ?? '' ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/atmung/index.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                        <h5 class="text-light px-2 py-1">Atmung</h5>
                                        <div class="col">
                                            <div class="row">
                                                <div class="col">
                                                    <div class="row my-2">
                                                        <div class="col">
                                                            <label for="beatmung" class="edivi__description">Beatmung</label>
                                                            <input type="text" name="beatmung" id="beatmung" class="w-100 form-control edivi__input-check" value="<?= $b_beatmung_labels[$daten['b_beatmung'] ?? ''] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="row my-2">
                                                        <div class="col">
                                                            <label for="o2gabe" class="edivi__description">O2-Gabe</label>
                                                            <input type="text" name="o2gabe" id="o2gabe" class="w-100 form-control" value="<?= $o2gabe_labels[$daten['o2gabe'] ?? ''] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/index.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                        <h5 class="text-light px-2 py-1">Zugänge <i id="icon-zugang_display" class="fa-solid fa-circle-exclamation" style="color:#d91425; margin-left:4px; display:none;"></i></h5>
                                        <input type="hidden" name="zugang_display" class="edivi__input-check" value="<?= $daten['c_zugang'] !== null ? $daten['c_zugang'] : '' ?>">
                                        <div class="col">
                                            <div class="row">
                                                <div class="col">
                                                    <div class="row my-2">
                                                        <div class="col">
                                                            <label for="pvk" class="edivi__description">PVK</label>
                                                            <textarea name="pvk" id="pvk" class="w-100 form-control" style="height: 200px; overflow-y: auto; resize: vertical;" readonly><?= displayZugaengeByArtText($daten['c_zugang'] ?? '', 'pvk') ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="row my-2">
                                                        <div class="col">
                                                            <label for="io" class="edivi__description">intraossär</label>
                                                            <textarea name="io" id="io" class="w-100 form-control" style="height: 200px; overflow-y: auto; resize: vertical;" readonly><?= displayZugaengeByArtText($daten['c_zugang'] ?? '', 'io') ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/medikamente/index.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                        <h5 class="text-light px-2 py-1 edivi__group-check">Medikamente <i id="icon-medikamente" class="fa-solid fa-circle-exclamation" style="color:#d91425; margin-left:4px; display:none;"></i></h5>
                                        <div class="col">
                                            <div class="row">
                                                <div class="col">
                                                    <div class="row my-2">
                                                        <div class="col">
                                                            <label for="medikamente" class="edivi__description" style="display: none;">Medikamente</label>
                                                            <textarea name="medikamente" id="medikamente" class="w-100 form-control edivi__input-check" style="height: 36vh; overflow-y: auto; resize: vertical;" readonly><?= displayAllMedikamente($daten['medis'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/weitere/index.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                        <h5 class="text-light px-2 py-1">Weitere</h5>
                                        <div class="col">
                                            <div class="row">
                                                <div class="col">
                                                    <div class="row my-2">
                                                        <div class="col">
                                                            <label for="lagerung" class="edivi__description">Lagerung</label>
                                                            <input type="text" name="lagerung" id="lagerung" class="w-100 form-control" value="<?= $lagerung_labels[$daten['lagerung'] ?? ''] ?? '' ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="row my-2">
                                                        <div class="col">
                                                            <label for="rettungstechnik" class="edivi__description">Rettungstechnik</label>
                                                            <input type="text" name="rettungstechnik" id="rettungstechnik" class="w-100 form-control" value="<?= $rettungstechnikDisplay ?>" readonly>
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
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>