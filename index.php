<?php
// Session wird durch config.php gestartet (SessionManager)
require_once __DIR__ . '/assets/config/config.php';

use App\Support\Overview;

if (!\App\Session\SessionManager::isLoggedIn() || !isset($_SESSION['permissions'])) {
    \App\Session\SessionManager::setRedirectFromRequest();
    return \EmergencyForge\Http\Response::redirect(BASE_PATH . 'login');
}

// Die Listen der Plugins nur, wenn das Plugin aktiv ist: die Partials lesen
// dessen Tabellen (intra_edivi, intra_fire_incidents) und Helfer.
$dashboardPlugins = app(\App\Plugins\PluginLoader::class);
$dashboardEnotf   = $dashboardPlugins->isActive('enotf');
$dashboardFiretab = $dashboardPlugins->isActive('firetab');
$dashboardForms   = $dashboardPlugins->isActive('forms');

// Gesamtzahl je eigener Liste für den Kartenkopf; die Karten zeigen nur
// die neuesten OwnRecords::PREVIEW, der Rest steht hinter „Alle anzeigen“.
$ownTotals = [
    'documents'    => \App\Support\OwnRecords::documents()->count(),
    'applications' => $dashboardForms ? \App\Support\OwnRecords::applications()->count() : 0,
    'enotf'        => $dashboardEnotf ? \App\Support\OwnRecords::enotfProtocols()->count() : 0,
    'firetab'      => $dashboardFiretab ? \App\Support\OwnRecords::firetabProtocols()->count() : 0,
];
$ownAllLink = static fn (string $path): string => '<a class="ignis-btn ignis-btn--secondary ignis-btn--sm" href="' . htmlspecialchars(BASE_PATH . $path) . '">Alle anzeigen</a>';

// Übersicht wie „ignis im Dienst“ aus der Spec zu ui 0.7.0: Kacheln, Einsätze,
// Fahrzeuge und Hinweise, jeweils nur mit Recht und echten Daten.
$dashboardNow       = new DateTimeImmutable();
$dashboardIncidents = Overview::incidents($dashboardPlugins, $dashboardNow);
$dashboardVehicles  = Overview::vehicles();
$dashboardProtocols = Overview::openProtocols($dashboardPlugins, $dashboardNow);

// Delta der Einsätze gegen gestern bis zur selben Uhrzeit, in der Kachel
// und im Fuß der Schale. Mehr Einsätze sind weder gut noch schlecht, die
// Pille bleibt deshalb neutral. Die Richtung steht als Text für
// Screenreader in der Pille, ein aria-label an einem span lesen sie nicht
// vor.
$dashboardDelta = '';
if ($dashboardIncidents !== null) {
    $dashboardDiff = $dashboardIncidents['today'] - $dashboardIncidents['yesterday'];
    $dashboardDelta = '<span class="ignis-delta" data-trend="neutral">' . match (true) {
        $dashboardDiff > 0 => '<span class="ignis-sr-only">plus </span><i class="fa-solid fa-arrow-up" aria-hidden="true"></i>' . $dashboardDiff,
        $dashboardDiff < 0 => '<span class="ignis-sr-only">minus </span><i class="fa-solid fa-arrow-down" aria-hidden="true"></i>' . -$dashboardDiff,
        default            => '<span aria-hidden="true">0</span><span class="ignis-sr-only">unverändert</span>',
    } . '</span>';
}

// Die Seite rendert durch die Hülle (templates/layouts/admin.php):
// Inhalt puffern, App\Helpers\Layout legt Topbar und Sidebar drumherum.
ob_start();
include __DIR__ . '/assets/components/index/hints.php';
include __DIR__ . '/assets/components/index/changelog.php';
$dashboardSide = trim((string) ob_get_clean());

