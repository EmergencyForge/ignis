<?php
/**
 * Ein ausgestelltes Dokument: Kopfzeile und das PDF im Rahmen.
 *
 * Erwartete Variablen (EditorDocumentController::show()):
 *   @var \App\Models\EditorDocument $document
 *   @var bool                       $canRevoke
 *
 * Fehlt `pdf_path` (was nur passieren kann, wenn das Erzeugen mitten im
 * Ausstellen gescheitert ist), steht hier ein Hinweis statt eines leeren
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
                <span class="ignis-breadcrumb__item" aria-current="page">Dokument</span>
            </nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Dokumente</p>
                    <h1><?= htmlspecialchars($document->title) ?></h1>
                    <p class="twplus-page-header__description">
                        Kennung <?= htmlspecialchars($document->docid) ?>, ausgestellt<?php
                        if ($document->issued_at !== null) {
                            echo ' am ' . htmlspecialchars($document->issued_at->format('d.m.Y H:i'));
                        } ?>.<?php if ($document->verification_code !== null): ?>
                        Prüfcode <span class="font-mono"><?= htmlspecialchars(\EmergencyForge\Training\VerificationCode::format($document->verification_code)) ?></span>.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <?php if ($canRevoke): ?>
                        <details class="ignis-menu ignis-menu--right" data-ignis-menu>
                            <summary class="ignis-btn ignis-btn--ghost-danger"><i class="fa-solid fa-ban" aria-hidden="true"></i> Widerrufen</summary>
                            <div class="ignis-menu__panel" style="width: 22rem; padding: 0.75rem;">
                                <form method="post" action="<?= BASE_PATH ?>documents/<?= (int) $document->id ?>/revoke" class="grid gap-2">
                                    <?= csrf_field() ?>
                                    <label class="ignis-field__label" for="revoke-reason">Begründung</label>
                                    <textarea id="revoke-reason" name="reason" class="ignis-input" rows="3" maxlength="500" required
                                              aria-describedby="revoke-hint" placeholder="z. B. Prüfung nachträglich aberkannt"></textarea>
                                    <p class="ignis-field__hint" id="revoke-hint">Die Begründung sieht nur, wer das Dokument öffnen darf. Die Echtheitsprüfung zeigt danach „Widerrufen am …“.</p>
                                    <button type="submit" class="ignis-btn ignis-btn--danger ignis-btn--sm">Urkunde widerrufen</button>
                                </form>
                            </div>
                        </details>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" rel="noopener"
                       class="ignis-btn ignis-btn--primary">
                        <i class="fa-solid fa-download" aria-hidden="true"></i> PDF öffnen
                    </a>
                </div>
            </div>

            <?php if ($document->isRevoked()): ?>
                <div class="ignis-alert ignis-alert--danger mb-4" role="alert">
                    <i class="fa-solid fa-ban ignis-alert__icon" aria-hidden="true"></i>
                    <div class="ignis-alert__body">
                        <strong>Widerrufen am <?= htmlspecialchars($document->revoked_at->format('d.m.Y')) ?><?php
                            if ($document->revoker !== null) {
                                echo ' von ' . htmlspecialchars($document->revoker->fullname ?: $document->revoker->username);
                            } ?></strong><br>
                        <?= nl2br(htmlspecialchars((string) $document->revoke_reason)) ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($document->pdf_path === null): ?>
                <div class="ignis-alert ignis-alert--warn" role="alert">
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
