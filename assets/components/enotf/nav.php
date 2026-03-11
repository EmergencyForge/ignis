<div class="col-12 col-md-1 d-flex flex-column" id="edivi__nidanav">
    <a href="<?= BASE_PATH ?>enotf/protokoll/rettdaten/index.php?enr=<?= $daten['enr'] ?>" data-page="stammdaten"
        data-requires="patsex,transportziel,ezeit,salarm,sende">
        <span>Rett. Daten</span>
    </a>
    <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/index.php?enr=<?= $daten['enr'] ?>" data-page="erstbefund"
        data-requires="awfrei_1,zyanose_1,b_symptome,b_auskult,c_kreislauf,c_ekg,d_bewusstsein,d_ex_1,d_pupillenw_1,d_pupillenw_2,d_lichtreakt_1,d_lichtreakt_2,d_gcs_1,d_gcs_2,d_gcs_3,spo2,atemfreq,rrsys,herzfreq,bz,psych,c_puls_rad,c_puls_reg">
        <span>Erstbefund</span>
    </a>
    <a href="<?= BASE_PATH ?>enotf/protokoll/anamnese/index.php?enr=<?= $daten['enr'] ?>" data-page="anamnese" data-requires="naca_initial, elokation"><span>Anamnese</span></a>
    <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/index.php?enr=<?= $daten['enr'] ?>" data-page="diagnose" data-requires="diagnose_haupt"><span>Diagnose</span></a>
    <a href="<?= BASE_PATH ?>enotf/protokoll/verlauf/index.php?enr=<?= $daten['enr'] ?>" data-page="verlauf">
        <span>Verlauf</span>
    </a>
    <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/index.php?enr=<?= $daten['enr'] ?>" data-page="massnahmen"
        data-requires="awsicherung_neu,b_beatmung,c_zugang,medis">
        <span>Maßnahmen</span>
    </a>
    <?php if ($daten['prot_by'] == 1) : ?>
        <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/index.php?enr=<?= $daten['enr'] ?>" data-page="abschluss"
            data-requires="transportziel,pfname,ebesonderheiten">
            <span>Abschluss</span>
        </a>
    <?php else : ?>
        <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/index.php?enr=<?= $daten['enr'] ?>" data-page="abschluss"
            data-requires="pfname,ebesonderheiten,na_nachf">
            <span>Abschluss</span>
        </a>
    <?php endif; ?>
</div>

<script>
    $(document).ready(function() {
        const currentPage = $("body").data("page");
        $("#edivi__nidanav a").removeClass("active");
        $("#edivi__nidanav a[data-page='" + currentPage + "']").addClass("active");
    });
</script>