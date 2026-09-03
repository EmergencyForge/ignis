<?php

/**
 * Section: Abschluss — eNOTF v2 im v1-Look.
 *
 * Nachbau der v1-Seiten plugins/enotf/templates/enotf/protokoll/abschluss/:
 *
 *   ÜBERSICHT (ohne ?t): index.php — Themen-Spalte (Besonderheiten/
 *     NA-Nachforderung/Übergabe/An Leitstelle senden), Boxen für
 *     Transportdaten (Fahrzeuge + Besatzung), Protokolldaten (Protokollant
 *     mit Namensvorschlägen, Protokollart), readonly Übergabe/Einsatzverlauf,
 *     unten der „Abschließen"-Button (→ ?t=freigabe).
 *
 *   ?t=besonderheiten (v1 1.php): ebesonderheiten[] als btn-check-
 *     Checkboxen in zwei Spalten — JSON-Array von int, Code 1 („keine")
 *     exklusiv, gespeichert über den Multi-JSON-Handler in edivi-bridge.js
 *     (data-ev2-multijson, psych-Muster).
 *   ?t=nanachf (v1 2.php): na_nachf Radio 1=nein/2=ja (nur prot_by != 1).
 *   ?t=uebergabe (v1 3_1/3_2.php): Wizard mit zwei Schritten Ort/An
 *     (uebergabe_ort/uebergabe_an-Radios), Subnav Ort/An/Freigabe.
 *   ?t=freigabe (v1 freigabe.php): Zusammenfassung + Plausibilität +
 *     „Abschließen!" — Freigabe über den v2-Flow: Autosave leeren,
 *     Plausibility-Gate (GET /api/enotf-v2/plausibility/{enr}), dann
 *     save-fields-Batch { freigeber: pfname } (ProtokollService::release).
 *
 * Datenformate exakt wie v1: fzg_transp/fzg_na = intra_fahrzeuge.identifier,
 * Personal als Freitext "Name (Quali)", ebesonderheiten = JSON-Array bzw.
 * NULL, na_nachf 1/2, uebergabe_ort/_an = Katalog-Codes (UebergabeCatalog).
 *
 * @var array<string,mixed> $protokoll
 * @var string $enr
 * @var bool $istGesperrt
 * @var string|null $fokusThema
 */

use App\Models\Personnel;
use App\Models\Vehicle;
use Plugin\EnotfV2\Catalogs\EinsatzCatalog;
use Plugin\EnotfV2\Catalogs\UebergabeCatalog;
use Plugin\EnotfV2\Helpers\EnotfV2Url;
use Plugin\EnotfV2\Support\ConditionsService;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

$sectionUrl = EnotfV2Url::protokoll($enr, 'abschluss');
$fokusUrl   = static fn (string $t): string => $sectionUrl . '?t=' . rawurlencode($t);

$protBy = ($protokoll['prot_by'] !== null && $protokoll['prot_by'] !== '')
    ? (int) $protokoll['prot_by']
    : null;

$fokus = $fokusThema ?? null;
$erlaubt = ['besonderheiten', 'nanachf', 'uebergabe', 'freigabe'];
if (!in_array($fokus, $erlaubt, true)) {
    $fokus = null;
}
if ($istGesperrt && $fokus !== null) {
    // freigegeben/gelöscht: nur die Übersicht (v1: Unterseiten readonly,
    // freigabe.php leitet um)
    $fokus = null;
}

// Freigabe-Seite ist wie v1 (abschluss/freigabe.php) chromelos: keine
// Topbar/Section-Nav, Rückweg über die zurück-Schaltfläche
if ($fokus === 'freigabe') {
    $sectionChromeless = true;
}

// Übergabe-Wizard: ohne explizites ?q KEIN Schritt offen — nur die
// Subnav-Spalte (v1: Themen-Einstieg zeigt die Navigation)
$initialStep = -1;
if ($fokus === 'uebergabe') {
    $qRaw = $_GET['q'] ?? null;
    if (is_string($qRaw) && preg_match('/^\d+$/', $qRaw)) {
        $initialStep = max(0, min(1, (int) $qRaw - 1));
    }
}

// ── ebesonderheiten (JSON-Array von int) ─────────────────────────────
$ebesonderheiten = [];
if (!empty($protokoll['ebesonderheiten'])) {
    $decoded = json_decode((string) $protokoll['ebesonderheiten'], true);
    if (is_array($decoded)) {
        $ebesonderheiten = array_map('intval', $decoded);
    }
}
$ebesonderheitenTexte = [];
foreach ($ebesonderheiten as $code) {
    if (isset(EinsatzCatalog::EBESONDERHEITEN[$code])) {
        $ebesonderheitenTexte[] = EinsatzCatalog::EBESONDERHEITEN[$code];
    }
}
$ebesonderheitenDisplay = implode(', ', $ebesonderheitenTexte);

