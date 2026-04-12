<?php
/**
 * View: enotf/protokoll/massnahmen/zugang/1_2_1.php
 *
 * @var \PDO $pdo
 */

require_once __DIR__ . '/../../../../../assets/functions/enotf/zugang_helpers.php';

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

$currentZugaenge = getCurrentZugaenge($daten['c_zugang'] ?? '');
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="massnahmen" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include __DIR__ . '/../../../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <?php include __DIR__ . '/../../../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'atemwege') ?>" data-requires="awsicherung_neu">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'atmung') ?>" data-requires="b_beatmung">
                                <span>Atmung</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang') ?>" data-requires="c_zugang" class="active">
                                <span>Zugang</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'medikamente') ?>" data-requires="medis">
                                <span>Medikamente</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'weitere') ?>">
                                <span>Weitere</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang/1') ?>" class="active">
                                <span>Zugang</span>
                            </a>
                            <input type="checkbox" class="btn-check" id="c_zugang-0" name="c_zugang" value="0"
                                <?php echo (isset($daten['c_zugang']) && $daten['c_zugang'] === '0') ? 'checked' : '' ?>
                                autocomplete="off">
                            <label for="c_zugang-0">Kein Zugang</label>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang/1_1') ?>">
                                <span>PVK</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang/1_2') ?>" class="active">
                                <span>intraossär</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang/1_2_1') ?>" class="active">
                                <span>Tibia proximal</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang/1_2_3') ?>">
                                <span>Tibia distal</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang/1_2_2') ?>">
                                <span>Humerus proximal</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang/1_2_4') ?>">
                                <span>Sternum</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang/1_2_5') ?>">
                                <span>anderer Ort</span>
                            </a>
                        </div>
                        <div class="col-1 d-flex flex-column edivi__interactbutton">
                            <label class="edivi__interactbutton-text">links</label>

                            <?php
                            $groessen = ['15mm', '25mm', '45mm'];
                            $currentLeftZugang = null;

                            foreach ($currentZugaenge as $zugang) {
                                if ($zugang['art'] === 'io' && $zugang['ort'] === 'Tibia proximal' && $zugang['seite'] === 'links') {
                                    $currentLeftZugang = $zugang;
                                    break;
                                }
                            }

                            foreach ($groessen as $index => $groesse):
                                $radioId = "c_zugang-io-tibiaproximal-l-" . ($index + 1);
                                $zugangData = [
                                    'art' => 'io',
                                    'groesse' => $groesse,
                                    'ort' => 'Tibia proximal',
                                    'seite' => 'links'
                                ];
                                $isChecked = ($currentLeftZugang && $currentLeftZugang['groesse'] === $groesse);
                            ?>
                                <input type="checkbox" class="btn-check zugang-checkbox"
                                    id="<?= $radioId ?>"
                                    name="zugang_selection"
                                    data-zugang='<?= htmlspecialchars(json_encode($zugangData), ENT_QUOTES) ?>'
                                    data-location="io-tibiaproximal-links"
                                    <?= $isChecked ? 'checked' : '' ?>
                                    autocomplete="off">
                                <label for="<?= $radioId ?>" class="edivi__zugang-<?= $groesse ?>"><?= $groesse ?></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-1 d-flex flex-column edivi__interactbutton">
                            <label class="edivi__interactbutton-text">rechts</label>

                            <?php
                            $currentRightZugang = null;

                            foreach ($currentZugaenge as $zugang) {
                                if ($zugang['art'] === 'io' && $zugang['ort'] === 'Tibia proximal' && $zugang['seite'] === 'rechts') {
                                    $currentRightZugang = $zugang;
                                    break;
                                }
                            }

                            foreach ($groessen as $index => $groesse):
                                $radioId = "c_zugang-io-tibiaproximal-r-" . ($index + 1);
                                $zugangData = [
                                    'art' => 'io',
                                    'groesse' => $groesse,
                                    'ort' => 'Tibia proximal',
                                    'seite' => 'rechts'
                                ];
                                $isChecked = ($currentRightZugang && $currentRightZugang['groesse'] === $groesse);
                            ?>
                                <input type="checkbox" class="btn-check zugang-checkbox"
                                    id="<?= $radioId ?>"
                                    name="zugang_selection"
                                    data-zugang='<?= htmlspecialchars(json_encode($zugangData), ENT_QUOTES) ?>'
                                    data-location="io-tibiaproximal-rechts"
                                    <?= $isChecked ? 'checked' : '' ?>
                                    autocomplete="off">
                                <label for="<?= $radioId ?>" class="edivi__zugang-<?= $groesse ?>"><?= $groesse ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
    </form>
    <?php
    include __DIR__ . '/../../../../../assets/functions/enotf/notify.php';
    include __DIR__ . '/../../../../../assets/functions/enotf/field_checks.php';
    include __DIR__ . '/../../../../../assets/functions/enotf/clock.php';
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
                        url: '<?= BASE_PATH ?>api/enotf/save-fields.php',
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
                                if (typeof checkGroupStatus === 'function') {
                                    checkGroupStatus();
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
                        url: '<?= BASE_PATH ?>api/enotf/save-fields.php',
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

                let currentPageInfo = {
                    art: 'io',
                    ort: 'Tibia proximal'
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
                    url: '<?= BASE_PATH ?>api/enotf/save-fields.php',
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