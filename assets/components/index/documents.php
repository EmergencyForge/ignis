<?php
/**
 * Die Dokumente der eigenen Personalakte, neueste zuerst. Auf dem
 * Dashboard (index.php, in einer ignis-card) die neuesten
 * OwnRecords::PREVIEW, auf /me/documents mit $list und $pgPath die ganze
 * Liste mit Suche, Sortierung und Seiten.
 *
 * @var \App\Support\ListQuery|null $list
 * @var string|null                 $pgPath
 */

use App\Support\OwnRecords;

$list   = $list ?? null;
$pgPath = $pgPath ?? '';

$userData = \App\Personnel\AccountLink::current();

$dokuresult = [];
if ($userData) {
    $docQuery = OwnRecords::documents();
    if ($list !== null) {
        if ($list->q !== '') {
            $docQuery->where(function ($q) use ($list) {
                $q->where('pd.docid', 'LIKE', $list->like())
                    ->orWhere('pd.aussteller_name', 'LIKE', $list->like())
                    ->orWhere('m.fullname', 'LIKE', $list->like());
            });
        }
        $docRows = $list->paginate($docQuery);
    } else {
        $docRows = $docQuery->orderByDesc('pd.ausstellungsdatum')->orderByDesc('pd.docid')->limit(OwnRecords::PREVIEW)->get();
    }
    $dokuresult = $docRows->map(fn ($row) => (array) $row)->all();
}

$docTh = static fn (string $key, string $label): string => $list !== null
    ? $list->th($key, $label, $pgPath)
    : '<th scope="col">' . $label . '</th>';

// Chip je Dokumenttyp. Die Kategorien des alten Systems gibt es nicht
// mehr; was bleibt, ist die Semantik der festen Typen.
$documentChip = static function (array $doc): string {
    $type = (int) $doc['type'];
    if ($type === 99) {
        return 'ignis-chip--info';
    }
    if ($type >= 10 && $type <= 13) {
        return 'ignis-chip--danger';
    }
    if ($type >= 5 && $type <= 7) {
        return 'ignis-chip--dark';
    }

    return 'ignis-chip--secondary';
};
?>
<table class="ignis-table" id="dashboardDocuments">
    <thead>
        <tr>
            <?= $docTh('typ', 'Dokumenten-Typ') ?>
            <?= $docTh('nr', 'Nr.') ?>
            <?= $docTh('ersteller', 'Ersteller') ?>
            <?= $docTh('ausgestellt', 'Ausgestellt') ?>
            <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$userData): ?>
            <?php
            $empty = [
                'variant' => 'sm',
                'tone'    => 'warn',
                'icon'    => 'fa-id-badge',
                'title'   => 'Kein Mitarbeiterprofil verknüpft',
                'text'    => 'Dokumente erscheinen, sobald die Personalverwaltung ein Profil mit deinem Konto verbindet.',
            ];
            ?>
            <tr><td colspan="5"><?php require dirname(__DIR__, 3) . '/templates/partials/empty.php'; ?></td></tr>
        <?php elseif (empty($dokuresult)): ?>
            <?php
            $empty = $list !== null && $list->q !== ''
                ? [
                    'variant' => 'sm',
                    'icon'    => 'fa-magnifying-glass',
                    'title'   => 'Keine Dokumente gefunden',
                    'text'    => 'Zur Suche passt kein Dokument.',
                    'actions' => [['label' => 'Suche zurücksetzen', 'href' => $list->url($pgPath, ['q' => null, 'page' => null]), 'style' => 'secondary']],
                ]
                : [
                    'variant' => 'sm',
                    'icon'    => 'fa-file-lines',
                    'title'   => 'Noch keine Dokumente',
                    'text'    => 'Urkunden und Zertifikate aus deiner Personalakte erscheinen hier.',
                ];
            ?>
            <tr><td colspan="5"><?php require dirname(__DIR__, 3) . '/templates/partials/empty.php'; ?></td></tr>
        <?php endif; ?>
        <?php foreach ($dokuresult as $doks):
            $docart  = \App\Models\PersonnelDocument::typeLabel((int) $doks['type']);
            $pdfPath = BASE_PATH . 'storage/documents/' . $doks['docid'] . '.pdf';
        ?>
            <tr>
                <td><span class="ignis-chip <?= htmlspecialchars($documentChip($doks)) ?>"><?= htmlspecialchars($docart) ?></span></td>
                <td><span class="ignis-mono"><?= htmlspecialchars((string) $doks['docid']) ?></span></td>
                <td><?= htmlspecialchars((string) $doks['ersteller_name']) ?></td>
                <td><?= date('d.m.Y', strtotime((string) $doks['ausstellungsdatum'])) ?></td>
                <td class="ignis-table__actions">
                    <div class="ignis-row-actions">
                        <a href="<?= htmlspecialchars($pdfPath) ?>" class="ignis-btn ignis-btn--sm ignis-btn--secondary" target="_blank" rel="noopener"><i class="fa-regular fa-eye" aria-hidden="true"></i> Ansehen</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
