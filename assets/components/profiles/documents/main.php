<?php

use App\Auth\Permissions;
?>

<?php if (Permissions::check(['admin', 'personnel.documents.manage'])): ?>
<div class="d-flex justify-content-end mb-2">
    <label class="form-check form-check-inline mb-0" style="font-size:0.78rem;">
        <input class="form-check-input" type="checkbox" id="chk-show-archived" onchange="document.querySelectorAll('.doc-archived').forEach(r => r.style.display = this.checked ? '' : 'none');">
        <span class="form-check-label text-muted">Archivierte anzeigen</span>
    </label>
</div>
<?php endif; ?>

<table class="table table-striped" id="documentTable">
    <thead>
        <th scope="col">Dokumenten-Typ</th>
        <th scope="col">#</th>
        <th scope="col">Ersteller</th>
        <th scope="col">Am</th>
        <th scope="col"></th>
    </thead>
    <tbody>
        <?php
        // Pruefe ob is_archived Spalte existiert (Abwaertskompatibilitaet)
        $hasArchived = false;
        try {
            $colCheck = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'intra_mitarbeiter_dokumente' AND COLUMN_NAME = 'is_archived'");
            $colCheck->execute();
            $hasArchived = (bool) $colCheck->fetchColumn();
        } catch (\PDOException $e) { /* ignore */ }

        $archivedCol = $hasArchived ? 'pd.is_archived' : '0 as is_archived';

        $query = "
    SELECT
        pd.docid,
        pd.ausstellerid,
        pd.ausstellungsdatum,
        pd.type,
        pd.template_id,
        pd.aussteller_name,
        pd.pdf_path,
        {$archivedCol},
        u.discord_id AS user_id,
        COALESCE(m.fullname, u.fullname) as fullname,
        u.aktenid,
        t.name as template_name,
        t.category as template_category,
        dk.color as category_color,
        dk.name as category_name,
        COALESCE(pd.aussteller_name, m.fullname, u.fullname, 'Unbekannt') as ersteller_name
    FROM intra_mitarbeiter_dokumente pd
    LEFT JOIN intra_users u ON pd.ausstellerid = u.discord_id
    LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag
    LEFT JOIN intra_dokument_templates t ON pd.template_id = t.id
    LEFT JOIN intra_dokument_kategorien dk ON t.category_id = dk.id
    WHERE pd.profileid = :profileid
    ORDER BY pd.ausstellungsdatum DESC
";

        $stmt = $pdo->prepare($query);
        $stmt->execute(['profileid' => $openedID]);
        $dokuresult = $stmt->fetchAll(PDO::FETCH_ASSOC);


        foreach ($dokuresult as $doks) {
            $austdatum = date("d.m.Y", strtotime($doks['ausstellungsdatum']));

            // Dokumenttyp bestimmen (zentrale Methode)
            $docart = \App\Documents\DocumentTemplateManager::getDocumentTypeLabel(
                (int) $doks['type'],
                $doks['template_name'] ?? null
            );

            // Direkter PDF-Pfad
            $pdfPath = BASE_PATH . "storage/documents/" . $doks['docid'] . ".pdf";

            // Badge-Farbe bestimmen
            if ($doks['type'] == 99 && !empty($doks['category_color'])) {
                $bg = $doks['category_color'];
            } elseif ($doks['type'] == 99) {
                $bg = match ($doks['template_category']) {
                    'urkunde' => 'text-bg-secondary',
                    'zertifikat' => 'text-bg-dark',
                    'schreiben' => 'text-bg-warning',
                    default => 'text-bg-info'
                };
            } elseif ($doks['type'] <= 3) {
                $bg = "text-bg-secondary";
            } elseif ($doks['type'] == 5 || $doks['type'] == 6 || $doks['type'] == 7) {
                $bg = "text-bg-dark";
            } elseif ($doks['type'] >= 10 && $doks['type'] <= 13) {
                $bg = "text-bg-danger";
            } else {
                $bg = "text-bg-secondary";
            }

            $isArchived = !empty($doks['is_archived']);
            $rowClass = $isArchived ? 'doc-archived' : '';
            $rowStyle = $isArchived ? 'display:none;opacity:0.5;' : '';

            echo "<tr class='{$rowClass}' style='{$rowStyle}'>";
            echo "<td><span class='badge $bg'>" . htmlspecialchars($docart) . "</span>";
            if ($isArchived) echo " <span class='badge text-bg-secondary' style='font-size:0.6rem;'>Archiviert</span>";
            echo "</td>";
            echo "<td>" . htmlspecialchars($doks['docid']) .  "</td>";
            echo "<td>" . htmlspecialchars($doks['ersteller_name']) . "</td>";
            echo "<td>" . htmlspecialchars($austdatum) . "</td>";
            echo "<td>";
            echo "<button class='btn btn-sm btn-soft-primary' onclick='openDocumentViewer(\"" . htmlspecialchars($doks['docid']) . "\")'><i class='fa-solid fa-eye'></i> Ansehen</button> ";
            // echo "<a href='$pdfPath' download class='btn btn-sm btn-success'><i class='las la-download'></i></a>";

            if (Permissions::check(['admin', 'personnel.documents.manage'])) {
                $escDocid = htmlspecialchars($doks['docid']);
                $escPid = htmlspecialchars($openedID);
                $archiveIcon = $isArchived ? 'fa-box-open' : 'fa-box-archive';
                $archiveTitle = $isArchived ? 'Wiederherstellen' : 'Archivieren';
                $archiveAction = $isArchived ? 'false' : 'true';

                echo " <button class='btn btn-sm btn-outline-secondary btn-icon' title='{$archiveTitle}' onclick='confirmArchiveDoc(\"{$escDocid}\", {$archiveAction})'><i class='fa-solid {$archiveIcon}'></i></button>";
                echo " <button class='btn btn-sm btn-outline-danger btn-icon' title='Endgültig löschen' onclick='confirmDeleteDoc(\"{$escDocid}\", \"{$escPid}\")'><i class='fa-solid fa-trash'></i></button>";
            }

            echo "</td>";
            echo "</tr>";
        }
        ?>

    </tbody>
</table>

<script>
async function confirmArchiveDoc(docid, archive) {
    const action = archive ? 'archivieren' : 'wiederherstellen';
    const confirmed = await showConfirm(
        'Möchtest du dieses Dokument wirklich ' + action + '?',
        { title: 'Dokument ' + action, confirmText: archive ? 'Archivieren' : 'Wiederherstellen' }
    );
    if (!confirmed) return;

    try {
        const res = await fetch('<?= BASE_PATH ?>api/documents/archive.php', {
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
        form.action = '<?= BASE_PATH ?>mitarbeiter/dokument-delete.php';
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