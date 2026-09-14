<?php
/**
 * Vorlagen-Editor des Dokumentensystems — dieselbe Ansicht für Anlegen und
 * Bearbeiten.
 *
 * Erwartete Variablen (EditorTemplateController::createView()/editView()):
 *   @var \App\Models\EditorTemplate|null $template
 *   @var string               $formAction
 *   @var array<string,string> $variables       Schlüssel auf Beschriftung
 *   @var array<string,mixed>  $initialContent  Start-Dokument für den Editor
 *
 * Die Editor-Dateien hängen an dieser Seite und nicht in head.php: das
 * Bundle wiegt knapp 500 kB, und außerhalb der beiden Editor-Seiten
 * braucht es niemand.
 *
 * Die zweite Leiste über dem Editor ist ignis-eigen. Das Paket bringt für
 * Felder, Titel und Wiederholung bewusst keine Bedienoberfläche mit, und
 * in seine Toolbar ließe sich nichts einhängen — createToolbar() leert
 * ihren Container beim Mounten.
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
    json_encode($initialContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"type":"doc","content":[]}',
    ENT_QUOTES,
);

$layout     = 'admin';
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
                <span class="ignis-breadcrumb__item is-active"><?= $isEdit ? 'Bearbeiten' : 'Anlegen' ?></span>
            </nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Dokumente</p>
                    <h1><?= htmlspecialchars($SITE_TITLE) ?></h1>
                    <p class="twplus-page-header__description">
                        Gesperrte Abschnitte bleiben in jedem Dokument aus dieser Vorlage unverändert;
                        freie Abschnitte darf der Aussteller später bearbeiten. Ausfüllbare Felder gehen
                        auch in gesperrtem Text — ein Doppelklick darauf öffnet die Eigenschaften.
                    </p>
                </div>
            </div>

            <form method="POST" action="<?= htmlspecialchars($formAction) ?>" id="template-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
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
                    </div>
                </div>

                <div class="ignis-card mb-4">
                    <div id="template-toolbar"></div>
                    <div class="efe-toolbar" role="toolbar" aria-label="Vorlagen-Bausteine">
                        <button type="button" id="template-field-button"
                                class="efe-toolbar__button efe-toolbar__button--labelled"
                                aria-label="Ausfüllbares Feld einfügen oder bearbeiten">Feld …</button>
                        <button type="button" id="template-section-button"
                                class="efe-toolbar__button efe-toolbar__button--labelled"
                                aria-label="Titel, Hinweis und Wiederholung des Abschnitts">Abschnitt …</button>
                    </div>
                    <div class="efe-surround">
                        <div class="efe-page"
                             id="template-editor-mount"
                             data-efe-variables="<?= $variablesJson ?>"
                             data-efe-content="<?= $contentJson ?>"></div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="ignis-btn ignis-btn--primary">Speichern</button>
                    <a href="<?= BASE_PATH ?>settings/documents/editor-templates" class="ignis-btn ignis-btn--secondary">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>

    <script src="<?= asset('assets/dist/editor.iife.js') ?>"></script>
    <script src="<?= asset('assets/js/pages/template-editor.js') ?>"></script>
