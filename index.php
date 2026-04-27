<?php
// Session wird durch config.php gestartet (SessionManager)
require_once __DIR__ . '/assets/config/config.php';
require_once __DIR__ . '/assets/config/database.php';
if (!\App\Session\SessionManager::isLoggedIn() || !isset($_SESSION['permissions'])) {
    \App\Session\SessionManager::setRedirectFromRequest();
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
            <div id="startpage">
                <h1>Dashboard</h1>
                <?php Flash::render(); ?>
            </div>
            <?php include __DIR__ . '/assets/components/index/stats.php' ?>
            <?php include __DIR__ . '/assets/components/index/setup-checklist.php' ?>

            <!-- Content sections: generous spacing between groups -->
            <div class="intra__tile" data-section="documents" style="margin-top:var(--space-xl)">
                <h4 class="mb-3">Eigene Dokumente</h4>
                <?php include __DIR__ . '/assets/components/index/documents.php' ?>
            </div>
            <div class="intra__tile" data-section="applications" style="margin-top:var(--space-lg)">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="mb-0">Eigene Anträge</h4>
                    <a href="<?= BASE_PATH ?>antrag/select.php" class="ignis-btn ignis-btn--sm ignis-btn--soft-success"><i class="fa-solid fa-plus"></i> Antrag einreichen</a>
                </div>
                <?php include __DIR__ . '/assets/components/index/applications.php' ?>
            </div>

            <!-- Protokolle group: tighter spacing (related) -->
            <div class="intra__tile" data-section="enotf" style="margin-top:var(--space-xl)">
                <h4 class="mb-3">Eigene eNOTF-Protokolle</h4>
                <?php include __DIR__ . '/assets/components/index/protocols.php' ?>
            </div>
            <div class="intra__tile" data-section="firetab" style="margin-top:var(--space-lg)">
                <h4 class="mb-3">Eigene fireTab-Protokolle</h4>
                <?php include __DIR__ . '/assets/components/index/fire-protocols.php' ?>
            </div>
            <div style="height:var(--space-xl)"></div>
        </div>
    </div>
    <?php include __DIR__ . "/assets/components/footer.php"; ?>
</body>

</html>