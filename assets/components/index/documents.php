<?php
/**
 * Dashboard: die Dokumente der eigenen Personalakte, neueste zuerst.
 * Eingebunden aus index.php innerhalb einer ignis-card.
 */

// Ohne Discord-ID (Konto aus der zentralen Anmeldung) würde where(..., null)
// zu IS NULL und träfe eine fremde Akte.
$userData = empty($_SESSION['discordtag']) ? null : \App\Models\Personnel::query()
    ->where('discordtag', $_SESSION['discordtag'])
    ->first(['id']);

$dokuresult = [];
if ($userData) {
    $dokuresult = \Illuminate\Database\Capsule\Manager::table('intra_mitarbeiter_dokumente as pd')
        ->leftJoin('intra_users as u', 'pd.ausstellerid', '=', 'u.discord_id')
        ->leftJoin('intra_mitarbeiter as m', 'u.discord_id', '=', 'm.discordtag')
        ->where('pd.profileid', $userData->id)
        ->orderByDesc('pd.ausstellungsdatum')
        ->get([
            'pd.docid',
            'pd.ausstellungsdatum',
            'pd.type',
            \Illuminate\Database\Capsule\Manager::connection()->raw("COALESCE(pd.aussteller_name, m.fullname, u.fullname, 'Unbekannt') as ersteller_name"),
        ])
        ->map(fn ($row) => (array) $row)
        ->all();
}

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
            <th scope="col">Dokumenten-Typ</th>
            <th scope="col">Nr.</th>
            <th scope="col">Ersteller</th>
            <th scope="col">Ausgestellt</th>
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
            $empty = [
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
