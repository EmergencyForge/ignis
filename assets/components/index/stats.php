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
        <span class="intra__stat-value"><?= htmlspecialchars((string)($statsData['users'] ?? 0)) ?></span>
        <span class="intra__stat-label">Benutzer</span>
    </div>
    <div class="intra__stat-item">
        <span class="intra__stat-value"><?= htmlspecialchars((string)($statsData['mitarbeiter'] ?? 0)) ?></span>
        <span class="intra__stat-label">Mitarbeiter</span>
    </div>
    <div class="intra__stat-item">
        <span class="intra__stat-value"><?= htmlspecialchars((string)($statsData['enotf'] ?? 0)) ?></span>
        <span class="intra__stat-label">eNOTF-Protokolle</span>
    </div>
    <div class="intra__stat-item">
        <span class="intra__stat-value"><?= htmlspecialchars((string)($statsData['dokumente'] ?? 0)) ?></span>
        <span class="intra__stat-label">Dokumente</span>
    </div>
</div>