// ── Anzeige-Kopie der Personalfelder (v1 abschluss/index.php) ─────────
// Felder der jeweils ANDEREN Protokollart nur in der ANZEIGE leeren;
// eigene Felder aus der Crew-Session vorbefüllen, wenn leer. Gespeichert
// wird erst, wenn der Autosave eine echte Änderung sieht.
$anzeige = $protokoll;
if ($protBy === 0) {
    $anzeige['fzg_na_perso']   = null;
    $anzeige['fzg_na_perso_2'] = null;
    $anzeige['fzg_na_perso_3'] = null;
} elseif ($protBy === 1) {
    $anzeige['fzg_transp_perso']   = null;
    $anzeige['fzg_transp_perso_2'] = null;
    $anzeige['fzg_transp_perso_3'] = null;
}

if (isset($_SESSION['fahrername'])) {
    $sessionName = static function (string $nameKey, string $qualiKey): string {
        $name = (string) ($_SESSION[$nameKey] ?? '');
        if ($name === '') {
            return '';
        }
        $quali = (string) ($_SESSION[$qualiKey] ?? '');
        return $quali !== '' ? "{$name} ({$quali})" : $name;
    };

    $fahrerName     = $sessionName('fahrername', 'fahrerquali');
    $beifahrerName  = $sessionName('beifahrername', 'beifahrerquali');
    $praktikantName = $sessionName('praktikantname', 'praktikantquali');

    $prefix = $protBy === 1 ? 'fzg_na_perso' : ($protBy === 0 ? 'fzg_transp_perso' : null);
    if ($prefix !== null) {
        if (empty($anzeige[$prefix])) {
            $anzeige[$prefix] = $fahrerName;
        }
        if (empty($anzeige[$prefix . '_2']) && $beifahrerName !== '') {
            $anzeige[$prefix . '_2'] = $beifahrerName;
        }
        if (empty($anzeige[$prefix . '_3']) && $praktikantName !== '') {
            $anzeige[$prefix . '_3'] = $praktikantName;
        }
    }
}

// ── Fahrzeuglisten (rd_type 2 = Transportmittel, 1 = NA-Fahrzeug) ─────
$normalizeFzg = static fn ($v): string => ($v === null || $v === 'NULL') ? '' : (string) $v;
$fzgTranspVal = $normalizeFzg($protokoll['fzg_transp'] ?? null);
$fzgNaVal     = $normalizeFzg($protokoll['fzg_na'] ?? null);

$loadVehicles = static function (int $rdType): array {
    return Vehicle::where('rd_type', $rdType)
        ->orderBy('priority', 'ASC')
        ->get(['identifier', 'name', 'active'])
        ->map(static fn ($v) => ['identifier' => (string) $v->identifier, 'name' => (string) $v->name, 'active' => (int) $v->active])
        ->all();
};
$fahrzeugeTransp = $loadVehicles(2);
$fahrzeugeNa     = $loadVehicles(1);

// v1-Verhalten: aktive Fahrzeuge wählbar, ein gespeichertes inaktives
// bleibt als selected+disabled sichtbar
$renderVehicleOptions = static function (array $fahrzeuge, string $current, string $placeholder) use ($e): string {
    $html = '<option value="NULL"' . ($current === '' ? ' selected' : '') . '>' . $e($placeholder) . '</option>';
    foreach ($fahrzeuge as $fzg) {
        $isCurrent = $current !== '' && $fzg['identifier'] === $current;
        if ($fzg['active'] !== 1 && !$isCurrent) {
            continue;
        }
        $html .= '<option value="' . $e($fzg['identifier']) . '"'
            . ($isCurrent ? ' selected' : '')
            . ($isCurrent && $fzg['active'] !== 1 ? ' disabled' : '')
            . '>' . $e($fzg['name']) . '</option>';
    }
    return $html;
};

$vehicleName = static function (array $fahrzeuge, string $identifier): string {
    foreach ($fahrzeuge as $fzg) {
        if ($fzg['identifier'] === $identifier) {
            return $fzg['name'];
        }
    }
    return '';
};

// ── Protokollant-Vorschläge (v1: Personnel::pluck('fullname')) ────────
$pfnameSuggestions = Personnel::orderBy('fullname', 'ASC')->pluck('fullname')->all();

$uebergabeOrtText = '';
if ($protokoll['uebergabe_ort'] !== null && $protokoll['uebergabe_ort'] !== '') {
    $uebergabeOrtText = UebergabeCatalog::ORT[(int) $protokoll['uebergabe_ort']] ?? '';
}
$uebergabeAnText = '';
if ($protokoll['uebergabe_an'] !== null && $protokoll['uebergabe_an'] !== '') {
    $uebergabeAnText = UebergabeCatalog::AN[(int) $protokoll['uebergabe_an']] ?? '';
}

