<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Security\CsrfProtection;
use App\Documents\DocumentTemplateManager;

$docid = $_GET['docid'] ?? '';
if (empty($docid)) {
    Flash::set('error', 'Dokument-ID fehlt');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

// Dokument laden
$stmt = $pdo->prepare("
    SELECT
        pd.*,
        IFNULL(pd.is_archived, 0) as is_archived,
        t.name as template_name,
        t.category as template_category,
        t.editor_type,
        dk.name as category_name,
        dk.color as category_color,
        COALESCE(pd.aussteller_name, m.fullname, u.fullname, 'Unbekannt') as ersteller_name,
        emp.fullname as empfaenger_fullname,
        emp.id as empfaenger_id
    FROM intra_mitarbeiter_dokumente pd
    LEFT JOIN intra_dokument_templates t ON pd.template_id = t.id
    LEFT JOIN intra_dokument_kategorien dk ON t.category_id = dk.id
    LEFT JOIN intra_users u ON pd.ausstellerid = u.discord_id
    LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag
    LEFT JOIN intra_mitarbeiter emp ON pd.profileid = emp.id
    WHERE pd.docid = :docid
");
$stmt->execute(['docid' => $docid]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    Flash::set('error', 'Dokument nicht gefunden');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

// Berechtigungspruefung
$isOwnDoc = ($doc['ausstellerid'] == ($_SESSION['discord_id'] ?? ''));
if (!$isOwnDoc && !Permissions::check(['admin', 'personnel.documents.manage', 'personnel.documents.view', 'personnel.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

$canManage = Permissions::check(['admin', 'personnel.documents.manage']);
$typLabel = DocumentTemplateManager::getDocumentTypeLabel((int) $doc['type'], $doc['template_name'] ?? null);
$pdfUrl = BASE_PATH . 'storage/documents/' . $doc['docid'] . '.pdf';
$pdfExists = file_exists(__DIR__ . '/../storage/documents/' . basename($doc['docid']) . '.pdf');
$isArchived = !empty($doc['is_archived']);
$austdatum = $doc['ausstellungsdatum'] ? date('d.m.Y', strtotime($doc['ausstellungsdatum'])) : '-';
$backUrl = $doc['empfaenger_id']
    ? BASE_PATH . 'mitarbeiter/profile.php?id=' . $doc['empfaenger_id'] . '#documents'
    : BASE_PATH . 'index.php';
?>
<?php $SITE_TITLE = htmlspecialchars($typLabel); ?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <?php include __DIR__ . '/../assets/components/_base/mitarbeiter/head.php'; ?>
    <style>
        body {
            margin: 0;
            overflow: hidden;
            background: #1e1c24;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .doc-topbar {
            background: var(--bs-body-bg);
            border-bottom: 1px solid var(--bs-border-color);
            padding: 0;
            flex-shrink: 0;
        }

        .doc-topbar-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 1.25rem;
        }

        .doc-topbar-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1.25rem;
            font-size: 0.82rem;
            color: var(--bs-body-color);
            background: var(--bs-tertiary-bg);
            border-top: 1px solid rgba(255,255,255,0.04);
        }

        .doc-topbar-meta .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .doc-topbar-meta .meta-chip i {
            opacity: 0.5;
            font-size: 0.72rem;
        }

        .doc-topbar-meta .meta-sep {
            opacity: 0.2;
        }

        .doc-viewer-area {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            min-height: 0;
        }

        .doc-viewer-area iframe {
            width: 100%;
            max-width: 800px;
            height: 100%;
            border: none;
            border-radius: 6px;
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.5);
        }

        .doc-viewer-area .doc-empty {
            text-align: center;
            color: var(--bs-secondary-color);
        }

        .doc-title {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .doc-actions .btn {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>

    <!-- Top Bar -->
    <div class="doc-topbar">
        <!-- Zeile 1: Navigation + Titel + Aktionen -->
        <div class="doc-topbar-row">
            <a href="<?= $backUrl ?>" class="btn btn-sm btn-ghost" title="Zurück">
                <i class="fa-solid fa-arrow-left"></i>
            </a>

            <span class="badge <?= htmlspecialchars($doc['category_color'] ?? 'text-bg-secondary') ?>" style="font-size:0.7rem;">
                <?= htmlspecialchars($doc['category_name'] ?? 'Dokument') ?>
            </span>

            <h1 class="doc-title"><?= htmlspecialchars($typLabel) ?></h1>

            <?php if ($isArchived): ?>
                <span class="badge text-bg-secondary" style="font-size:0.65rem;"><i class="fa-solid fa-box-archive me-1"></i>Archiviert</span>
            <?php else: ?>
                <span class="badge text-bg-success" style="font-size:0.65rem;opacity:0.8;"><i class="fa-solid fa-circle-check me-1"></i>Aktiv</span>
            <?php endif; ?>

            <div class="ms-auto d-flex gap-1 doc-actions">
                <?php if ($pdfExists): ?>
                    <a href="<?= $pdfUrl ?>" download class="btn btn-outline-primary" title="PDF herunterladen"><i class="fa-solid fa-download"></i></a>
                    <a href="<?= $pdfUrl ?>" target="_blank" class="btn btn-outline-light" title="PDF in neuem Tab"><i class="fa-solid fa-up-right-from-square"></i></a>
                <?php endif; ?>
                <?php if ($canManage): ?>
                    <button class="btn btn-outline-secondary" id="btn-toggle-archive" title="<?= $isArchived ? 'Wiederherstellen' : 'Archivieren' ?>">
                        <i class="fa-solid <?= $isArchived ? 'fa-box-open' : 'fa-box-archive' ?>"></i>
                    </button>
                    <button class="btn btn-outline-danger" id="btn-delete-doc" title="Endgültig löschen">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Zeile 2: Meta-Chips -->
        <div class="doc-topbar-meta">
            <span class="meta-chip"><i class="fa-solid fa-hashtag"></i> <code style="font-size:0.82rem;color:var(--bs-body-color);"><?= htmlspecialchars($doc['docid']) ?></code></span>
            <span class="meta-sep">|</span>
            <span class="meta-chip"><i class="fa-solid fa-user"></i> <?= htmlspecialchars($doc['erhalter'] ?? $doc['empfaenger_fullname'] ?? '-') ?></span>
            <span class="meta-sep">|</span>
            <span class="meta-chip"><i class="fa-solid fa-pen-nib"></i> <?= htmlspecialchars($doc['ersteller_name']) ?></span>
            <span class="meta-sep">|</span>
            <span class="meta-chip"><i class="fa-solid fa-calendar"></i> <?= $austdatum ?></span>
        </div>
    </div>

    <!-- PDF Viewer (zentriert auf dunklem Hintergrund) -->
    <div class="doc-viewer-area">
        <?php if ($pdfExists): ?>
            <iframe src="<?= $pdfUrl ?>"></iframe>
        <?php else: ?>
            <div class="doc-empty">
                <i class="fa-solid fa-file-circle-exclamation fa-3x mb-3" style="opacity:0.2;"></i>
                <p>PDF-Datei nicht verfügbar</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
    <script>
    document.getElementById('btn-toggle-archive')?.addEventListener('click', async function() {
        const archive = <?= $isArchived ? 'false' : 'true' ?>;
        const action = archive ? 'archivieren' : 'wiederherstellen';
        const confirmed = await showConfirm('Dokument wirklich ' + action + '?', {
            title: 'Dokument ' + action,
            confirmText: archive ? 'Archivieren' : 'Wiederherstellen'
        });
        if (!confirmed) return;

        try {
            const res = await fetch('<?= BASE_PATH ?>api/documents/archive.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    docid: '<?= htmlspecialchars($doc['docid']) ?>',
                    archived: archive,
                    csrf_token: '<?= CsrfProtection::getToken() ?>'
                })
            });
            const result = await res.json();
            if (result.success) location.reload();
            else showAlert('Fehler: ' + result.error, { type: 'error' });
        } catch (err) {
            showAlert('Fehler: ' + err.message, { type: 'error' });
        }
    });

    document.getElementById('btn-delete-doc')?.addEventListener('click', async function() {
        const confirmed = await showConfirm('Dieses Dokument wird endgültig gelöscht. Die PDF-Datei wird unwiderruflich entfernt.', {
            title: 'Dokument löschen',
            danger: true,
            confirmText: 'Endgültig löschen'
        });
        if (!confirmed) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= BASE_PATH ?>mitarbeiter/dokument-delete.php';
        form.innerHTML = '<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CsrfProtection::getToken()) ?>">'
            + '<input type="hidden" name="docid" value="<?= htmlspecialchars($doc['docid']) ?>">'
            + '<input type="hidden" name="pid" value="<?= htmlspecialchars($doc['profileid']) ?>">';
        document.body.appendChild(form);
        form.submit();
    });
    </script>
    <?php endif; ?>

</body>
</html>
