<?php
/**
 * View: enotf/protokoll/rettdaten/index.php
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

// Adresse aus JSON dekodieren für Anzeige im Format: Straße HNR, Ort-Ortsteil
$transp_display = '';
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

    $transp_display = implode(', ', $parts);
}

// Ziel-Adresse aus JSON dekodieren für Anzeige
$ziel_display = '';
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

    $ziel_display = implode(', ', $parts);
}
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

<body data-bs-theme="dark" data-page="stammdaten" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include __DIR__ . '/../../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-full">
                <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content">
                    <div class="row">
                        <div class="col">
                            <div class="row shadow edivi__box">
                                <h5 class="text-light px-2 py-1">Patientendaten</h5>
                                <div class="col">
                                    <div class="row my-2">
                                        <div class="col">
                                            <label for="pat_vorname" class="edivi__description">Vorname</label>
                                            <input type="text" name="pat_vorname" id="pat_vorname" placeholder="Max" class="w-100 form-control" value="<?= htmlspecialchars($daten['pat_vorname'] ?? '') ?>">
                                        </div>
                                        <div class="col">
                                            <label for="pat_nachname" class="edivi__description">Nachname</label>
                                            <input type="text" name="pat_nachname" id="pat_nachname" placeholder="Mustermann" class="w-100 form-control" value="<?= htmlspecialchars($daten['pat_nachname'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="row my-2">
                                        <div class="col">
                                            <label for="patsex" class="edivi__description">Geschlecht</label>
                                            <?php
                                            if ($daten['patsex'] === NULL) {
                                            ?>
                                                <select name="patsex" id="patsex" class="w-100 form-select edivi__input-check" required data-custom-dropdown="true">
                                                    <option disabled hidden selected>---</option>
                                                    <option value="0">männlich</option>
                                                    <option value="1">weiblich</option>
                                                    <option value="2">divers</option>
                                                    <option value="9">unbekannt</option>
                                                </select>
                                            <?php
                                            } else {
                                            ?>
                                                <select name="patsex" id="patsex" class="w-100 form-select edivi__input-check" required autocomplete="off" data-custom-dropdown="true">
                                                    <option disabled hidden selected>---</option>
                                                    <option value="0" <?php echo ($daten['patsex'] == 0 ? 'selected' : '') ?>>männlich</option>
                                                    <option value="1" <?php echo ($daten['patsex'] == 1 ? 'selected' : '') ?>>weiblich</option>
                                                    <option value="2" <?php echo ($daten['patsex'] == 2 ? 'selected' : '') ?>>divers</option>
                                                    <option value="9" <?php echo ($daten['patsex'] == 9 ? 'selected' : '') ?>>unbekannt</option>
                                                </select>
                                            <?php
                                            }
                                            ?>
                                        </div>
                                        <div class="col">
                                            <label for="patgebdat" class="edivi__description">Geburtsdatum</label>
                                            <input type="date" name="patgebdat" id="patgebdat" class="w-100 form-control" value="<?= !empty($daten['patgebdat']) ? date('Y-m-d', strtotime($daten['patgebdat'])) : '' ?>">
                                        </div>
                                        <div class="col-2">
                                            <label for="_AGE_" class="edivi__description">Alter</label>
                                            <input type="text" name="_AGE_" id="_AGE_" class="w-100 form-control" value="0" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row shadow edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'rettdaten', '1') ?>" style="cursor:pointer">
                                <h5 class="text-light px-2 py-1 edivi__group-check">Transport von / Einsatzort</h5>
                                <div class="col">
                                    <div class="row my-2">
                                        <div class="col">
                                            <label for="transp_poi_name" class="edivi__description">Von Einrichtung</label>
                                            <input type="text" name="transp_poi_name" id="transp_poi_name" class="w-100 form-control" value="<?= htmlspecialchars($daten['transp_poi'] ?? '') ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="row my-2">
                                        <div class="col">
                                            <label for="transp_display" class="edivi__description">Von Adresse</label>
                                            <input type="text" name="transp_display" id="transp_display" class="w-100 form-control edivi__input-check" value="<?= htmlspecialchars($transp_display) ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="row shadow edivi__box">
                                <h5 class="text-light px-2 py-1 edivi__group-check">Einsatzdaten</h5>
                                <div class="col">
                                    <div class="row my-2">
                                        <div class="col">
                                            <label for="enr" class="edivi__description">Einsatznummer</label>
                                            <input type="text" name="enr" id="enr" class="w-100 form-control" value="<?= $_GET['enr'] ?>" readonly>
                                        </div>
                                        <div class="col">
                                            <label for="edatum" class="edivi__description">Einsatzdatum</label>
                                            <input type="date" name="edatum" id="edatum" class="w-100 form-control edivi__input-check" value="<?= !empty($daten['edatum']) ? date('Y-m-d', strtotime($daten['edatum'])) : '' ?>" required>
                                        </div>
                                        <div class="col">
                                            <label for="ezeit" class="edivi__description">Einsatzzeit</label>
                                            <input type="time" name="ezeit" id="ezeit" class="w-100 form-control edivi__input-check" value="<?= $daten['ezeit'] ?>" required>
                                        </div>
                                    </div>
                                    <div class="row my-2">
                                        <div class="col-6">
                                            <label for="transportziel" class="edivi__description">Versorgung</label>
                                            <select name="transportziel" id="transportziel" class="w-100 form-select edivi__input-check" required autocomplete="off" data-custom-dropdown="true" data-search-threshold="8">
                                                <option disabled hidden selected>---</option>
                                                <option value="1" <?php echo ($daten['transportziel'] == 1 ? 'selected' : '') ?>>ambulante Versorgung vor Ort</option>
                                                <option value="2" <?php echo ($daten['transportziel'] == 2 ? 'selected' : '') ?>>Transport ohne NA (oder mit TNA)</option>
                                                <option value="21" <?php echo ($daten['transportziel'] == 21 ? 'selected' : '') ?>>Transport mit NA (bodengebunden)</option>
                                                <option value="22" <?php echo ($daten['transportziel'] == 22 ? 'selected' : '') ?>>Transport mit NA (RTH)</option>
                                                <option value="3" <?php echo ($daten['transportziel'] == 3 ? 'selected' : '') ?>>Übergabe anderes Rettungsmittel</option>
                                                <option value="4" <?php echo ($daten['transportziel'] == 4 ? 'selected' : '') ?>>Fehleinsatz - kein Patient</option>
                                                <option value="5" <?php echo ($daten['transportziel'] == 5 ? 'selected' : '') ?>>Patient nicht transportfähig</option>
                                                <option value="6" <?php echo ($daten['transportziel'] == 6 ? 'selected' : '') ?>>primäre Todesfeststellung (ohne CPR)</option>
                                                <option value="99" <?php echo ($daten['transportziel'] == 99 ? 'selected' : '') ?>>Sonstige</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label for="eart" class="edivi__description">Einsatzart</label>
                                            <select name="eart" id="eart" class="w-100 form-select edivi__input-check" required autocomplete="off" data-custom-dropdown="true">
                                                <option disabled hidden selected>---</option>
                                                <option value="1" <?php echo ($daten['eart'] == 1 ? 'selected' : '') ?>>Notfallrettung - Primäreinsatz mit NA</option>
                                                <option value="11" <?php echo ($daten['eart'] == 11 ? 'selected' : '') ?>>Notfallrettung - Primäreinsatz ohne NA</option>
                                                <option value="2" <?php echo ($daten['eart'] == 2 ? 'selected' : '') ?>>Notfallrettung - Verlegung mit NA</option>
                                                <option value="21" <?php echo ($daten['eart'] == 21 ? 'selected' : '') ?>>Notfallrettung - Verlegung ohne NA</option>
                                                <option value="3" <?php echo ($daten['eart'] == 3 ? 'selected' : '') ?>>Intensivtransport</option>
                                                <option value="4" <?php echo ($daten['eart'] == 4 ? 'selected' : '') ?>>Krankentransport</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row shadow edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'rettdaten', '2') ?>" style="cursor:pointer">
                                <h5 class="text-light px-2 py-1 edivi__group-check">Transportziel</h5>
                                <div class="col">
                                    <div class="row my-2">
                                        <div class="col">
                                            <label for="ziel_poi_name" class="edivi__description">Ziel Einrichtung</label>
                                            <input type="text" name="ziel_poi_name" id="ziel_poi_name" class="w-100 form-control" value="<?= htmlspecialchars($daten['ziel_poi'] ?? '') ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="row my-2">
                                        <div class="col">
                                            <label for="ziel_poi_adresse" class="edivi__description">Ziel Adresse</label>
                                            <input type="text" name="ziel_poi_adresse" id="ziel_poi_adresse" class="w-100 form-control edivi__input-check" value="<?= htmlspecialchars($ziel_display) ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="row shadow edivi__box">
                                <h5 class="text-light px-2 py-1 edivi__group-check">Zeiten</h5>
                                <div class="col">
                                    <div class="row my-2">
                                        <div class="col">
                                            <label for="salarm" class="edivi__description">
                                                Alarm
                                                <i id="icon-salarm" class="fa-solid fa-circle-exclamation" style="color:#d91425; <?= !empty($zeiten['salarm']) ? 'display:none;' : '' ?>"></i>
                                            </label>
                                            <input type="time" name="salarm" id="salarm" class="w-100 form-control text-center edivi__input-check" value="<?= !empty($zeiten['salarm']) ? date('H:i', strtotime($zeiten['salarm'])) : '' ?>" required>
                                            <input type="date" name="salarm_datum" id="salarm_datum" class="w-100 form-control mt-1 text-center edivi__input-check" style="font-size:1rem;color:#a2a2a2;" value="<?= !empty($zeiten['salarm']) ? date('Y-m-d', strtotime($zeiten['salarm'])) : '' ?>" required>
                                        </div>
                                        <div class="col">
                                            <label for="s3" class="edivi__description">aus (3)</label>
                                            <input type="time" name="s3" id="s3" class="w-100 form-control text-center" value="<?= !empty($zeiten['s3']) ? date('H:i', strtotime($zeiten['s3'])) : '' ?>" required>
                                            <input type="date" name="s3_datum" id="s3_datum" class="w-100 form-control mt-1 text-center" style="font-size:1rem;color:#a2a2a2;" value="<?= !empty($zeiten['s3']) ? date('Y-m-d', strtotime($zeiten['s3'])) : '' ?>" required>
                                        </div>
                                        <div class="col">
                                            <label for="s4" class="edivi__description">E.-an (4)</label>
                                            <input type="time" name="s4" id="s4" class="w-100 form-control text-center" value="<?= !empty($zeiten['s4']) ? date('H:i', strtotime($zeiten['s4'])) : '' ?>" required>
                                            <input type="date" name="s4_datum" id="s4_datum" class="w-100 form-control mt-1 text-center" style="font-size:1rem;color:#a2a2a2;" value="<?= !empty($zeiten['s4']) ? date('Y-m-d', strtotime($zeiten['s4'])) : '' ?>" required>
                                        </div>
                                        <div class="col">
                                            <label for="spat" class="edivi__description">
                                                Pat.-an
                                                <i id="icon-spat" class="fa-solid fa-circle-exclamation" style="color:#d91425; <?= !empty($zeiten['spat']) ? 'display:none;' : '' ?>"></i>
                                            </label>
                                            <input type="time" name="spat" id="spat" class="w-100 form-control text-center edivi__input-check" value="<?= !empty($zeiten['spat']) ? date('H:i', strtotime($zeiten['spat'])) : '' ?>" required>
                                            <input type="date" name="spat_datum" id="spat_datum" class="w-100 form-control mt-1 text-center edivi__input-check" style="font-size:1rem;color:#a2a2a2;" value="<?= !empty($zeiten['spat']) ? date('Y-m-d', strtotime($zeiten['spat'])) : '' ?>" required>
                                        </div>
                                        <div class="col">
                                            <label for="s7" class="edivi__description">
                                                E.-ab (7)
                                                <i id="icon-s7" class="fa-solid fa-circle-exclamation" style="color:#d91425; <?= !empty($zeiten['s7']) ? 'display:none;' : '' ?>"></i>
                                            </label>
                                            <input type="time" name="s7" id="s7" class="w-100 form-control text-center edivi__input-optional" value="<?= !empty($zeiten['s7']) ? date('H:i', strtotime($zeiten['s7'])) : '' ?>" required>
                                            <input type="date" name="s7_datum" id="s7_datum" class="w-100 form-control mt-1 text-center edivi__input-optional" style="font-size:1rem;color:#a2a2a2;" value="<?= !empty($zeiten['s7']) ? date('Y-m-d', strtotime($zeiten['s7'])) : '' ?>" required>
                                        </div>
                                        <div class="col">
                                            <label for="s8" class="edivi__description">
                                                KH an (8)
                                                <i id="icon-s8" class="fa-solid fa-circle-exclamation" style="color:#d91425; <?= !empty($zeiten['s8']) ? 'display:none;' : '' ?>"></i>
                                            </label>
                                            <input type="time" name="s8" id="s8" class="w-100 form-control text-center edivi__input-optional" value="<?= !empty($zeiten['s8']) ? date('H:i', strtotime($zeiten['s8'])) : '' ?>" required>
                                            <input type="date" name="s8_datum" id="s8_datum" class="w-100 form-control mt-1 text-center edivi__input-optional" style="font-size:1rem;color:#a2a2a2;" value="<?= !empty($zeiten['s8']) ? date('Y-m-d', strtotime($zeiten['s8'])) : '' ?>" required>
                                        </div>
                                        <div class="col">
                                            <label for="s1" class="edivi__description">frei (1)</label>
                                            <input type="time" name="s1" id="s1" class="w-100 form-control text-center" value="<?= !empty($zeiten['s1']) ? date('H:i', strtotime($zeiten['s1'])) : '' ?>" required>
                                            <input type="date" name="s1_datum" id="s1_datum" class="w-100 form-control mt-1 text-center" style="font-size:1rem;color:#a2a2a2;" value="<?= !empty($zeiten['s1']) ? date('Y-m-d', strtotime($zeiten['s1'])) : '' ?>" required>
                                        </div>
                                        <div class="col">
                                            <label for="s2" class="edivi__description">Wache (2)</label>
                                            <input type="time" name="s2" id="s2" class="w-100 form-control text-center" value="<?= !empty($zeiten['s2']) ? date('H:i', strtotime($zeiten['s2'])) : '' ?>" required>
                                            <input type="date" name="s2_datum" id="s2_datum" class="w-100 form-control mt-1 text-center" style="font-size:1rem;color:#a2a2a2;" value="<?= !empty($zeiten['s2']) ? date('Y-m-d', strtotime($zeiten['s2'])) : '' ?>" required>
                                        </div>
                                        <div class="col">
                                            <label for="sende" class="edivi__description">
                                                Ende
                                                <i id="icon-sende" class="fa-solid fa-circle-exclamation" style="color:#d91425; <?= !empty($zeiten['sende']) ? 'display:none;' : '' ?>"></i>
                                            </label>
                                            <input type="time" name="sende" id="sende" class="w-100 form-control  text-center edivi__input-check" value="<?= !empty($zeiten['sende']) ? date('H:i', strtotime($zeiten['sende'])) : '' ?>" required>
                                            <input type="date" name="sende_datum" id="sende_datum" class="w-100 form-control mt-1 text-center edivi__input-check" style="font-size:1rem;color:#a2a2a2;" value="<?= !empty($zeiten['sende']) ? date('Y-m-d', strtotime($zeiten['sende'])) : '' ?>" required>
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
    <script>
        function calculateAge(birthDateString) {
            if (!birthDateString) return 0;

            // <input type="date"> liefert YYYY-MM-DD; manuelle Eingabe ggf. DD.MM.YYYY
            let birthDate;
            if (/^\d{4}-\d{2}-\d{2}$/.test(birthDateString)) {
                const [y, mo, d] = birthDateString.split('-').map(Number);
                birthDate = new Date(y, mo - 1, d);
            } else {
                const parts = birthDateString.split('.');
                if (parts.length !== 3) return 0;
                birthDate = new Date(parseInt(parts[2]), parseInt(parts[1]) - 1, parseInt(parts[0]));
            }

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
            const el = document.getElementById('patgebdat');
            if (!el) return;
            const age = calculateAge(el.value);
            const ageEl = document.getElementById('_AGE_');
            if (ageEl) ageEl.value = age;
        }

        // Initial run + nachträglich (force-german-date.js läuft als defer-script,
        // konvertiert type=date→text und kann den Wert nach DOMContentLoaded nochmal
        // ändern). DOMContentLoaded reicht NICHT, weil es vor dem Reformat feuert.
        document.addEventListener('DOMContentLoaded', updateAge);
        window.addEventListener('load', updateAge);

        // Event-Delegation auf document, damit auch synthetisch dispatched Events
        // (von force-german-date.js nach blur-Format) zuverlässig gefangen werden.
        document.addEventListener('input', function (e) {
            if (e.target && e.target.id === 'patgebdat') updateAge();
        }, true);
        document.addEventListener('change', function (e) {
            if (e.target && e.target.id === 'patgebdat') updateAge();
        }, true);
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const zeitFelder = ['salarm', 's1', 's2', 's3', 's4', 'spat', 's7', 's8', 'sende'];
            const enr = <?= json_encode($enr) ?>;
            const _now = new Date();
            const heute = String(_now.getDate()).padStart(2, '0') + '.' + String(_now.getMonth() + 1).padStart(2, '0') + '.' + _now.getFullYear();

            function updateIcon(feld, hatWert) {
                const icon = document.getElementById('icon-' + feld);
                if (icon) {
                    icon.style.display = hatWert ? 'none' : 'inline';
                }
            }

            zeitFelder.forEach(feld => {
                const zeitInput = document.getElementById(feld);
                const datumInput = document.getElementById(feld + '_datum');

                if (zeitInput && datumInput) {
                    // Automatisches Setzen von Datum und Zeit beim Anklicken eines leeren Feldes
                    zeitInput.addEventListener('focus', function() {
                        if (!zeitInput.value) {
                            const now = new Date();
                            const hours = String(now.getHours()).padStart(2, '0');
                            const minutes = String(now.getMinutes()).padStart(2, '0');
                            zeitInput.value = hours + ':' + minutes;

                            if (!datumInput.value) {
                                datumInput.value = heute;
                            }
                        }
                    });

                    datumInput.addEventListener('focus', function() {
                        if (!datumInput.value) {
                            datumInput.value = heute;

                            if (!zeitInput.value) {
                                const now = new Date();
                                const hours = String(now.getHours()).padStart(2, '0');
                                const minutes = String(now.getMinutes()).padStart(2, '0');
                                zeitInput.value = hours + ':' + minutes;
                            }
                        }
                    });

                    zeitInput.addEventListener('input', function() {
                        if (zeitInput.value && !datumInput.value) {
                            datumInput.value = heute;
                        }
                    });

                    [zeitInput, datumInput].forEach(input => {
                        input.addEventListener('change', function() {
                            if (zeitInput.value && datumInput.value) {
                                // DD.MM.YYYY → YYYY-MM-DD für DB-Combined
                                const datumParts = datumInput.value.split('.');
                                const isoDatum = datumParts[2] + '-' + datumParts[1] + '-' + datumParts[0];
                                const combined = isoDatum + ' ' + zeitInput.value + ':00';

                                $.ajax({
                                    url: '<?= BASE_PATH ?>api/enotf/save-fields.php',
                                    type: 'POST',
                                    data: {
                                        enr: enr,
                                        field: feld,
                                        value: combined
                                    },
                                    success: function(response) {
                                        showToast("Feld gespeichert", 'success');
                                        window.__dynamicDaten[feld] = combined;
                                        updateIcon(feld, true);
                                    },
                                    error: function() {
                                        showToast("Fehler beim Speichern von '" + feld + "'", 'error');
                                    }
                                });
                            } else {
                                updateIcon(feld, false);
                            }
                        });
                    });
                }
            });
        });
    </script>
</body>

</html>