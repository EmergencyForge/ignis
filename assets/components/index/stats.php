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
<div class="row">
    <div class="col">
        <div class="card my-2 intra__stats-card intra__stats-users">
            <div class="card-body">
                <h5 class="card-title">Registrierte Benutzer</h5>
                <p class="card-text display-4">
                    <?= htmlspecialchars($statsData['users'] ?? 0) ?>
                </p>
                <i class="fa-solid fa-user-tie"></i>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card my-2 intra__stats-card intra__stats-workers">
            <div class="card-body">
                <h5 class="card-title">Angelegte Mitarbeiter</h5>
                <p class="card-text display-4">
                    <?= htmlspecialchars($statsData['mitarbeiter'] ?? 0) ?>
                </p>
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card my-2 intra__stats-card intra__stats-enotf">
            <div class="card-body">
                <h5 class="card-title">eNOTF-Protokolle</h5>
                <p class="card-text display-4">
                    <?= htmlspecialchars($statsData['enotf'] ?? 0) ?>
                </p>
                <i class="fa-solid fa-house-medical-flag"></i>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card my-2 intra__stats-card intra__stats-documents">
            <div class="card-body">
                <h5 class="card-title">Erstellte Dokumente</h5>
                <p class="card-text display-4">
                    <?= htmlspecialchars($statsData['dokumente'] ?? 0) ?>
                </p>
                <i class="fa-solid fa-folder-open"></i>
            </div>
        </div>
    </div>
</div>
