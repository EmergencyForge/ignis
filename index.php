<?php
// Session wird durch config.php gestartet (SessionManager)
require_once __DIR__ . '/assets/config/config.php';

if (!\App\Session\SessionManager::isLoggedIn() || !isset($_SESSION['permissions'])) {
    \App\Session\SessionManager::setRedirectFromRequest();
    return \App\Http\Response::redirect(BASE_PATH . 'login');
}

// Die Listen der Plugins nur, wenn das Plugin aktiv ist: die Partials lesen
// dessen Tabellen (intra_edivi, intra_fire_incidents) und Helfer.
$dashboardPlugins = app(\App\Plugins\PluginLoader::class);
$dashboardEnotf   = $dashboardPlugins->isActive('enotf');
$dashboardFiretab = $dashboardPlugins->isActive('firetab');

// Die Seite rendert durch die Hülle (templates/layouts/admin.php):
// Inhalt puffern, App\Helpers\Layout legt Topbar und Sidebar drumherum.
ob_start();
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div id="startpage" class="twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Übersicht</p>
                    <h1>Dashboard</h1>
                    <p class="twplus-page-header__description">Deine wichtigsten Kennzahlen, Dokumente, Anträge und Protokolle auf einen Blick.</p>
                </div>
                <div class="twplus-page-header__actions">
                    <a href="<?= BASE_PATH ?>forms/select" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Antrag einreichen</a>
                </div>
            </div>
            <?php include __DIR__ . '/assets/components/index/stats.php' ?>
            <?php include __DIR__ . '/assets/components/index/setup-checklist.php' ?>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 mt-10">
                <?php include __DIR__ . '/assets/components/index/changelog.php' ?>
                <?php include __DIR__ . '/assets/components/index/blog.php' ?>
            </div>

            <div class="grid grid-cols-1 gap-6 mt-10">
                <section class="ignis-card" data-section="documents" aria-labelledby="dashboard-documents-title">
                    <div class="ignis-card__header">
                        <h2 class="ignis-card__title" id="dashboard-documents-title">Eigene Dokumente</h2>
                    </div>
                    <div class="twplus-table-card__scroll">
                        <?php include __DIR__ . '/assets/components/index/documents.php' ?>
                    </div>
                </section>
                <section class="ignis-card" data-section="applications" aria-labelledby="dashboard-applications-title">
                    <div class="ignis-card__header">
                        <h2 class="ignis-card__title" id="dashboard-applications-title">Eigene Anträge</h2>
                        <div class="ignis-card__actions">
                            <a href="<?= BASE_PATH ?>forms/select" class="ignis-btn ignis-btn--sm ignis-btn--secondary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Antrag einreichen</a>
                        </div>
                    </div>
                    <div class="twplus-table-card__scroll">
                        <?php include __DIR__ . '/assets/components/index/applications.php' ?>
                    </div>
                </section>
                <?php if ($dashboardEnotf): ?>
                    <section class="ignis-card" data-section="enotf" aria-labelledby="dashboard-enotf-title">
                        <div class="ignis-card__header">
                            <h2 class="ignis-card__title" id="dashboard-enotf-title">Eigene eNOTF-Protokolle</h2>
                        </div>
                        <div class="twplus-table-card__scroll">
                            <?php include __DIR__ . '/assets/components/index/protocols.php' ?>
                        </div>
                    </section>
                <?php endif; ?>
                <?php if ($dashboardFiretab): ?>
                    <section class="ignis-card" data-section="firetab" aria-labelledby="dashboard-firetab-title">
                        <div class="ignis-card__header">
                            <h2 class="ignis-card__title" id="dashboard-firetab-title">Eigene fireTab-Protokolle</h2>
                        </div>
                        <div class="twplus-table-card__scroll">
                            <?php include __DIR__ . '/assets/components/index/fire-protocols.php' ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php
echo \App\Helpers\Layout::render('admin', (string) ob_get_clean(), ['SITE_TITLE' => 'Dashboard', 'bodyId' => 'dashboard']);