// Themen-Spalte (v1: in Übersicht und jeder Unterseite identisch)
$themenSpalte = static function (?string $aktiv) use ($fokusUrl, $e, $protBy): string {
    $html = '<div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">';
    $html .= '<a href="' . $e($fokusUrl('besonderheiten')) . '" data-requires="ebesonderheiten"'
        . ($aktiv === 'besonderheiten' ? ' class="active"' : '') . '>'
        . '<span>Einsatzverlauf Besonderheiten</span></a>';
    if ($protBy !== 1) {
        $html .= '<a href="' . $e($fokusUrl('nanachf')) . '" data-requires="na_nachf"'
            . ($aktiv === 'nanachf' ? ' class="active"' : '') . '>'
            . '<span>Nachforderung NA</span></a>';
    }
    $html .= '<a href="' . $e($fokusUrl('uebergabe')) . '"'
        . ($aktiv === 'uebergabe' ? ' class="active"' : '') . '>'
        . '<span>Übergabe</span></a>';
    $html .= '<a href="#" id="btn-send-patient"><span>An Leitstelle senden</span></a>';
    return $html . '</div>';
};

// Radio-Spalte (v1 btn-check in edivi__interactbutton)
$radioSpalte = static function (string $name, array $options, array $opts = []) use ($e, $istGesperrt, $protokoll): string {
    $current = (string) ($protokoll[$name] ?? '');
    $html = '<div class="col-2 d-flex flex-column edivi__interactbutton px-3">';
    foreach ($options as $code => $label) {
        $checked = $current !== '' && $current === (string) $code ? ' checked' : '';
        $cls = isset($opts['green']) && (string) $opts['green'] === (string) $code ? ' class="edivi__unauffaellig"' : '';
        $html .= '<input type="radio" class="btn-check" id="' . $e($name) . '-' . $code . '" name="' . $e($name) . '" value="' . $code . '"'
            . $checked . ($istGesperrt ? ' disabled' : '') . ' autocomplete="off">'
            . '<label for="' . $e($name) . '-' . $code . '"' . $cls . '>' . $e($label) . '</label>';
    }
    return $html . '</div>';
};

// Checkbox-Spalte für ebesonderheiten (Multi-JSON via edivi-bridge.js)
$besonderheitenSpalte = static function (array $codes) use ($e, $istGesperrt, $ebesonderheiten): string {
    $html = '<div class="col-2 d-flex flex-column edivi__interactbutton px-3">';
    foreach ($codes as $code) {
        $label   = EinsatzCatalog::EBESONDERHEITEN[$code];
        $checked = in_array($code, $ebesonderheiten, true) ? ' checked' : '';
        $cls     = $code === EinsatzCatalog::EBESONDERHEITEN_EXKLUSIV ? ' class="edivi__unauffaellig"' : '';
        $html .= '<input type="checkbox" class="btn-check" id="ebesonderheiten-' . $code . '" name="ebesonderheiten[]" value="' . $code . '"'
            . ' data-autosave-ignore data-ev2-multijson="ebesonderheiten"'
            . ' data-ev2-exclusive="' . EinsatzCatalog::EBESONDERHEITEN_EXKLUSIV . '"'
            . $checked . ($istGesperrt ? ' disabled' : '') . ' autocomplete="off">'
            . '<label for="ebesonderheiten-' . $code . '"' . $cls . '>' . $e($label) . '</label>';
    }
    return $html . '</div>';
};

// ebesonderheiten-Spaltenaufteilung wie v1 1.php (1–9 / 10–99)
$besCodes  = array_keys(EinsatzCatalog::EBESONDERHEITEN);
$besCol1   = array_values(array_filter($besCodes, static fn (int $c): bool => $c <= 9));
$besCol2   = array_values(array_filter($besCodes, static fn (int $c): bool => $c > 9));

// uebergabe_ort-Spaltenaufteilung wie v1 3_1.php (1–9 / 10–99)
$ortCodes = array_keys(UebergabeCatalog::ORT);
$ortCol1  = array_values(array_filter($ortCodes, static fn (int $c): bool => $c <= 9));
$ortCol2  = array_values(array_filter($ortCodes, static fn (int $c): bool => $c > 9));
$pick     = static fn (array $codes, array $katalog): array => array_combine($codes, array_map(static fn ($c) => $katalog[$c], $codes));
?>

<?php if ($fokus === 'besonderheiten'): ?>

    <!-- ── EINSATZVERLAUF BESONDERHEITEN (v1 abschluss/1.php) ── -->
    <div class="row" style="margin-left: 0">
        <?= $themenSpalte('besonderheiten') ?>
        <?= $besonderheitenSpalte($besCol1) ?>
        <?= $besonderheitenSpalte($besCol2) ?>
    </div>

<?php elseif ($fokus === 'nanachf'): ?>

    <!-- ── NACHFORDERUNG NA (v1 abschluss/2.php) ── -->
    <div class="row" style="margin-left: 0">
        <?= $themenSpalte('nanachf') ?>
        <?= $radioSpalte('na_nachf', [1 => 'nein', 2 => 'ja']) ?>
    </div>

