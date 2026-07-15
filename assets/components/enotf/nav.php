<?php
require_once __DIR__ . '/../../functions/enotf/conditions.php';
use Plugin\Enotf\Helpers\EnotfUrl;
$_navTz = isset($daten['transportziel']) ? (int)$daten['transportziel'] : null;
$_navEnr = $daten['enr'];
?>
<div class="col-12 col-md-1 d-flex flex-column" id="edivi__nidanav">
    <a href="<?= EnotfUrl::protokoll($_navEnr, 'rettdaten') ?>" data-page="stammdaten"
        data-requires="<?= enotf_get_nav_requires($_navTz, 1) ?>">
        <span>Rett. Daten</span>
    </a>
    <a href="<?= EnotfUrl::protokoll($_navEnr, 'erstbefund') ?>" data-page="erstbefund"
        data-requires="<?= enotf_get_nav_requires($_navTz, 2) ?>">
        <span>Erstbefund</span>
    </a>
    <a href="<?= EnotfUrl::protokoll($_navEnr, 'anamnese') ?>" data-page="anamnese"
        data-requires="<?= enotf_get_nav_requires($_navTz, 3) ?>">
        <span>Anamnese</span>
    </a>
    <a href="<?= EnotfUrl::protokoll($_navEnr, 'diagnose') ?>" data-page="diagnose"
        data-requires="<?= enotf_get_nav_requires($_navTz, 4) ?>">
        <span>Diagnose</span>
    </a>
    <a href="<?= EnotfUrl::protokoll($_navEnr, 'verlauf') ?>" data-page="verlauf">
        <span>Verlauf</span>
    </a>
    <a href="<?= EnotfUrl::protokoll($_navEnr, 'massnahmen') ?>" data-page="massnahmen"
        data-requires="<?= enotf_get_nav_requires($_navTz, 6) ?>">
        <span>Maßnahmen</span>
    </a>
    <a href="<?= EnotfUrl::protokoll($_navEnr, 'abschluss') ?>" data-page="abschluss"
        data-requires="<?= enotf_get_nav_requires($_navTz, 7) ?>">
        <span>Abschluss</span>
    </a>
</div>

<script>
    $(document).ready(function() {
        const currentPage = $("body").data("page");
        $("#edivi__nidanav a").removeClass("active");
        $("#edivi__nidanav a[data-page='" + currentPage + "']").addClass("active");
    });
</script>
