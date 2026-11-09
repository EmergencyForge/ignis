<?php
/**
 * Briefbogen anlegen oder bearbeiten. Derselbe Editor wie für Vorlagen
 * (template-editor.js); vom Paket speichert LetterheadController nur
 * Layout und Bänder.
 *
 * Erwartete Variablen (LetterheadController::createView()/editView()):
 *   @var \App\Models\Letterhead|null $letterhead
 *   @var string               $formAction
 *   @var int                  $usage      Zahl der Vorlagen, die ihn nutzen
 *   @var array<string,string> $variables  Schlüssel auf Beschriftung
 *   @var string               $uploadUrl
 */

use App\Security\CsrfProtection;

$isEdit    = $letterhead !== null;
$csrfToken = CsrfProtection::getToken();
// Ein Textkörper zum Ausprobieren; gespeichert wird er nicht.
$package = $letterhead->content ?? [
    'format' => 2,
    'layout' => null,
    'body'   => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
    'header' => ['first' => null, 'rest' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]]],
    'footer' => ['first' => null, 'rest' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]]],
];
$contentJson   = htmlspecialchars(json_encode($package, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null', ENT_QUOTES);
$variablesJson = htmlspecialchars(json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES);

$layout       = 'admin';
$windowSingle = true;
$bodyId       = 'settings';
$SITE_TITLE   = $isEdit ? 'Briefbogen bearbeiten' : 'Briefbogen anlegen';
$layoutHead   = '<link rel="stylesheet" href="' . asset('assets/dist/editor.css') . '">';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb">
                <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span>
                <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/index">Einstellungen</a></span>
                <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/documents/letterheads">Briefbögen</a></span>
                <span class="ignis-breadcrumb__item" aria-current="page"><?= $isEdit ? 'Bearbeiten' : 'Anlegen' ?></span>
            </nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Dokumente</p>
                    <h1><?= htmlspecialchars($SITE_TITLE) ?></h1>
                    <p class="twplus-page-header__description">
                        Gespeichert werden Seitenränder, Kopf- und Fußzeilen. Der Text auf dem Blatt ist nur zum Ausprobieren.
                        <?php if ($usage > 0): ?>
                            <?= $usage === 1 ? 'Eine Vorlage nutzt' : $usage . ' Vorlagen nutzen' ?> diesen Briefbogen und bekommen beim Speichern eine neue Fassung.
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <form method="POST" action="<?= htmlspecialchars($formAction) ?>" id="template-form">
                <input type="hidden" name="csrf_token" id="template-csrf-input" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="content" id="template-content-input" value="">

                <div class="ignis-card mb-4">
                    <div class="ignis-card__body">
                        <div class="ignis-field" style="max-width: 24rem;">
                            <label class="ignis-field__label" for="letterhead-name">Name *</label>
                            <input type="text" class="ignis-input" id="letterhead-name" name="name" required
                                   maxlength="150" placeholder="Dienstbrief"
                                   value="<?= htmlspecialchars($letterhead->name ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="mb-4"
                     id="template-editor-mount"
                     data-efe-variables="<?= $variablesJson ?>"
                     data-efe-content="<?= $contentJson ?>"
                     data-efe-upload-url="<?= htmlspecialchars($uploadUrl) ?>"></div>

                <div class="flex gap-2">
                    <button type="submit" class="ignis-btn ignis-btn--primary">Speichern</button>
                    <a href="<?= BASE_PATH ?>settings/documents/letterheads" class="ignis-btn ignis-btn--secondary">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>

    <script src="<?= asset('assets/dist/editor.iife.js') ?>"></script>
    <script src="<?= asset('assets/js/pages/template-editor.js') ?>"></script>
