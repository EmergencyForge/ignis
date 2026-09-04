<?php

use App\Auth\Permissions;
use Illuminate\Database\Capsule\Manager as Capsule;

// Optimiert: Eine UNION-Query statt separater Queries. Die eNOTF-Zahl
// läuft getrennt — intra_edivi gehört zum Plugin, und eine fehlende
// Tabelle würde sonst die komplette UNION (alle vier Werte) reißen.
$statsRows = Capsule::table('intra_users')
    ->selectRaw("'users' as stat_type, COUNT(*) as stat_count")
    ->unionAll(Capsule::table('intra_mitarbeiter')->selectRaw("'mitarbeiter', COUNT(*)"))
    ->unionAll(Capsule::table('intra_mitarbeiter_dokumente')->selectRaw("'dokumente', COUNT(*)"))
    ->get();
$statsData = [];
foreach ($statsRows as $row) {
    $statsData[$row->stat_type] = (int)$row->stat_count;
}

$statsEnotfActive = function_exists('app') && app(\App\Plugins\PluginLoader::class)->isActive('enotf');
if ($statsEnotfActive) {
    try {
        $statsData['enotf'] = (int)Capsule::table('intra_edivi')->count();
    } catch (Exception) {
        $statsEnotfActive = false;
    }
}

// Kachel = Weg zur Liste: wer die Liste sehen darf, bekommt einen Link,
// sonst bleibt es eine Zahl. Rechte wie in config/navigation.php.
$statTiles = [
    ['Benutzer', 'fa-solid fa-users', (int) ($statsData['users'] ?? 0), BASE_PATH . 'users/list', ['admin', 'users.view']],
    ['Mitarbeiter', 'fa-solid fa-id-card', (int) ($statsData['mitarbeiter'] ?? 0), BASE_PATH . 'personnel/list', ['admin', 'personnel.view']],
];
if ($statsEnotfActive) {
    $statTiles[] = ['eNOTF-Protokolle', 'fa-solid fa-truck-medical', (int) ($statsData['enotf'] ?? 0), BASE_PATH . 'enotf/admin/list', ['admin', 'edivi.view']];
}
$statTiles[] = ['Dokumente', 'fa-solid fa-folder-open', (int) ($statsData['dokumente'] ?? 0), null, []];
?>
<div class="twplus-stats" aria-label="Systemstatistiken">
    <?php foreach ($statTiles as [$statLabel, $statIcon, $statValue, $statHref, $statPermissions]):
        $statLinked = $statHref !== null && Permissions::check($statPermissions);
    ?>
        <<?= $statLinked ? 'a href="' . htmlspecialchars($statHref) . '"' : 'div' ?> class="twplus-stats__item">
            <span class="twplus-stats__label"><i class="<?= $statIcon ?> mr-1" aria-hidden="true"></i> <?= $statLabel ?><?= $statLinked ? ' <i class="fa-solid fa-arrow-right twplus-stats__arrow" aria-hidden="true"></i>' : '' ?></span>
            <span class="twplus-stats__value" data-count-to="<?= $statValue ?>">0</span>
        </<?= $statLinked ? 'a' : 'div' ?>>
    <?php endforeach; ?>
</div>
<script>
// Stats count-up: command center power-on effect
(function() {
    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var counters = document.querySelectorAll('[data-count-to]');
    if (!counters.length) return;

    // Reduced motion: show final values immediately
    if (prefersReducedMotion) {
        counters.forEach(function(el) { el.textContent = el.getAttribute('data-count-to'); });
        return;
    }

    var duration = 600; // ms total
    var stagger = 80;   // ms between each counter start

    counters.forEach(function(el, i) {
        var target = parseInt(el.getAttribute('data-count-to'), 10) || 0;
        if (target === 0) return;

        var startTime = null;
        var delay = i * stagger;

        function easeOutExpo(t) {
            return t >= 1 ? 1 : 1 - Math.pow(2, -10 * t);
        }

        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var elapsed = timestamp - startTime - delay;
            if (elapsed < 0) { requestAnimationFrame(step); return; }

            var progress = Math.min(elapsed / duration, 1);
            var value = Math.round(easeOutExpo(progress) * target);
            el.textContent = value;

            if (progress < 1) requestAnimationFrame(step);
        }

        requestAnimationFrame(step);
    });
})();
</script>
