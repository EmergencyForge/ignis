<?php
// Optimiert: Eine UNION-Query statt 4 separate Queries
$statsQuery = "
    SELECT 'users' as stat_type, COUNT(*) as stat_count FROM intra_users
    UNION ALL
    SELECT 'mitarbeiter', COUNT(*) FROM intra_mitarbeiter
    UNION ALL
    SELECT 'enotf', COUNT(*) FROM intra_edivi
    UNION ALL
    SELECT 'dokumente', COUNT(*) FROM intra_mitarbeiter_dokumente
";
$statsStmt = $pdo->query($statsQuery);
$statsData = [];
while ($row = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
    $statsData[$row['stat_type']] = (int)$row['stat_count'];
}
?>
<div class="intra__stats-strip">
    <div class="intra__stat-item">
        <span class="intra__stat-value" data-count-to="<?= (int)($statsData['users'] ?? 0) ?>">0</span>
        <span class="intra__stat-label">Benutzer</span>
    </div>
    <div class="intra__stat-item">
        <span class="intra__stat-value" data-count-to="<?= (int)($statsData['mitarbeiter'] ?? 0) ?>">0</span>
        <span class="intra__stat-label">Mitarbeiter</span>
    </div>
    <div class="intra__stat-item">
        <span class="intra__stat-value" data-count-to="<?= (int)($statsData['enotf'] ?? 0) ?>">0</span>
        <span class="intra__stat-label">eNOTF-Protokolle</span>
    </div>
    <div class="intra__stat-item">
        <span class="intra__stat-value" data-count-to="<?= (int)($statsData['dokumente'] ?? 0) ?>">0</span>
        <span class="intra__stat-label">Dokumente</span>
    </div>
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
