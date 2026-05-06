<?php
/**
 * View: Dokument-Templates verwalten
 *
 * @var \PDO $pdo
 */

use App\Auth\Permissions;
use App\Helpers\Flash;

// Lade Dienstgrade und RD-Qualifikationen für Auswahlfelder
$dienstgradeStmt = $pdo->query("SELECT id, name, name_m, name_w FROM intra_mitarbeiter_dienstgrade WHERE archive = 0 ORDER BY priority ASC");
$dienstgrade = $dienstgradeStmt->fetchAll(PDO::FETCH_ASSOC);

$rdQualisStmt = $pdo->query("SELECT id, name, name_m, name_w FROM intra_mitarbeiter_rdquali WHERE trainable = 1 AND none = 0 ORDER BY priority ASC");
$rdQualis = $rdQualisStmt->fetchAll(PDO::FETCH_ASSOC);

// Lade Dokumenten-Kategorien
$katStmt = $pdo->query("SELECT id, name, color, icon FROM intra_dokument_kategorien ORDER BY sort_order ASC, name ASC");
$kategorien = $katStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    include __DIR__ . '/../../../assets/components/_base/admin/head.php';
    ?>
    <script src="<?= BASE_PATH ?>assets/_ext/sortablejs/Sortable.min.js"></script>
    <style>
        .template-card:hover {
            border-color: var(--bs-primary) !important;
        }

        .template-card .card-footer {
            opacity: 0.6;
            transition: opacity 0.15s;
        }

        .template-card:hover .card-footer {
            opacity: 1;
        }

        .field-list {
            min-height: 40px;
        }

        .field-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 0.6rem;
            border-radius: var(--bs-border-radius);
            border: 1px solid transparent;
            transition: background-color 0.1s;
            cursor: default;
        }

        .field-item:hover {
            background: rgba(255,255,255,0.03);
            border-color: var(--bs-border-color);
        }

        .field-item + .field-item {
            border-top-color: rgba(255,255,255,0.05);
        }

        .field-item .drag-handle {
            cursor: grab;
            color: var(--bs-secondary-color);
            opacity: 0.4;
            font-size: 0.75rem;
            user-select: none;
        }

        .field-item:hover .drag-handle { opacity: 0.8; }
        .field-item .drag-handle:active { cursor: grabbing; }

        .field-item .field-name {
            font-size: 0.88rem;
            font-weight: 500;
            flex-shrink: 0;
        }

        .field-item .field-meta {
            font-size: 0.72rem;
            color: var(--bs-secondary-color);
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .field-item .field-badges {
            display: flex;
            gap: 0.25rem;
            flex-shrink: 0;
        }

        .field-item .field-badges .badge {
            font-size: 0.6rem;
            padding: 0.15rem 0.35rem;
            font-weight: 500;
        }

        .field-item .field-actions {
            display: flex;
            gap: 0.25rem;
            flex-shrink: 0;
            opacity: 0;
            transition: opacity 0.1s;
        }

        .field-item:hover .field-actions,
        .field-item.editing .field-actions { opacity: 1; }

        .field-item.editing {
            background: rgba(255,255,255,0.03);
            border-color: var(--bs-primary);
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }

        .field-edit-panel {
            padding: 0.75rem;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--bs-primary);
            border-top: none;
            border-radius: 0 0 var(--bs-border-radius) var(--bs-border-radius);
            margin-bottom: 0.5rem;
        }

        .template-preview {
            border: 1px solid #dee2e6;
            padding: 2rem;
            border-radius: 0.375rem;
        }

        .option-item {
            padding: 1rem;
            border-radius: 0.375rem;
            border: 1px solid #495057;
            background-color: rgba(0, 0, 0, 0.2);
        }

        .sortable-ghost {
            opacity: 0.4;
            background-color: #f8f9fa;
        }

        .sortable-drag {
            opacity: 0.8;
        }

        .gender-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .option-item .gender-inputs {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #495057;
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container my-5">
            <?php Flash::render(); ?>

            <!-- Header mit Titel + Aktionen -->
            <div class="flex justify-between items-center mb-4">
                <h1 class="mb-0">Dokumenten-Templates</h1>
                <div class="flex gap-2">
                    <button class="ignis-btn ignis-btn--outline-info ignis-btn--sm" id="btn-convert-all" title="Alle Twig-Templates in visuelle Editor-Layouts neu konvertieren">
                        <i class="fa-solid fa-arrows-rotate mr-1"></i> Aus Vorlagen neu generieren
                    </button>
                    <?php if (($_ENV['APP_ENV'] ?? 'production') === 'development'): ?>
                        <button class="ignis-btn ignis-btn--outline-warning ignis-btn--sm" id="btn-regenerate-all" title="Alle Twig-Dateien neu generieren (Dev)">
                            <i class="fa-solid fa-flask mr-1"></i> Twig regenerieren
                        </button>
                    <?php endif; ?>
                    <button class="ignis-btn ignis-btn--soft-primary ignis-btn--sm" id="btn-new-template">
                        <i class="fa-solid fa-plus mr-1"></i> Neues Template
                    </button>
                </div>
            </div>

            <!-- Template-Liste als Karten-Grid -->
            <div id="templateGrid" class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <!-- Wird dynamisch befüllt -->
            </div>
            <div id="templateGridEmpty" class="text-center text-[var(--text-dimmed,#818189)] py-5" style="display:none;">
                <i class="fa-solid fa-file-circle-plus fa-3x mb-3" style="opacity:0.2;"></i>
                <p>Noch keine Templates vorhanden</p>
            </div>

        </div>
    </div>

    <!--
        Drei Park-Container: werden zur Laufzeit per ignis-Dialog (preserveBody=true)
        in den Dialog-Body verschoben und beim Schliessen zurueckgehaengt.
        Bewusst keine .modal-Klassen mehr — die Dialog-Wrapper bringen ihre
        eigene Box mit, die Park-Container brauchen keine Bootstrap-Modal-DOM.
    -->

    <!-- Template bearbeiten/erstellen — Park-Body -->
    <div id="templateFormModal" class="ignis-dialog-park" hidden>
        <form id="templateForm">
            <input type="hidden" id="templateId" name="templateId">

            <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                <div class="md:col-span-6">
                    <div class="mb-3">
                        <label for="templateName" class="ignis-field__label">Template-Name <span class="text-[#d46b6b]">*</span></label>
                        <input type="text" class="ignis-input" id="templateName" name="name" required>
                    </div>
                </div>
                <div class="md:col-span-3">
                    <div class="mb-3">
                        <label for="templateCategory" class="ignis-field__label">Kategorie <span class="text-[#d46b6b]">*</span></label>
                        <select class="form-select" id="templateCategory" name="category_id" required>
                            <option value="">Bitte wählen</option>
                            <?php foreach ($kategorien as $kat): ?>
                                <option value="<?= (int)$kat['id'] ?>"><?= htmlspecialchars($kat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="ignis-field__hint">
                            <a href="<?= BASE_PATH ?>settings/documents/categories">Kategorien verwalten</a>
                        </div>
                    </div>
                </div>
                <div class="md:col-span-3">
                    <div class="mb-3">
                        <label for="templateFile" class="ignis-field__label">Dateiname</label>
                        <input type="text" class="ignis-input" id="templateFile" name="template_file"
                            pattern="[a-z_]+\.html\.twig"
                            placeholder="auto">
                        <small class="text-[var(--text-dimmed,#818189)]">Automatisch wenn leer</small>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="templateDescription" class="ignis-field__label">Beschreibung</label>
                <textarea class="ignis-input" id="templateDescription" name="description" rows="1"></textarea>
            </div>

            <hr class="my-3">

            <div class="flex justify-between items-center mb-3">
                <h6 class="mb-0">Formularfelder</h6>
                <button type="button" class="ignis-btn ignis-btn--outline-secondary ignis-btn--sm" id="addFieldBtn">
                    <i class="fa-solid fa-plus mr-1"></i> Feld hinzufügen
                </button>
            </div>

            <div id="fieldList" class="field-list"></div>
        </form>
        <div class="ignis-dialog-park__footer flex justify-end gap-2 mt-3">
            <button type="button" class="ignis-btn ignis-btn--outline-secondary" id="previewBtn">Vorschau</button>
            <button type="button" class="ignis-btn ignis-btn--ghost" data-dialog-dismiss>Abbrechen</button>
            <button type="button" class="ignis-btn ignis-btn--soft-primary" id="saveTemplateBtn">
                <i class="fa-solid fa-floppy-disk mr-1"></i> Template speichern
            </button>
        </div>
    </div>

    <!-- Vorschau — Park-Body -->
    <div id="previewModal" class="ignis-dialog-park" hidden>
        <div id="previewContent" class="template-preview"></div>
    </div>

    <!-- Feld-Konfiguration — Park-Body -->
    <div id="fieldModal" class="ignis-dialog-park" hidden>
        <form id="fieldForm">
            <input type="hidden" id="fieldIndex">

            <div class="mb-3">
                <label for="fieldLabel" class="ignis-field__label">Feld-Label <span class="text-[#d46b6b]">*</span></label>
                <input type="text" class="ignis-input" id="fieldLabel" required>
            </div>

            <div class="mb-3">
                <label for="fieldName" class="ignis-field__label">Feld-Name (technisch) <span class="text-[#d46b6b]">*</span></label>
                <input type="text" class="ignis-input" id="fieldName" required
                    pattern="[a-z_]+" title="Nur Kleinbuchstaben und Unterstriche">
                <small class="text-[var(--text-dimmed,#818189)]">Nur Kleinbuchstaben und Unterstriche erlaubt</small>
            </div>

            <div class="mb-3">
                <label for="fieldType" class="ignis-field__label">Feld-Typ <span class="text-[#d46b6b]">*</span></label>
                <select class="form-select" id="fieldType" required>
                    <option value="text">Textfeld</option>
                    <option value="textarea">Mehrzeiliger Text</option>
                    <option value="richtext">Rich-Text Editor</option>
                    <option value="date">Datum</option>
                    <option value="number">Zahl</option>
                    <option value="select">Auswahlfeld (manuell)</option>
                    <option value="db_dg">Rank-Auswahl (aus DB)</option>
                    <option value="db_rdq">RD-Qualifikation (aus DB)</option>
                </select>
            </div>

            <div class="mb-3" id="genderSpecificContainer" style="display: none;">
                <div class="ignis-checkbox">
                    <input type="checkbox" id="genderSpecific">
                    <label for="genderSpecific">
                        Geschlechtsspezifische Optionen
                    </label>
                    <small class="ignis-field__hint text-[var(--text-dimmed,#818189)] block">
                        Aktivieren für männlich/weiblich/neutral Varianten
                    </small>
                </div>
            </div>

            <div id="optionsContainer" class="mb-3" style="display: none;">
                <label class="ignis-field__label">Auswahloptionen</label>
                <div id="optionsList"></div>
                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--outline-secondary mt-2" id="addOptionBtn">
                    + Option hinzufügen
                </button>
            </div>

            <div class="ignis-alert ignis-alert--info" id="dbFieldInfo" style="display: none;">
                <strong>Hinweis:</strong> Dieses Feld wird automatisch mit Daten aus der Datenbank befüllt.
                Die geschlechtsspezifischen Varianten werden automatisch berücksichtigt.
            </div>

            <div class="ignis-checkbox mb-3">
                <input type="checkbox" id="fieldRequired">
                <label for="fieldRequired">
                    Pflichtfeld
                </label>
            </div>
        </form>
        <div class="ignis-dialog-park__footer flex justify-end gap-2 mt-3">
            <button type="button" class="ignis-btn ignis-btn--ghost" data-dialog-dismiss>Abbrechen</button>
            <button type="button" class="ignis-btn ignis-btn--soft-primary" id="saveFieldBtn">Feld speichern</button>
        </div>
    </div>

    <script>
        window.TemplatesAppConfig = {
            basePath:    "<?= BASE_PATH ?>",
            dienstgrade: <?= json_encode($dienstgrade) ?>,
            rdQualis:    <?= json_encode($rdQualis) ?>,
            csrfToken:   "<?= \App\Security\CsrfProtection::getToken() ?>"
        };
    </script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/modules/templates-app.js"></script>
    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>