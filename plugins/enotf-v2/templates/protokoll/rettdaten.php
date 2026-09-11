<?php

/**
 * Section: Rettungsdaten — eNOTF v2 im v1-Look.
 *
 * Drei Ansichten, alle als getreue Nachbauten der v1-Seiten
 * (plugins/enotf/templates/enotf/protokoll/rettdaten/):
 *
 *   ÜBERSICHT (ohne ?t): Eingabeseite wie v1 index.php — Patientendaten,
 *     Einsatzdaten, die beiden klickbaren Adress-Kacheln und die
 *     Statuszeiten-Leiste (Klick in ein leeres Zeitfeld übernimmt die
 *     aktuelle Uhrzeit, Zeit+Datum werden als EIN 'Y-m-d H:i:00'-Wert
 *     über den v2-Batch-Autosave gespeichert).
 *
 *   ?t=von / ?t=ziel: Aufbau von v1 1.php/2.php — POI-Suche mit
 *     Autocomplete (v2-Endpoint /api/enotf-v2/poi/search), Adressfelder,
 *     Sonderrechte-Tri-State, zurück/speichern. Gespeichert wird über
 *     POST /api/enotf-v2/poi/save-address (transp_- bzw. ziel_-Spalten
 *     plus JSON-Adresse).
 *
 * Datumsfelder wie v1: type="date"-Inputs, die force-german-date.js zu
 * Text mit TT.MM.JJJJ umbaut (lädt das v1-Look-Layout). Der Autosave
 * schickt den deutschen Wert; ProtokollService::DATE_FIELDS (edatum,
 * patgebdat) konvertiert serverseitig nach Y-m-d. Die Statuszeiten
 * (salarm…sende) sind KEINE DATE_FIELDS — sie werden clientseitig zu
 * 'Y-m-d H:i:00' kombiniert (exakt der v1-Wert) und roh gespeichert.
 *
 * @var array<string,mixed> $protokoll
 * @var string $enr
 * @var bool $istGesperrt
 * @var string|null $fokusThema   ?t-Param aus ProtokollController::show
 */

use Plugin\EnotfV2\Catalogs\EinsatzCatalog;
use Plugin\EnotfV2\Catalogs\TransportzielCatalog;
use Plugin\EnotfV2\Helpers\EnotfV2Url;
use Plugin\EnotfV2\Support\ProtokollService;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

// Altdaten-Fallback fürs Versorgung-Select: transportziel kann statt des
// Katalog-Codes eine POI-Kennung tragen (v1-Doppelsemantik). Dann eine
// eigene Option mit dem aufgelösten POI-Namen zeigen statt des leeren
// Platzhalters — sonst sähe ein gefülltes Altprotokoll hier leer aus.
$tzPoiAnzeige = app(ProtokollService::class)->transportzielPoiAnzeige($protokoll['transportziel'] ?? null);

$sectionUrl = EnotfV2Url::protokoll($enr, 'rettdaten');

// v1-Body-Attribut der Rettdaten-Seiten (nav.php-Konvention)
$sectionBodyPage = 'stammdaten';

// Fokus: 'von' (v1 1.php) / 'ziel' (v1 2.php); gesperrt → Übersicht
// (v1 leitet die Adress-Unterseiten bei freigegeben=1 auf index um)
$fokus = $fokusThema ?? null;
if (!in_array($fokus, ['von', 'ziel'], true) || $istGesperrt) {
    $fokus = null;
}

// Adress-Unterseiten sind wie v1 (rettdaten/1.php + 2.php) chromelos:
// keine Topbar/Section-Nav, Rückweg über die zurück/speichern-Buttons
if ($fokus !== null) {
    $sectionChromeless = true;
}

