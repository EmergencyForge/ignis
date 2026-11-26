<?php
/**
 * Löschen eines Editor-Dokuments mit Rückfrage im Menü.
 *
 * @var \App\Models\EditorDocument $document
 * @var string                     $deleteAction
 */
?>
<details class="ignis-menu ignis-menu--right" data-ignis-menu>
    <summary class="ignis-btn ignis-btn--ghost-danger"><i class="fa-solid fa-trash" aria-hidden="true"></i> Löschen</summary>
    <div class="ignis-menu__panel" style="width: 22rem; padding: 0.75rem;">
        <form method="post" action="<?= htmlspecialchars($deleteAction) ?>" class="grid gap-2">
            <?= csrf_field() ?>
            <p class="ignis-field__hint">
                <?php if ($document->isIssued()): ?>
                    „<?= htmlspecialchars($document->title) ?>“ ist bereits ausgestellt. Löschen entfernt Dokument und PDF endgültig<?= $document->verification_code !== null ? ', die Prüfseite kennt den Prüfcode danach nicht mehr' : '' ?>.
                    Soll die Urkunde nur nicht mehr gelten, ist Widerrufen der bessere Weg.
                <?php else: ?>
                    Der Entwurf „<?= htmlspecialchars($document->title) ?>“ wird endgültig gelöscht.
                <?php endif; ?>
                Im Verlauf der Akte bleibt ein Vermerk.
            </p>
            <button type="submit" class="ignis-btn ignis-btn--danger ignis-btn--sm">Endgültig löschen</button>
        </form>
    </div>
</details>
