<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Enotf\ProtocolTypeService;

if (!Permissions::check(['admin', 'edivi.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

$typeService = new ProtocolTypeService($pdo);

$typeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = $typeService->getType($typeId);

if (!$type) {
    Flash::set('error', 'Protokolltyp nicht gefunden.');
    header("Location: " . BASE_PATH . "settings/enotf/protokolltypen/index.php");
    exit();
}

$typeSections = $typeService->getSectionsForType($typeId);
$allSections = $typeService->getAllSections();
$allFieldDefs = $typeService->getAllFieldDefinitions();
$validationRules = $typeService->getValidationRules($typeId);

// Index sections by slug for quick lookup
$typeSectionSlugs = array_column($typeSections, 'slug');

$activeTab = $_GET['tab'] ?? 'basis';
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . "/../../../assets/components/_base/admin/head.php"; ?>
    <script src="<?= BASE_PATH ?>assets/_ext/sortablejs/Sortable.min.js"></script>
    <style>
        .drag-handle { cursor: grab; color: #6c757d; margin-right: 0.5rem; user-select: none; }
        .drag-handle:active { cursor: grabbing; }
        .sortable-ghost { opacity: 0.4; }
        .sortable-drag { opacity: 0.8; }

        .section-card, .field-card {
            background: var(--body-bg-lighter);
            border: 1px solid var(--darkgray);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .section-card.disabled { opacity: 0.4; }
        .field-card.disabled { opacity: 0.4; }
        .section-card .section-info { flex: 1; }
        .field-card .field-info { flex: 1; }
        .field-card .field-badges { display: flex; gap: 0.3rem; }

        .rule-card {
            background: var(--body-bg-lighter);
            border: 1px solid var(--darkgray);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
        .rule-card .rule-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }

        .config-tabs .nav-link { font-size: 0.85rem; }
        .config-tabs .nav-link.active { background: var(--btn-primary-bg); color: #fff; }
    </style>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <nav class="admin-breadcrumb">
                        <a href="<?= BASE_PATH ?>index.php">Dashboard</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <a href="<?= BASE_PATH ?>settings/enotf/index.php">eNOTF</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <a href="<?= BASE_PATH ?>settings/enotf/protokolltypen/index.php">Protokolltypen</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span class="current"><?= htmlspecialchars($type['short_name']) ?> bearbeiten</span>
                    </nav>

                    <div class="page-header mb-4">
                        <h1>
                            <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:<?= htmlspecialchars($type['color']) ?>;vertical-align:middle;margin-right:0.5rem"></span>
                            <?= htmlspecialchars($type['name']) ?>
                            <?php if ($type['is_builtin']): ?>
                                <span class="badge bg-secondary ms-2" style="font-size:0.7rem;vertical-align:middle">System</span>
                            <?php endif; ?>
                        </h1>
                        <div class="header-actions">
                            <a href="<?= BASE_PATH ?>settings/enotf/protokolltypen/index.php" class="btn btn-outline-secondary">
                                <i class="fa-solid fa-arrow-left"></i> Zurück
                            </a>
                        </div>
                    </div>

                    <?php Flash::render(); ?>

                    <!-- Tab Navigation -->
                    <ul class="nav nav-pills config-tabs mb-4">
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'basis' ? 'active' : '' ?>" href="?id=<?= $typeId ?>&tab=basis">
                                <i class="fa-solid fa-circle-info me-1"></i> Basis
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'sektionen' ? 'active' : '' ?>" href="?id=<?= $typeId ?>&tab=sektionen">
                                <i class="fa-solid fa-layer-group me-1"></i> Sektionen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'felder' ? 'active' : '' ?>" href="?id=<?= $typeId ?>&tab=felder">
                                <i class="fa-solid fa-input-text me-1"></i> Felder
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'validierung' ? 'active' : '' ?>" href="?id=<?= $typeId ?>&tab=validierung">
                                <i class="fa-solid fa-check-double me-1"></i> Validierung
                            </a>
                        </li>
                    </ul>

                    <!-- TAB: Basis -->
                    <?php if ($activeTab === 'basis'): ?>
                        <div class="intra__tile py-3 px-4">
                            <form action="<?= BASE_PATH ?>api/enotf/admin/save-type.php" method="POST">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= $typeId ?>">

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="type-name" class="form-label">Name</label>
                                        <input type="text" class="form-control" name="name" id="type-name" value="<?= htmlspecialchars($type['name']) ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="type-short-name" class="form-label">Kurzname</label>
                                        <input type="text" class="form-control" name="short_name" id="type-short-name" value="<?= htmlspecialchars($type['short_name']) ?>" maxlength="10" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="type-sort-order" class="form-label">Sortierung</label>
                                        <input type="number" class="form-control" name="sort_order" id="type-sort-order" value="<?= $type['sort_order'] ?>">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label for="type-color" class="form-label">Farbe</label>
                                        <input type="color" class="form-control form-control-color" name="color" id="type-color" value="<?= htmlspecialchars($type['color']) ?>">
                                    </div>
                                    <div class="col-md-9">
                                        <label for="type-icon" class="form-label">Icon <small class="form-hint">(Font Awesome Klasse)</small></label>
                                        <input type="text" class="form-control" name="icon" id="type-icon" value="<?= htmlspecialchars($type['icon'] ?? '') ?>" placeholder="fa-solid fa-file-medical">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="type-description" class="form-label">Beschreibung</label>
                                    <textarea class="form-control" name="description" id="type-description" rows="2"><?= htmlspecialchars($type['description'] ?? '') ?></textarea>
                                </div>

                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" name="active" id="type-active" <?= $type['active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="type-active">Aktiv</label>
                                </div>

                                <button type="submit" class="btn btn-soft-primary">Speichern</button>
                            </form>
                        </div>

                    <!-- TAB: Sektionen -->
                    <?php elseif ($activeTab === 'sektionen'): ?>
                        <div class="intra__tile py-3 px-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <p class="text-muted mb-0" style="font-size:0.82rem">
                                    Sektionen per Drag & Drop umsortieren. Aktiviere/deaktiviere Sektionen für diesen Protokolltyp.
                                </p>
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#createSectionModal">
                                    <i class="fa-solid fa-plus"></i> Neue Sektion
                                </button>
                            </div>

                            <div id="sectionList">
                                <?php foreach ($typeSections as $section): ?>
                                    <div class="section-card" data-section-id="<?= $section['id'] ?>">
                                        <span class="drag-handle"><i class="fa-solid fa-grip-vertical"></i></span>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input section-toggle" type="checkbox" data-section-id="<?= $section['id'] ?>" checked>
                                        </div>
                                        <div class="section-info">
                                            <strong>
                                                <?php if ($section['icon']): ?>
                                                    <i class="<?= htmlspecialchars($section['icon']) ?> me-1"></i>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($section['name']) ?>
                                            </strong>
                                            <?php if ($section['is_builtin']): ?>
                                                <span class="badge bg-secondary ms-1" style="font-size:0.65rem">System</span>
                                            <?php endif; ?>
                                            <?php if ($section['component_template']): ?>
                                                <span class="badge bg-dark ms-1" style="font-size:0.65rem"><?= htmlspecialchars($section['component_template']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input section-required" type="checkbox" data-section-id="<?= $section['id'] ?>" <?= $section['section_required'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" style="font-size:0.78rem">Pflicht</label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <?php
                                // Show available but not-yet-assigned sections
                                foreach ($allSections as $section):
                                    if (in_array($section['slug'], $typeSectionSlugs)) continue;
                                ?>
                                    <div class="section-card disabled" data-section-id="<?= $section['id'] ?>">
                                        <span class="drag-handle"><i class="fa-solid fa-grip-vertical"></i></span>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input section-toggle" type="checkbox" data-section-id="<?= $section['id'] ?>">
                                        </div>
                                        <div class="section-info">
                                            <strong>
                                                <?php if ($section['icon']): ?>
                                                    <i class="<?= htmlspecialchars($section['icon']) ?> me-1"></i>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($section['name']) ?>
                                            </strong>
                                            <span class="text-muted ms-1" style="font-size:0.75rem">(deaktiviert)</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <button class="btn btn-soft-primary mt-3" id="saveSectionsBtn">
                                <i class="fa-solid fa-save me-1"></i> Sektionen speichern
                            </button>
                        </div>

                    <!-- TAB: Felder -->
                    <?php elseif ($activeTab === 'felder'): ?>
                        <div class="row">
                            <!-- Section selector -->
                            <div class="col-md-3">
                                <div class="intra__tile py-2 px-3">
                                    <small class="text-muted d-block mb-2" style="font-size:0.72rem;text-transform:uppercase;font-weight:600">Sektion wählen</small>
                                    <div class="d-flex flex-column gap-1">
                                        <?php
                                        $activeSection = $_GET['section'] ?? ($typeSections[0]['id'] ?? 0);
                                        foreach ($typeSections as $section):
                                        ?>
                                            <a href="?id=<?= $typeId ?>&tab=felder&section=<?= $section['id'] ?>" class="btn btn-sm <?= $activeSection == $section['id'] ? 'btn-soft-primary' : 'btn-ghost' ?> text-start">
                                                <?php if ($section['icon']): ?>
                                                    <i class="<?= htmlspecialchars($section['icon']) ?> me-1"></i>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($section['name']) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Fields for selected section -->
                            <div class="col-md-9">
                                <div class="intra__tile py-3 px-4">
                                    <?php if ($activeSection):
                                        $sectionFields = $typeService->getFieldsForSection($typeId, (int)$activeSection);
                                    ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <p class="text-muted mb-0" style="font-size:0.82rem">
                                                <?= count($sectionFields) ?> Felder in dieser Sektion. Drag & Drop zum Umsortieren.
                                            </p>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addExistingFieldModal">
                                                    <i class="fa-solid fa-plus"></i> Bestehendes Feld
                                                </button>
                                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#createFieldModal">
                                                    <i class="fa-solid fa-plus"></i> Neues Custom Field
                                                </button>
                                            </div>
                                        </div>

                                        <div id="fieldList" data-section-id="<?= $activeSection ?>">
                                            <?php foreach ($sectionFields as $field): ?>
                                                <div class="field-card" data-field-def-id="<?= $field['id'] ?>">
                                                    <span class="drag-handle"><i class="fa-solid fa-grip-vertical"></i></span>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input field-toggle" type="checkbox" data-field-id="<?= $field['id'] ?>" checked>
                                                    </div>
                                                    <div class="field-info">
                                                        <strong><?= htmlspecialchars($field['label']) ?></strong>
                                                        <small class="text-muted ms-2"><?= htmlspecialchars($field['field_key']) ?></small>
                                                    </div>
                                                    <div class="field-badges">
                                                        <span class="badge bg-dark"><?= htmlspecialchars($field['field_type']) ?></span>
                                                        <?php if ($field['is_legacy_column']): ?>
                                                            <span class="badge bg-secondary">Legacy</span>
                                                        <?php endif; ?>
                                                        <?php if ($field['is_core']): ?>
                                                            <span class="badge bg-warning text-dark">Kern</span>
                                                        <?php endif; ?>
                                                        <?php if ($field['widget']): ?>
                                                            <span class="badge bg-info"><?= htmlspecialchars($field['widget']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input field-required" type="checkbox" data-field-id="<?= $field['id'] ?>" <?= $field['is_required'] ? 'checked' : '' ?>>
                                                        <label class="form-check-label" style="font-size:0.78rem">Pflicht</label>
                                                    </div>
                                                    <select class="form-select form-select-sm field-width" data-field-id="<?= $field['id'] ?>" style="width:120px">
                                                        <option value="full" <?= ($field['column_width'] ?? 'full') === 'full' ? 'selected' : '' ?>>Voll</option>
                                                        <option value="half" <?= ($field['column_width'] ?? '') === 'half' ? 'selected' : '' ?>>Halb</option>
                                                        <option value="third" <?= ($field['column_width'] ?? '') === 'third' ? 'selected' : '' ?>>Drittel</option>
                                                        <option value="quarter" <?= ($field['column_width'] ?? '') === 'quarter' ? 'selected' : '' ?>>Viertel</option>
                                                    </select>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <button class="btn btn-soft-primary mt-3" id="saveFieldsBtn">
                                            <i class="fa-solid fa-save me-1"></i> Felder speichern
                                        </button>
                                    <?php else: ?>
                                        <p class="text-muted">Wähle eine Sektion aus der Liste links.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    <!-- TAB: Validierung -->
                    <?php elseif ($activeTab === 'validierung'): ?>
                        <div class="intra__tile py-3 px-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <p class="text-muted mb-0" style="font-size:0.82rem">
                                    Validierungsregeln definieren, wann Felder Pflicht werden (abhängig von anderen Feldwerten).
                                </p>
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#createRuleModal">
                                    <i class="fa-solid fa-plus"></i> Neue Regel
                                </button>
                            </div>

                            <?php if (empty($validationRules)): ?>
                                <p class="text-muted">Keine Validierungsregeln konfiguriert.</p>
                            <?php else: ?>
                                <?php foreach ($validationRules as $rule):
                                    $ruleData = json_decode($rule['rule_json'], true);
                                ?>
                                    <div class="rule-card">
                                        <div class="rule-header">
                                            <div>
                                                <strong><?= htmlspecialchars($rule['name']) ?></strong>
                                                <span class="badge <?= $rule['severity'] === 'error' ? 'bg-danger' : 'bg-warning text-dark' ?> ms-2"><?= $rule['severity'] ?></span>
                                                <?php if ($ruleData): ?>
                                                    <span class="badge bg-dark ms-1"><?= htmlspecialchars($ruleData['type'] ?? 'custom') ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-sm btn-soft-primary btn-icon edit-rule-btn"
                                                        data-id="<?= $rule['id'] ?>"
                                                        data-name="<?= htmlspecialchars($rule['name']) ?>"
                                                        data-rule-json="<?= htmlspecialchars($rule['rule_json']) ?>"
                                                        data-error-message="<?= htmlspecialchars($rule['error_message']) ?>"
                                                        data-severity="<?= $rule['severity'] ?>"
                                                        title="Bearbeiten">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger btn-icon delete-rule-btn" data-id="<?= $rule['id'] ?>" title="Löschen">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="text-muted" style="font-size:0.82rem">
                                            <?= htmlspecialchars($rule['error_message']) ?>
                                        </div>
                                        <?php if ($ruleData && !empty($ruleData['description'])): ?>
                                            <div class="text-muted mt-1" style="font-size:0.75rem;font-style:italic">
                                                <?= htmlspecialchars($ruleData['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- Create Section Modal -->
    <div class="modal fade" id="createSectionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="createSectionForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Neue Sektion erstellen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="section-name" class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" id="section-name" required>
                        </div>
                        <div class="mb-3">
                            <label for="section-icon" class="form-label">Icon</label>
                            <input type="text" class="form-control" name="icon" id="section-icon" placeholder="fa-solid fa-file">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-success">Erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Custom Field Modal -->
    <div class="modal fade" id="createFieldModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="createFieldForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Neues Custom Field erstellen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="field-label" class="form-label">Label</label>
                            <input type="text" class="form-control" name="label" id="field-label" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="field-type" class="form-label">Feldtyp</label>
                                <select class="form-select" name="field_type" id="field-type" required>
                                    <option value="text">Text</option>
                                    <option value="textarea">Textfeld (mehrzeilig)</option>
                                    <option value="number">Zahl</option>
                                    <option value="radio">Radio-Auswahl</option>
                                    <option value="checkbox">Checkbox</option>
                                    <option value="select">Dropdown</option>
                                    <option value="date">Datum</option>
                                    <option value="time">Uhrzeit</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="field-suffix" class="form-label">Einheit <small class="form-hint">(optional)</small></label>
                                <input type="text" class="form-control" name="input_suffix" id="field-suffix" placeholder="z.B. mmHg, %, ml">
                            </div>
                        </div>
                        <div class="mb-3" id="optionsGroup" style="display:none">
                            <label for="field-options" class="form-label">Optionen <small class="form-hint">(eine pro Zeile, Format: wert|Label)</small></label>
                            <textarea class="form-control" name="options" id="field-options" rows="4" placeholder="1|Option A&#10;2|Option B&#10;3|Option C"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="field-placeholder" class="form-label">Platzhalter <small class="form-hint">(optional)</small></label>
                            <input type="text" class="form-control" name="placeholder" id="field-placeholder">
                        </div>
                        <div class="mb-3">
                            <label for="field-hint" class="form-label">Hilfetext <small class="form-hint">(optional)</small></label>
                            <input type="text" class="form-control" name="hint_text" id="field-hint">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-success">Erstellen & Hinzufügen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Existing Field Modal -->
    <div class="modal fade" id="addExistingFieldModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bestehendes Feld hinzufügen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control mb-3" id="fieldSearchInput" placeholder="Feld suchen...">
                    <div id="availableFieldsList" style="max-height:400px;overflow-y:auto">
                        <?php
                        // Show all fields not yet assigned to this section
                        $assignedFieldIds = array_column($sectionFields ?? [], 'id');
                        foreach ($allFieldDefs as $fd):
                            if (in_array($fd['id'], $assignedFieldIds)) continue;
                        ?>
                            <div class="field-card available-field" data-field-def-id="<?= $fd['id'] ?>" data-search="<?= strtolower(htmlspecialchars($fd['label'] . ' ' . $fd['field_key'])) ?>">
                                <div class="field-info">
                                    <strong><?= htmlspecialchars($fd['label']) ?></strong>
                                    <small class="text-muted ms-2"><?= htmlspecialchars($fd['field_key']) ?></small>
                                </div>
                                <div class="field-badges">
                                    <span class="badge bg-dark"><?= htmlspecialchars($fd['field_type']) ?></span>
                                    <?php if ($fd['is_legacy_column']): ?>
                                        <span class="badge bg-secondary">Legacy</span>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn-sm btn-soft-success add-field-btn" data-field-def-id="<?= $fd['id'] ?>">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Rule Modal -->
    <div class="modal fade" id="createRuleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="createRuleForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Neue Validierungsregel</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="rule-name" class="form-label">Regelname</label>
                            <input type="text" class="form-control" name="name" id="rule-name" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="rule-type" class="form-label">Regeltyp</label>
                                <select class="form-select" name="rule_type" id="rule-type">
                                    <option value="override">Override (Felder optional machen)</option>
                                    <option value="addition">Addition (Felder zur Pflicht machen)</option>
                                    <option value="custom">Custom (eigene Bedingung)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="rule-severity" class="form-label">Schweregrad</label>
                                <select class="form-select" name="severity" id="rule-severity">
                                    <option value="error">Fehler (blockiert Freigabe)</option>
                                    <option value="warning">Warnung (nur Hinweis)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3 p-3" style="background:var(--body-bg);border-radius:8px">
                            <label class="form-label">Bedingung</label>
                            <div class="row mb-2">
                                <div class="col-md-4">
                                    <select class="form-select form-select-sm" name="condition_field" id="rule-condition-field">
                                        <option value="">Feld wählen...</option>
                                        <?php foreach ($allFieldDefs as $fd): ?>
                                            <option value="<?= htmlspecialchars($fd['field_key']) ?>"><?= htmlspecialchars($fd['label']) ?> (<?= $fd['field_key'] ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select form-select-sm" name="condition_operator" id="rule-condition-op">
                                        <option value="equals">ist gleich</option>
                                        <option value="not_equals">ist nicht gleich</option>
                                        <option value="is_empty">ist leer</option>
                                        <option value="is_not_empty">ist nicht leer</option>
                                        <option value="greater_than">größer als</option>
                                        <option value="less_than">kleiner als</option>
                                        <option value="in_list">in Liste</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <input type="text" class="form-control form-control-sm" name="condition_value" id="rule-condition-value" placeholder="Wert (kommagetrennt für Liste)">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="rule-target-fields" class="form-label">Ziel-Felder <small class="form-hint">(kommagetrennte field_keys)</small></label>
                            <input type="text" class="form-control" name="target_fields" id="rule-target-fields" placeholder="z.B. patsex,spat,awfrei_1">
                        </div>

                        <div class="mb-3">
                            <label for="rule-error-message" class="form-label">Fehlermeldung</label>
                            <input type="text" class="form-control" name="error_message" id="rule-error-message" required>
                        </div>

                        <div class="mb-3">
                            <label for="rule-description" class="form-label">Beschreibung <small class="form-hint">(intern, optional)</small></label>
                            <input type="text" class="form-control" name="description" id="rule-description">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-success">Erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>

    <script>
        const BASE_PATH = '<?= BASE_PATH ?>';
        const TYPE_ID = <?= $typeId ?>;
        const ACTIVE_SECTION = <?= (int)($activeSection ?? 0) ?>;

        // ──── Sortable.js for Sections ────
        (function() {
            const sectionList = document.getElementById('sectionList');
            if (sectionList) {
                new Sortable(sectionList, {
                    animation: 150,
                    handle: '.drag-handle',
                    ghostClass: 'sortable-ghost',
                    dragClass: 'sortable-drag'
                });
            }
        })();

        // ──── Sortable.js for Fields ────
        (function() {
            const fieldList = document.getElementById('fieldList');
            if (fieldList) {
                new Sortable(fieldList, {
                    animation: 150,
                    handle: '.drag-handle',
                    ghostClass: 'sortable-ghost',
                    dragClass: 'sortable-drag'
                });
            }
        })();

        // ──── Save Sections ────
        document.getElementById('saveSectionsBtn')?.addEventListener('click', async function() {
            const cards = document.querySelectorAll('#sectionList .section-card');
            const sections = [];

            cards.forEach(function(card, index) {
                const sectionId = card.dataset.sectionId;
                const enabled = card.querySelector('.section-toggle')?.checked ? 1 : 0;
                const required = card.querySelector('.section-required')?.checked ? 1 : 0;

                sections.push({
                    section_id: parseInt(sectionId),
                    enabled: enabled,
                    sort_order: index + 1,
                    is_required: required
                });
            });

            try {
                const response = await fetch(BASE_PATH + 'api/enotf/admin/save-type-config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'save_sections', type_id: TYPE_ID, sections: sections })
                });
                const result = await response.json();
                if (result.success) {
                    showAlert('Sektionen gespeichert.', { type: 'success' });
                    setTimeout(function() { location.reload(); }, 500);
                } else {
                    showAlert('Fehler: ' + result.error, { type: 'error' });
                }
            } catch (err) {
                showAlert('Fehler: ' + err.message, { type: 'error' });
            }
        });

        // ──── Save Fields ────
        document.getElementById('saveFieldsBtn')?.addEventListener('click', async function() {
            const cards = document.querySelectorAll('#fieldList .field-card');
            const fields = [];

            cards.forEach(function(card, index) {
                const fieldDefId = card.dataset.fieldDefId;
                const enabled = card.querySelector('.field-toggle')?.checked ? 1 : 0;
                const required = card.querySelector('.field-required')?.checked ? 1 : 0;
                const width = card.querySelector('.field-width')?.value || 'full';

                fields.push({
                    field_definition_id: parseInt(fieldDefId),
                    enabled: enabled,
                    is_required: required,
                    sort_order: index + 1,
                    column_width: width
                });
            });

            try {
                const response = await fetch(BASE_PATH + 'api/enotf/admin/save-type-config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'save_fields',
                        type_id: TYPE_ID,
                        section_id: ACTIVE_SECTION,
                        fields: fields
                    })
                });
                const result = await response.json();
                if (result.success) {
                    showAlert('Felder gespeichert.', { type: 'success' });
                } else {
                    showAlert('Fehler: ' + result.error, { type: 'error' });
                }
            } catch (err) {
                showAlert('Fehler: ' + err.message, { type: 'error' });
            }
        });

        // ──── Field type → show/hide options ────
        document.getElementById('field-type')?.addEventListener('change', function() {
            const needsOptions = ['radio', 'select', 'checkbox_group', 'custom_dropdown'];
            document.getElementById('optionsGroup').style.display = needsOptions.includes(this.value) ? 'block' : 'none';
        });

        // ──── Field search in Add Existing modal ────
        document.getElementById('fieldSearchInput')?.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            document.querySelectorAll('.available-field').forEach(function(el) {
                el.style.display = el.dataset.search.includes(query) ? '' : 'none';
            });
        });

        // ──── Add existing field ────
        document.querySelectorAll('.add-field-btn').forEach(function(btn) {
            btn.addEventListener('click', async function() {
                const fieldDefId = parseInt(this.dataset.fieldDefId);
                try {
                    const response = await fetch(BASE_PATH + 'api/enotf/admin/save-type-config.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'add_field',
                            type_id: TYPE_ID,
                            section_id: ACTIVE_SECTION,
                            field_definition_id: fieldDefId
                        })
                    });
                    const result = await response.json();
                    if (result.success) {
                        location.reload();
                    } else {
                        showAlert('Fehler: ' + result.error, { type: 'error' });
                    }
                } catch (err) {
                    showAlert('Fehler: ' + err.message, { type: 'error' });
                }
            });
        });

        // ──── Create custom field ────
        document.getElementById('createFieldForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            // Parse options if present
            if (data.options) {
                const lines = data.options.split('\n').filter(function(l) { return l.trim(); });
                const values = lines.map(function(line) {
                    const parts = line.split('|');
                    return { value: parts[0].trim(), label: (parts[1] || parts[0]).trim() };
                });
                data.options_json = JSON.stringify({ values: values });
            }

            data.action = 'create_field';
            data.type_id = TYPE_ID;
            data.section_id = ACTIVE_SECTION;

            try {
                const response = await fetch(BASE_PATH + 'api/enotf/admin/save-type-config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    showAlert('Fehler: ' + result.error, { type: 'error' });
                }
            } catch (err) {
                showAlert('Fehler: ' + err.message, { type: 'error' });
            }
        });

        // ──── Create section ────
        document.getElementById('createSectionForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            data.action = 'create_section';
            data.type_id = TYPE_ID;

            try {
                const response = await fetch(BASE_PATH + 'api/enotf/admin/save-type-config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    showAlert('Fehler: ' + result.error, { type: 'error' });
                }
            } catch (err) {
                showAlert('Fehler: ' + err.message, { type: 'error' });
            }
        });

        // ──── Create validation rule ────
        document.getElementById('createRuleForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            // Build rule_json
            const conditionValue = data.condition_value;
            let parsedValue = conditionValue;
            if (data.condition_operator === 'in_list') {
                parsedValue = conditionValue.split(',').map(function(v) { return v.trim(); });
            }

            const targetFields = data.target_fields.split(',').map(function(v) { return v.trim(); }).filter(Boolean);

            const ruleJson = {
                type: data.rule_type,
                description: data.description || '',
                condition: {
                    type: 'condition',
                    field: data.condition_field,
                    operator: data.condition_operator,
                    value: parsedValue
                },
                action: {
                    type: data.rule_type === 'override' ? 'make_optional' : 'require',
                    target_fields: targetFields
                }
            };

            try {
                const response = await fetch(BASE_PATH + 'api/enotf/admin/save-type-config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create_rule',
                        type_id: TYPE_ID,
                        name: data.name,
                        rule_json: JSON.stringify(ruleJson),
                        error_message: data.error_message,
                        severity: data.severity
                    })
                });
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    showAlert('Fehler: ' + result.error, { type: 'error' });
                }
            } catch (err) {
                showAlert('Fehler: ' + err.message, { type: 'error' });
            }
        });

        // ──── Delete rule ────
        document.querySelectorAll('.delete-rule-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var ruleId = this.dataset.id;
                showConfirm('Regel wirklich löschen?', { danger: true, confirmText: 'Löschen' }).then(function(result) {
                    if (result) {
                        fetch(BASE_PATH + 'api/enotf/admin/save-type-config.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'delete_rule', rule_id: parseInt(ruleId) })
                        }).then(function() { location.reload(); });
                    }
                });
            });
        });
    </script>
</body>

</html>
