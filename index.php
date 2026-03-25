<?php
// Session wird durch config.php gestartet (SessionManager)
require_once __DIR__ . '/assets/config/config.php';
require_once __DIR__ . '/assets/config/database.php';
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];

    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Helpers\Flash;

?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="dashboard">
    <!-- PRELOAD -->

    <?php include __DIR__ . "/assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <!-- Page header + stats: tight grouping (related) -->
            <div class="row" id="startpage">
                <div class="col">
                    <h1>Dashboard</h1>
                    <?php
                    Flash::render();
                    ?>
                </div>
            </div>
            <?php include __DIR__ . '/assets/components/index/stats.php' ?>
            <?php include __DIR__ . '/assets/components/index/setup-checklist.php' ?>

            <!-- Content sections: generous spacing between groups -->
            <div class="row" style="margin-top:var(--space-xl)">
                <div class="col intra__tile" data-section="documents">
                    <h4 class="mb-3">Eigene Dokumente</h4>
                    <?php include __DIR__ . '/assets/components/index/documents.php' ?>
                </div>
            </div>
            <div class="row" style="margin-top:var(--space-lg)">
                <div class="col intra__tile" data-section="applications">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h4 class="mb-0">Eigene Anträge</h4>
                        <a href="<?= BASE_PATH ?>antrag/select.php" class="btn btn-success btn-sm"><i class="fa-solid fa-plus"></i> Antrag einreichen</a>
                    </div>
                    <?php include __DIR__ . '/assets/components/index/applications.php' ?>
                </div>
            </div>

            <!-- Protokolle group: tighter spacing (related) -->
            <div class="row" style="margin-top:var(--space-xl)">
                <div class="col intra__tile" data-section="enotf">
                    <h4 class="mb-3">Eigene eNOTF-Protokolle</h4>
                    <?php include __DIR__ . '/assets/components/index/protocols.php' ?>
                </div>
            </div>
            <div class="row" style="margin-top:var(--space-lg)">
                <div class="col intra__tile" data-section="firetab">
                    <h4 class="mb-3">Eigene fireTab-Protokolle</h4>
                    <?php include __DIR__ . '/assets/components/index/fire-protocols.php' ?>
                </div>
            </div>
            <div style="height:var(--space-xl)"></div>
        </div>
    </div>
    <?php include __DIR__ . "/assets/components/footer.php"; ?>
</body>

</html>