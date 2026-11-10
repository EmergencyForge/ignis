<?php
/**
 * Knopf „Importieren“ mit Dropzone im Menü.
 *
 * @var string $importUrl
 * @var string $importAccept
 * @var string $importHint
 * @var string $importId
 */
?>
<details class="ignis-menu ignis-menu--right" data-ignis-menu>
    <summary class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-file-import" aria-hidden="true"></i> Importieren</summary>
    <div class="ignis-menu__panel tr-import">
        <form method="post" action="<?= htmlspecialchars($importUrl) ?>" enctype="multipart/form-data" class="grid gap-2">
            <?= csrf_field() ?>
            <div class="ignis-file ignis-file--dropzone" data-ignis-file data-max-bytes="5242880">
                <input type="file" id="<?= htmlspecialchars($importId) ?>" name="file" class="ignis-file__input" accept="<?= htmlspecialchars($importAccept) ?>" required>
                <label for="<?= htmlspecialchars($importId) ?>" class="ignis-file__zone">
                    <span class="ignis-file__icon" aria-hidden="true"><i class="fa-solid fa-file-import"></i></span>
                    <span class="ignis-file__title">Datei hierher ziehen oder <span class="ignis-file__link">auswählen</span></span>
                    <span class="ignis-file__hint"><?= htmlspecialchars($importHint) ?></span>
                </label>
                <div class="ignis-file__selected" hidden></div>
            </div>
            <button type="submit" class="ignis-btn ignis-btn--primary">Importieren</button>
        </form>
    </div>
</details>
