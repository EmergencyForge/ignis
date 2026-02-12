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
<html lang="en" data-bs-theme="light">

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
            <div class="row" id="startpage">
                <div class="col">
                    <h1>Dashboard</h1>
                    <?php
                    Flash::render();
                    ?>
                </div>
            </div>
            <?php include __DIR__ . '/assets/components/index/stats.php' ?>
            <div class="row mt-4">
                <div class="col intra__tile">
                    <h4 class="mt-2 mb-3">Eigene Dokumente</h4>
                    <?php include __DIR__ . '/assets/components/index/documents.php' ?>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col intra__tile">
                    <div class="row">
                        <div class="col">
                            <h4 class="mt-2 mb-3">Eigene Anträge</h4>
                        </div>
                        <div class="col d-flex justify-content-end align-items-center">
                            <a href="<?= BASE_PATH ?>antrag/select.php" class="btn btn-success btn-sm"><i class="fa-solid fa-plus"></i> Antrag einreichen</a>
                        </div>
                    </div>
                    <?php include __DIR__ . '/assets/components/index/applications.php' ?>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col intra__tile">
                    <h4 class="mt-2 mb-3">Eigene eNOTF-Protokolle</h4>
                    <?php include __DIR__ . '/assets/components/index/protocols.php' ?>
                </div>
            </div>
            <div class="row mt-4 mb-5">
                <div class="col intra__tile">
                    <h4 class="mt-2 mb-3">Eigene fireTab-Protokolle</h4>
                    <?php include __DIR__ . '/assets/components/index/fire-protocols.php' ?>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . "/assets/components/footer.php"; ?>
</body>

</html>