<?php

use App\Auth\Permissions;
use Illuminate\Database\Capsule\Manager as Capsule;

$statDefinitions = [
    ['users', 'Benutzer', 'intra_users', 'users/list', ['admin', 'users.view']],
    ['personnel', 'Mitarbeiter', 'intra_mitarbeiter', 'personnel/list', ['admin', 'personnel.view']],
    ['documents', 'Dokumente', 'intra_mitarbeiter_dokumente', null, ['admin', 'personnel.view']],
];
if ($dashboardPlugins->isActive('enotf')) {
    $statDefinitions[] = ['enotf', 'eNOTF-Protokolle', 'intra_edivi', 'enotf/admin/list', ['admin', 'edivi.view']];
}
$statTiles = [];
foreach ($statDefinitions as [$key, $label, $table, $href, $permissions]) {
    if (!Permissions::check($permissions)) {
        continue;
    }
    try {
        $statTiles[] = [$label, Capsule::table($table)->count(), $href];
    } catch (\Throwable $error) {
        \App\Logging\Logger::error('Dashboard-Zähler nicht verfügbar', ['counter' => $key, 'error' => $error->getMessage()]);
        $statTiles[] = [$label, null, $href];
    }
}
?>
<?php if ($statTiles !== []): ?>
<div class="twplus-stats" aria-label="Systemstatistiken">
    <?php foreach ($statTiles as [$statLabel, $statValue, $statHref]): ?>
        <<?= $statHref !== null ? 'a href="' . htmlspecialchars(BASE_PATH . $statHref, ENT_QUOTES) . '"' : 'div' ?> class="twplus-stats__item">
            <span class="twplus-stats__label"><?= htmlspecialchars($statLabel) ?></span>
            <span class="twplus-stats__value"><?= $statValue === null ? 'Nicht verfügbar' : (int) $statValue ?></span>
        </<?= $statHref !== null ? 'a' : 'div' ?>>
    <?php endforeach; ?>
</div>
<?php endif; ?>
