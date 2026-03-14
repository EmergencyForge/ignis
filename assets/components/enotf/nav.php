<?php
require_once __DIR__ . '/../../functions/enotf/conditions.php';
$_navTz = isset($daten['transportziel']) ? (int)$daten['transportziel'] : null;
?>
<div class="col-12 col-md-1 d-flex flex-column" id="edivi__nidanav">
    <a href="<?= BASE_PATH ?>enotf/protokoll/rettdaten/index.php?enr=<?= $daten['enr'] ?>" data-page="stammdaten"
        data-requires="<?= enotf_get_nav_requires($_navTz, 1) ?>">
        <span>Rett. Daten</span>
    </a>
    <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/index.php?enr=<?= $daten['enr'] ?>" data-page="erstbefund"
        data-requires="<?= enotf_get_nav_requires($_navTz, 2) ?>">
        <span>Erstbefund</span>
    </a>
    <a href="<?= BASE_PATH ?>enotf/protokoll/anamnese/index.php?enr=<?= $daten['enr'] ?>" data-page="anamnese"
        data-requires="<?= enotf_get_nav_requires($_navTz, 3) ?>">
        <span>Anamnese</span>
    </a>
    <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/index.php?enr=<?= $daten['enr'] ?>" data-page="diagnose"
        data-requires="<?= enotf_get_nav_requires($_navTz, 4) ?>">
        <span>Diagnose</span>
    </a>
    <a href="<?= BASE_PATH ?>enotf/protokoll/verlauf/index.php?enr=<?= $daten['enr'] ?>" data-page="verlauf">
        <span>Verlauf</span>
    </a>
    <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/index.php?enr=<?= $daten['enr'] ?>" data-page="massnahmen"
        data-requires="<?= enotf_get_nav_requires($_navTz, 6) ?>">
        <span>Maßnahmen</span>
    </a>
    <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/index.php?enr=<?= $daten['enr'] ?>" data-page="abschluss"
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
