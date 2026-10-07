<?php
/**
 * Partial: Reanimationssituation (7 Abschluss), eingebunden von
 * v1 abschluss/4.php und v2 abschluss.php (?t=rea).
 *
 * Spaltenbaum wie auf dem Tablet: Status → (Begründung | Details) →
 * Optionen des gewählten Details. "keine Reanimationssituation" und
 * "Reanimation nicht durchgeführt, weil …" schließen den Baum ab, nur
 * "Reanimation durchgeführt" öffnet die Details. Gespeichert wird über
 * den jeweiligen Autosave (alle Felder sind normale benannte Inputs).
 *
 * Erwartet:
 * @var array<string,mixed>|\ArrayAccess<string,mixed> $reaDaten   Protokollzeile
 * @var bool   $reaGesperrt   freigegeben → alles disabled
 * @var string $reaCol        Klasse für Menüspalten (v1 w-2/12, v2 col-2)
 * @var string $reaColWide    Klasse für Optionsspalten (v1 w-3/12, v2 col-3)
 */

use Plugin\Enotf\Helpers\ReanimationCatalog;

$reaE = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$reaWert = static fn (string $feld): string => (string) ($reaDaten[$feld] ?? '');
$reaDis = $reaGesperrt ? ' disabled' : '';
$reaStatus = $reaWert('rea_status');
$reaDurchgefuehrt = $reaStatus === (string) ReanimationCatalog::STATUS_DURCHGEFUEHRT;
$reaNicht = isset(ReanimationCatalog::NICHT_DURCHGEFUEHRT[(int) $reaStatus]);

$reaRadio = static function (string $name, int $code, string $label) use ($reaE, $reaWert, $reaDis): string {
    $id = $name . '-' . $code;
    $checked = $reaWert($name) === (string) $code ? ' checked' : '';
    return '<input type="radio" class="btn-check" id="' . $reaE($id) . '" name="' . $reaE($name) . '" value="' . $code . '"' . $checked . $reaDis . ' autocomplete="off">'
        . '<label for="' . $reaE($id) . '">' . $reaE($label) . '</label>';
};
// Ein Schalter wie „erfolglos“ ist selbst die Auswahl und steht direkt in der
// Detailspalte. Als Link öffnete er eine Spalte, in der nur er noch einmal stand.
$reaToggle = static function (string $name, string $label) use ($reaE, $reaWert, $reaDis): string {
    $id = str_replace('_', '', $name) . '_1';
    $checked = $reaWert($name) === '1' ? ' checked' : '';
    return '<input type="checkbox" class="btn-check" id="' . $reaE($id) . '" name="' . $reaE($name) . '" value="1"' . $checked . $reaDis . ' autocomplete="off">'
        . '<label for="' . $reaE($id) . '">' . $reaE($label) . '</label>';
};
?>
<div class="<?= $reaE($reaCol) ?> d-flex flex-column edivi__interactbutton px-3" id="rea-tree">
    <?= $reaRadio('rea_status', 1, ReanimationCatalog::STATUS[1]) ?>
    <?= $reaRadio('rea_status', 2, ReanimationCatalog::STATUS[2]) ?>
    <a href="#" data-rea-open="nicht"><span>Reanimation nicht durchgeführt,</span></a>
    <a href="#" data-rea-open="details" data-rea-requires="<?= $reaE(implode(',', ReanimationCatalog::pflichtDetails())) ?>"<?= $reaDurchgefuehrt ? '' : ' style="display:none"' ?>><span>Details</span></a>
</div>

<?php // Ein-/Ausblenden am Wrapper: .d-flex setzt display mit !important und schlägt sonst display:none ?>
<div data-rea-panel="nicht" style="display:<?= $reaNicht ? 'contents' : 'none' ?>">
    <div class="<?= $reaE($reaColWide) ?> d-flex flex-column edivi__interactbutton px-3">
        <?php foreach (ReanimationCatalog::NICHT_DURCHGEFUEHRT as $code => $label) : ?>
            <?= $reaRadio('rea_status', $code, $label) ?>
        <?php endforeach; ?>
    </div>
</div>

