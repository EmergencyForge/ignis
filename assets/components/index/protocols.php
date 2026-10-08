<?php
/**
 * Die eNOTF-Protokolle, in denen der eigene Name als Personal steht,
 * neueste zuerst. Auf dem Dashboard (index.php, nur bei aktivem eNOTF)
 * die neuesten OwnRecords::PREVIEW, auf /me/protocols mit $list und
 * $pgPath die ganze Liste mit Suche, Sortierung und Seiten.
 *
 * @var \App\Support\ListQuery|null $list
 * @var string|null                 $pgPath
 */

use App\Support\OwnRecords;

$list   = $list ?? null;
$pgPath = $pgPath ?? '';

$ediviQuery = OwnRecords::enotfProtocols();
if ($list !== null) {
    if ($list->q !== '') {
        $ediviQuery->where(function ($q) use ($list) {
            $q->where('e.enr', 'LIKE', $list->like())
                ->orWhere('e.bearbeiter', 'LIKE', $list->like());
        });
    }
    $ediviResult = $list->paginate($ediviQuery);
} else {
    $ediviResult = $ediviQuery->orderByDesc('e.sendezeit')->orderByDesc('e.id')->limit(OwnRecords::PREVIEW)->get();
}
$ediviRows = $ediviResult->map(fn ($row) => (array) $row)->all();

$ediviTh = static fn (string $key, string $label): string => $list !== null
    ? $list->th($key, $label, $pgPath)
    : '<th scope="col">' . $label . '</th>';

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
            <?= $ediviTh('status', 'Status') ?>
            <?= $ediviTh('nr', 'Nr.') ?>
            <?= $ediviTh('bearbeiter', 'Bearbeiter') ?>
            <?= $ediviTh('gesendet', 'Gesendet') ?>
            <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($ediviRows)): ?>
            <?php
            $empty = $list !== null && $list->q !== ''
                ? [
                    'variant' => 'sm',
                    'icon'    => 'fa-magnifying-glass',
                    'title'   => 'Keine Protokolle gefunden',
                    'text'    => 'Zur Suche passt kein Protokoll.',
                    'actions' => [['label' => 'Suche zurücksetzen', 'href' => $list->url($pgPath, ['q' => null, 'page' => null]), 'style' => 'secondary']],
                ]
                : [
                    'variant' => 'sm',
                    'icon'    => 'fa-file-medical',
                    'title'   => 'Noch keine eNOTF-Protokolle',
                    'text'    => 'Abgeschlossene Einsatzprotokolle erscheinen hier von selbst.',
                ];
            ?>
            <tr><td colspan="5"><?php require dirname(__DIR__, 3) . '/templates/partials/empty.php'; ?></td></tr>
        <?php endif; ?>
        <?php foreach ($ediviRows as $row):
            [$stateText, $stateChip] = $protokollStatus[(int) $row['protokoll_status']] ?? ['Ungenügend', 'danger'];
            $pruefer   = !empty($row['bearbeiter']) ? 'Prüfer: ' . $row['bearbeiter'] : '';
            $released  = (int) $row['freigegeben'] === 1 && (int) $row['hidden_user'] !== 1;
            $viewUrl   = \Plugin\Enotf\Helpers\EnotfUrl::protokoll((string) $row['enr']);
        ?>
            <tr>
                <td><span class="ignis-chip ignis-chip--dot ignis-chip--<?= $stateChip ?>"<?= $pruefer !== '' ? ' data-ignis-tooltip="' . htmlspecialchars($pruefer) . '"' : '' ?>><?= $stateText ?></span></td>
                <td>
                    <a class="ignis-mono" href="<?= htmlspecialchars($viewUrl) ?>"><?= htmlspecialchars((string) $row['enr']) ?></a>
                    <?php if ($released): ?>
                        <span class="ignis-chip ignis-chip--sm ignis-chip--ok" data-ignis-tooltip="Freigegeben von: <?= htmlspecialchars((string) $row['freigeber_name']) ?>">F</span>
                    <?php endif; ?>
                </td>
                <td><?= !empty($row['bearbeiter']) ? htmlspecialchars((string) $row['bearbeiter']) : '<span class="text-tertiary-text">-</span>' ?></td>
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
