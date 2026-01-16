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
require_once __DIR__ . '/../../../../assets/config/config.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';
require __DIR__ . '/../../../../assets/config/database.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/user_auth_middleware.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/pin_middleware.php';

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

function getCurrentZugaenge($zugangJson)
{
    if (empty($zugangJson) || $zugangJson === '0') {
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

function hasZugangAtLocation($zugaenge, $art, $ort, $seite)
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

function addZugang($zugaenge, $newZugang)
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

function removeZugang($zugaenge, $art, $ort, $seite)
{
    return array_values(array_filter($zugaenge, function ($zugang) use ($art, $ort, $seite) {
        return !($zugang['art'] === $art &&
            $zugang['ort'] === $ort &&
            $zugang['seite'] === $seite);
    }));
}

$currentZugaenge = getCurrentZugaenge($daten['c_zugang'] ?? '');

function hasAnyZugang($zugangJson)
{
    $zugaenge = getCurrentZugaenge($zugangJson);
    return !empty($zugaenge);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="massnahmen" data-pin-enabled="<?= $pinEnabled ?>">
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
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/atemwege/index.php?enr=<?= $daten['enr'] ?>" data-requires="awsicherung_neu">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/atmung/index.php?enr=<?= $daten['enr'] ?>" data-requires="b_beatmung">
                                <span>Atmung</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/index.php?enr=<?= $daten['enr'] ?>" data-requires="c_zugang" class="active">
                                <span>Zugang</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/medikamente/index.php?enr=<?= $daten['enr'] ?>" data-requires="medis">
                                <span>Medikamente</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/weitere/index.php?enr=<?= $daten['enr'] ?>">
                                <span>Weitere</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1.php?enr=<?= $daten['enr'] ?>" class="active">
                                <span>Zugang</span>
                            </a>
                            <input type="checkbox" class="btn-check" id="c_zugang-0" name="c_zugang" value="0"
                                <?php echo (isset($daten['c_zugang']) && $daten['c_zugang'] === '0') ? 'checked' : '' ?>
                                autocomplete="off">
                            <label for="c_zugang-0">Kein Zugang</label>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_1.php?enr=<?= $daten['enr'] ?>" class="active">
                                <span>PVK</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_2.php?enr=<?= $daten['enr'] ?>">
                                <span>intraossär</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_1_1.php?enr=<?= $daten['enr'] ?>">
                                <span>Handrücken</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_1_2.php?enr=<?= $daten['enr'] ?>">
                                <span>Unterarm</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_1_3.php?enr=<?= $daten['enr'] ?>">
                                <span>Ellbeuge</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_1_5.php?enr=<?= $daten['enr'] ?>">
                                <span>Oberarm</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_1_6.php?enr=<?= $daten['enr'] ?>">
                                <span>Hals</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_1_7.php?enr=<?= $daten['enr'] ?>">
                                <span>Kopf</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_1_8.php?enr=<?= $daten['enr'] ?>">
                                <span>Bein</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_1_4.php?enr=<?= $daten['enr'] ?>" class="active">
                                <span>Fuss</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_1_9.php?enr=<?= $daten['enr'] ?>">
                                <span>Sonstige</span>
                            </a>
                        </div>
                        <div class="col-1 d-flex flex-column edivi__interactbutton">
                            <label class="edivi__interactbutton-text">links</label>

                            <?php
                            $groessen = [
                                ['id' => '24G',       'label' => '24 G'],
                                ['id' => '22G',       'label' => '22 G'],
                                ['id' => '20G',       'label' => '20 G'],
                                ['id' => '18G',       'label' => '18 G'],
                                ['id' => '18G_kurz',  'label' => '18 G kurz'],
                                ['id' => '17G',       'label' => '17 G'],
                                ['id' => '16G',       'label' => '16 G'],
                                ['id' => '14G',       'label' => '14 G'],
                            ];

                            $currentLeftZugang = null;
                            foreach ($currentZugaenge as $zugang) {
                                if ($zugang['art'] === 'pvk' && $zugang['ort'] === 'Fuß' && $zugang['seite'] === 'links') {
                                    $currentLeftZugang = $zugang;
                                    break;
                                }
                            }

                            foreach ($groessen as $index => $size):
                                $idSlug   = $size['id'];
                                $labelTxt = $size['label'];
                                $inputId  = "c_zugang-pvk-fuss-l-" . ($index + 1);

                                $zugangData = [
                                    'art'     => 'pvk',
                                    'groesse' => $idSlug,
                                    'ort'     => 'Fuß',
                                    'seite'   => 'links'
                                ];

                                $isChecked = ($currentLeftZugang && ($currentLeftZugang['groesse'] ?? null) === $idSlug);
                            ?>
                                <input
                                    type="checkbox"
                                    class="btn-check zugang-checkbox"
                                    id="<?= $inputId ?>"
                                    name="zugang_selection"
                                    data-zugang='<?= htmlspecialchars(json_encode($zugangData), ENT_QUOTES) ?>'
                                    data-location="pvk-fuss-links"
                                    <?= $isChecked ? 'checked' : '' ?>
                                    autocomplete="off">
                                <label for="<?= $inputId ?>" class="edivi__zugang-<?= $idSlug ?>"><?= $labelTxt ?></label>
                            <?php endforeach; ?>

                        </div>
                        <div class="col-1 d-flex flex-column edivi__interactbutton">
                            <label class="edivi__interactbutton-text">rechts</label>

                            <?php
                            $currentRightZugang = null;
                            foreach ($currentZugaenge as $zugang) {
                                if ($zugang['art'] === 'pvk' && $zugang['ort'] === 'Fuß' && $zugang['seite'] === 'rechts') {
                                    $currentRightZugang = $zugang;
                                    break;
                                }
                            }

                            foreach ($groessen as $index => $size):
                                $idSlug   = $size['id'];
                                $labelTxt = $size['label'];
                                $inputId  = "c_zugang-pvk-fuss-r-" . ($index + 1);

                                $zugangData = [
                                    'art'     => 'pvk',
                                    'groesse' => $idSlug,
                                    'ort'     => 'Fuß',
                                    'seite'   => 'rechts'
                                ];

                                $isChecked = ($currentRightZugang && ($currentRightZugang['groesse'] ?? null) === $idSlug);
                            ?>
                                <input
                                    type="checkbox"
                                    class="btn-check zugang-checkbox"
                                    id="<?= $inputId ?>"
                                    name="zugang_selection"
                                    data-zugang='<?= htmlspecialchars(json_encode($zugangData), ENT_QUOTES) ?>'
                                    data-location="pvk-fuss-rechts"
                                    <?= $isChecked ? 'checked' : '' ?>
                                    autocomplete="off">
                                <label for="<?= $inputId ?>" class="edivi__zugang-<?= $idSlug ?>"><?= $labelTxt ?></label>
                            <?php endforeach; ?>

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
    <script>
        $(document).ready(function() {
            const normalInputs = $("form[name='form'] input:not([readonly]):not([disabled]):not(.zugang-checkbox), form[name='form'] select:not([readonly]):not([disabled]), form[name='form'] textarea:not([readonly]):not([disabled])");

            $('.zugang-checkbox').off('change blur').on('change', function(e) {
                e.stopPropagation();

                const $clicked = $(this);
                const location = $clicked.data('location');

                $(`.zugang-checkbox[data-location="${location}"]`).not($clicked).each(function() {
                    if ($(this).is(':checked')) {
                        $(this).prop('checked', false);
                    }
                });

                updateZugaenge();
            });

            $('#c_zugang-0').off('change').on('change', function() {
                if ($(this).is(':checked')) {
                    $('.zugang-checkbox').prop('checked', false);

                    $.ajax({
                        url: '<?= BASE_PATH ?>assets/functions/save_fields.php',
                        type: 'POST',
                        data: {
                            enr: '<?= $enr ?>',
                            field: 'c_zugang',
                            value: '0'
                        },
                        success: function(response) {
                            if (typeof window.__dynamicDaten !== 'undefined') {
                                window.__dynamicDaten['c_zugang'] = '0';

                                if (typeof updateNavFillStates === 'function') {
                                    updateNavFillStates(window.__dynamicDaten);
                                }
                                if (typeof validateLinks === 'function') {
                                    validateLinks();
                                }
                            }
                            if (typeof showToast === 'function') {
                                showToast('Alle Zugänge entfernt', 'success');
                            }
                        },
                        error: function(xhr) {
                            console.error('Fehler beim Entfernen:', xhr.responseText);
                            if (typeof showToast === 'function') {
                                showToast('Fehler beim Entfernen der Zugänge', 'error');
                            }
                        }
                    });
                } else {
                    $.ajax({
                        url: '<?= BASE_PATH ?>assets/functions/save_fields.php',
                        type: 'POST',
                        data: {
                            enr: '<?= $enr ?>',
                            field: 'c_zugang',
                            value: null
                        },
                        success: function(response) {
                            if (typeof window.__dynamicDaten !== 'undefined') {
                                window.__dynamicDaten['c_zugang'] = null;

                                if (typeof updateNavFillStates === 'function') {
                                    updateNavFillStates(window.__dynamicDaten);
                                }
                                if (typeof validateLinks === 'function') {
                                    validateLinks();
                                }
                            }
                            window.showToast("Zugang-Auswahl zurückgesetzt", 'success');
                        },
                        error: function(xhr) {
                            console.error('Fehler beim Zurücksetzen:', xhr.responseText);
                            window.showToast("Fehler beim Zurücksetzen", 'error');
                        }
                    });
                }
            });

            function updateZugaenge() {
                let currentPageZugaenge = [];
                $('.zugang-checkbox:checked').each(function() {
                    const zugangData = $(this).data('zugang');
                    currentPageZugaenge.push(zugangData);
                });

                let existingZugaenge = [];
                if (typeof window.__dynamicDaten !== 'undefined' && window.__dynamicDaten['c_zugang']) {
                    const currentData = window.__dynamicDaten['c_zugang'];
                    if (currentData !== '0' && currentData !== 0) {
                        try {
                            const parsed = JSON.parse(currentData);
                            if (Array.isArray(parsed)) {
                                existingZugaenge = parsed;
                            } else if (parsed && typeof parsed === 'object') {
                                existingZugaenge = [parsed];
                            }
                        } catch (e) {
                            console.log('Could not parse existing zugang data:', e);
                            existingZugaenge = [];
                        }
                    }
                }

                let currentPageInfo = null;

                currentPageInfo = {
                    art: 'pvk',
                    ort: 'Fuß'
                };

                let mergedZugaenge = existingZugaenge.filter(zugang => {
                    return !(zugang.art === currentPageInfo.art && zugang.ort === currentPageInfo.ort);
                });

                mergedZugaenge = mergedZugaenge.concat(currentPageZugaenge);

                if (mergedZugaenge.length > 0) {
                    $('#c_zugang-0').prop('checked', false);
                }

                let dbValue = mergedZugaenge.length === 0 ? '0' : JSON.stringify(mergedZugaenge);

                $.ajax({
                    url: '<?= BASE_PATH ?>assets/functions/save_fields.php',
                    type: 'POST',
                    data: {
                        enr: '<?= $enr ?>',
                        field: 'c_zugang',
                        value: dbValue
                    },
                    success: function(response) {
                        console.log('Zugänge gespeichert:', dbValue);

                        if (typeof window.__dynamicDaten !== 'undefined') {
                            window.__dynamicDaten['c_zugang'] = dbValue;

                            if (typeof updateNavFillStates === 'function') {
                                updateNavFillStates(window.__dynamicDaten);
                            }
                            if (typeof validateLinks === 'function') {
                                validateLinks();
                            }
                        }

                        if (typeof showToast === 'function') {
                            if (mergedZugaenge.length === 0) {
                                showToast("Zugang entfernt", 'success');
                            } else if (currentPageZugaenge.length > 0) {
                                const artNames = {
                                    'pvk': 'PVK',
                                    'zvk': 'ZVK',
                                    'io': 'i.o.'
                                };
                                const lastZugang = currentPageZugaenge[currentPageZugaenge.length - 1];
                                const artName = artNames[lastZugang.art] || lastZugang.art;
                                showToast(`${artName} ${lastZugang.groesse} an ${lastZugang.ort} ${lastZugang.seite} gespeichert`, 'success');
                            } else {
                                showToast("Zugang von dieser Stelle entfernt", 'success');
                            }
                        }
                    },
                    error: function(xhr) {
                        console.error('Fehler beim Speichern:', xhr.responseText);
                        if (typeof showToast === 'function') {
                            showToast('Fehler beim Speichern der Zugänge', 'error');
                        }
                    }
                });
            }
        });
    </script>
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