<div data-rea-panel="details" style="display:<?= $reaDurchgefuehrt ? 'contents' : 'none' ?>">
    <div class="<?= $reaE($reaCol) ?> d-flex flex-column edivi__interactbutton-more px-3">
        <?php foreach (ReanimationCatalog::DETAILS as $feld => $detail) : ?>
            <?php if ($detail['typ'] === 'toggle') : ?>
                <?= $reaToggle($feld, $detail['label']) ?>
            <?php else : ?>
                <a href="#" data-rea-detail-open="<?= $reaE($feld) ?>"<?= $detail['pflicht'] ? ' data-rea-requires="' . $reaE($feld) . '"' : '' ?>><span><?= $reaE($detail['label']) ?></span></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<?php foreach (ReanimationCatalog::DETAILS as $feld => $detail) : ?>
    <?php if ($detail['typ'] === 'toggle') {
        continue;
    } ?>
    <div data-rea-detail="<?= $reaE($feld) ?>" style="display:none">
        <?php if ($detail['typ'] === 'radio') : ?>
            <?php foreach (array_chunk($detail['optionen'], 9, true) as $spalte) : ?>
                <div class="<?= $reaE($reaColWide) ?> d-flex flex-column edivi__interactbutton px-3">
                    <?php foreach ($spalte as $code => $label) : ?>
                        <?= $reaRadio($feld, $code, $label) ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="<?= $reaE($reaCol) ?> d-flex flex-column edivi__interactbutton px-3">
                <label class="edivi__interactbutton-text"><?= $reaE($feld === 'rea_tod_zeit' ? 'Todeszeitpunkt' : 'Uhrzeit') ?></label>
                <input type="time" name="<?= $reaE($feld) ?>" id="<?= $reaE($feld) ?>" class="edivi__interactbutton-input" value="<?= $reaE($reaWert($feld)) ?>"<?= $reaGesperrt ? ' readonly' : '' ?>>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<script>
    // Spaltenbaum ein-/ausblenden und rote Pflichtpunkte für die Details
    // (eigenes data-rea-requires, weil die Details nur bei Rea Pflicht sind
    // und deshalb nicht in den globalen data-requires-Regeln stehen)
    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('rea-tree');
        if (!root) return;
        var container = root.parentElement;
        var panels = container.querySelectorAll('[data-rea-panel]');
        var details = container.querySelectorAll('[data-rea-detail]');
        var detailsLink = container.querySelector('[data-rea-open="details"]');

        function show(el, on, mode) { el.style.display = on ? (mode || '') : 'none'; }

        function openPanel(name) {
            panels.forEach(function (p) { show(p, p.dataset.reaPanel === name, 'contents'); });
            container.querySelectorAll('[data-rea-open]').forEach(function (a) {
                a.classList.toggle('active', a.dataset.reaOpen === name);
            });
            openDetail(null);
        }

        function openDetail(feld) {
            details.forEach(function (d) { show(d, d.dataset.reaDetail === feld, 'contents'); });
            container.querySelectorAll('[data-rea-detail-open]').forEach(function (a) {
                a.classList.toggle('active', a.dataset.reaDetailOpen === feld);
            });
        }

        function filled(feld) {
            var inputs = container.querySelectorAll('[name="' + feld + '"]');
            for (var i = 0; i < inputs.length; i++) {
                var el = inputs[i];
                if ((el.type === 'radio' || el.type === 'checkbox') ? el.checked : el.value.trim() !== '') return true;
            }
            return false;
        }

        function validate() {
            container.querySelectorAll('[data-rea-requires]').forEach(function (a) {
                var felder = a.dataset.reaRequires.split(',');
                var n = felder.filter(filled).length;
                a.classList.remove('edivi__validation-green', 'edivi__validation-yellow', 'edivi__validation-red');
                a.classList.add(n === felder.length ? 'edivi__validation-green' : (n > 0 ? 'edivi__validation-yellow' : 'edivi__validation-red'));
            });
        }

        container.addEventListener('click', function (e) {
            var a = e.target.closest('[data-rea-open], [data-rea-detail-open]');
            if (!a) return;
            e.preventDefault();
            if (a.dataset.reaOpen) openPanel(a.dataset.reaOpen);
            else openDetail(a.dataset.reaDetailOpen);
        });

        container.addEventListener('change', function (e) {
            if (e.target.name === 'rea_status') {
                var durchgefuehrt = e.target.value === '<?= ReanimationCatalog::STATUS_DURCHGEFUEHRT ?>';
                show(detailsLink, durchgefuehrt);
                if (durchgefuehrt) openPanel('details');
                else if (e.target.value === '1') openPanel(null);
            }
            validate();
        });
        container.addEventListener('blur', validate, true);

        openPanel(<?= $reaDurchgefuehrt ? "'details'" : ($reaNicht ? "'nicht'" : 'null') ?>);
        validate();
    });
</script>
