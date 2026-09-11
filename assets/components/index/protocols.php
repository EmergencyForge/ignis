<?php
/**
 * Dashboard: die eNOTF-Protokolle, in denen der eigene Name als Personal
 * steht, neueste zuerst. Eingebunden aus index.php, nur bei aktivem eNOTF.
 */

$ediviRows = \Illuminate\Database\Capsule\Manager::table('intra_edivi as e')
    ->join('intra_mitarbeiter as m', function ($join) {
        $join->where('m.discordtag', '=', $_SESSION['discordtag']);
    })
    ->where(function ($q) {
        $q->whereRaw("e.pfname LIKE CONCAT('%', m.fullname, '%')")
            ->orWhereRaw("e.fzg_transp_perso LIKE CONCAT('%', m.fullname, '%')")
            ->orWhereRaw("e.fzg_transp_perso_2 LIKE CONCAT('%', m.fullname, '%')")
            ->orWhereRaw("e.fzg_transp_perso_3 LIKE CONCAT('%', m.fullname, '%')")
            ->orWhereRaw("e.fzg_na_perso LIKE CONCAT('%', m.fullname, '%')")
            ->orWhereRaw("e.fzg_na_perso_2 LIKE CONCAT('%', m.fullname, '%')")
            ->orWhereRaw("e.fzg_na_perso_3 LIKE CONCAT('%', m.fullname, '%')");
    })
    ->where('e.hidden', '<>', 1)
    ->where('e.hidden_user', '<>', 1)
    ->orderByDesc('e.sendezeit')
    ->get([
        'e.enr',
        'e.sendezeit',
        'e.protokoll_status',
        'e.bearbeiter',
        'e.freigegeben',
        'e.freigeber_name',
        'e.hidden_user',
    ])
    ->map(fn ($row) => (array) $row)
    ->all();

// Prüfstatus => [Text, Chip-Semantik]
$protokollStatus = [
    0 => ['Ungesehen', 'secondary'],
    1 => ['In Prüfung', 'warn'],
    2 => ['Geprüft', 'ok'],
    4 => ['Ausgeblendet', 'dark'],
];
?>
<table class="ignis-table" id="dashboardProtocols">
    <thead>
        <tr>
            <th scope="col">Status</th>
            <th scope="col">Nr.</th>
            <th scope="col">Bearbeiter</th>
            <th scope="col">Gesendet</th>
            <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($ediviRows)): ?>
            <tr><td colspan="5" class="ignis-table-empty">Noch keine eNOTF-Protokolle. Abgeschlossene Einsatzprotokolle erscheinen hier von selbst.</td></tr>
        <?php endif; ?>
        <?php foreach ($ediviRows as $row):
            [$stateText, $stateChip] = $protokollStatus[(int) $row['protokoll_status']] ?? ['Ungenügend', 'danger'];
            $pruefer   = !empty($row['bearbeiter']) ? 'Prüfer: ' . $row['bearbeiter'] : '';
            $released  = (int) $row['freigegeben'] === 1 && (int) $row['hidden_user'] !== 1;
            $viewUrl   = \Plugin\Enotf\Helpers\EnotfUrl::protokoll((string) $row['enr']);
        ?>
            <tr>
                <td><span class="ignis-chip ignis-chip--dot ignis-chip--<?= $stateChip ?>"<?= $pruefer !== '' ? ' title="' . htmlspecialchars($pruefer) . '"' : '' ?>><?= $stateText ?></span></td>
                <td>
                    <a class="ignis-mono" href="<?= htmlspecialchars($viewUrl) ?>"><?= htmlspecialchars((string) $row['enr']) ?></a>
                    <?php if ($released): ?>
                        <span class="ignis-chip ignis-chip--sm ignis-chip--ok" title="Freigegeben von: <?= htmlspecialchars((string) $row['freigeber_name']) ?>">F</span>
                    <?php endif; ?>
                </td>
                <td><?= !empty($row['bearbeiter']) ? htmlspecialchars((string) $row['bearbeiter']) : '<span class="text-[var(--text-3)]">—</span>' ?></td>
                <td><?= (new DateTime((string) $row['sendezeit']))->format('d.m.Y | H:i') ?></td>
                <td class="ignis-table__actions">
                    <div class="ignis-row-actions">
                        <a href="<?= htmlspecialchars($viewUrl) ?>" class="ignis-btn ignis-btn--sm ignis-btn--secondary"><i class="fa-regular fa-eye" aria-hidden="true"></i> Ansehen</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
