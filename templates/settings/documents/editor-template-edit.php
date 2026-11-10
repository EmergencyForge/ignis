<?php
/**
 * Vorlagen-Editor des Dokumentensystems, dieselbe Ansicht für Anlegen und
 * Bearbeiten.
 *
 * Erwartete Variablen (EditorTemplateController::createView()/editView()):
 *   @var \App\Models\EditorTemplate|null $template
 *   @var string               $formAction
 *   @var array<string,string> $variables       Schlüssel auf Beschriftung
 *   @var array<string,mixed>|null $initialContent  Dokumentpaket; null fragt zuerst nach der Art
 *   @var list<array<string,mixed>> $letterheads   Briefbögen in der Form für createDocumentEditor
 *   @var list<array{key: string, label: string}> $who  Rechte, die ein Feld verlangen kann
 *   @var string               $uploadUrl       Bild-Upload für Logo und Wappen
 *
 * Die Editor-Dateien hängen an dieser Seite und nicht in head.php: das
 * Bundle wiegt gut 500 kB, und außerhalb der Editor-Seiten braucht es
 * niemand. Werkzeuge, Lineal und Eigenschaften bringt createDocumentEditor
 * mit.
 *
 * Variablen und Startinhalt gehen als escaptes JSON in data-Attribute,
 * nicht als Inline-Skript.
 */

use App\Security\CsrfProtection;

$isEdit    = $template !== null;
$csrfToken = CsrfProtection::getToken();

$variablesJson = htmlspecialchars(
    json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES,
);
$contentJson = htmlspecialchars(
    json_encode($initialContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null',
    ENT_QUOTES,
);
$letterheadsJson = htmlspecialchars(json_encode($letterheads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES);
$whoJson         = htmlspecialchars(json_encode($who, JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES);
// Listen aus den Stammdaten für „Liste aus dem System“.
$sourcesJson = htmlspecialchars(json_encode(\App\Documents\Editor\FieldChoices::lists(), JSON_UNESCAPED_UNICODE) ?: '{}', ENT_QUOTES);
$sourceLabelsJson = htmlspecialchars(json_encode(\App\Documents\Editor\FieldChoices::SOURCES, JSON_UNESCAPED_UNICODE) ?: '{}', ENT_QUOTES);

$layout     = 'admin';
$windowSingle = true;
$bodyId     = 'settings';
$SITE_TITLE = $isEdit ? 'Vorlage bearbeiten' : 'Vorlage anlegen';
$layoutHead = '<link rel="stylesheet" href="' . asset('assets/dist/editor.css') . '">';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb">
                <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span>
                <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/index">Einstellungen</a></span>
                <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/documents/editor-templates">Dokumentvorlagen</a></span>
                <span class="ignis-breadcrumb__item" aria-current="page"><?= $isEdit ? 'Bearbeiten' : 'Anlegen' ?></span>
            </nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Dokumente</p>
                    <h1><?= htmlspecialchars($SITE_TITLE) ?></h1>
                    <p class="twplus-page-header__description">
                        Text der Vorlage ist im Dokument fest. Ausfüllen kann der Aussteller nur die
                        Eingabebereiche und Felder, die du hier anlegst. Dokumente, die schon laufen,
                        behalten ihre Fassung, bis jemand sie umstellt.
                    </p>
                </div>
            </div>

            <form method="POST" action="<?= htmlspecialchars($formAction) ?>" id="template-form">
                <input type="hidden" name="csrf_token" id="template-csrf-input" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="content" id="template-content-input" value="">

                <div class="ignis-card mb-4">
                    <div class="ignis-card__body flex flex-wrap gap-3">
                        <div class="ignis-field" style="flex: 1 1 16rem;">
                            <label class="ignis-field__label" for="template-name">Name *</label>
                            <input type="text" class="ignis-input" id="template-name" name="name" required
                                   maxlength="150" placeholder="Beförderungsurkunde"
                                   value="<?= htmlspecialchars($template->name ?? '') ?>">
                        </div>
                        <div class="ignis-field" style="flex: 1 1 12rem;">
                            <label class="ignis-field__label" for="template-category">Kategorie</label>
                            <input type="text" class="ignis-input" id="template-category" name="category"
                                   maxlength="100" placeholder="Urkunde"
                                   value="<?= htmlspecialchars($template->category ?? '') ?>">
                        </div>
                        <div class="ignis-field" style="flex: 1 1 16rem;">
                            <input type="hidden" name="print_verification" value="0">
                            <label class="ignis-checkbox">
                                <input type="checkbox" name="print_verification" value="1" aria-describedby="template-verification-hint"<?= ($template->print_verification ?? false) ? ' checked' : '' ?>>
                                <span>Prüfcode aufdrucken</span>
                            </label>
                            <p class="ignis-field__hint" id="template-verification-hint">Ausgestellte Dokumente bekommen einen Prüfcode, unten rechts mit QR-Code. Über /verify lässt sich die Echtheit ohne Anmeldung prüfen.</p>
                        </div>
                    </div>
                </div>

                <?php if ($initialContent === null): ?>
                    <div class="ignis-card mb-4" id="template-start">
                        <div class="ignis-card__body">
                            <p class="mb-3">Wie soll die Vorlage beginnen?</p>
                            <div class="flex flex-wrap gap-3">
                                <button type="button" class="ignis-btn ignis-btn--secondary" data-template-start="free">
                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Frei, mit festen Teilen
                                </button>
                                <button type="button" class="ignis-btn ignis-btn--secondary" data-template-start="fixed">
                                    <i class="fa-solid fa-file-lines" aria-hidden="true"></i> Fest, mit Lücken
                                </button>
                            </div>
                            <p class="ignis-field__hint mt-2">
                                Frei: der Aussteller schreibt den ganzen Text, du setzt einzelne Teile fest.
                                Fest: der Text steht, der Aussteller füllt nur die Lücken aus. Beides lässt sich
                                danach noch ändern.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="ignis-segmented mb-3" role="group" aria-label="Ansicht" id="template-modes"<?= $initialContent === null ? ' hidden' : '' ?>>
                    <button type="button" class="is-active" data-template-mode="template" aria-pressed="true">Vorlage</button>
                    <button type="button" data-template-mode="write" aria-pressed="false">Probe ausfüllen</button>
                    <button type="button" data-template-mode="preview" aria-pressed="false">Druckansicht</button>
                </div>

                <div class="mb-4"
                     id="template-editor-mount"
                     data-efe-variables="<?= $variablesJson ?>"
                     data-efe-content="<?= $contentJson ?>"
                     data-efe-letterheads="<?= $letterheadsJson ?>"
                     data-efe-who="<?= $whoJson ?>"
                     data-efe-sources="<?= $sourcesJson ?>"
                     data-efe-source-labels="<?= $sourceLabelsJson ?>"
                     data-efe-upload-url="<?= htmlspecialchars($uploadUrl) ?>"></div>

                <div class="flex gap-2">
                    <button type="submit" class="ignis-btn ignis-btn--primary">Speichern</button>
                    <a href="<?= BASE_PATH ?>settings/documents/editor-templates" class="ignis-btn ignis-btn--secondary">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>

    <script src="<?= asset('assets/dist/editor.iife.js') ?>"></script>
    <script src="<?= asset('assets/js/pages/template-editor.js') ?>"></script>