ob_start();
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <header id="startpage" class="twplus-page-header">
                <div class="twplus-page-header__copy">
                    <h1>Dashboard</h1>
                    <p class="twplus-page-header__description"><?= htmlspecialchars(Overview::dateLabel($dashboardNow)) ?></p>
                </div>
                <?php if ($dashboardForms): ?>
                <div class="twplus-page-header__actions">
                    <a href="<?= BASE_PATH ?>forms/select" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Antrag einreichen</a>
                </div>
                <?php endif; ?>
            </header>
            <?php include __DIR__ . '/assets/components/index/setup-checklist.php' ?>
            <?php include __DIR__ . '/assets/components/index/kpis.php' ?>

            <?php // Ohne Hinweise und Ankündigungen bleibt nur die Hauptspalte, ohne leere 320 px daneben. ?>
            <?php if ($dashboardSide !== ''): ?><div class="ignis-dash"><?php endif; ?>
                <div class="ignis-dash__col<?= $dashboardSide === '' ? ' mt-4' : '' ?>">
                    <?php include __DIR__ . '/assets/components/index/incidents.php' ?>
                    <?php include __DIR__ . '/assets/components/index/vehicles.php' ?>
                    <section class="ignis-card ignis-card--table" data-ignis-reveal data-section="documents" aria-labelledby="dashboard-documents-title">
                        <div class="ignis-card__header">
                            <h2 class="ignis-card__title" id="dashboard-documents-title">Eigene Dokumente <span class="ignis-count"><?= $ownTotals['documents'] ?></span></h2>
                            <?php if ($ownTotals['documents'] > 0): ?>
                                <div class="ignis-card__actions"><?= $ownAllLink('me/documents') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="ignis-card__scroll">
                            <?php include __DIR__ . '/assets/components/index/documents.php' ?>
                        </div>
                    </section>
                    <?php if ($dashboardForms): ?>
                    <section class="ignis-card ignis-card--table" data-ignis-reveal data-section="applications" aria-labelledby="dashboard-applications-title">
                        <div class="ignis-card__header">
                            <h2 class="ignis-card__title" id="dashboard-applications-title">Eigene Anträge <span class="ignis-count"><?= $ownTotals['applications'] ?></span></h2>
                            <div class="ignis-card__actions">
                                <a href="<?= BASE_PATH ?>forms/select" class="ignis-btn ignis-btn--sm ignis-btn--secondary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Antrag einreichen</a>
                                <?php if ($ownTotals['applications'] > 0): ?>
                                    <?= $ownAllLink('me/applications') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ignis-card__scroll">
                            <?php include __DIR__ . '/assets/components/index/applications.php' ?>
                        </div>
                    </section>
                    <?php endif; ?>
                    <?php if ($dashboardEnotf): ?>
                        <section class="ignis-card ignis-card--table" data-ignis-reveal data-section="enotf" aria-labelledby="dashboard-enotf-title">
                            <div class="ignis-card__header">
                                <h2 class="ignis-card__title" id="dashboard-enotf-title">Eigene eNOTF-Protokolle <span class="ignis-count"><?= $ownTotals['enotf'] ?></span></h2>
                                <?php if ($ownTotals['enotf'] > 0): ?>
                                    <div class="ignis-card__actions"><?= $ownAllLink('me/protocols?source=enotf') ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="ignis-card__scroll">
                                <?php include __DIR__ . '/assets/components/index/protocols.php' ?>
                            </div>
                        </section>
                    <?php endif; ?>
                    <?php if ($dashboardFiretab): ?>
                        <section class="ignis-card ignis-card--table" data-ignis-reveal data-section="firetab" aria-labelledby="dashboard-firetab-title">
                            <div class="ignis-card__header">
                                <h2 class="ignis-card__title" id="dashboard-firetab-title">Eigene fireTab-Protokolle <span class="ignis-count"><?= $ownTotals['firetab'] ?></span></h2>
                                <?php if ($ownTotals['firetab'] > 0): ?>
                                    <div class="ignis-card__actions"><?= $ownAllLink('me/protocols?source=firetab') ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="ignis-card__scroll">
                                <?php include __DIR__ . '/assets/components/index/fire-protocols.php' ?>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>
            <?php if ($dashboardSide !== ''): ?>
                <div class="ignis-dash__col ignis-dash__side">
                    <?= $dashboardSide ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php
echo \App\Helpers\Layout::render('admin', (string) ob_get_clean(), [
    'SITE_TITLE' => 'Dashboard',
    'bodyId'     => 'dashboard',
    'layoutHead' => $dashboardIncidents !== null ? '<script type="module" src="' . BASE_PATH . 'assets/js/ui/spark.js"></script>' : '',
]);