<?php elseif ($fokus === 'uebergabe'): ?>

    <!-- ── ÜBERGABE (v1 abschluss/3_1.php + 3_2.php als Wizard) ── -->
    <div class="row" style="margin-left: 0" data-ev2-steps>
        <?= $themenSpalte('uebergabe') ?>

        <div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">
            <a href="<?= $e($fokusUrl('uebergabe')) ?>&q=1" data-wiz-goto="0" data-requires="uebergabe_ort">
                <span>Ort</span>
            </a>
            <a href="<?= $e($fokusUrl('uebergabe')) ?>&q=2" data-wiz-goto="1" data-requires="uebergabe_an">
                <span>An</span>
            </a>
            <a href="<?= $e($fokusUrl('freigabe')) ?>">
                <span>Freigabe</span>
            </a>
        </div>

        <div class="ev2-stepwrap<?= $initialStep === 0 ? '' : ' is-hidden' ?>" data-wiz-step data-wiz-fields="uebergabe_ort">
            <?= $radioSpalte('uebergabe_ort', $pick($ortCol1, UebergabeCatalog::ORT)) ?>
            <?= $radioSpalte('uebergabe_ort', $pick($ortCol2, UebergabeCatalog::ORT)) ?>
        </div>
        <div class="ev2-stepwrap<?= $initialStep === 1 ? '' : ' is-hidden' ?>" data-wiz-step data-wiz-fields="uebergabe_an">
            <?= $radioSpalte('uebergabe_an', UebergabeCatalog::AN) ?>
        </div>
    </div>

