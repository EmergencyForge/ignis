<?php
/**
 * Die Anträge des angemeldeten Kontos, neueste zuerst. Auf dem Dashboard
 * (index.php, in einer ignis-card) die neuesten OwnRecords::PREVIEW, auf
 * /me/applications mit $list und $pgPath die ganze Liste mit Suche,
 * Statusfilter, Sortierung und Seiten. Nur bei aktivem Plugin forms.
 *
 * @var \App\Support\ListQuery|null $list
 * @var string|null                 $pgPath
 */

use App\Support\OwnRecords;

$list   = $list ?? null;
$pgPath = $pgPath ?? '';

$appQuery = OwnRecords::applications();
if ($list !== null) {
    if ($list->q !== '') {
        $appQuery->where(function ($q) use ($list) {
            $q->where('a.uniqueid', 'LIKE', $list->like())
                ->orWhere('at.name', 'LIKE', $list->like())
                ->orWhere('a.cirs_manager', 'LIKE', $list->like());
        });
    }
    if ($list->filter('status') !== '') {
        $appQuery->where('a.cirs_status', (int) $list->filter('status'));
    }
    $appRows = $list->paginate($appQuery);
} else {
    $appRows = $appQuery->orderByDesc('a.time_added')->orderByDesc('a.id')->limit(OwnRecords::PREVIEW)->get();
}
$appresult = $appRows->map(fn ($row) => (array) $row)->all();

$appTh = static fn (string $key, string $label): string => $list !== null
    ? $list->th($key, $label, $pgPath)
    : '<th scope="col">' . $label . '</th>';

// Status => [Text, Chip-Semantik]
$appStatus = [
    \Plugin\Forms\Models\Form::STATUS_IN_PROGRESS => ['In Bearbeitung', 'info'],
    \Plugin\Forms\Models\Form::STATUS_REJECTED    => ['Abgelehnt', 'danger'],
    \Plugin\Forms\Models\Form::STATUS_DEFERRED    => ['Aufgeschoben', 'warn'],
    \Plugin\Forms\Models\Form::STATUS_ACCEPTED    => ['Angenommen', 'ok'],
];
?>
<table class="ignis-table" id="dashboardApplications">
    <thead>
        <tr>
            <?= $appTh('typ', 'Typ') ?>
            <?= $appTh('status', 'Status') ?>
            <?= $appTh('nr', 'Nr.') ?>
            <?= $appTh('bearbeiter', 'Bearbeiter') ?>
            <?= $appTh('eingereicht', 'Eingereicht') ?>
            <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($appresult)): ?>
            <?php
            $empty = $list !== null && ($list->q !== '' || $list->filter('status') !== '')
                ? [
                    'variant' => 'sm',
                    'icon'    => 'fa-magnifying-glass',
                    'title'   => 'Keine Anträge gefunden',
                    'text'    => 'Mit den gesetzten Filtern passt kein Antrag.',
                    'actions' => [['label' => 'Filter zurücksetzen', 'href' => $list->url($pgPath, ['q' => null, 'status' => null, 'page' => null]), 'style' => 'secondary']],
                ]
                : [
                    'variant' => 'sm',
                    'icon'    => 'fa-clipboard-list',
                    'title'   => 'Noch keine Anträge',
                    'text'    => 'Den ersten stellst du über „Antrag einreichen“. Danach siehst du hier seinen Stand.',
                ];
            ?>
            <tr><td colspan="6"><?php require dirname(__DIR__, 3) . '/templates/partials/empty.php'; ?></td></tr>
        <?php endif; ?>
        <?php foreach ($appresult as $row):
            [$stateText, $stateChip] = $appStatus[(int) $row['cirs_status']] ?? ['Unbekannt', 'secondary'];
            $viewUrl = BASE_PATH . 'forms/view?antrag=' . urlencode((string) $row['uniqueid']);
        ?>
            <tr>
                <td><i class="<?= htmlspecialchars((string) $row['typ_icon']) ?> mr-1" aria-hidden="true"></i> <?= htmlspecialchars((string) $row['typ_name']) ?></td>
                <td><span class="ignis-chip ignis-chip--dot ignis-chip--<?= $stateChip ?>"><?= $stateText ?></span></td>
                <td><a class="ignis-mono" href="<?= htmlspecialchars($viewUrl) ?>"><?= htmlspecialchars((string) $row['uniqueid']) ?></a></td>
                <td><?= !empty($row['cirs_manager']) ? htmlspecialchars((string) $row['cirs_manager']) : '<span class="text-tertiary-text">-</span>' ?></td>
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
