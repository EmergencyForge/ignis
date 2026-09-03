<?php

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
?>
<dl class="twplus-stats" aria-label="Systemstatistiken">
    <div class="twplus-stats__item">
        <dt class="twplus-stats__label"><i class="fa-solid fa-users mr-1" aria-hidden="true"></i> Benutzer</dt>
        <dd class="twplus-stats__value" data-count-to="<?= (int)($statsData['users'] ?? 0) ?>">0</dd>
    </div>
    <div class="twplus-stats__item">
        <dt class="twplus-stats__label"><i class="fa-solid fa-id-card mr-1" aria-hidden="true"></i> Mitarbeiter</dt>
        <dd class="twplus-stats__value" data-count-to="<?= (int)($statsData['mitarbeiter'] ?? 0) ?>">0</dd>
    </div>
    <?php if ($statsEnotfActive): ?>
        <div class="twplus-stats__item">
            <dt class="twplus-stats__label"><i class="fa-solid fa-truck-medical mr-1" aria-hidden="true"></i> eNOTF-Protokolle</dt>
            <dd class="twplus-stats__value" data-count-to="<?= (int)($statsData['enotf'] ?? 0) ?>">0</dd>
        </div>
    <?php endif; ?>
    <div class="twplus-stats__item">
        <dt class="twplus-stats__label"><i class="fa-solid fa-folder-open mr-1" aria-hidden="true"></i> Dokumente</dt>
        <dd class="twplus-stats__value" data-count-to="<?= (int)($statsData['dokumente'] ?? 0) ?>">0</dd>
    </div>
</dl>
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