/** 'Y-m-d H:i:s'-Kombiwert → ['zeit' => 'H:i', 'datum' => 'Y-m-d'] */
$splitZeit = static function (?string $wert): array {
    if ($wert === null || trim($wert) === '') {
        return ['zeit' => '', 'datum' => ''];
    }
    $ts = strtotime($wert);
    if ($ts === false) {
        return ['zeit' => '', 'datum' => ''];
    }
    return ['zeit' => date('H:i', $ts), 'datum' => date('Y-m-d', $ts)];
};

// Statuszeiten in v1-Reihenfolge; check/optional = v1-Klassen + Icon
$zeitFelder = [
    'salarm' => ['label' => 'Alarm',      'stil' => 'check'],
    's3'     => ['label' => 'aus (3)',    'stil' => ''],
    's4'     => ['label' => 'E.-an (4)',  'stil' => ''],
    'spat'   => ['label' => 'Pat.-an',    'stil' => 'check'],
    's7'     => ['label' => 'E.-ab (7)',  'stil' => 'optional'],
    's8'     => ['label' => 'KH an (8)',  'stil' => 'optional'],
    's1'     => ['label' => 'frei (1)',   'stil' => ''],
    's2'     => ['label' => 'Wache (2)',  'stil' => ''],
    'sende'  => ['label' => 'Ende',       'stil' => 'check'],
];

// Adress-Zusammenfassung "Straße HNR, Ort-Ortsteil" (v1 index.php)
$adressDisplay = static function (?string $json): string {
    if ($json === null || trim($json) === '') {
        return '';
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return '';
    }
    $parts = [];
    $strasseHnr = array_filter([$decoded['strasse'] ?? '', $decoded['hnr'] ?? '']);
    if (!empty($strasseHnr)) {
        $parts[] = implode(' ', $strasseHnr);
    }
    $ortOrtsteil = array_filter([$decoded['ort'] ?? '', $decoded['ortsteil'] ?? '']);
    if (!empty($ortOrtsteil)) {
        $parts[] = implode('-', $ortOrtsteil);
    }
    return implode(', ', $parts);
};

$dis = $istGesperrt ? 'disabled' : '';
$ro  = $istGesperrt ? 'readonly' : '';
?>