<?php elseif ($fokus === 'freigabe'): ?>

    <!-- ── FREIGABE (v1 abschluss/freigabe.php, v2-Flow) ── -->
    <?php
    // Zusammenfassungsdaten (v1 freigabe.php)
    $formatAdresse = static function (?string $poi, ?string $json): string {
        $parts = [];
        if (!empty($poi)) {
            $parts[] = $poi;
        }
        if (!empty($json)) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $strasseHnr = array_filter([$decoded['strasse'] ?? '', $decoded['hnr'] ?? ''], static fn ($v) => $v !== '' && $v !== null);
                if ($strasseHnr !== []) {
                    $parts[] = implode(' ', $strasseHnr);
                }
                $ortOrtsteil = array_filter([$decoded['ort'] ?? '', $decoded['ortsteil'] ?? ''], static fn ($v) => $v !== '' && $v !== null);
                if ($ortOrtsteil !== []) {
                    $parts[] = implode('-', $ortOrtsteil);
                }
            }
        }
        return implode(', ', $parts);
    };

    $transpVonDisplay  = $formatAdresse($protokoll['transp_poi'] ?? null, $protokoll['transp_adresse'] ?? null);
    $transpNachDisplay = $formatAdresse($protokoll['ziel_poi'] ?? null, $protokoll['ziel_adresse'] ?? null);

    // v1-Parität (abschluss/freigabe.php): ohne ziel_poi/ziel_adresse kann
    // das Ziel noch in transportziel stecken (POI-Kennung aus Altdaten) —
    // dann den aufgelösten POI-Namen zeigen statt „Kein Zielort hinterlegt".
    if ($transpNachDisplay === '') {
        $transpNachDisplay = (string) (app(\Plugin\EnotfV2\Support\ProtokollService::class)
            ->transportzielPoiAnzeige($protokoll['transportziel'] ?? null) ?? '');
    }

    $gebdatDisplay = !empty($protokoll['patgebdat']) ? (new DateTime((string) $protokoll['patgebdat']))->format('d.m.Y') : '';
    $edatumDisplay = !empty($protokoll['edatum']) ? (new DateTime((string) $protokoll['edatum']))->format('d.m.Y') : '';

    $fzgTranspName = $vehicleName($fahrzeugeTransp, $fzgTranspVal);
    $fzgNaName     = $vehicleName($fahrzeugeNa, $fzgNaVal);

    $fehlt = static fn (string $text): string => '<span style="color:lightgray">' . $text . '</span>';

    // Initiale Plausibilität (Serverstand; #final prüft vor der Freigabe
    // noch einmal frisch über die API)
    $openInitial  = app(ConditionsService::class)->evaluate($protokoll);
    $openMessages = [];
    foreach ($openInitial as $rules) {
        foreach ($rules as $rule) {
            $openMessages[] = (string) $rule['message'];
        }
    }
    ?>
    <div class="row" style="margin-left: 0">
        <div class="col">
            <div class="row">
                <div class="col">
                    <h5>Patient</h5>
                </div>
            </div>
            <div class="row">
                <div class="col edivi__freigabe">
                    <table class="container-fluid">
                        <tbody>
                            <tr>
                                <td><?= !empty($protokoll['patname']) ? $e($protokoll['patname']) : $fehlt('Kein Name hinterlegt') ?> * <?= $gebdatDisplay !== '' ? $e($gebdatDisplay) : $fehlt('Kein Datum hinterlegt') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="row">
                <div class="col">
                    <h5>Transport</h5>
                </div>
            </div>
            <div class="row">
                <div class="col edivi__freigabe">
                    <table class="container-fluid">
                        <tbody>
                            <tr>
                                <td>von: <?= $transpVonDisplay !== '' ? $e($transpVonDisplay) : $fehlt('Kein Ort hinterlegt') ?></td>
                            </tr>
                            <tr>
                                <td>nach: <?= $transpNachDisplay !== '' ? $e($transpNachDisplay) : $fehlt('Kein Zielort hinterlegt') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row" style="margin-left: 0">
        <div class="col">
            <div class="row">
                <div class="col">
                    <h5>Besatzung</h5>
                </div>
            </div>
            <div class="row">
                <div class="col edivi__freigabe">
                    <table class="container-fluid">
                        <tbody>
                            <?php if ($protBy === 0): // Rettungsdienst-Protokoll ?>
                                <tr>
                                    <td><?= $fzgTranspName !== '' ? $e($fzgTranspName) : $fehlt('Kein Transportmittel hinterlegt') ?></td>
                                </tr>
                                <tr>
                                    <td><?= !empty($anzeige['fzg_transp_perso']) ? $e($anzeige['fzg_transp_perso']) : $fehlt('Kein Transportführer hinterlegt') ?>, <?= !empty($anzeige['fzg_transp_perso_2']) ? $e($anzeige['fzg_transp_perso_2']) : $fehlt('Kein Fahrzeugführer hinterlegt') ?></td>
                                </tr>
                                <?php if (!empty($anzeige['fzg_transp_perso_3'])): ?>
                                    <tr>
                                        <td>Praktikant: <?= $e($anzeige['fzg_transp_perso_3']) ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php elseif ($protBy === 1): // Notarzt-Protokoll ?>
                                <tr>
                                    <td><?= $fzgNaName !== '' ? $e($fzgNaName) : $fehlt('Kein Notarztzubringer hinterlegt') ?></td>
                                </tr>
                                <tr>
                                    <td><?= !empty($anzeige['fzg_na_perso']) ? $e($anzeige['fzg_na_perso']) : $fehlt('Kein Notarzt hinterlegt') ?>, <?= !empty($anzeige['fzg_na_perso_2']) ? $e($anzeige['fzg_na_perso_2']) : $fehlt('Kein Fahrzeugführer/HEMS hinterlegt') ?></td>
                                </tr>
                                <?php if (!empty($anzeige['fzg_na_perso_3'])): ?>
                                    <tr>
                                        <td>Praktikant: <?= $e($anzeige['fzg_na_perso_3']) ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php else: ?>
                                <tr>
                                    <td><?= $fehlt('Keine Protokollart festgelegt') ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="row">
                <div class="col">
                    <h5>Sonstige Fahrzeuge</h5>
                </div>
            </div>
            <div class="row">
                <div class="col edivi__freigabe">
                    <table class="container-fluid">
                        <tbody>
                            <tr>
                                <td><?= !empty($protokoll['fzg_sonst']) ? $e($protokoll['fzg_sonst']) : $fehlt('Keine weiteren Rettungsmittel hinterlegt') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row" style="margin-left: 0">
        <div class="col">
            <div class="row">
                <div class="col">
                    <h5>Einsatzdaten</h5>
                </div>
            </div>
            <div class="row">
                <div class="col edivi__freigabe">
                    <table class="container-fluid">
                        <tbody>
                            <tr>
                                <td>Einsatz-Nr.: <?= $e($enr) ?></td>
                            </tr>
                            <tr>
                                <td>Beginn: <?= $edatumDisplay !== '' ? $e($edatumDisplay) : $fehlt('Kein Datum hinterlegt') ?>, <?= !empty($protokoll['ezeit']) ? $e($protokoll['ezeit']) : $fehlt('keine Zeit hinterlegt') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="row">
                <div class="col">
                    <h5>Protokollant und freigebende Person</h5>
                </div>
            </div>
            <div class="row">
                <div class="col edivi__freigabe">
                    <table class="container-fluid">
                        <tbody>
                            <tr>
                                <td><?= !empty($protokoll['pfname']) ? $e($protokoll['pfname']) : $fehlt('Kein Protokollant hinterlegt') ?></td>
                            </tr>
                            <tr>
                                <td>
                                    <?php
                                    if ($protBy === 0) {
                                        echo $fzgTranspName !== '' ? $e($fzgTranspName) : $fehlt('Kein Transportmittel hinterlegt');
                                    } elseif ($protBy === 1) {
                                        echo $fzgNaName !== '' ? $e($fzgNaName) : $fehlt('Kein Notarztzubringer hinterlegt');
                                    } else {
                                        echo $fehlt('Keine Protokollart festgelegt');
                                    }
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row" style="margin-left: 0">
        <div class="col">
            <div class="row">
                <div class="col">
                    <h5>Plausibilitätsprüfung</h5>
                </div>
            </div>
            <div class="row">
                <div class="col edivi__freigabe">
                    <table class="container-fluid">
                        <tbody>
                            <tr>
                                <td class="edivi__checks-text" id="plausibility"><?php
                                    // Initialstand wie v1 plausibility.php (eine Meldung pro Zeile)
                                    echo $openMessages === []
                                        ? ''
                                        : implode('<br>', array_map(static fn (string $m): string => $e($m), $openMessages));
                                ?></td>
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
                <a href="#" id="final">Abschließen!</a>
            </div>
        </div>
    </div>

    <script>
        // Freigabe über den v2-Flow: Autosave leeren → Plausibility-Gate →
        // save-fields { freigeber: pfname } (ProtokollService::release
        // setzt freigeber_name + freigegeben=1 und feuert das Event).
        (function () {
            'use strict';
            var ENR = <?= json_encode($enr) ?>;
            var SAVE_URL = <?= json_encode(EnotfV2Url::api('save-fields')) ?>;
            var PLAUSIBILITY_URL = <?= json_encode(EnotfV2Url::api('plausibility/' . rawurlencode($enr))) ?>;
            var REDIRECT_URL = <?= json_encode($sectionUrl) ?>;
            var PFNAME = <?= json_encode((string) ($protokoll['pfname'] ?? '')) ?>;

            var finalBtn = document.getElementById('final');
            if (!finalBtn) return;

            function waitForAutosaveIdle() {
                return new Promise(function (resolve) {
                    var autosave = window.EnotfV2Autosave;
                    if (!autosave) { resolve(); return; }
                    autosave.flushNow();
                    var waited = 0;
                    (function poll() {
                        if (!autosave.hasPending() || waited >= 3000) { resolve(); return; }
                        waited += 150;
                        setTimeout(poll, 150);
                    })();
                });
            }

            function fetchPlausibility() {
                return fetch(PLAUSIBILITY_URL, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .catch(function () { return null; });
            }

            function zeigeOffenePunkte(data) {
                // Plausibilitäts-Feld nachziehen (frischer Serverstand)
                var td = document.getElementById('plausibility');
                if (td && data && data.open) {
                    var messages = [];
                    Object.keys(data.open).forEach(function (section) {
                        (data.open[section] || []).forEach(function (rule) {
                            messages.push(rule.message);
                        });
                    });
                    td.textContent = '';
                    messages.forEach(function (message, i) {
                        if (i > 0) td.appendChild(document.createElement('br'));
                        td.appendChild(document.createTextNode(message));
                    });
                }
                var count = data && typeof data.openCount === 'number' ? data.openCount : null;
                var text = count !== null
                    ? count + ' offene Pflichtangabe' + (count === 1 ? '' : 'n') + ' — Freigabe noch nicht möglich.'
                    : 'Freigabe noch nicht möglich — offene Pflichtangaben vorhanden.';
                if (window.Dialog) window.Dialog.alert(text, { type: 'warning', title: 'Plausibilitätsprüfung' });
                else window.alert(text);
            }

            function release() {
                fetch(SAVE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ enr: ENR, fields: { freigeber: PFNAME } })
                })
                    .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { status: r.status, data: d }; }); })
                    .then(function (result) {
                        var updated = (result.data && result.data.updated) || [];
                        if (result.status === 200 && updated.indexOf('freigeber') !== -1) {
                            window.location.href = REDIRECT_URL;
                            return;
                        }
                        var errors = (result.data && result.data.errors) || {};
                        var msg = errors.freigeber || errors._request || 'Freigabe fehlgeschlagen';
                        if (window.Dialog) window.Dialog.alert(msg, { type: 'error', title: 'Freigabe fehlgeschlagen' });
                        else window.alert(msg);
                    })
                    .catch(function () {
                        if (window.Dialog) window.Dialog.alert('Netzwerkfehler bei der Freigabe.', { type: 'error', title: 'Freigabe fehlgeschlagen' });
                    });
            }

            finalBtn.addEventListener('click', function (event) {
                event.preventDefault();
                waitForAutosaveIdle()
                    .then(fetchPlausibility)
                    .then(function (data) {
                        if (!data || !data.ok || !data.releasable) {
                            zeigeOffenePunkte(data);
                            return;
                        }
                        if (PFNAME.trim() === '') {
                            var msg = 'Freigabe erst möglich, wenn ein Protokollant eingetragen ist.';
                            if (window.Dialog) window.Dialog.alert(msg, { type: 'warning', title: 'Protokollant fehlt' });
                            else window.alert(msg);
                            return;
                        }
                        var frage = 'Protokoll #' + ENR + ' wirklich abschließen? Es kann danach nicht mehr bearbeitet werden.';
                        var entscheidung = window.Dialog
                            ? window.Dialog.confirm(frage, { title: 'Protokoll abschließen', confirmText: 'Abschließen!', danger: true })
                            : Promise.resolve(window.confirm(frage));
                        entscheidung.then(function (ok) {
                            if (ok) release();
                        });
                    });
            });
        })();
    </script>

