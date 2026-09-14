<?php
/**
 * Dokumenten-Editor: ein Entwurf wird bearbeitet.
 *
 * Erwartete Variablen (EditorDocumentController::editView()):
 *   @var \App\Models\EditorDocument $document
 *   @var array<string,string>       $variables   aufgelöste Werte
 *   @var array<string,string>       $catalog     Schlüssel auf Beschriftung
 *   @var string                     $saveAction
 *
 * Gespeichert wird per fetch, nicht als Formular mit Weiterleitung: ein
 * Seitenwechsel würde den Zustand des Editors verwerfen, und die Autosave
 * alle 30 Sekunden hätte dasselbe Problem. Der CSRF-Token liegt in einem
 * versteckten Feld, das `document-editor.js` nach jeder Antwort
 * nachzieht — der Token rotiert bei jeder Prüfung.
 *
 * Ausstellen ist dagegen ein eigenes, klassisches Formular: ein einmaliger,
 * folgenreicher Schritt, nach dem die Weiterleitung auf das PDF genau
 * richtig ist. Sein Token wird erst unmittelbar vor dem Abschicken aus dem
 * Speichern-Feld übernommen — zwei von Hand gepflegte Felder würden
 * auseinanderlaufen, sobald ein Autosave dazwischenkommt.
 *
 * Fehlt die Vorlage (gelöscht, Fremdschlüssel auf null), ist der Entwurf
 * schreibgeschützt: ohne Referenz kann der SectionGuard gesperrte
 * Abschnitte nicht zurückholen, und „gesperrt" wäre nur noch behauptet.
 */

use App\Security\CsrfProtection;

$csrfToken = CsrfProtection::getToken();
$readOnly  = $document->template === null;
$issueUrl  = BASE_PATH . 'documents/' . $document->id . '/issue';

$contentJson = htmlspecialchars(
    json_encode($document->content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"type":"doc","content":[]}',
    ENT_QUOTES,
);
$labelsJson = htmlspecialchars(
    json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES,
);
$resolvedJson = htmlspecialchars(
    json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES,
);

$layout     = 'admin';
$bodyId     = 'documents';
$SITE_TITLE = $document->title;
$layoutHead = '<link rel="stylesheet" href="' . asset('assets/dist/editor.css') . '">';
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
                <span class="ignis-breadcrumb__item is-active">Dokument bearbeiten</span>
            </nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Dokumente</p>
                    <h1><?= htmlspecialchars($document->title) ?></h1>
                    <p class="twplus-page-header__description">
                        Kennung <?= htmlspecialchars($document->docid) ?> — Entwurf. Gesperrte Abschnitte sind
                        aus der Vorlage vorgegeben und lassen sich nicht ändern.
                    </p>
                </div>
            </div>

            <?php if ($readOnly): ?>
                <div class="ignis-alert ignis-alert--warning mb-4" id="document-readonly-notice" role="alert">
                    <i class="fa-solid fa-triangle-exclamation ignis-alert__icon" aria-hidden="true"></i>
                    <div class="ignis-alert__body">
                        <strong>Schreibgeschützt</strong><br>
                        Die Vorlage dieses Dokuments wurde gelöscht — der Entwurf kann nicht mehr gespeichert werden.
                    </div>
                </div>
            <?php endif; ?>

            <form id="document-form" data-save-url="<?= htmlspecialchars($saveAction) ?>">
                <input type="hidden" id="document-csrf-input" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="ignis-card mb-4">
                    <div class="ignis-card__body flex flex-wrap items-center gap-3">
                        <div class="ignis-field" style="flex: 1 1 20rem;">
                            <label class="ignis-field__label" for="document-title-input">Titel</label>
                            <input type="text" class="ignis-input" id="document-title-input" maxlength="200"
                                   placeholder="Titel des Dokuments"
                                   value="<?= htmlspecialchars($document->title) ?>" <?= $readOnly ? 'disabled' : '' ?>>
                        </div>
                        <div class="flex items-center gap-2" style="margin-left: auto;">
                            <span id="document-save-status" class="text-sm"></span>
                            <button type="button" id="document-save-button" class="ignis-btn ignis-btn--primary"
                                    <?= $readOnly ? 'disabled title="Kein Speichern möglich — Vorlage fehlt."' : '' ?>>
                                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Speichern
                            </button>
                            <button type="submit" form="document-issue-form" id="document-issue-button"
                                    class="ignis-btn ignis-btn--accent">
                                <i class="fa-solid fa-file-export" aria-hidden="true"></i> Ausstellen
                            </button>
                        </div>
                    </div>
                </div>

                <div class="ignis-card mb-4">
                    <div id="document-toolbar"></div>
                    <div class="efe-surround">
                        <div class="efe-page"
                             id="document-editor-mount"
                             data-efe-content="<?= $contentJson ?>"
                             data-efe-variables="<?= $labelsJson ?>"
                             data-efe-resolved="<?= $resolvedJson ?>"
                             data-efe-readonly="<?= $readOnly ? '1' : '0' ?>"></div>
                    </div>
                </div>
            </form>

            <?php
            // Eigenes Formular, weil der Knopf oben im Speichern-Formular
            // steht und verschachtelte <form> ungültig sind; das
            // form-Attribut verbindet beide. Der Token kommt erst hier aus
            // dem Speichern-Feld, danach die Rückfrage.
            $tokenCopy = "document.getElementById('document-issue-csrf-input').value = "
                . "document.getElementById('document-csrf-input').value; ";
            $confirmText = 'Dokument "' . $document->title . '" wirklich ausstellen? Das ist unwiderruflich '
                . '— der Entwurf kann danach nicht mehr bearbeitet werden.';
            ?>
            <form id="document-issue-form" method="POST" action="<?= htmlspecialchars($issueUrl) ?>"
                  onsubmit="<?= htmlspecialchars($tokenCopy, ENT_QUOTES) ?><?= confirm_attr($confirmText) ?>">
                <input type="hidden" name="csrf_token" id="document-issue-csrf-input"
                       value="<?= htmlspecialchars($csrfToken) ?>">
            </form>
        </div>
    </div>

    <script src="<?= asset('assets/dist/editor.iife.js') ?>"></script>
    <script src="<?= asset('assets/js/pages/document-editor.js') ?>"></script>