<?php if ($fokus === null): ?>

    <!-- ── ÜBERSICHT: Eingabeseite (v1 rettdaten/index.php) ── -->
    <div class="row" style="margin-left: 0">
        <div class="col">
            <div class="row shadow edivi__box">
                <h5 class="text-light px-2 py-1">Patientendaten</h5>
                <div class="col">
                    <div class="row my-2">
                        <div class="col">
                            <label for="pat_vorname" class="edivi__description">Vorname</label>
                            <input type="text" name="pat_vorname" id="pat_vorname" placeholder="Max" class="w-100 ignis-input" value="<?= $e($protokoll['pat_vorname'] ?? '') ?>" <?= $ro ?>>
                        </div>
                        <div class="col">
                            <label for="pat_nachname" class="edivi__description">Nachname</label>
                            <input type="text" name="pat_nachname" id="pat_nachname" placeholder="Mustermann" class="w-100 ignis-input" value="<?= $e($protokoll['pat_nachname'] ?? '') ?>" <?= $ro ?>>
                        </div>
                    </div>
                    <div class="row my-2">
                        <div class="col">
                            <label for="patsex" class="edivi__description">Geschlecht</label>
                            <select name="patsex" id="patsex" class="w-100 form-select ignis-input edivi__input-check" required autocomplete="off" <?= $dis ?>>
                                <option disabled hidden <?= ($protokoll['patsex'] === null || $protokoll['patsex'] === '') ? 'selected' : '' ?>>---</option>
                                <?php foreach ([0 => 'männlich', 1 => 'weiblich', 2 => 'divers', 9 => 'unbekannt'] as $code => $label): ?>
                                    <option value="<?= $code ?>" <?= ($protokoll['patsex'] !== null && $protokoll['patsex'] !== '' && (int) $protokoll['patsex'] === $code) ? 'selected' : '' ?>><?= $e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col">
                            <label for="patgebdat" class="edivi__description">Geburtsdatum</label>
                            <input type="date" name="patgebdat" id="patgebdat" class="w-100 ignis-input" value="<?= !empty($protokoll['patgebdat']) ? date('Y-m-d', strtotime((string) $protokoll['patgebdat'])) : '' ?>" <?= $ro ?>>
                        </div>
                        <div class="col-2">
                            <label for="_AGE_" class="edivi__description">Alter</label>
                            <input type="text" name="_AGE_" id="_AGE_" class="w-100 ignis-input" value="0" readonly>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row shadow edivi__box <?= $istGesperrt ? '' : 'edivi__box-clickable' ?>" <?= $istGesperrt ? '' : 'data-href="' . $e($sectionUrl . '?t=von') . '" style="cursor:pointer"' ?>>
                <h5 class="text-light px-2 py-1 edivi__group-check">Transport von / Einsatzort</h5>
                <div class="col">
                    <div class="row my-2">
                        <div class="col">
                            <label for="transp_poi_name" class="edivi__description">Von Einrichtung</label>
                            <input type="text" name="transp_poi_name" id="transp_poi_name" class="w-100 ignis-input" value="<?= $e($protokoll['transp_poi'] ?? '') ?>" readonly>
                        </div>
                    </div>
                    <div class="row my-2">
                        <div class="col">
                            <label for="transp_display" class="edivi__description">Von Adresse</label>
                            <input type="text" name="transp_display" id="transp_display" class="w-100 ignis-input edivi__input-check" value="<?= $e($adressDisplay($protokoll['transp_adresse'] ?? null)) ?>" readonly>
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
                            <input type="text" name="enr" id="enr" class="w-100 ignis-input" value="<?= $e($enr) ?>" readonly>
                        </div>
                        <div class="col">
                            <label for="edatum" class="edivi__description">Einsatzdatum</label>
                            <input type="date" name="edatum" id="edatum" class="w-100 ignis-input edivi__input-check" value="<?= !empty($protokoll['edatum']) ? date('Y-m-d', strtotime((string) $protokoll['edatum'])) : '' ?>" required <?= $ro ?>>
                        </div>
                        <div class="col">
                            <label for="ezeit" class="edivi__description">Einsatzzeit</label>
                            <input type="time" name="ezeit" id="ezeit" class="w-100 ignis-input edivi__input-check" value="<?= $e($protokoll['ezeit'] ?? '') ?>" required <?= $ro ?>>
                        </div>
                    </div>
                    <div class="row my-2">
                        <div class="col-6">
                            <label for="transportziel" class="edivi__description">Versorgung</label>
                            <select name="transportziel" id="transportziel" class="w-100 form-select ignis-input edivi__input-check" required autocomplete="off" <?= $dis ?>>
                                <option disabled hidden <?= ($protokoll['transportziel'] === null || $protokoll['transportziel'] === '') ? 'selected' : '' ?>>---</option>
                                <?php if ($tzPoiAnzeige !== null): ?>
                                    <option value="<?= $e($protokoll['transportziel']) ?>" selected disabled><?= $e($tzPoiAnzeige) ?></option>
                                <?php endif; ?>
                                <?php foreach (TransportzielCatalog::VERSORGUNGSARTEN as $code => $label): ?>
                                    <option value="<?= $code ?>" <?= ($tzPoiAnzeige === null && $protokoll['transportziel'] !== null && $protokoll['transportziel'] !== '' && (int) $protokoll['transportziel'] === $code) ? 'selected' : '' ?>><?= $e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="eart" class="edivi__description">Einsatzart</label>
                            <select name="eart" id="eart" class="w-100 form-select ignis-input edivi__input-check" required autocomplete="off" <?= $dis ?>>
                                <option disabled hidden <?= ($protokoll['eart'] === null || $protokoll['eart'] === '') ? 'selected' : '' ?>>---</option>
                                <?php foreach (EinsatzCatalog::EART as $code => $label): ?>
                                    <option value="<?= $code ?>" <?= ($protokoll['eart'] !== null && $protokoll['eart'] !== '' && (int) $protokoll['eart'] === $code) ? 'selected' : '' ?>><?= $e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row shadow edivi__box <?= $istGesperrt ? '' : 'edivi__box-clickable' ?>" <?= $istGesperrt ? '' : 'data-href="' . $e($sectionUrl . '?t=ziel') . '" style="cursor:pointer"' ?>>
                <h5 class="text-light px-2 py-1 edivi__group-check">Transportziel</h5>
                <div class="col">
                    <div class="row my-2">
                        <div class="col">
                            <label for="ziel_poi_name" class="edivi__description">Ziel Einrichtung</label>
                            <input type="text" name="ziel_poi_name" id="ziel_poi_name" class="w-100 ignis-input" value="<?= $e($protokoll['ziel_poi'] ?? '') ?>" readonly>
                        </div>
                    </div>
                    <div class="row my-2">
                        <div class="col">
                            <label for="ziel_poi_adresse" class="edivi__description">Ziel Adresse</label>
                            <input type="text" name="ziel_poi_adresse" id="ziel_poi_adresse" class="w-100 ignis-input edivi__input-check" value="<?= $e($adressDisplay($protokoll['ziel_adresse'] ?? null)) ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row" style="margin-left: 0">
        <div class="col">
            <div class="row shadow edivi__box">
                <h5 class="text-light px-2 py-1 edivi__group-check">Zeiten</h5>
                <div class="col">
                    <div class="row my-2">
                        <?php foreach ($zeitFelder as $feld => $zf): ?>
                            <?php
                            $wert = $splitZeit(isset($protokoll[$feld]) ? (string) $protokoll[$feld] : null);
                            // v1-Klassen: check = Pflicht (rote/grüne Kante),
                            // optional = konditionale Pflicht (s7/s8 je Versorgung)
                            $cls  = $zf['stil'] === 'check' ? ' edivi__input-check' : ($zf['stil'] === 'optional' ? ' edivi__input-optional' : '');
                            // Icon wie v1 nur an den Feldern mit eigenem Marker
                            $icon = in_array($feld, ['salarm', 'spat', 's7', 's8', 'sende'], true);
                            ?>
                            <div class="col">
                                <label for="<?= $feld ?>" class="edivi__description">
                                    <?= $e($zf['label']) ?>
                                    <?php if ($icon): ?>
                                        <i id="icon-<?= $feld ?>" class="fa-solid fa-circle-exclamation" style="color:#d91425; <?= $wert['zeit'] !== '' ? 'display:none;' : '' ?>"></i>
                                    <?php endif; ?>
                                </label>
                                <input type="time" name="<?= $feld ?>" id="<?= $feld ?>" class="w-100 ignis-input text-center<?= $cls ?>" value="<?= $e($wert['zeit']) ?>" required data-autosave-ignore <?= $ro ?>>
                                <input type="date" name="<?= $feld ?>_datum" id="<?= $feld ?>_datum" class="w-100 ignis-input mt-1 text-center<?= $cls ?>" style="font-size:1rem;color:#a2a2a2;" value="<?= $e($wert['datum']) ?>" required data-autosave-ignore <?= $ro ?>>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Alter aus Geburtsdatum (v1 rettdaten/index.php) — versteht ISO
        // und das deutsche Format, das force-german-date.js herstellt
        function calculateAge(birthDateString) {
            if (!birthDateString) return 0;

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
            const ageEl = document.getElementById('_AGE_');
            if (ageEl) ageEl.value = calculateAge(el.value);
        }

        // Initial + nach dem Reformat durch force-german-date.js (defer;
        // konvertiert type=date→text und ändert den Wert nach DOMContentLoaded)
        document.addEventListener('DOMContentLoaded', updateAge);
        window.addEventListener('load', updateAge);
        document.addEventListener('input', function (e) {
            if (e.target && e.target.id === 'patgebdat') updateAge();
        }, true);
        document.addEventListener('change', function (e) {
            if (e.target && e.target.id === 'patgebdat') updateAge();
        }, true);
    </script>
    <script>
        // Ev2Select manuell initialisieren (Geschlecht/Versorgung/Einsatzart):
        // der Auto-Init läuft nur auf body[data-page="enotf-v2"], die
        // v1-Look-Seiten tragen den Section-Key als data-page. Ein
        // Mechanismus für alle v2-Selects — v1s dropdown.js ist hier
        // bewusst abgeklemmt.
        document.addEventListener('DOMContentLoaded', function () {
            if (window.Ev2Select) window.Ev2Select.init(document);
        });
    </script>
    <script>
        // Statuszeiten (v1-Bedienung): Fokus auf ein leeres Feld übernimmt
        // aktuelle Zeit + heutiges Datum; sind Zeit UND Datum gesetzt, wird
        // 'Y-m-d H:i:00' über den v2-Batch-Autosave gespeichert (v1: ein
        // jQuery-POST pro Feld gegen /api/enotf/save-fields).
        document.addEventListener('DOMContentLoaded', function () {
            var locked = <?= $istGesperrt ? 'true' : 'false' ?>;
            var zeitFelder = <?= json_encode(array_keys($zeitFelder)) ?>;
            var _now = new Date();
            var heute = String(_now.getDate()).padStart(2, '0') + '.' + String(_now.getMonth() + 1).padStart(2, '0') + '.' + _now.getFullYear();

            function updateIcon(feld, hatWert) {
                var icon = document.getElementById('icon-' + feld);
                if (icon) icon.style.display = hatWert ? 'none' : 'inline';
            }

            // Datumseingabe → ISO. force-german-date.js formatiert die
            // Felder zu TT.MM.JJJJ; ohne das Skript bleibt der ISO-Wert.
            function datumIso(value) {
                if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
                var parts = value.split('.');
                if (parts.length !== 3) return null;
                return parts[2] + '-' + parts[1].padStart(2, '0') + '-' + parts[0].padStart(2, '0');
            }

            zeitFelder.forEach(function (feld) {
                var zeitInput = document.getElementById(feld);
                var datumInput = document.getElementById(feld + '_datum');
                if (!zeitInput || !datumInput) return;

                function save() {
                    if (locked) return;
                    if (zeitInput.value && datumInput.value) {
                        var iso = datumIso(datumInput.value);
                        if (!iso) { updateIcon(feld, false); return; }
                        var combined = iso + ' ' + zeitInput.value + ':00';
                        if (window.EnotfV2Autosave) window.EnotfV2Autosave.queue(feld, combined);
                        if (window.__dynamicDaten) window.__dynamicDaten[feld] = combined;
                        updateIcon(feld, true);
                    } else {
                        updateIcon(feld, false);
                    }
                }

                // Klick/Fokus auf leeres Feld übernimmt aktuelle Zeit + heute.
                // Direkt speichern: das programmatische Befüllen feuert kein
                // change-Event, der Wert würde sonst erst nach einer
                // manuellen Änderung gespeichert.
                zeitInput.addEventListener('focus', function () {
                    if (locked || zeitInput.value) return;
                    var now = new Date();
                    zeitInput.value = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
                    if (!datumInput.value) datumInput.value = heute;
                    save();
                });

                datumInput.addEventListener('focus', function () {
                    if (locked || datumInput.value) return;
                    datumInput.value = heute;
                    if (!zeitInput.value) {
                        var now = new Date();
                        zeitInput.value = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
                    }
                    save();
                });

                zeitInput.addEventListener('input', function () {
                    if (zeitInput.value && !datumInput.value) datumInput.value = heute;
                });

                [zeitInput, datumInput].forEach(function (input) {
                    input.addEventListener('change', save);
                });
            });
        });
    </script>

