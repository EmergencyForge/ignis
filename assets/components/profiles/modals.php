<?php

use App\Auth\Permissions;
use App\Security\CsrfProtection;
?>

<!-- Dokument-Viewer Modal (Akte-Stil) -->
<div data-dialog-source class="modal twplus-dialog-surface" id="documentViewerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <!-- Akte-Header: Metadaten als kompakte Zeile -->
            <div class="modal-header flex-col items-stretch p-0 border-0">
                <!-- Titel-Zeile -->
                <div class="flex items-center justify-between px-3 py-2" style="border-bottom:1px solid var(--bs-border-color);">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="ignis-chip" id="docViewer-badge">Dokument</span>
                        <h6 class="mb-0 truncate" id="docViewer-title" style="font-size:0.88rem;"></h6>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <a href="#" id="docViewer-detailLink" class="ignis-btn ignis-btn--sm ignis-btn--ghost" title="Detailseite"><i class="fa-solid fa-up-right-from-square"></i></a>
                        <button type="button" class="btn-close" data-dialog-dismiss></button>
                    </div>
                </div>
                <!-- Meta-Chips -->
                <div class="px-3 py-2 flex flex-wrap gap-2 items-center" id="docViewer-chips" style="font-size:0.78rem;background:var(--bs-tertiary-bg);border-bottom:1px solid var(--bs-border-color);">
                    <div class="text-center py-2 w-full"><i class="fa-solid fa-spinner fa-spin"></i></div>
                </div>
            </div>

            <!-- PDF / HTML Vorschau -->
            <div class="modal-body p-0" style="height:65vh;">
                <iframe id="docViewer-iframe" style="width:100%;height:100%;border:none;" src="about:blank"></iframe>
            </div>

            <!-- Aktions-Leiste -->
            <div class="modal-footer twplus-mobile-actions justify-between py-2 px-3" id="docViewer-actions">
                <div id="docViewer-status"></div>
                <div class="flex gap-1" id="docViewer-buttons"></div>
            </div>
        </div>
    </div>
</div>

<script>
function openDocumentViewer(docid) {
    const modal = Dialog.openElement('#documentViewerModal');
    const chipsEl = document.getElementById('docViewer-chips');
    const iframe = document.getElementById('docViewer-iframe');
    const titleEl = document.getElementById('docViewer-title');
    const badgeEl = document.getElementById('docViewer-badge');
    const detailLink = document.getElementById('docViewer-detailLink');
    const statusEl = document.getElementById('docViewer-status');
    const buttonsEl = document.getElementById('docViewer-buttons');

    // Reset
    chipsEl.innerHTML = '<div class="text-center py-2 w-full"><i class="fa-solid fa-spinner fa-spin"></i></div>';
    iframe.src = 'about:blank';
    statusEl.innerHTML = '';
    buttonsEl.innerHTML = '';

    fetch('<?= BASE_PATH ?>api/documents/get-document?docid=' + encodeURIComponent(docid))
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                chipsEl.innerHTML = '<span class="text-[#d46b6b]">Fehler: ' + (data.error || 'Unbekannt') + '</span>';
                return;
            }
            const doc = data.document;
            const esc = (s) => s ? String(s).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') : '';

            // Header
            titleEl.textContent = doc.type_label;
            badgeEl.textContent = doc.category_name || 'Dokument';
            badgeEl.className = 'ignis-chip ' + (doc.category_color || 'ignis-chip--secondary');
            detailLink.href = '<?= BASE_PATH ?>personnel/document-view.php?docid=' + doc.docid;

            // Meta-Chips (kompakte Zeile)
            const chip = (icon, text) => '<span class="inline-flex items-center gap-1"><i class="fa-solid ' + icon + '" style="opacity:0.5;font-size:0.7rem;"></i>' + esc(text) + '</span>';
            const sep = '<span style="opacity:0.2;">|</span>';

            let chips = chip('fa-hashtag', doc.docid) + sep;
            chips += chip('fa-user', doc.erhalter || doc.empfaenger_fullname || '-') + sep;
            chips += chip('fa-pen-nib', doc.ersteller_name) + sep;
            chips += chip('fa-calendar', doc.ausstellungsdatum_formatted);
            chipsEl.innerHTML = chips;

            // PDF laden
            if (doc.pdf_exists) {
                iframe.src = doc.pdf_url;
            } else {
                iframe.srcdoc = '<div style="display:flex;align-items:center;justify-content:center;height:100%;font-family:sans-serif;color:#666;"><div class="text-center"><p style="font-size:1.2rem;">PDF nicht verfügbar</p></div></div>';
            }

            // Status (links im Footer)
            statusEl.innerHTML = doc.is_archived
                ? '<span class="ignis-chip"><i class="fa-solid fa-box-archive mr-1"></i>Archiviert</span>'
                : '<span class="ignis-chip ignis-chip--success" style="opacity:0.8;"><i class="fa-solid fa-circle-check mr-1"></i>Aktiv</span>';

            // Aktions-Buttons (rechts im Footer, als Icon-Buttons)
            let btns = '';
            if (doc.pdf_exists) {
                btns += '<a href="' + esc(doc.pdf_url) + '" download class="ignis-btn ignis-btn--sm ignis-btn--outline-primary" title="PDF herunterladen"><i class="fa-solid fa-download"></i></a>';
                btns += '<a href="' + esc(doc.pdf_url) + '" target="_blank" class="ignis-btn ignis-btn--sm ignis-btn--outline-secondary" title="PDF in neuem Tab"><i class="fa-solid fa-up-right-from-square"></i></a>';
            }
            btns += '<a href="<?= BASE_PATH ?>personnel/document-view?docid=' + doc.docid + '" class="ignis-btn ignis-btn--sm ignis-btn--outline-secondary" title="Detailseite"><i class="fa-solid fa-file-lines"></i></a>';

            <?php if (Permissions::check(['admin', 'personnel.documents.manage'])): ?>
            const archIcon = doc.is_archived ? 'fa-box-open' : 'fa-box-archive';
            const archTitle = doc.is_archived ? 'Wiederherstellen' : 'Archivieren';
            btns += '<button class="ignis-btn ignis-btn--sm ignis-btn--outline-secondary" title="' + archTitle + '" onclick="toggleArchiveFromViewer(\'' + doc.docid + '\', ' + !doc.is_archived + ')"><i class="fa-solid ' + archIcon + '"></i></button>';
            <?php endif; ?>

            buttonsEl.innerHTML = btns;
        })
        .catch(err => {
            chipsEl.innerHTML = '<span class="text-[#d46b6b]">Fehler: ' + err.message + '</span>';
        });
}

