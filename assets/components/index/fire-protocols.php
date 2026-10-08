<?php
/**
 * Die fireTab-Einsätze, die das eigene Mitarbeiterprofil geleitet hat,
 * neueste zuerst. Auf dem Dashboard (index.php, nur bei aktivem fireTab)
 * die neuesten OwnRecords::PREVIEW, auf /me/protocols?source=firetab mit
 * $list und $pgPath die ganze Liste mit Suche, Sortierung und Seiten.
 *
 * @var \App\Support\ListQuery|null $list
 * @var string|null                 $pgPath
 */

use App\Support\OwnRecords;

$list   = $list ?? null;
$pgPath = $pgPath ?? '';

$fireQuery = OwnRecords::firetabProtocols();
if ($list !== null) {
    if ($list->q !== '') {
        $fireQuery->where(function ($q) use ($list) {
            $q->where('i.incident_number', 'LIKE', $list->like())
                ->orWhere('i.location', 'LIKE', $list->like());
        });
    }
    $fireResult = $list->paginate($fireQuery);
} else {
    $fireResult = $fireQuery->orderByDesc('i.created_at')->orderByDesc('i.id')->limit(OwnRecords::PREVIEW)->get();
}
$fireRows = $fireResult->map(fn ($row) => (array) $row)->all();

$fireTh = static fn (string $key, string $label): string => $list !== null
    ? $list->th($key, $label, $pgPath)
    : '<th scope="col">' . $label . '</th>';

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
            <?= $fireTh('status', 'Status') ?>
            <?= $fireTh('nr', 'Nr.') ?>
            <?= $fireTh('ort', 'Einsatzort') ?>
            <th scope="col">Einsatzleiter</th>
            <?= $fireTh('beginn', 'Beginn') ?>
            <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($fireRows)): ?>
            <?php
            $empty = $list !== null && $list->q !== ''
                ? [
                    'variant' => 'sm',
                    'icon'    => 'fa-magnifying-glass',
                    'title'   => 'Keine Einsätze gefunden',
                    'text'    => 'Zur Suche passt kein Einsatz.',
                    'actions' => [['label' => 'Suche zurücksetzen', 'href' => $list->url($pgPath, ['q' => null, 'page' => null]), 'style' => 'secondary']],
                ]
                : [
                    'variant' => 'sm',
                    'icon'    => 'fa-fire',
                    'title'   => 'Noch keine fireTab-Protokolle',
                    'text'    => 'Einsätze, die du im fireTab leitest, erscheinen hier von selbst.',
                ];
            ?>
            <tr><td colspan="6"><?php require dirname(__DIR__, 3) . '/templates/partials/empty.php'; ?></td></tr>
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