<?php else: ?>

    <!-- ── ADRESSE: POI-Suche (v1 rettdaten/1.php bzw. 2.php) ── -->
    <?php
    // Feld-Prefix + Beschriftung je Ziel ('von' → transp_*, 'ziel' → ziel_*)
    $target   = $fokus === 'von' ? 'transp' : 'ziel';
    $srFeld   = $fokus === 'von' ? 'sonderrechte_anfahrt' : 'sonderrechte_transport';
    $srLabel  = $fokus === 'von' ? 'SR Anfahrt' : 'SR Transport';
    $srWert   = $protokoll[$srFeld] ?? null;
    $adresse  = json_decode((string) ($protokoll[$target . '_adresse'] ?? ''), true);
    $adresse  = is_array($adresse) ? $adresse : [];
    ?>
    <style>
        /* POI-Autocomplete (v1 1.php/2.php, unverändert) */
        .poi-autocomplete-wrapper { position: relative; }
        .poi-dropdown {
            display: none; position: absolute; top: 100%; left: 0; right: 0;
            z-index: 1000; background-color: #444; border: 1px solid #555;
            border-radius: 4px; max-height: 200px; overflow-y: auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }
        .poi-item { padding: 8px 12px; cursor: pointer; color: white; border-bottom: 1px solid #555; }
        .poi-item:last-child { border-bottom: none; }
        .poi-item:hover { background-color: #555; }
        .poi-item-name { font-weight: bold; }
        .poi-item-address { font-size: 0.85em; opacity: 0.8; }
    </style>

    <div class="row" style="margin-left: 0">
        <div class="col">
            <div class="row my-1">
                <div class="col">
                    <h5>Objekt / POI / Einrichtung</h5>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <table class="container-fluid">
                        <tbody>
                            <tr>
                                <td>
                                    <div class="poi-autocomplete-wrapper">
                                        <input type="text" name="<?= $target ?>_poi" id="<?= $target ?>_poi" class="w-100 ignis-input edivi__target" value="<?= $e($protokoll[$target . '_poi'] ?? '') ?>" autocomplete="off" data-autosave-ignore>
                                        <div class="poi-dropdown" id="<?= $target ?>_poi-dropdown"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="row my-1">
                <div class="col">
                    <h5>Straße</h5>
                </div>
                <div class="col-3">
                    <h5>HNR / Postal</h5>
                </div>
                <div class="col-2">
                    <h5><?= $e($srLabel) ?> <i id="icon-sonderrechte" class="fa-solid fa-circle-exclamation" style="color:#d91425;<?= !empty($srWert) ? 'display:none;' : '' ?>"></i></h5>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <table class="container-fluid">
                        <tbody>
                            <tr>
                                <td>
                                    <input type="text" name="<?= $target ?>_adresse_strasse" id="<?= $target ?>_adresse_strasse" class="w-100 ignis-input edivi__target" value="<?= $e($adresse['strasse'] ?? '') ?>" data-autosave-ignore>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-3">
                    <table class="container-fluid">
                        <tbody>
                            <tr>
                                <td>
                                    <input type="text" name="<?= $target ?>_adresse_hnr" id="<?= $target ?>_adresse_hnr" class="w-100 ignis-input edivi__target" value="<?= $e($adresse['hnr'] ?? '') ?>" data-autosave-ignore>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-2">
                    <button type="button" id="btn-sonderrechte" class="w-100 ignis-input edivi__target" style="cursor:pointer;text-align:center;background-color:#333333;border:1px solid #595959;border-radius:0;color:#fff;font-size:1.2rem;padding:0.2rem;"><?php
                        if ($srWert === 'ja') echo 'ja';
                        elseif ($srWert === 'nein') echo 'nein';
                        else echo '—';
                    ?></button>
                </div>
            </div>
            <div class="row my-1">
                <div class="col">
                    <h5>Ort</h5>
                </div>
                <div class="col">
                    <h5>Ortsteil</h5>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <table class="container-fluid">
                        <tbody>
                            <tr>
                                <td>
                                    <input type="text" name="<?= $target ?>_adresse_ort" id="<?= $target ?>_adresse_ort" class="w-100 ignis-input edivi__target" value="<?= $e($adresse['ort'] ?? '') ?>" data-autosave-ignore>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col">
                    <table class="container-fluid">
                        <tbody>
                            <tr>
                                <td>
                                    <input type="text" name="<?= $target ?>_adresse_ortsteil" id="<?= $target ?>_adresse_ortsteil" class="w-100 ignis-input edivi__target" value="<?= $e($adresse['ortsteil'] ?? '') ?>" data-autosave-ignore>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="edivi__freigabe-buttons">
        <div class="row">
            <div class="col">
                <a href="<?= $e($sectionUrl) ?>">zurück</a>
            </div>
            <div class="col">
                <a href="#" id="save-address-btn">speichern</a>
            </div>
        </div>
    </div>

    <script>
        // POI-Autocomplete (v1 1.php/2.php) gegen den v2-Endpoint
        // /api/enotf-v2/poi/search ({ok, pois:[…]} statt v1s rohem Array)
        (function () {
            'use strict';

            var TARGET = <?= json_encode($target) ?>;
            var ENR = <?= json_encode($enr) ?>;
            var SEARCH_URL = <?= json_encode(EnotfV2Url::api('poi/search')) ?>;
            var SAVE_URL = <?= json_encode(EnotfV2Url::api('poi/save-address')) ?>;
            var BACK_URL = <?= json_encode($sectionUrl) ?>;

            var poiData = [];

            function loadPOIs(searchTerm) {
                var url = SEARCH_URL + (searchTerm ? '?search=' + encodeURIComponent(searchTerm) : '');
                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        poiData = (data && data.pois) ? data.pois : [];
                        if (populatePOIDropdown(searchTerm || '')) {
                            document.getElementById(TARGET + '_poi-dropdown').style.display = 'block';
                        }
                    })
                    .catch(function (error) { console.error('POI Fetch Error:', error); });
            }

            function populatePOIDropdown(filterValue) {
                var dropdown = document.getElementById(TARGET + '_poi-dropdown');
                if (!dropdown) return false;
                dropdown.innerHTML = '';

                var filtered = poiData.filter(function (poi) {
                    return (poi.name || '').toLowerCase().includes(filterValue.toLowerCase())
                        || (poi.ort || '').toLowerCase().includes(filterValue.toLowerCase());
                });
                if (filtered.length === 0) {
                    dropdown.style.display = 'none';
                    return false;
                }

                filtered.forEach(function (poi) {
                    var item = document.createElement('div');
                    item.className = 'poi-item';

                    var name = document.createElement('div');
                    name.className = 'poi-item-name';
                    name.textContent = poi.name || '';
                    item.appendChild(name);

                    var addrParts = [poi.strasse, poi.hnr, poi.ort, poi.ortsteil ? '(' + poi.ortsteil + ')' : ''].filter(Boolean);
                    if (addrParts.length > 0) {
                        var addr = document.createElement('div');
                        addr.className = 'poi-item-address';
                        addr.textContent = addrParts.join(' ');
                        item.appendChild(addr);
                    }

                    item.addEventListener('click', function () {
                        selectPOI(poi);
                        dropdown.style.display = 'none';
                    });

                    dropdown.appendChild(item);
                });

                return true;
            }

            function selectPOI(poi) {
                document.getElementById(TARGET + '_poi').value = poi.name || '';
                document.getElementById(TARGET + '_adresse_strasse').value = poi.strasse || '';
                document.getElementById(TARGET + '_adresse_hnr').value = poi.hnr || '';
                document.getElementById(TARGET + '_adresse_ort').value = poi.ort || '';
                document.getElementById(TARGET + '_adresse_ortsteil').value = poi.ortsteil || '';
            }

            document.addEventListener('DOMContentLoaded', function () {
                var poiInput = document.getElementById(TARGET + '_poi');
                var poiDropdown = document.getElementById(TARGET + '_poi-dropdown');
                if (!poiInput || !poiDropdown) return;

                poiInput.addEventListener('focus', function () {
                    if (poiData.length === 0) {
                        loadPOIs();
                    } else if (populatePOIDropdown(this.value)) {
                        poiDropdown.style.display = 'block';
                    }
                });

                poiInput.addEventListener('input', function () {
                    var value = this.value;
                    if (value.length >= 2) {
                        loadPOIs(value);
                    } else if (value.length === 0) {
                        loadPOIs();
                    } else if (populatePOIDropdown(value)) {
                        poiDropdown.style.display = 'block';
                    } else {
                        poiDropdown.style.display = 'none';
                    }
                });

                document.addEventListener('click', function (e) {
                    if (!e.target.closest('.poi-autocomplete-wrapper')) {
                        poiDropdown.style.display = 'none';
                    }
                });
            });

            // Speichern: POI + Adresse als EIN Request (v2 poi/save-address),
            // danach zurück zur Übersicht (v1-Bedienung)
            document.getElementById('save-address-btn').addEventListener('click', function (e) {
                e.preventDefault();

                fetch(SAVE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        enr: ENR,
                        target: TARGET,
                        poi: document.getElementById(TARGET + '_poi').value.trim(),
                        adresse: {
                            strasse: document.getElementById(TARGET + '_adresse_strasse').value.trim(),
                            hnr: document.getElementById(TARGET + '_adresse_hnr').value.trim(),
                            ort: document.getElementById(TARGET + '_adresse_ort').value.trim(),
                            ortsteil: document.getElementById(TARGET + '_adresse_ortsteil').value.trim()
                        }
                    })
                })
                    .then(function (r) { return r.json().catch(function () { return {}; }); })
                    .then(function (data) {
                        if (data && data.ok) {
                            if (window.showToast) window.showToast('Adresse gespeichert', 'success');
                            setTimeout(function () { window.location.href = BACK_URL; }, 1000);
                        } else if (window.showToast) {
                            window.showToast('Fehler beim Speichern: ' + (data.error || 'Unbekannter Fehler'), 'error');
                        }
                    })
                    .catch(function (error) {
                        console.error('Save Error:', error);
                        if (window.showToast) window.showToast('Fehler beim Speichern der Adresse', 'error');
                    });
            });

            // Sonderrechte-Tri-State: leer → nein → ja → leer (v1),
            // gespeichert über den v2-Batch-Autosave
            (function () {
                var btn = document.getElementById('btn-sonderrechte');
                var icon = document.getElementById('icon-sonderrechte');
                var feld = <?= json_encode($srFeld) ?>;
                var currentValue = <?= json_encode($srWert) ?>;

                btn.addEventListener('click', function () {
                    if (currentValue === null || currentValue === '') {
                        currentValue = 'nein';
                    } else if (currentValue === 'nein') {
                        currentValue = 'ja';
                    } else {
                        currentValue = null;
                    }
                    updateButton();
                    if (window.EnotfV2Autosave) {
                        window.EnotfV2Autosave.queue(feld, currentValue || '');
                        window.EnotfV2Autosave.flushNow();
                    }
                    if (window.__dynamicDaten) window.__dynamicDaten[feld] = currentValue || '';
                });

                function updateButton() {
                    if (currentValue === 'ja') {
                        btn.textContent = 'ja';
                        if (icon) icon.style.display = 'none';
                    } else if (currentValue === 'nein') {
                        btn.textContent = 'nein';
                        if (icon) icon.style.display = 'none';
                    } else {
                        btn.textContent = '—';
                        if (icon) icon.style.display = '';
                    }
                }
            })();
        })();
    </script>

<?php endif; ?>
