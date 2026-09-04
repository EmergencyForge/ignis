<?php
/**
 * Personalakte: die Dokumente des Mitarbeiters als ignis-Tabelle. Wer
 * Dokumente verwalten darf, sieht archivierte auf Wunsch mit (Kästchen
 * über der Tabelle, Zeilen tragen `hidden`) und bekommt je Zeile
 * Archivieren und Löschen. Erwartet $openedID aus templates/personnel/profile.php.
 */

use App\Auth\Permissions;

$canManageDocs = Permissions::check(['admin', 'personnel.documents.manage']);

// Pruefe ob is_archived Spalte existiert (Abwaertskompatibilitaet)
$hasArchived = false;
try {
    $hasArchived = \Illuminate\Database\Capsule\Manager::schema()
        ->hasColumn('intra_mitarbeiter_dokumente', 'is_archived');
} catch (\PDOException $e) { /* ignore */ }

$archivedCol = $hasArchived ? 'pd.is_archived' : '0 as is_archived';

$dokuresult = \Illuminate\Database\Capsule\Manager::table('intra_mitarbeiter_dokumente as pd')
    ->leftJoin('intra_users as u', 'pd.ausstellerid', '=', 'u.discord_id')
    ->leftJoin('intra_mitarbeiter as m', 'u.discord_id', '=', 'm.discordtag')
    ->leftJoin('intra_dokument_templates as t', 'pd.template_id', '=', 't.id')
    ->leftJoin('intra_dokument_kategorien as dk', 't.category_id', '=', 'dk.id')
    ->where('pd.profileid', $openedID)
    ->orderByDesc('pd.ausstellungsdatum')
    ->get([
        'pd.docid',
        'pd.ausstellungsdatum',
        'pd.type',
        \Illuminate\Database\Capsule\Manager::raw($archivedCol),
        't.name as template_name',
        't.category as template_category',
        'dk.color as category_color',
        \Illuminate\Database\Capsule\Manager::raw("COALESCE(pd.aussteller_name, m.fullname, u.fullname, 'Unbekannt') as ersteller_name"),
    ])
    ->map(fn ($row) => (array) $row)
    ->all();

// Chip je Dokumenttyp: eigene Vorlagen tragen die Farbe ihrer Kategorie
// (Klassenname aus intra_dokument_kategorien), die festen Typen eine Semantik.
$profileDocumentChip = static function (array $doc): string {
    $type = (int) $doc['type'];
    if ($type === 99 && !empty($doc['category_color'])) {
        return (string) $doc['category_color'];
    }
    if ($type === 99) {
        return match ($doc['template_category']) {
            'urkunde'    => 'ignis-chip--secondary',
            'zertifikat' => 'ignis-chip--dark',
            'schreiben'  => 'ignis-chip--warn',
            default      => 'ignis-chip--info',
        };
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

<?php if ($canManageDocs): ?>
<div class="flex justify-end mb-2">
    <label class="ignis-checkbox">
        <input type="checkbox" id="chk-show-archived" onchange="document.querySelectorAll('.doc-archived').forEach(r => { r.hidden = !this.checked; });">
        <span>Archivierte anzeigen</span>
    </label>
</div>
<?php endif; ?>

<div class="twplus-table-card__scroll">
<table class="ignis-table" id="documentTable">
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
        <?php if ($dokuresult === []): ?>
            <tr><td colspan="5" class="ignis-table-empty">Noch keine Dokumente in dieser Akte.</td></tr>
        <?php endif; ?>
        <?php foreach ($dokuresult as $doks):
            $docart     = \App\Documents\DocumentTemplateManager::getDocumentTypeLabel((int) $doks['type'], $doks['template_name'] ?? null);
            $isArchived = !empty($doks['is_archived']);
            $escDocid   = htmlspecialchars((string) $doks['docid'], ENT_QUOTES);
        ?>
            <tr<?= $isArchived ? ' class="doc-archived is-muted" hidden' : '' ?>>
                <td>
                    <span class="ignis-chip <?= htmlspecialchars($profileDocumentChip($doks)) ?>"><?= htmlspecialchars($docart) ?></span>
                    <?php if ($isArchived): ?>
                        <span class="ignis-chip ignis-chip--sm ignis-chip--secondary">Archiviert</span>
                    <?php endif; ?>
                </td>
                <td><span class="ignis-mono"><?= $escDocid ?></span></td>
                <td><?= htmlspecialchars((string) $doks['ersteller_name']) ?></td>
                <td><?= date('d.m.Y', strtotime((string) $doks['ausstellungsdatum'])) ?></td>
                <td class="ignis-table__actions">
                    <div class="ignis-row-actions">
                        <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--secondary" onclick="openDocumentViewer('<?= $escDocid ?>')"><i class="fa-solid fa-eye" aria-hidden="true"></i> Ansehen</button>
                        <?php if ($canManageDocs): ?>
                            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="<?= $isArchived ? 'Wiederherstellen' : 'Archivieren' ?>" aria-label="<?= $isArchived ? 'Wiederherstellen' : 'Archivieren' ?>" onclick="confirmArchiveDoc('<?= $escDocid ?>', <?= $isArchived ? 'false' : 'true' ?>)"><i class="fa-solid <?= $isArchived ? 'fa-box-open' : 'fa-box-archive' ?>" aria-hidden="true"></i></button>
                            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger ignis-btn--icon" data-ignis-tooltip="Endgültig löschen" aria-label="Endgültig löschen" onclick="confirmDeleteDoc('<?= $escDocid ?>', '<?= (int) $openedID ?>')"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<script>
async function confirmArchiveDoc(docid, archive) {
    const action = archive ? 'archivieren' : 'wiederherstellen';
    const confirmed = await showConfirm(
        'Möchtest du dieses Dokument wirklich ' + action + '?',
        { title: 'Dokument ' + action, confirmText: archive ? 'Archivieren' : 'Wiederherstellen' }
    );
    if (!confirmed) return;

    try {
        const res = await fetch('<?= BASE_PATH ?>api/documents/archive', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                docid: docid,
                archived: archive,
                csrf_token: '<?= \App\Security\CsrfProtection::getToken() ?>'
            })
        });
        const result = await res.json();
        if (result.success) {
            location.reload();
        } else {
            showAlert('Fehler: ' + result.error, { type: 'error', title: 'Fehler' });
        }
    } catch (err) {
        showAlert('Fehler: ' + err.message, { type: 'error', title: 'Fehler' });
    }
}

async function confirmDeleteDoc(docid, pid) {
    const confirmed = await showConfirm(
        'Dieses Dokument wird endgültig gelöscht. Die PDF-Datei wird unwiderruflich entfernt.',
        { title: 'Dokument löschen', danger: true, confirmText: 'Endgültig löschen' }
    );
    if (!confirmed) return;

    try {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= BASE_PATH ?>personnel/document-delete.php';
        form.innerHTML = '<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Security\CsrfProtection::getToken()) ?>">'
            + '<input type="hidden" name="docid" value="' + docid + '">'
            + '<input type="hidden" name="pid" value="' + pid + '">';
        document.body.appendChild(form);
        form.submit();
    } catch (err) {
        showAlert('Fehler: ' + err.message, { type: 'error', title: 'Fehler' });
    }
}
</script>
