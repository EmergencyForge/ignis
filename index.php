<?php
// Session wird durch config.php gestartet (SessionManager)
require_once __DIR__ . '/assets/config/config.php';

if (!\App\Session\SessionManager::isLoggedIn() || !isset($_SESSION['permissions'])) {
    \App\Session\SessionManager::setRedirectFromRequest();
    return \App\Http\Response::redirect(BASE_PATH . 'login');
}

use App\Helpers\Flash;

?>

<!DOCTYPE html>
<html lang="de" data-theme="light">

<head>
    <?php include __DIR__ . '/assets/components/_base/admin/head.php'; ?>
</head>

<body data-theme="dark" data-page="dashboard">
    <!-- PRELOAD -->

    <?php include __DIR__ . "/assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="twplus-page">
            <!-- Page header + stats: tight grouping (related) -->
            <div id="startpage" class="twplus-page-header">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Übersicht</p>
                    <h1>Dashboard</h1>
                    <p class="twplus-page-header__description">Deine wichtigsten Kennzahlen, Dokumente, Anträge und Protokolle auf einen Blick.</p>
                </div>
            </div>
            <?php Flash::render(); ?>
            <?php include __DIR__ . '/assets/components/index/stats.php' ?>
            <?php include __DIR__ . '/assets/components/index/setup-checklist.php' ?>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2" style="margin-top:var(--space-xl);">
                <?php include __DIR__ . '/assets/components/index/changelog.php' ?>
                <?php include __DIR__ . '/assets/components/index/blog.php' ?>
            </div>

            <!-- Content sections: generous spacing between groups -->
            <div class="twplus-dashboard-widget" data-section="documents" style="margin-top:var(--space-xl)">
                <div class="twplus-section-card__header"><h4 class="twplus-section-card__title">Eigene Dokumente</h4></div>
                <?php include __DIR__ . '/assets/components/index/documents.php' ?>
            </div>
            <div class="twplus-dashboard-widget" data-section="applications" style="margin-top:var(--space-lg)">
                <div class="twplus-section-card__header">
                    <h4 class="twplus-section-card__title">Eigene Anträge</h4>
                    <a href="<?= BASE_PATH ?>forms/select" class="ignis-btn ignis-btn--sm ignis-btn--soft-success"><i class="fa-solid fa-plus"></i> Antrag einreichen</a>
                </div>
                <?php include __DIR__ . '/assets/components/index/applications.php' ?>
            </div>

            <!-- Protokolle group: tighter spacing (related) -->
            <div class="twplus-dashboard-widget" data-section="enotf" style="margin-top:var(--space-xl)">
                <div class="twplus-section-card__header"><h4 class="twplus-section-card__title">Eigene eNOTF-Protokolle</h4></div>
                <?php include __DIR__ . '/assets/components/index/protocols.php' ?>
            </div>
            <div class="twplus-dashboard-widget" data-section="firetab" style="margin-top:var(--space-lg)">
                <div class="twplus-section-card__header"><h4 class="twplus-section-card__title">Eigene fireTab-Protokolle</h4></div>
                <?php include __DIR__ . '/assets/components/index/fire-protocols.php' ?>
            </div>
            <div style="height:var(--space-xl)"></div>
        </div>
    </div>
    <?php include __DIR__ . "/assets/components/footer.php"; ?>
</body>

</html>
