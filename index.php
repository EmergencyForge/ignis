<?php
// Session wird durch config.php gestartet (SessionManager)
require_once __DIR__ . '/assets/config/config.php';

if (!\App\Session\SessionManager::isLoggedIn() || !isset($_SESSION['permissions'])) {
    \App\Session\SessionManager::setRedirectFromRequest();
    return \App\Http\Response::redirect(BASE_PATH . 'login');
}

// Die Seite rendert durch die Hülle (templates/layouts/admin.php):
// Inhalt puffern, App\Helpers\Layout legt Topbar und Sidebar drumherum.
ob_start();
?>
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
            <?php include __DIR__ . '/assets/components/index/stats.php' ?>
            <?php include __DIR__ . '/assets/components/index/setup-checklist.php' ?>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2" style="margin-top:var(--space-xl);">
<?php
echo \App\Helpers\Layout::render('admin', (string) ob_get_clean(), ['SITE_TITLE' => 'Dashboard', 'bodyId' => 'dashboard']);
