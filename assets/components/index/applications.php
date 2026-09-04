<?php
/**
 * Dashboard: die Anträge des angemeldeten Kontos, neueste zuerst.
 * Eingebunden aus index.php innerhalb einer ignis-card.
 */

$appresult = \Illuminate\Database\Capsule\Manager::table('intra_antraege as a')
    ->join('intra_antrag_typen as at', 'a.antragstyp_id', '=', 'at.id')
    ->where('a.discordid', $_SESSION['discordtag'])
    ->orderByDesc('a.time_added')
    ->get([
        'a.uniqueid',
        'at.name as typ_name',
        'at.icon as typ_icon',
        'a.cirs_status',
        'a.cirs_manager',
        'a.time_added',
    ])
    ->map(fn ($row) => (array) $row)
    ->all();

// Status => [Text, Chip-Semantik]
$appStatus = [
    \App\Models\Form::STATUS_IN_PROGRESS => ['In Bearbeitung', 'info'],
    \App\Models\Form::STATUS_REJECTED    => ['Abgelehnt', 'danger'],
    \App\Models\Form::STATUS_DEFERRED    => ['Aufgeschoben', 'warn'],
    \App\Models\Form::STATUS_ACCEPTED    => ['Angenommen', 'ok'],
];
?>
<table class="ignis-table" id="dashboardApplications">
    <thead>
        <tr>
            <th scope="col">Typ</th>
            <th scope="col">Status</th>
            <th scope="col">Nr.</th>
            <th scope="col">Bearbeiter</th>
            <th scope="col">Eingereicht</th>
            <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($appresult)): ?>
            <tr><td colspan="6" class="ignis-table-empty">Noch keine Anträge. Der erste geht über „Antrag einreichen" oben rechts.</td></tr>
        <?php endif; ?>
        <?php foreach ($appresult as $row):
            [$stateText, $stateChip] = $appStatus[(int) $row['cirs_status']] ?? ['Unbekannt', 'secondary'];
            $viewUrl = BASE_PATH . 'forms/view?antrag=' . urlencode((string) $row['uniqueid']);
        ?>
            <tr>
                <td><i class="<?= htmlspecialchars((string) $row['typ_icon']) ?> mr-1" aria-hidden="true"></i> <?= htmlspecialchars((string) $row['typ_name']) ?></td>
                <td><span class="ignis-chip ignis-chip--dot ignis-chip--<?= $stateChip ?>"><?= $stateText ?></span></td>
                <td><a class="ignis-mono" href="<?= htmlspecialchars($viewUrl) ?>"><?= htmlspecialchars((string) $row['uniqueid']) ?></a></td>
                <td><?= !empty($row['cirs_manager']) ? htmlspecialchars((string) $row['cirs_manager']) : '<span class="text-[var(--text-3)]">—</span>' ?></td>
                <td><?= date('d.m.Y | H:i', strtotime((string) $row['time_added'])) ?></td>
                <td class="ignis-table__actions">
                    <div class="ignis-row-actions">
                        <a class="ignis-btn ignis-btn--sm ignis-btn--secondary" href="<?= htmlspecialchars($viewUrl) ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> Ansehen</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
