<?php
// GET /desk/widgets: Kennzahlen und Hinweise der Übersicht als Widgets für
// den Schreibtisch und den Startbildschirm des Tablets. desk.js holt das
// Fragment einmal beim Start und setzt es selbst ein, deshalb ohne Layout.
require_once __DIR__ . '/assets/config/config.php';

use App\Support\Overview;
use EmergencyForge\Http\Response;

if (!\App\Session\SessionManager::isLoggedIn() || !isset($_SESSION['permissions'])) {
    return Response::html('', 401);
}

$dashboardPlugins   = app(\App\Plugins\PluginLoader::class);
$dashboardNow       = new DateTimeImmutable();
$dashboardIncidents = Overview::incidents($dashboardPlugins, $dashboardNow);
$dashboardVehicles  = Overview::vehicles();
$dashboardProtocols = Overview::openProtocols($dashboardPlugins, $dashboardNow);
$dashboardDelta     = Overview::delta($dashboardIncidents);

ob_start();
?>
<div class="ignis-desk-widgets">
    <?php include __DIR__ . '/assets/components/index/kpis.php' ?>
    <?php include __DIR__ . '/assets/components/index/hints.php' ?>
</div>
<?php
return Response::html((string) ob_get_clean());
