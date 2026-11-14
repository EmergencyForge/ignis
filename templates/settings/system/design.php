<?php
/**
 * View: Design (Einstellungen › System › Design).
 *
 * Three forms in tabs, one per part. After a failed save the controller
 * opens the tab with the error and fills in the input again.
 *
 * @var string                    $tab       css, styles or js
 * @var list<string>              $errors
 * @var string                    $scss
 * @var list<mixed>               $styleRows rows as posted after an error
 * @var string                    $jsHead
 * @var string                    $jsBody
 * @var bool                      $canEditJs
 */

$layout = 'admin';
$windowSingle = true;
$bodyId = 'settings';
$SITE_TITLE = 'Design';
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$styleRow = static function (array $row, string $index) use ($e): string {
    $kind = ($row['kind'] ?? 'block') === 'inline' ? 'inline' : 'block';
    ob_start(); ?>
    <div class="ignis-design-style" data-style-row>
        <div class="ignis-design-style__head">
            <div>
                <label class="ignis-field__label" for="style-label-<?= $index ?>">Name</label>
                <input type="text" class="ignis-input" id="style-label-<?= $index ?>" name="styles[<?= $index ?>][label]" maxlength="40" value="<?= $e($row['label'] ?? '') ?>">
            </div>
            <div>
                <label class="ignis-field__label" for="style-key-<?= $index ?>">Klasse</label>
                <div class="ignis-design-prefix"><span>efs-</span><input type="text" class="ignis-input ignis-mono" id="style-key-<?= $index ?>" name="styles[<?= $index ?>][key]" maxlength="32" pattern="[a-z][a-z0-9\-]{0,31}" value="<?= $e($row['key'] ?? '') ?>"></div>
            </div>
            <div>
                <label class="ignis-field__label" for="style-kind-<?= $index ?>">Art</label>
                <select class="ignis-input" id="style-kind-<?= $index ?>" name="styles[<?= $index ?>][kind]" data-custom-dropdown="true">
                    <option value="block"<?= $kind === 'block' ? ' selected' : '' ?>>Absatz</option>
                    <option value="inline"<?= $kind === 'inline' ? ' selected' : '' ?>>Textstelle</option>
                </select>
            </div>
            <button type="button" class="ignis-btn ignis-btn--ghost-danger ignis-btn--icon" aria-label="Stil entfernen" data-style-remove><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
        </div>
        <div class="ignis-design-style__body">
            <textarea class="ignis-input ignis-design-code" rows="3" spellcheck="false" name="styles[<?= $index ?>][css]" aria-label="CSS des Stils" data-style-css><?= $e($row['css'] ?? '') ?></textarea>
            <div class="ignis-design-style__preview" aria-hidden="true"><span data-style-sample<?= $kind === 'block' ? ' style="display:block"' : '' ?>>Beispieltext</span></div>
        </div>
    </div>
    <?php return (string) ob_get_clean();
};
?>
    <style>
        .ignis-design-code { font-family: var(--mono); font-size: 13px; line-height: 1.5; tab-size: 2; white-space: pre; }
        .ignis-design-styles { display: grid; gap: .75rem; }
        .ignis-design-style { min-width: 0; border: 1px solid var(--border); border-radius: var(--radius); padding: .75rem; }
        .ignis-design-style__head { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr) 9rem auto; gap: .75rem; align-items: end; margin-bottom: .5rem; }
        .ignis-design-style__body { display: grid; grid-template-columns: minmax(0,1fr) 12rem; gap: .75rem; }
        .ignis-design-style__preview { display: flex; align-items: center; padding: 0 .75rem; border-radius: var(--radius); background: var(--surface-2); overflow: hidden; }
        .ignis-design-prefix { display: flex; }
        .ignis-design-prefix span { display: flex; align-items: center; padding: 0 .5rem; border: 1px solid var(--border-strong); border-right: 0; border-radius: var(--radius) 0 0 var(--radius); font-family: var(--mono); font-size: 13px; color: var(--text-3); }
        .ignis-design-prefix input { border-top-left-radius: 0; border-bottom-left-radius: 0; }
        @media (max-width: 700px) { .ignis-design-style__head, .ignis-design-style__body { grid-template-columns: 1fr; } }
    </style>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Design</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">System</p>
                    <h1>Design</h1>
                    <p class="twplus-page-header__description">Eigenes CSS, Stile für den Editor und eigenes JavaScript. Änderungen gelten ab dem nächsten Seitenaufruf.</p>
                </div>
            </div>

            <?php if ($errors !== []): ?>
                <div class="ignis-alert ignis-alert--danger mb-4" role="alert">
                    <i class="fa-solid fa-circle-exclamation ignis-alert__icon" aria-hidden="true"></i>
                    <div class="ignis-alert__body">
                        <div class="ignis-alert__title">Nicht gespeichert</div>
                        <ul class="m-0 pl-5"><?php foreach ($errors as $error): ?><li><?= $e($error) ?></li><?php endforeach; ?></ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="ignis-tabs" data-ignis-tabs data-default="<?= $e($tab) ?>">
                <div class="ignis-tabs__headers mb-4" role="tablist">
                    <button type="button" class="ignis-tabs__header" data-tab="css">CSS</button>
                    <button type="button" class="ignis-tabs__header" data-tab="styles">Editor-Stile</button>
                    <button type="button" class="ignis-tabs__header" data-tab="js">JavaScript</button>
                </div>
                <div class="ignis-tabs__panels">
                    <section class="ignis-tabs__panel" data-panel="css">
                        <form method="post" action="<?= BASE_PATH ?>settings/system/design/css" class="ignis-card mb-4">
                            <?= csrf_field() ?>
                            <div class="ignis-card__header">
                                <h2 class="ignis-card__title">Eigenes CSS</h2>
                            </div>
                            <div class="ignis-card__body">
                                <label class="ignis-field__label" for="design-scss">SCSS oder CSS</label>
                                <textarea class="ignis-input ignis-design-code" id="design-scss" name="scss" rows="16" spellcheck="false"><?= $e($scss) ?></textarea>
                                <p class="ignis-field__hint">Wird nach dem CSS von ıgnıs geladen. Variablen wie <code>--accent</code> oder <code>--radius</code> lassen sich hier überschreiben. SCSS wird beim Speichern übersetzt, <code>@import</code> von Dateien geht nicht. Höchstens 60 KB.</p>
                                <p class="ignis-field__hint mb-0">Zerlegt das CSS die Seite, hilft der abgesicherte Modus: <code>?safemode=1</code> an die Adresse hängen.</p>
                            </div>
                            <div class="ignis-card__footer">
                                <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-save" aria-hidden="true"></i> Speichern</button>
                            </div>
                        </form>
                    </section>

                    <section class="ignis-tabs__panel" data-panel="styles">
                        <form method="post" action="<?= BASE_PATH ?>settings/system/design/styles" class="ignis-card mb-4" data-design-styles>
                            <?= csrf_field() ?>
                            <div class="ignis-card__header">
                                <h2 class="ignis-card__title">Editor-Stile</h2>
                            </div>
                            <div class="ignis-card__body">
                                <p class="ignis-field__hint mt-0 mb-3">Erscheinen im Editor im Menü „Stil“, in Dokumenten, Mails, der Wissensdatenbank und in Lerneinheiten. Absatzstile gelten für den ganzen Absatz, Textstile für die markierte Stelle. Ins CSS gehören nur Angaben wie <code>color: …;</code>, den Selektor setzt ıgnıs. Bilder und <code>@</code>-Regeln sind nicht erlaubt, weil die Stile auch im PDF gelten.</p>
                                <div class="ignis-design-styles mb-3" data-style-list>
                                    <?php foreach ($styleRows as $i => $row): ?>
                                        <?= $styleRow(is_array($row) ? $row : [], (string) $i) ?>
                                    <?php endforeach; ?>
                                </div>
                                <template data-style-template><?= $styleRow([], '__i__') ?></template>
                                <button type="button" class="ignis-btn ignis-btn--secondary" data-style-add><i class="fa-solid fa-plus" aria-hidden="true"></i> Stil hinzufügen</button>
                            </div>
                            <div class="ignis-card__footer">
                                <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-save" aria-hidden="true"></i> Speichern</button>
                            </div>
                        </form>
                    </section>

                    <section class="ignis-tabs__panel" data-panel="js">
                        <?php if ($canEditJs): ?>
                            <div class="ignis-alert ignis-alert--warn mb-4">
                                <i class="fa-solid fa-triangle-exclamation ignis-alert__icon" aria-hidden="true"></i>
                                <div class="ignis-alert__body">Dieser Code läuft im Browser jedes Nutzers, auch bei Admins, und kann in deren Namen handeln. Trag nur Code aus einer Quelle ein, der du vertraust. Jede Änderung steht im Audit-Log.</div>
                            </div>
                            <form method="post" action="<?= BASE_PATH ?>settings/system/design/js" class="ignis-card mb-4">
                                <?= csrf_field() ?>
                                <div class="ignis-card__header">
                                    <h2 class="ignis-card__title">Eigenes JavaScript</h2>
                                </div>
                                <div class="ignis-card__body grid gap-4">
                                    <div>
                                        <label class="ignis-field__label" for="design-js-head">Im Kopf der Seite</label>
                                        <textarea class="ignis-input ignis-design-code" id="design-js-head" name="js_head" rows="8" spellcheck="false"><?= $e($jsHead) ?></textarea>
                                        <p class="ignis-field__hint mb-0">Nur JavaScript, ohne <code>&lt;script&gt;</code>. Läuft, bevor die Seite aufgebaut ist.</p>
                                    </div>
                                    <div>
                                        <label class="ignis-field__label" for="design-js-body">Am Ende der Seite</label>
                                        <textarea class="ignis-input ignis-design-code" id="design-js-body" name="js_body" rows="8" spellcheck="false"><?= $e($jsBody) ?></textarea>
                                    </div>
                                </div>
                                <div class="ignis-card__footer">
                                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-save" aria-hidden="true"></i> Speichern</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="ignis-alert ignis-alert--info mb-4">
                                <i class="fa-solid fa-lock ignis-alert__icon" aria-hidden="true"></i>
                                <div class="ignis-alert__body">Eigenes JavaScript kann nur ein Admin+ bearbeiten.</div>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= asset('assets/js/pages/design-settings.js') ?>"></script>
