<?php
/**
 * Ein ausgestelltes Dokument: Kopfzeile und das PDF im Rahmen.
 *
 * Erwartete Variablen (EditorDocumentController::show()):
 *   @var \App\Models\EditorDocument $document
 *
 * Fehlt `pdf_path` — was nur passieren kann, wenn das Erzeugen mitten im
 * Ausstellen gescheitert ist —, steht hier ein Hinweis statt eines leeren
 * Rahmens.
 */

$pdfUrl = BASE_PATH . 'documents/' . $document->id . '/pdf';

$layout     = 'admin';
$bodyId     = 'documents';
$SITE_TITLE = $document->title;
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb">
                <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span>
                <?php if ($document->mitarbeiter !== null): ?>
                    <span class="ignis-breadcrumb__item">
                        <a href="<?= BASE_PATH ?>personnel/profile?id=<?= (int) $document->mitarbeiter_id ?>">
                            <?= htmlspecialchars($document->mitarbeiter->fullname) ?>
                        </a>
                    </span>
                <?php endif; ?>
                <span class="ignis-breadcrumb__item is-active">Dokument</span>
            </nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Dokumente</p>
                    <h1><?= htmlspecialchars($document->title) ?></h1>
                    <p class="twplus-page-header__description">
                        Kennung <?= htmlspecialchars($document->docid) ?> — ausgestellt<?php
                        if ($document->issued_at !== null) {
                            echo ' am ' . htmlspecialchars($document->issued_at->format('d.m.Y H:i'));
                        } ?>.
                    </p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" rel="noopener"
                       class="ignis-btn ignis-btn--primary">
                        <i class="fa-solid fa-download" aria-hidden="true"></i> PDF öffnen
                    </a>
                </div>
            </div>

            <?php if ($document->pdf_path === null): ?>
                <div class="ignis-alert ignis-alert--warning" role="alert">
                    <i class="fa-solid fa-triangle-exclamation ignis-alert__icon" aria-hidden="true"></i>
                    <div class="ignis-alert__body">
                        <strong>PDF fehlt</strong><br>
                        Zu diesem ausgestellten Dokument liegt keine PDF-Datei vor.
                    </div>
                </div>
            <?php else: ?>
                <div class="twplus-table-card">
                    <iframe src="<?= htmlspecialchars($pdfUrl) ?>" title="Dokument als PDF"
                            style="width: 100%; height: 80vh; border: none;"></iframe>
                </div>
            <?php endif; ?>
        </div>
    </div>