<?php else: ?>

    <!-- ── ÜBERSICHT (v1 abschluss/index.php) ── -->
    <div class="row" style="margin-left: 0">
        <?php if (!$istGesperrt): ?>
            <?= $themenSpalte(null) ?>
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
                                    <select name="fzg_transp" id="fzg_transp" class="w-100 form-select ignis-input" <?= $istGesperrt ? 'disabled' : '' ?>>
                                        <?= $renderVehicleOptions($fahrzeugeTransp, $fzgTranspVal, 'Fzg. Transp.') ?>
                                    </select>
                                </div>
                                <div class="col">
                                    <label for="fzg_transp_perso" class="edivi__description">Besatzung Transportmittel</label>
                                    <input type="text" name="fzg_transp_perso" id="fzg_transp_perso" class="w-100 ignis-input" placeholder="Transportführer RTW/KTW" value="<?= $e($anzeige['fzg_transp_perso'] ?? '') ?>" <?= $istGesperrt ? 'readonly' : '' ?>>
                                </div>
                            </div>
                            <div class="row mb-2" id="fzg_transp_row_2">
                                <div class="col-5"></div>
                                <div class="col">
                                    <input type="text" name="fzg_transp_perso_2" id="fzg_transp_perso_2" class="w-100 ignis-input" placeholder="Fahrzeugführer RTW/KTW" value="<?= $e($anzeige['fzg_transp_perso_2'] ?? '') ?>" <?= $istGesperrt ? 'readonly' : '' ?>>
                                </div>
                            </div>
                            <div class="row mb-2" id="fzg_transp_row_3">
                                <div class="col-5"></div>
                                <div class="col">
                                    <input type="text" name="fzg_transp_perso_3" id="fzg_transp_perso_3" class="w-100 ignis-input" placeholder="Praktikant RTW/KTW" value="<?= $e($anzeige['fzg_transp_perso_3'] ?? '') ?>" <?= $istGesperrt ? 'readonly' : '' ?>>
                                </div>
                            </div>
                            <div class="row mt-2" id="fzg_na_row">
                                <div class="col-5">
                                    <label for="fzg_na" class="edivi__description">Fahrzeug Notarzt</label>
                                    <select name="fzg_na" id="fzg_na" class="w-100 form-select ignis-input" <?= $istGesperrt ? 'disabled' : '' ?>>
                                        <?= $renderVehicleOptions($fahrzeugeNa, $fzgNaVal, 'Fzg. NA') ?>
                                    </select>
                                </div>
                                <div class="col">
                                    <label for="fzg_na_perso" class="edivi__description">Besatzung Notarztzubringer</label>
                                    <input type="text" name="fzg_na_perso" id="fzg_na_perso" class="w-100 ignis-input" placeholder="Notarzt" value="<?= $e($anzeige['fzg_na_perso'] ?? '') ?>" <?= $istGesperrt ? 'readonly' : '' ?>>
                                </div>
                            </div>
                            <div class="row mb-2" id="fzg_na_row_2">
                                <div class="col-5"></div>
                                <div class="col">
                                    <input type="text" name="fzg_na_perso_2" id="fzg_na_perso_2" class="w-100 ignis-input" placeholder="Fahrzeugführer NEF/HEMS-TC" value="<?= $e($anzeige['fzg_na_perso_2'] ?? '') ?>" <?= $istGesperrt ? 'readonly' : '' ?>>
                                </div>
                            </div>
                            <div class="row mb-2" id="fzg_na_row_3">
                                <div class="col-5"></div>
                                <div class="col">
                                    <input type="text" name="fzg_na_perso_3" id="fzg_na_perso_3" class="w-100 ignis-input" placeholder="Praktikant NEF/HEMS-TC" value="<?= $e($anzeige['fzg_na_perso_3'] ?? '') ?>" <?= $istGesperrt ? 'readonly' : '' ?>>
                                </div>
                            </div>
                            <div class="row my-2">
                                <div class="col">
                                    <label for="fzg_sonst" class="edivi__description">Sonstige Fahrzeuge</label>
                                    <input type="text" name="fzg_sonst" id="fzg_sonst" class="w-100 ignis-input" placeholder="Weitere Rettungsmittel" value="<?= $e($protokoll['fzg_sonst'] ?? '') ?>" <?= $istGesperrt ? 'readonly' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="row edivi__box">
                        <h5 class="text-light px-2 py-1 edivi__group-check">Protokolldaten</h5>
                        <div class="col">
                            <div class="row my-2">
                                <div class="col">
                                    <label for="pfname" class="edivi__description">Protokollant</label>
                                    <input type="text" class="w-100 ignis-input edivi__input-check" name="pfname" id="pfname"
                                           value="<?= $e($protokoll['pfname'] ?? '') ?>" required autocomplete="off"
                                           data-ev2-suggest="ev2-pfname" <?= $istGesperrt ? 'readonly' : '' ?> />
                                    <!-- Namensvorschläge für Ev2Suggest (datalist-Popup
                                         zeigt der FiveM-CEF nicht an) -->
                                    <script type="application/json" data-ev2-suggest-source="ev2-pfname"><?= json_encode($pfnameSuggestions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
                                </div>
                            </div>
                            <div class="row my-2">
                                <div class="col">
                                    <label for="prot_by" class="edivi__description">Protokoll durch</label>
                                    <!-- ignis-input → läuft wie fzg_transp/fzg_na über Ev2Select,
                                         damit alle Selects der Seite dieselbe Box-Optik
                                         mit Chevron tragen -->
                                    <select name="prot_by" id="prot_by" class="w-100 form-select ignis-input edivi__input-check" required autocomplete="off" <?= $istGesperrt ? 'disabled' : '' ?>>
                                        <option disabled hidden <?= $protBy === null ? 'selected' : '' ?>>---</option>
                                        <option value="0" <?= $protBy === 0 ? 'selected' : '' ?>>Transportmittel</option>
                                        <option value="1" <?= $protBy === 1 ? 'selected' : '' ?>>Notarzt</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('uebergabe')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1">Übergabe</h5>
                        <div class="col">
                            <div class="row my-2">
                                <div class="col">
                                    <label for="uebergabeort" class="edivi__description">Übergabe-Ort</label>
                                    <input type="text" name="uebergabeort" id="uebergabeort" class="w-100 ignis-input" value="<?= $e($uebergabeOrtText) ?>" readonly>
                                </div>
                            </div>
                            <div class="row my-2">
                                <div class="col">
                                    <label for="uebergabean" class="edivi__description">Übergabe an</label>
                                    <input type="text" name="uebergabean" id="uebergabean" class="w-100 ignis-input" value="<?= $e($uebergabeAnText) ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('besonderheiten')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1 edivi__group-check">Einsatzverlauf</h5>
                        <div class="col">
                            <div class="row my-2">
                                <div class="col">
                                    <label for="einsatzverlauf_besonderheiten" class="edivi__description">Besonderheiten</label>
                                    <input type="text" name="einsatzverlauf_besonderheiten" id="einsatzverlauf_besonderheiten" class="w-100 ignis-input edivi__input-check" value="<?= $e($ebesonderheitenDisplay) ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php if (!$istGesperrt): ?>
        <a href="<?= $e($fokusUrl('freigabe')) ?>" id="abschluss__btn">Abschließen</a>
    <?php endif; ?>

    <!-- Ev2Select-Styles kommen zentral aus templates/_ev2-select-styles.php (Layout-Head) -->

    <script>
        // Fahrzeug-Zeilen je Protokollart ein-/ausblenden (v1-Logik) —
        // reagiert zusätzlich live auf prot_by-Änderungen.
        (function () {
            'use strict';
            var protBySelect = document.getElementById('prot_by');

            function currentProtBy() {
                var v = protBySelect ? protBySelect.value : '';
                return v === '0' || v === '1' ? v : null;
            }

            function updateRows() {
                var protBy = currentProtBy();
                ['fzg_transp_row', 'fzg_transp_row_2', 'fzg_transp_row_3'].forEach(function (id) {
                    var row = document.getElementById(id);
                    if (row) row.style.display = protBy === '1' ? 'none' : '';
                });
                ['fzg_na_row', 'fzg_na_row_2', 'fzg_na_row_3'].forEach(function (id) {
                    var row = document.getElementById(id);
                    if (row) row.style.display = protBy === '0' ? 'none' : '';
                });
            }

            updateRows();
            if (protBySelect) protBySelect.addEventListener('change', updateRows);
        })();
    </script>

<?php endif; ?>

<script>
    // Ev2Suggest/Ev2Select manuell initialisieren: der Auto-Init läuft
    // nur auf body[data-page="enotf-v2"], die v1-Look-Seiten tragen den
    // Section-Key als data-page. Auch bei gesperrtem Protokoll — die
    // Trigger erben disabled und alle Selects behalten dieselbe Optik.
    document.addEventListener('DOMContentLoaded', function () {
        if (window.Ev2Select) window.Ev2Select.init(document);
    });
</script>

<?php if (!$istGesperrt && $fokus !== 'freigabe'): ?>
    <script>
        // „An Leitstelle senden" (v1: sendPatientToDispatch) — gegen den
        // v2-Endpoint, Icon-Farblogik wie v1.
        (function () {
            'use strict';
            var btn = document.getElementById('btn-send-patient');
            if (!btn) return;
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                var syncIcon = document.getElementById('pat-sync-icon');
                var icon = syncIcon ? syncIcon.querySelector('i') : null;
                if (icon) icon.style.color = '#f0ad4e';
                fetch(<?= json_encode(EnotfV2Url::api('patient-sync')) ?>, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ enr: <?= json_encode($enr) ?> })
                })
                    .then(function (r) { return r.json().catch(function () { return {}; }); })
                    .then(function (data) {
                        if (data && data.success) {
                            if (icon) icon.style.color = '#f0ad4e';
                        } else {
                            if (icon) icon.style.color = '#dc3545';
                            if (window.Dialog) window.Dialog.alert('Fehler: ' + ((data && data.error) || 'Unbekannter Fehler'), { type: 'error' });
                        }
                    })
                    .catch(function () {
                        if (icon) icon.style.color = '#dc3545';
                        if (window.Dialog) window.Dialog.alert('Verbindungsfehler beim Senden.', { type: 'error' });
                    });
            });
        })();
    </script>
<?php endif; ?>
