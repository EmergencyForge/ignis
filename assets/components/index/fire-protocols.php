<?php
/**
 * Dashboard: die fireTab-Einsätze, die das eigene Mitarbeiterprofil geleitet
 * hat, neueste zuerst. Eingebunden aus index.php, nur bei aktivem fireTab.
 */

$fireRows = \Illuminate\Database\Capsule\Manager::table('intra_fire_incidents as i')
    ->leftJoin('intra_mitarbeiter as m', 'i.leader_id', '=', 'm.id')
    ->where('i.leader_id', '=', function ($q) {
        $q->select('id')
            ->from('intra_mitarbeiter')
            ->where('discordtag', $_SESSION['discordtag']);
    })
    ->where('i.archived', 0)
    ->orderByDesc('i.created_at')
    ->get([
        'i.id',
        'i.incident_number',
        'i.location',
        'i.started_at',
        'i.status',
        'i.finalized',
        'm.fullname AS leader_name',
    ])
    ->map(fn ($row) => (array) $row)
    ->all();

// QM-Status => [Text, Chip-Semantik]
$fireStatus = [
    0 => ['Ungesehen', 'secondary'],
    1 => ['In Prüfung', 'warn'],
    2 => ['Freigegeben', 'ok'],
    3 => ['Ungenügend', 'danger'],
    4 => ['Ausgeblendet', 'dark'],
];
?>
<table class="ignis-table" id="dashboardFireProtocols">
    <thead>
        <tr>
            <th scope="col">Status</th>
            <th scope="col">Nr.</th>
            <th scope="col">Einsatzort</th>
            <th scope="col">Einsatzleiter</th>
            <th scope="col">Beginn</th>
            <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($fireRows)): ?>
            <tr><td colspan="6" class="ignis-table-empty">Noch keine fireTab-Protokolle. Abgeschlossene Einsätze aus dem fireTab erscheinen hier.</td></tr>
        <?php endif; ?>
        <?php foreach ($fireRows as $row):
            [$stateText, $stateChip] = $row['finalized']
                ? ($fireStatus[(int) $row['status']] ?? ['Unbekannt', 'secondary'])
                : ['In Bearbeitung', 'info'];
            $viewUrl = BASE_PATH . 'firetab/view?id=' . (int) $row['id'];
        ?>
            <tr>
                <td><span class="ignis-chip ignis-chip--dot ignis-chip--<?= $stateChip ?>"><?= $stateText ?></span></td>
                <td><a class="ignis-mono" href="<?= htmlspecialchars($viewUrl) ?>"><?= htmlspecialchars((string) $row['incident_number']) ?></a></td>
                <td><?= htmlspecialchars((string) $row['location']) ?></td>
                <td><?= htmlspecialchars((string) ($row['leader_name'] ?? 'Unbekannt')) ?></td>
                <td><?= (new DateTime((string) $row['started_at']))->format('d.m.Y | H:i') ?></td>
                <td class="ignis-table__actions">
                    <div class="ignis-row-actions">
                        <a href="<?= htmlspecialchars($viewUrl) ?>" class="ignis-btn ignis-btn--sm ignis-btn--secondary"><i class="fa-regular fa-eye" aria-hidden="true"></i> Ansehen</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
