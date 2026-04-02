<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Documents\DocumentTemplateManager;
use App\Security\CsrfProtection;

if (!Permissions::check(['admin', 'personnel.documents.manage'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

$templateId = (int) ($_GET['id'] ?? 0);
if (!$templateId) {
    Flash::set('error', 'Kein Template angegeben');
    header("Location: " . BASE_PATH . "settings/documents/templates.php");
    exit();
}

$manager = new DocumentTemplateManager($pdo);
$template = $manager->getTemplate($templateId);

if (!$template) {
    Flash::set('error', 'Template nicht gefunden');
    header("Location: " . BASE_PATH . "settings/documents/templates.php");
    exit();
}

$SITE_TITLE = 'Template Editor - ' . htmlspecialchars($template['name']);
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/template-editor.min.css" />
    <style>
        /* Nur Styles die spezifisch fuer visual-editor.php sind und nicht in template-editor.css */
        body { overflow: hidden; }
        .editor-wrapper { height: calc(100vh - 38px); }
        .editor-sidebar.collapsed { width: 0; min-width: 0; padding: 0; overflow: hidden; border-right: none; }
        .editor-properties.collapsed { width: 0; min-width: 0; padding: 0; overflow: hidden; border-left: none; }
        .editor-sidebar, .editor-properties { transition: width 0.2s, min-width 0.2s, padding 0.2s; position: relative; }
        .sidebar-toggle { position: absolute; top: 8px; z-index: 10; width: 24px; height: 24px; border-radius: 50%; background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.65rem; color: var(--bs-secondary-color); }
        .sidebar-toggle:hover { background: var(--bs-tertiary-bg); }
        .editor-canvas-area.drag-over .canvas-container-wrapper { box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.6), 0 4px 20px rgba(0, 0, 0, 0.4); }
        .editor-toolbar { flex-wrap: nowrap; overflow-x: auto; min-height: 38px; gap: 0.35rem; padding: 0.3rem 0.75rem; }
        .editor-toolbar .btn { font-size: 0.8rem; padding: 0.2rem 0.5rem; }
        .editor-toolbar .separator { width: 1px; height: 22px; background: var(--bs-border-color); margin: 0 0.15rem; }
        .editor-toolbar .form-check { margin: 0; display: flex; align-items: center; gap: 0.25rem; }
        .editor-toolbar .form-check-label { line-height: 1; }
        .editor-toolbar .form-select { height: auto; padding-top: 0.2rem; padding-bottom: 0.2rem; }
        .element-item.field-placed { opacity: 0.5; }
        .element-item.field-placed::after { content: '\f00c'; font-family: 'Font Awesome 7 Free'; font-weight: 900; font-size: 0.6rem; color: var(--bs-success); margin-left: auto; }
    </style>
</head>

<body data-bs-theme="dark">
    <!-- Einzeilige Toolbar -->
    <div class="editor-toolbar">
        <a href="<?= BASE_PATH ?>settings/documents/templates.php" class="btn btn-sm btn-outline-secondary" id="btn-back"
            onclick="if(window.TemplateEditor&&window.TemplateEditor.isDirty){event.preventDefault();if(confirm('Ungespeicherte Änderungen verwerfen?'))window.location=this.href;}">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <span class="text-truncate" style="font-size:0.8rem;max-width:200px;opacity:0.7;" title="<?= htmlspecialchars($template['name']) ?>">
            <strong id="editor-template-name"><?= htmlspecialchars($template['name']) ?></strong>
        </span>
        <?php
            $templateConfig = json_decode($template['config'] ?? '{}', true) ?: [];
            $isDraft = !empty($templateConfig['is_draft']);
        ?>
        <label class="form-check form-check-inline mb-0 ms-2" style="font-size:0.72rem;" title="Entwurfs-Wasserzeichen auf PDFs anzeigen">
            <input class="form-check-input" type="checkbox" id="chk-draft" style="width:0.85em;height:0.85em;"<?= $isDraft ? ' checked' : '' ?>>
            <span class="form-check-label text-warning">Entwurf</span>
        </label>

        <div class="separator"></div>

        <button class="btn btn-sm btn-outline-light" id="btn-add-text" title="Text hinzufügen">
            <i class="fa-solid fa-font"></i>
        </button>
        <button class="btn btn-sm btn-outline-light" id="btn-add-field" title="Feld hinzufügen">
            <i class="fa-solid fa-i-cursor"></i>
        </button>
        <button class="btn btn-sm btn-outline-light" id="btn-add-image" title="Bild hinzufügen">
            <i class="fa-solid fa-image"></i>
        </button>
        <button class="btn btn-sm btn-outline-light" id="btn-set-background" title="Hintergrundbild setzen">
            <i class="fa-solid fa-panorama"></i>
        </button>
        <button class="btn btn-sm btn-outline-light" id="btn-remove-background" title="Hintergrundbild entfernen" style="display:none;">
            <i class="fa-solid fa-panorama" style="position:relative;"></i><i class="fa-solid fa-xmark" style="font-size:0.5rem;margin-left:-4px;color:var(--bs-danger);"></i>
        </button>

        <div class="separator"></div>

        <button class="btn btn-sm btn-outline-light" id="btn-duplicate" title="Duplizieren (Ctrl+D)">
            <i class="fa-solid fa-copy"></i>
        </button>
        <button class="btn btn-sm btn-outline-light" id="btn-delete" title="Löschen (Entf)">
            <i class="fa-solid fa-trash"></i>
        </button>
        <button class="btn btn-sm btn-outline-light" id="btn-bring-front" title="Nach vorne">
            <i class="fa-solid fa-layer-group"></i><i class="fa-solid fa-arrow-up" style="font-size:0.55rem;margin-left:1px;"></i>
        </button>
        <button class="btn btn-sm btn-outline-light" id="btn-send-back" title="Nach hinten">
            <i class="fa-solid fa-layer-group"></i><i class="fa-solid fa-arrow-down" style="font-size:0.55rem;margin-left:1px;"></i>
        </button>

        <div class="separator"></div>

        <div class="dropdown">
            <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Ausrichten">
                <i class="fa-solid fa-align-center"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-dark" style="min-width:180px;">
                <li class="dropdown-header" style="font-size:0.7rem;">Horizontal</li>
                <li><a class="dropdown-item" href="#" data-align="left"><i class="fa-solid fa-align-left me-2"></i>Links</a></li>
                <li><a class="dropdown-item" href="#" data-align="center-h"><i class="fa-solid fa-arrows-left-right me-2"></i>Mitte</a></li>
                <li><a class="dropdown-item" href="#" data-align="right"><i class="fa-solid fa-align-right me-2"></i>Rechts</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li class="dropdown-header" style="font-size:0.7rem;">Vertikal</li>
                <li><a class="dropdown-item" href="#" data-align="top"><i class="fa-solid fa-arrow-up me-2"></i>Oben</a></li>
                <li><a class="dropdown-item" href="#" data-align="center-v"><i class="fa-solid fa-arrows-up-down me-2"></i>Mitte</a></li>
                <li><a class="dropdown-item" href="#" data-align="bottom"><i class="fa-solid fa-arrow-down me-2"></i>Unten</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="#" data-align="page-center"><i class="fa-solid fa-crosshairs me-2"></i>Seitenmitte</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li class="dropdown-header" style="font-size:0.7rem;">Verteilen (3+ Elemente)</li>
                <li><a class="dropdown-item" href="#" data-align="distribute-h"><i class="fa-solid fa-arrows-left-right me-2"></i>Horizontal verteilen</a></li>
                <li><a class="dropdown-item" href="#" data-align="distribute-v"><i class="fa-solid fa-arrows-up-down me-2"></i>Vertikal verteilen</a></li>
            </ul>
        </div>

        <button class="btn btn-sm btn-outline-light" id="btn-undo" title="Rückgängig (Ctrl+Z)">
            <i class="fa-solid fa-undo"></i>
        </button>
        <button class="btn btn-sm btn-outline-light" id="btn-redo" title="Wiederholen (Ctrl+Y)">
            <i class="fa-solid fa-redo"></i>
        </button>

        <button class="btn btn-sm btn-outline-light" id="btn-versions" title="Versionsverlauf">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </button>

        <div class="separator"></div>

        <div class="zoom-controls">
            <button class="btn btn-sm btn-outline-light" id="btn-zoom-out"><i class="fa-solid fa-minus"></i></button>
            <span id="zoom-level">100%</span>
            <button class="btn btn-sm btn-outline-light" id="btn-zoom-in"><i class="fa-solid fa-plus"></i></button>
            <button class="btn btn-sm btn-outline-light" id="btn-zoom-fit" title="Einpassen"><i class="fa-solid fa-expand"></i></button>
        </div>

        <div class="separator"></div>

        <label class="form-check form-check-inline mb-0" style="font-size:0.75rem;">
            <input class="form-check-input" type="checkbox" id="chk-snap-grid" style="width:0.85em;height:0.85em;">
            <span class="form-check-label">Snap</span>
        </label>
        <label class="form-check form-check-inline mb-0" style="font-size:0.75rem;">
            <input class="form-check-input" type="checkbox" id="chk-grid-overlay" style="width:0.85em;height:0.85em;">
            <span class="form-check-label">Raster</span>
        </label>
        <label class="form-check form-check-inline mb-0" style="font-size:0.75rem;">
            <input class="form-check-input" type="checkbox" id="chk-guides" style="width:0.85em;height:0.85em;">
            <span class="form-check-label">Guides</span>
        </label>
        <select class="form-select form-select-sm" id="sel-margins" style="width:auto;font-size:0.75rem;padding:0.25rem 2rem 0.25rem 0.5rem;">
            <option value="schmal" selected>Schmal (1,27cm)</option>
            <option value="normal">Normal (2,5cm)</option>
            <option value="mittel">Mittel (1,91cm)</option>
        </select>

        <div class="ms-auto d-flex gap-1 align-items-center">
            <span id="autosave-indicator" class="text-muted" style="font-size:0.68rem;white-space:nowrap;"></span>
            <button class="btn btn-sm btn-outline-info" id="btn-preview" title="Vorschau">
                <i class="fa-solid fa-eye"></i>
            </button>
            <button class="btn btn-sm btn-success" id="btn-save" title="Speichern (Ctrl+S)">
                <i class="fa-solid fa-floppy-disk"></i>
            </button>
        </div>
    </div>

    <!-- Floating Text-Toolbar (erscheint beim Bearbeiten von Text) -->
    <div id="text-floating-toolbar" class="text-floating-toolbar" style="display:none;">
        <button class="tft-btn" data-tft-action="bold" title="Fett (Ctrl+B)"><i class="fa-solid fa-bold"></i></button>
        <button class="tft-btn" data-tft-action="italic" title="Kursiv (Ctrl+I)"><i class="fa-solid fa-italic"></i></button>
        <button class="tft-btn" data-tft-action="underline" title="Unterstrichen (Ctrl+U)"><i class="fa-solid fa-underline"></i></button>
        <span class="tft-sep"></span>
        <button class="tft-btn" data-tft-action="align-left" title="Links"><i class="fa-solid fa-align-left"></i></button>
        <button class="tft-btn" data-tft-action="align-center" title="Zentriert"><i class="fa-solid fa-align-center"></i></button>
        <button class="tft-btn" data-tft-action="align-right" title="Rechts"><i class="fa-solid fa-align-right"></i></button>
        <span class="tft-sep"></span>
        <select class="tft-select" data-tft-action="fontSize" title="Schriftgr&ouml;&szlig;e">
            <option value="8">8</option><option value="9">9</option><option value="10">10</option>
            <option value="11">11</option><option value="12" selected>12</option><option value="14">14</option>
            <option value="16">16</option><option value="18">18</option><option value="20">20</option>
            <option value="24">24</option><option value="28">28</option><option value="36">36</option>
        </select>
    </div>

    <!-- Canvas Loading Overlay -->
    <div id="canvas-loading" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;">
        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;color:#fff;">
            <i class="fa-solid fa-spinner fa-spin fa-2x"></i>
            <p class="mt-2" style="font-size:0.85rem;" id="canvas-loading-text">Layout wird geladen...</p>
        </div>
    </div>

    <!-- Main editor layout -->
    <div class="editor-wrapper">
        <!-- Left sidebar: Element library + Layers (Tab-basiert) -->
        <div class="editor-sidebar" id="sidebar-left">
            <!-- Sidebar-Tabs -->
            <ul class="nav nav-pills nav-fill sidebar-tabs" id="sidebar-left-tabs">
                <li class="nav-item">
                    <a class="nav-link active" data-sidebar-tab="elements" href="#"><i class="fa-solid fa-puzzle-piece"></i> Elemente</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-sidebar-tab="layers" href="#"><i class="fa-solid fa-layer-group"></i> Ebenen <span class="badge bg-secondary ms-1" id="layer-count" style="font-size:0.6rem;">0</span></a>
                </li>
            </ul>

            <!-- Tab: Elemente -->
            <div class="sidebar-tab-content" data-sidebar-tab-content="elements">
                <div class="sidebar-section" id="element-library">
                    <!-- Wird von element-library.js befüllt -->
                </div>
            </div>

            <!-- Tab: Ebenen -->
            <div class="sidebar-tab-content" data-sidebar-tab-content="layers" style="display:none;">
                <div class="sidebar-section">
                    <div id="layer-list">
                        <!-- Wird von editor-core.js befüllt -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Canvas area (mit Toggle-Buttons an den Rändern) -->
        <div class="editor-canvas-area" id="canvas-area" style="position:relative;">
            <!-- Toggle links -->
            <div class="sidebar-toggle" id="toggle-left" style="left:8px;" onclick="toggleSidebar('sidebar-left','toggle-left')" title="Elemente-Panel">
                <i class="fa-solid fa-chevron-left"></i>
            </div>
            <!-- Toggle rechts -->
            <div class="sidebar-toggle" id="toggle-right" style="right:8px;" onclick="toggleSidebar('sidebar-right','toggle-right')" title="Eigenschaften-Panel">
                <i class="fa-solid fa-chevron-right"></i>
            </div>

            <div id="ruler-canvas-group" style="display:inline-block;position:relative;">
                <!-- Horizontales Lineal (oben) -->
                <canvas id="ruler-h" style="position:absolute;top:-20px;left:0;height:20px;pointer-events:none;"></canvas>
                <!-- Vertikales Lineal (links) -->
                <canvas id="ruler-v" style="position:absolute;left:-20px;top:0;width:20px;pointer-events:none;"></canvas>
                <div class="canvas-container-wrapper" id="canvas-wrapper">
                    <canvas id="editor-canvas"></canvas>
                </div>
            </div>
        </div>

        <!-- Right sidebar: Properties panel -->
        <div class="editor-properties" id="sidebar-right">
            <div id="no-selection-msg">
                <i class="fa-solid fa-mouse-pointer" style="font-size:2rem;opacity:0.3;"></i>
                <p class="mt-2">Wähle ein Element auf dem Canvas aus, um seine Eigenschaften zu bearbeiten.</p>
            </div>
            <div id="selection-props" style="display:none;">
                <!-- Wird von properties-panel.js befüllt -->
            </div>
        </div>
    </div>

    <!-- Field selection modal -->
    <div class="modal fade" id="fieldSelectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Feld hinzufügen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group" id="field-select-list">
                        <!-- Wird dynamisch befüllt -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Asset manager modal -->
    <div class="modal fade" id="assetManagerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bild auswählen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Neues Bild hochladen</label>
                        <input type="file" class="form-control" id="asset-upload-input" accept="image/png,image/jpeg,image/gif,image/svg+xml">
                    </div>
                    <hr>
                    <div class="sidebar-section-title">Vorhandene Bilder</div>
                    <div class="row g-2" id="asset-gallery">
                        <!-- Wird dynamisch befüllt -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">PDF-Vorschau</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="height:80vh;">
                    <iframe id="preview-iframe" style="width:100%;height:100%;border:none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Versions-Modal -->
    <div class="modal fade" id="versionsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-clock-rotate-left me-2"></i>Versionsverlauf</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" id="versions-list" style="max-height:400px;overflow-y:auto;">
                    <div class="text-center p-3"><i class="fa-solid fa-spinner fa-spin"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Tab- & Toggle-Logik -->
    <script>
        // Sidebar-Tabs (Elemente / Ebenen)
        document.querySelectorAll('[data-sidebar-tab]').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                const target = tab.dataset.sidebarTab;
                document.querySelectorAll('[data-sidebar-tab]').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('[data-sidebar-tab-content]').forEach(c => c.style.display = 'none');
                tab.classList.add('active');
                const content = document.querySelector('[data-sidebar-tab-content="' + target + '"]');
                if (content) content.style.display = 'block';
            });
        });

        function toggleSidebar(sidebarId, toggleId) {
            const sidebar = document.getElementById(sidebarId);
            const toggle = document.getElementById(toggleId);
            if (!sidebar) return;
            const isCollapsed = sidebar.classList.toggle('collapsed');
            localStorage.setItem('editor-' + sidebarId, isCollapsed);
            // Chevron-Richtung umkehren
            const icon = toggle?.querySelector('i');
            if (icon && sidebarId === 'sidebar-left') {
                icon.className = isCollapsed ? 'fa-solid fa-chevron-right' : 'fa-solid fa-chevron-left';
            } else if (icon && sidebarId === 'sidebar-right') {
                icon.className = isCollapsed ? 'fa-solid fa-chevron-left' : 'fa-solid fa-chevron-right';
            }
        }
        // Restore aus localStorage
        if (localStorage.getItem('editor-sidebar-left') === 'true') {
            document.getElementById('sidebar-left')?.classList.add('collapsed');
            const icon = document.getElementById('toggle-left')?.querySelector('i');
            if (icon) icon.className = 'fa-solid fa-chevron-right';
        }
        if (localStorage.getItem('editor-sidebar-right') === 'true') {
            document.getElementById('sidebar-right')?.classList.add('collapsed');
            const icon = document.getElementById('toggle-right')?.querySelector('i');
            if (icon) icon.className = 'fa-solid fa-chevron-left';
        }
    </script>

    <!-- Template data for JS -->
    <script>
        window.TEMPLATE_EDITOR_CONFIG = {
            templateId: <?= $templateId ?>,
            templateName: <?= json_encode($template['name']) ?>,
            basePath: <?= json_encode(BASE_PATH) ?>,
            fields: <?= json_encode($template['fields'] ?? []) ?>,
            systemVars: {
                'SYSTEM_NAME': <?= json_encode(SYSTEM_NAME) ?>,
                'SERVER_CITY': <?= json_encode(SERVER_CITY) ?>,
                'RP_ORGTYPE': <?= json_encode(RP_ORGTYPE) ?>,
                'RP_STREET': <?= json_encode(RP_STREET) ?>,
                'RP_ZIP': <?= json_encode(RP_ZIP) ?>,
                'SERVER_NAME': <?= json_encode(SERVER_NAME) ?>,
            },
            // A4 bei 96dpi: 210mm * 3.7795 = 793.7px, 297mm * 3.7795 = 1122.5px
            canvasWidth: 794,
            canvasHeight: 1123,
            mmToPx: 3.7795,
            csrfToken: <?= json_encode(CsrfProtection::getToken()) ?>,
        };
    </script>

    <!-- SortableJS (Layer-Reorder) -->
    <script src="<?= BASE_PATH ?>assets/_ext/sortablejs/Sortable.min.js"></script>

    <!-- Fabric.js -->
    <script src="<?= BASE_PATH ?>assets/_ext/fabricjs/fabric.min.js"></script>

    <!-- Editor modules -->
    <script src="<?= BASE_PATH ?>assets/js/template-editor/utils.js"></script>
    <script src="<?= BASE_PATH ?>assets/js/template-editor/editor-core.js"></script>
    <script src="<?= BASE_PATH ?>assets/js/template-editor/element-library.js"></script>
    <script src="<?= BASE_PATH ?>assets/js/template-editor/toolbar.js"></script>
    <script src="<?= BASE_PATH ?>assets/js/template-editor/properties-panel.js"></script>
    <script src="<?= BASE_PATH ?>assets/js/template-editor/asset-manager.js"></script>
</body>

</html>