<?php if (Permissions::check(['admin', 'personnel.documents.manage'])): ?>
async function toggleArchiveFromViewer(docid, archive) {
    const action = archive ? 'archivieren' : 'wiederherstellen';
    const confirmed = await showConfirm('Dokument wirklich ' + action + '?', { title: 'Dokument ' + action, confirmText: archive ? 'Archivieren' : 'Wiederherstellen' });
    if (!confirmed) return;

    fetch('<?= BASE_PATH ?>api/documents/archive', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            docid: docid,
            archived: archive,
            csrf_token: '<?= CsrfProtection::getToken() ?>'
        })
    }).then(r => r.json()).then(result => {
        if (result.success) {
            Dialog.closeElement('#documentViewerModal');
            location.reload();
        }
    });
}
<?php endif; ?>
</script>

<?php if (Permissions::check(['admin', 'personnel.edit'])) {
    $fdqualis = json_decode($row['fachdienste'], true) ?? [];
    $fachdienste = \Illuminate\Database\Capsule\Manager::table('intra_mitarbeiter_fdquali')
        ->orderBy('sgnr')
        ->get(['sgnr', 'sgname'])
        ->map(fn ($fd) => (array) $fd)
        ->all();
?>
<template id="fdqualiFormTemplate">
    <table class="ignis-table">
        <thead>
            <tr>
                <th scope="col" class="ignis-table__check"><span class="sr-only">Zugewiesen</span></th>
                <th scope="col">Nr.</th>
                <th scope="col">Bezeichnung</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fachdienste as $fd): ?>
                <tr>
                    <td class="ignis-table__check">
                        <label class="ignis-checkbox">
                            <input type="checkbox" name="fachdienste[]" value="<?= htmlspecialchars($fd['sgnr']) ?>"
                                <?php if (in_array($fd['sgnr'], $fdqualis)) echo 'checked'; ?> aria-label="<?= htmlspecialchars($fd['sgname']) ?>">
                        </label>
                    </td>
                    <td class="ignis-mono"><?= htmlspecialchars($fd['sgnr']) ?></td>
                    <td><?= htmlspecialchars($fd['sgname']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</template>
<?php } ?>

<?php if (Permissions::check(['admin', 'personnel.view'])): ?>
<template id="newCommentFormTemplate">
    <select class="ignis-input mb-2" name="noteType">
        <option value="0">Allgemein</option>
        <option value="1">Positiv</option>
        <option value="2">Negativ</option>
    </select>
    <textarea class="ignis-input" name="content" rows="3" placeholder="Notiztext" style="resize:none"></textarea>
</template>
<?php endif; ?>

<script>
    // Profile-Action-Modals: drei kleine Server-Form-Submits (FDQuali,
    // Notiz, Mitarbeiter loeschen) laufen ueber Dialog.form/Dialog.confirm.
    // documentViewerModal haengt als [data-dialog-source] am
    // Dialog-System (Dialog.openElement/closeElement).

    function openFDQualiModal() {
        Dialog.form({
            title:        'Fachdienste',
            template:     'fdqualiFormTemplate',
            size:         'md',
            formAction:   '',
            hiddenFields: { new: '4' },
            submitLabel:  'Speichern',
            submitVariant:'success',
        });
    }

    function openNewCommentModal() {
        Dialog.form({
            title:        'Neue Notiz erstellen',
            template:     'newCommentFormTemplate',
            size:         'md',
            formAction:   '',
            hiddenFields: { new: '5' },
            submitLabel:  'Speichern',
            submitVariant:'success',
        });
    }

    <?php if (Permissions::check(['admin', 'personnel.delete'])): ?>
    function confirmPersoDelete() {
        showConfirm('Möchtest du diesen Mitarbeiter wirklich unwiderruflich löschen?', {
            danger:      true,
            confirmText: 'Löschen',
            title:       'Mitarbeiter löschen',
        }).then(function (ok) {
            if (ok) {
                window.location.href = '<?= BASE_PATH ?>personnel/delete?id=<?= htmlspecialchars($_GET['id'] ?? '') ?>';
            }
        });
    }
    <?php endif; ?>
</script>

<!-- MODAL ENDE -->

<!-- modalPersoDelete entfaellt: confirmPersoDelete() oben nutzt
     showConfirm() direkt (kein eigenes Modal mehr noetig). -->
