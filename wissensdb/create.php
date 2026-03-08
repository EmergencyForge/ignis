<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;

// Must be logged in
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

// Must have edit permission
if (!Permissions::check(['admin', 'kb.edit'])) {
    Flash::error('Keine Berechtigung');
    header("Location: " . BASE_PATH . "wissensdb/index.php");
    exit();
}

// Lade Kategorien und Tags für Auswahlfelder
$catStmt = $pdo->query("SELECT id, parent_id, name, icon FROM intra_kb_categories ORDER BY sort_order ASC, name ASC");
$allCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

$tagStmt = $pdo->query("SELECT id, name, color FROM intra_kb_tags ORDER BY name ASC");
$allTags = $tagStmt->fetchAll(PDO::FETCH_ASSOC);

// Check if editing existing entry
$editId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEdit = false;
$entry = null;
$updaterName = null;
$entryTags = [];

if ($editId) {
    $isEdit = true;
    $stmt = $pdo->prepare("
        SELECT kb.*, u.fullname as updater_name
        FROM intra_kb_entries kb
        LEFT JOIN intra_users u ON kb.updated_by = u.id
        WHERE kb.id = :id
    ");
    $stmt->execute(['id' => $editId]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$entry) {
        Flash::error('Eintrag nicht gefunden');
        header("Location: " . BASE_PATH . "wissensdb/index.php");
        exit();
    }
    
    $updaterName = $entry['updater_name'] ?? null;

    // Lade Tags des Eintrags
    $tagStmt2 = $pdo->prepare("SELECT tag_id FROM intra_kb_entry_tags WHERE entry_id = :id");
    $tagStmt2->execute(['id' => $editId]);
    $entryTags = $tagStmt2->fetchAll(PDO::FETCH_COLUMN);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? 'general';
    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $competency_level = !empty($_POST['competency_level']) ? $_POST['competency_level'] : null;
    $content = $_POST['content'] ?? '';
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $selectedTags = $_POST['tags'] ?? [];
    
    // Medication fields
    $med_wirkstoff = trim($_POST['med_wirkstoff'] ?? '');
    $med_wirkstoffgruppe = trim($_POST['med_wirkstoffgruppe'] ?? '');
    $med_wirkmechanismus = trim($_POST['med_wirkmechanismus'] ?? '');
    $med_indikationen = trim($_POST['med_indikationen'] ?? '');
    $med_kontraindikationen = trim($_POST['med_kontraindikationen'] ?? '');
    $med_uaw = trim($_POST['med_uaw'] ?? '');
    $med_dosierung = trim($_POST['med_dosierung'] ?? '');
    $med_besonderheiten = trim($_POST['med_besonderheiten'] ?? '');
    
    // Measure fields
    $mass_wirkprinzip = trim($_POST['mass_wirkprinzip'] ?? '');
    $mass_indikationen = trim($_POST['mass_indikationen'] ?? '');
    $mass_kontraindikationen = trim($_POST['mass_kontraindikationen'] ?? '');
    $mass_risiken = trim($_POST['mass_risiken'] ?? '');
    $mass_alternativen = trim($_POST['mass_alternativen'] ?? '');
    $mass_durchfuehrung = trim($_POST['mass_durchfuehrung'] ?? '');
    
    // Validation
    $errors = [];
    if (empty($title)) {
        $errors[] = 'Titel ist erforderlich';
    }
    if (!in_array($type, ['general', 'medication', 'measure'])) {
        $errors[] = 'Ungültiger Typ';
    }
    
    if (empty($errors)) {
        try {
            // Get is_pinned and hide_editor values for editing
            $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
            $hide_editor = isset($_POST['hide_editor']) ? 1 : 0;
            
            if ($isEdit) {
                // Update existing entry
                $sql = "UPDATE intra_kb_entries SET
                        type = :type,
                        category_id = :category_id,
                        title = :title,
                        subtitle = :subtitle,
                        competency_level = :competency_level,
                        content = :content,
                        med_wirkstoff = :med_wirkstoff,
                        med_wirkstoffgruppe = :med_wirkstoffgruppe,
                        med_wirkmechanismus = :med_wirkmechanismus,
                        med_indikationen = :med_indikationen,
                        med_kontraindikationen = :med_kontraindikationen,
                        med_uaw = :med_uaw,
                        med_dosierung = :med_dosierung,
                        med_besonderheiten = :med_besonderheiten,
                        mass_wirkprinzip = :mass_wirkprinzip,
                        mass_indikationen = :mass_indikationen,
                        mass_kontraindikationen = :mass_kontraindikationen,
                        mass_risiken = :mass_risiken,
                        mass_alternativen = :mass_alternativen,
                        mass_durchfuehrung = :mass_durchfuehrung,
                        is_pinned = :is_pinned,
                        hide_editor = :hide_editor,
                        updated_by = :user_id,
                        updated_at = NOW()
                        WHERE id = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'type' => $type,
                    'category_id' => $category_id,
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'competency_level' => $competency_level,
                    'content' => $content,
                    'med_wirkstoff' => $med_wirkstoff,
                    'med_wirkstoffgruppe' => $med_wirkstoffgruppe,
                    'med_wirkmechanismus' => $med_wirkmechanismus,
                    'med_indikationen' => $med_indikationen,
                    'med_kontraindikationen' => $med_kontraindikationen,
                    'med_uaw' => $med_uaw,
                    'med_dosierung' => $med_dosierung,
                    'med_besonderheiten' => $med_besonderheiten,
                    'mass_wirkprinzip' => $mass_wirkprinzip,
                    'mass_indikationen' => $mass_indikationen,
                    'mass_kontraindikationen' => $mass_kontraindikationen,
                    'mass_risiken' => $mass_risiken,
                    'mass_alternativen' => $mass_alternativen,
                    'mass_durchfuehrung' => $mass_durchfuehrung,
                    'is_pinned' => $is_pinned,
                    'hide_editor' => $hide_editor,
                    'user_id' => $_SESSION['userid'],
                    'id' => $editId
                ]);

                // Tags aktualisieren
                $pdo->prepare("DELETE FROM intra_kb_entry_tags WHERE entry_id = :id")->execute(['id' => $editId]);
                if (!empty($selectedTags)) {
                    $tagInsert = $pdo->prepare("INSERT IGNORE INTO intra_kb_entry_tags (entry_id, tag_id) VALUES (:entry_id, :tag_id)");
                    foreach ($selectedTags as $tagId) {
                        $tagInsert->execute(['entry_id' => $editId, 'tag_id' => (int)$tagId]);
                    }
                }

                Flash::success('Eintrag erfolgreich aktualisiert');
                header("Location: " . BASE_PATH . "wissensdb/view.php?id=" . $editId);
                exit();
            } else {
                // Create new entry
                $sql = "INSERT INTO intra_kb_entries (
                        type, category_id, title, subtitle, competency_level, content,
                        med_wirkstoff, med_wirkstoffgruppe, med_wirkmechanismus,
                        med_indikationen, med_kontraindikationen, med_uaw, med_dosierung, med_besonderheiten,
                        mass_wirkprinzip, mass_indikationen, mass_kontraindikationen,
                        mass_risiken, mass_alternativen, mass_durchfuehrung,
                        created_by
                    ) VALUES (
                        :type, :category_id, :title, :subtitle, :competency_level, :content,
                        :med_wirkstoff, :med_wirkstoffgruppe, :med_wirkmechanismus,
                        :med_indikationen, :med_kontraindikationen, :med_uaw, :med_dosierung, :med_besonderheiten,
                        :mass_wirkprinzip, :mass_indikationen, :mass_kontraindikationen,
                        :mass_risiken, :mass_alternativen, :mass_durchfuehrung,
                        :user_id
                    )";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'type' => $type,
                    'category_id' => $category_id,
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'competency_level' => $competency_level,
                    'content' => $content,
                    'med_wirkstoff' => $med_wirkstoff,
                    'med_wirkstoffgruppe' => $med_wirkstoffgruppe,
                    'med_wirkmechanismus' => $med_wirkmechanismus,
                    'med_indikationen' => $med_indikationen,
                    'med_kontraindikationen' => $med_kontraindikationen,
                    'med_uaw' => $med_uaw,
                    'med_dosierung' => $med_dosierung,
                    'med_besonderheiten' => $med_besonderheiten,
                    'mass_wirkprinzip' => $mass_wirkprinzip,
                    'mass_indikationen' => $mass_indikationen,
                    'mass_kontraindikationen' => $mass_kontraindikationen,
                    'mass_risiken' => $mass_risiken,
                    'mass_alternativen' => $mass_alternativen,
                    'mass_durchfuehrung' => $mass_durchfuehrung,
                    'user_id' => $_SESSION['userid']
                ]);

                $newId = $pdo->lastInsertId();

                // Tags verknüpfen
                if (!empty($selectedTags)) {
                    $tagInsert = $pdo->prepare("INSERT IGNORE INTO intra_kb_entry_tags (entry_id, tag_id) VALUES (:entry_id, :tag_id)");
                    foreach ($selectedTags as $tagId) {
                        $tagInsert->execute(['entry_id' => $newId, 'tag_id' => (int)$tagId]);
                    }
                }

                Flash::success('Eintrag erfolgreich erstellt');
                header("Location: " . BASE_PATH . "wissensdb/view.php?id=" . $newId);
                exit();
            }
        } catch (PDOException $e) {
            $errors[] = 'Datenbankfehler: ' . $e->getMessage();
        }
    }
}

// Prefill form values
$formData = $entry ?? [
    'type' => $_POST['type'] ?? 'general',
    'category_id' => $_POST['category_id'] ?? '',
    'title' => $_POST['title'] ?? '',
    'subtitle' => $_POST['subtitle'] ?? '',
    'competency_level' => $_POST['competency_level'] ?? '',
    'content' => $_POST['content'] ?? '',
    'med_wirkstoff' => $_POST['med_wirkstoff'] ?? '',
    'med_wirkstoffgruppe' => $_POST['med_wirkstoffgruppe'] ?? '',
    'med_wirkmechanismus' => $_POST['med_wirkmechanismus'] ?? '',
    'med_indikationen' => $_POST['med_indikationen'] ?? '',
    'med_kontraindikationen' => $_POST['med_kontraindikationen'] ?? '',
    'med_uaw' => $_POST['med_uaw'] ?? '',
    'med_dosierung' => $_POST['med_dosierung'] ?? '',
    'med_besonderheiten' => $_POST['med_besonderheiten'] ?? '',
    'mass_wirkprinzip' => $_POST['mass_wirkprinzip'] ?? '',
    'mass_indikationen' => $_POST['mass_indikationen'] ?? '',
    'mass_kontraindikationen' => $_POST['mass_kontraindikationen'] ?? '',
    'mass_risiken' => $_POST['mass_risiken'] ?? '',
    'mass_alternativen' => $_POST['mass_alternativen'] ?? '',
    'mass_durchfuehrung' => $_POST['mass_durchfuehrung'] ?? ''
];

?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    $SITE_TITLE = ($isEdit ? 'Bearbeiten' : 'Erstellen') . ' - Wissensdatenbank';
    include __DIR__ . "/../assets/components/_base/admin/head.php";
    ?>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/_ext/ckeditor5/ckeditor5.css">
    <style>
        .type-fields {
            display: none;
        }
        .type-fields.active {
            display: block;
        }
        .competency-option {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 5px;
            cursor: pointer;
        }
        .competency-option:hover {
            opacity: 0.9;
        }
        .competency-option input {
            margin-right: 10px;
        }
        /* CKEditor dark theme styling */
        .ck-editor__editable {
            min-height: 120px;
            background-color: #2d2d2d !important;
            color: #e0e0e0 !important;
            border-color: #555 !important;
        }
        .ck-editor__editable:focus {
            border-color: #0d6efd !important;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
        }
        .ck.ck-editor__main > .ck-editor__editable {
            background-color: #2d2d2d !important;
            color: #e0e0e0 !important;
        }
        .ck.ck-toolbar {
            background-color: #1e1e1e !important;
            border-color: #555 !important;
        }
        .ck.ck-toolbar .ck-toolbar__items .ck-button {
            color: #e0e0e0 !important;
        }
        .ck.ck-toolbar .ck-toolbar__items .ck-button:hover {
            background-color: #444 !important;
        }
        /* CKEditor active button state */
        .ck.ck-toolbar .ck-toolbar__items .ck-button.ck-on,
        .ck.ck-button.ck-on {
            background-color: #0d6efd !important;
            color: #ffffff !important;
        }
        .ck.ck-editor__editable p,
        .ck.ck-editor__editable li,
        .ck.ck-editor__editable h1,
        .ck.ck-editor__editable h2,
        .ck.ck-editor__editable h3 {
            color: #e0e0e0 !important;
        }
        /* CKEditor dropdown styling - fully dark theme */
        .ck.ck-dropdown__panel {
            background-color: #1e1e1e !important;
            border: 1px solid #555 !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5) !important;
        }
        .ck.ck-dropdown__panel .ck-list {
            background-color: #1e1e1e !important;
        }
        .ck.ck-list__item .ck-button {
            color: #e0e0e0 !important;
            background-color: transparent !important;
        }
        .ck.ck-list__item .ck-button:hover {
            background-color: #333 !important;
        }
        .ck.ck-list__item .ck-button.ck-on {
            background-color: #0d6efd !important;
            color: #fff !important;
        }
        .ck.ck-dropdown__button {
            color: #e0e0e0 !important;
        }
        .ck.ck-dropdown__button:hover {
            background-color: #444 !important;
        }
        .ck.ck-dropdown__button.ck-on {
            background-color: #0d6efd !important;
            color: #fff !important;
        }
        /* Fix dropdown panel borders */
        .ck.ck-dropdown .ck-dropdown__panel {
            border-top: 1px solid #555 !important;
        }
        .ck.ck-dropdown .ck-dropdown__panel.ck-dropdown__panel_se,
        .ck.ck-dropdown .ck-dropdown__panel.ck-dropdown__panel_sw {
            border-radius: 0 0 6px 6px !important;
        }
        /* CKEditor rounded border */
        .ck.ck-editor {
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #555 !important;
        }
        .ck.ck-toolbar {
            border-radius: 6px 6px 0 0 !important;
            border: none !important;
        }
        .ck.ck-editor__main > .ck-editor__editable {
            border-radius: 0 0 6px 6px !important;
            border: none !important;
            border-top: 1px solid #555 !important;
        }
        /* Back link styling */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #e0e0e0;
            text-decoration: none;
            padding: 8px 0;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: #0d6efd;
        }
        /* Smaller CKEditor for inline fields */
        .ck-editor-small .ck-editor__editable {
            min-height: 80px;
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="wissensdb">
    <?php include __DIR__ . "/../assets/components/navbar.php"; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    
                    <!-- Back Link -->
                    <a href="<?= BASE_PATH ?>wissensdb/index.php" class="back-link mb-3">
                        <i class="fa-solid fa-arrow-left"></i> Zurück zur Übersicht
                    </a>

                    <h1 class="mb-4"><?= $isEdit ? 'Eintrag bearbeiten' : 'Neuer Eintrag' ?></h1>

                    <?php Flash::render(); ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($isEdit && $entry['updated_at']): ?>
                        <div class="alert alert-info">
                            <i class="fa-solid fa-info-circle"></i>
                            Zuletzt bearbeitet am <?= date('d.m.Y H:i', strtotime($entry['updated_at'])) ?>
                            <?php if ($updaterName): ?>
                                von <?= htmlspecialchars($updaterName) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <div class="intra__tile p-4 mb-4">
                            <h4 class="mb-3">Grunddaten</h4>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="type" class="form-label">Kategorie <span class="text-danger">*</span></label>
                                    <select name="type" id="type" class="form-select" required>
                                        <option value="general" <?= $formData['type'] === 'general' ? 'selected' : '' ?>>Allgemein</option>
                                        <option value="medication" <?= $formData['type'] === 'medication' ? 'selected' : '' ?>>Medikament</option>
                                        <option value="measure" <?= $formData['type'] === 'measure' ? 'selected' : '' ?>>Maßnahme</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="competency_level" class="form-label">Freigabestufe</label>
                                    <select name="competency_level" id="competency_level" class="form-select">
                                        <option value="" <?= empty($formData['competency_level']) ? 'selected' : '' ?>>Keine Angabe</option>
                                        <option value="basis" <?= $formData['competency_level'] === 'basis' ? 'selected' : '' ?>>Basis - Basismaßnahmen</option>
                                        <option value="rettsan" <?= $formData['competency_level'] === 'rettsan' ? 'selected' : '' ?>>RettSan - Rettungssanitäter</option>
                                        <option value="notsan_2c" <?= $formData['competency_level'] === 'notsan_2c' ? 'selected' : '' ?>>NFS 2c - § 4 Abs. 2c NotSanG</option>
                                        <option value="notsan_2a" <?= $formData['competency_level'] === 'notsan_2a' ? 'selected' : '' ?>>NFS 2a - § 2a NotSanG</option>
                                        <option value="notarzt" <?= $formData['competency_level'] === 'notarzt' ? 'selected' : '' ?>>Notarzt</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Titel <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control" required
                                       value="<?= htmlspecialchars($formData['title']) ?>" 
                                       placeholder="z.B. Dimetinden (Fenistil)">
                            </div>
                            
                            <div class="mb-3">
                                <label for="subtitle" class="form-label">Untertitel / Beschreibung</label>
                                <input type="text" name="subtitle" id="subtitle" class="form-control"
                                       value="<?= htmlspecialchars($formData['subtitle']) ?>"
                                       placeholder="z.B. Ruhigstellung, Extremitäten-Immobilisation, SAM-Splint">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="category_id" class="form-label">Kategorie</label>
                                    <select name="category_id" id="category_id" class="form-select">
                                        <option value="">Keine Kategorie</option>
                                        <?php
                                        // Hierarchische Anzeige mit Einrückung
                                        function renderCategoryOptions(array $categories, int $selectedId = 0, ?int $parentId = null, int $depth = 0): void
                                        {
                                            foreach ($categories as $cat) {
                                                if ((int)($cat['parent_id'] ?? 0) !== ($parentId ?? 0) && ($parentId !== null || $cat['parent_id'] !== null)) {
                                                    continue;
                                                }
                                                if ($parentId === null && $cat['parent_id'] !== null) {
                                                    continue;
                                                }
                                                $prefix = str_repeat('— ', $depth);
                                                $selected = ((int)$cat['id'] === $selectedId) ? 'selected' : '';
                                                $icon = !empty($cat['icon']) ? '<i class="' . htmlspecialchars($cat['icon']) . '"></i> ' : '';
                                                echo "<option value=\"{$cat['id']}\" {$selected}>{$prefix}" . htmlspecialchars($cat['name']) . "</option>";
                                                // Kinder rendern
                                                renderCategoryOptions($categories, $selectedId, (int)$cat['id'], $depth + 1);
                                            }
                                        }
                                        renderCategoryOptions($allCategories, (int)($formData['category_id'] ?? 0));
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tags</label>
                                    <div class="d-flex flex-wrap gap-2" style="min-height: 38px;">
                                        <?php foreach ($allTags as $tag):
                                            $checked = in_array($tag['id'], $entryTags) ? 'checked' : '';
                                        ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="tags[]" value="<?= $tag['id'] ?>" id="tag_<?= $tag['id'] ?>" <?= $checked ?>>
                                                <label class="form-check-label" for="tag_<?= $tag['id'] ?>">
                                                    <span class="badge" style="background-color: <?= htmlspecialchars($tag['color']) ?>; color: #fff;"><?= htmlspecialchars($tag['name']) ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (empty($allTags)): ?>
                                            <small class="text-muted">Noch keine Tags vorhanden.</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Medication Fields -->
                        <div id="medication-fields" class="type-fields intra__tile p-4 mb-4">
                            <h4 class="mb-3"><i class="fa-solid fa-pills"></i> Medikament-Informationen</h4>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="med_wirkstoff" class="form-label">Wirkstoff</label>
                                    <input type="text" name="med_wirkstoff" id="med_wirkstoff" class="form-control"
                                           value="<?= htmlspecialchars($formData['med_wirkstoff']) ?>"
                                           placeholder="z.B. Dimetinden (Fenistil)">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="med_wirkstoffgruppe" class="form-label">Wirkstoffgruppe</label>
                                    <input type="text" name="med_wirkstoffgruppe" id="med_wirkstoffgruppe" class="form-control"
                                           value="<?= htmlspecialchars($formData['med_wirkstoffgruppe']) ?>"
                                           placeholder="z.B. Antihistaminikum">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="med_wirkmechanismus" class="form-label">Wirkmechanismus</label>
                                <textarea name="med_wirkmechanismus" id="med_wirkmechanismus" class="form-control" rows="2"
                                          placeholder="z.B. Blockade von Histamin am H1-Rezeptor → antiallergische Wirkung, Sedierung"><?= htmlspecialchars($formData['med_wirkmechanismus']) ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="med_indikationen" class="form-label">Indikationen</label>
                                    <textarea name="med_indikationen" id="med_indikationen" class="form-control" rows="3"
                                              placeholder="• Anaphylaxie"><?= htmlspecialchars($formData['med_indikationen']) ?></textarea>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="med_kontraindikationen" class="form-label">Kontraindikationen</label>
                                    <textarea name="med_kontraindikationen" id="med_kontraindikationen" class="form-control" rows="3"
                                              placeholder="• Unverträglichkeit"><?= htmlspecialchars($formData['med_kontraindikationen']) ?></textarea>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="med_uaw" class="form-label">Unerwünschte Arzneimittelwirkungen (UAW)</label>
                                <textarea name="med_uaw" id="med_uaw" class="form-control" rows="3"
                                          placeholder="• Müdigkeit&#10;• Mundtrockenheit&#10;• Kopfschmerzen"><?= htmlspecialchars($formData['med_uaw']) ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="med_dosierung" class="form-label">Dosierung</label>
                                <textarea name="med_dosierung" id="med_dosierung" class="form-control" rows="2"
                                          placeholder="• 4 mg i.v."><?= htmlspecialchars($formData['med_dosierung']) ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="med_besonderheiten" class="form-label">Besonderheiten / CAVE</label>
                                <textarea name="med_besonderheiten" id="med_besonderheiten" class="form-control" rows="3"
                                          placeholder="• Wirkt nur lindernd auf Juckreiz → Verabreichung nur, wenn Basismaßnahmen nicht verzögert werden"><?= htmlspecialchars($formData['med_besonderheiten']) ?></textarea>
                            </div>
                        </div>

                        <!-- Measure Fields -->
                        <div id="measure-fields" class="type-fields intra__tile p-4 mb-4">
                            <h4 class="mb-3"><i class="fa-solid fa-hand-holding-medical"></i> Maßnahmen-Informationen</h4>
                            
                            <div class="mb-3">
                                <label for="mass_wirkprinzip" class="form-label">Wirkprinzip</label>
                                <textarea name="mass_wirkprinzip" id="mass_wirkprinzip" class="form-control" rows="2"
                                          placeholder="Ruhigstellung eines Körperteils und Verhindern von Bewegung → Vermeidung von weiteren Verletzungen durch Bewegung"><?= htmlspecialchars($formData['mass_wirkprinzip']) ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="mass_indikationen" class="form-label">Indikationen</label>
                                    <textarea name="mass_indikationen" id="mass_indikationen" class="form-control" rows="3"
                                              placeholder="V.a. Fraktur einer Extremität mit intakter pDMS"><?= htmlspecialchars($formData['mass_indikationen']) ?></textarea>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="mass_kontraindikationen" class="form-label">Kontraindikationen</label>
                                    <textarea name="mass_kontraindikationen" id="mass_kontraindikationen" class="form-control" rows="3"
                                              placeholder="Unmöglichkeit, schmerzbedingte Intoleranz"><?= htmlspecialchars($formData['mass_kontraindikationen']) ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="mass_risiken" class="form-label">Risiken</label>
                                    <textarea name="mass_risiken" id="mass_risiken" class="form-control" rows="2"
                                              placeholder="Schmerzen"><?= htmlspecialchars($formData['mass_risiken']) ?></textarea>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="mass_alternativen" class="form-label">Alternativen</label>
                                    <textarea name="mass_alternativen" id="mass_alternativen" class="form-control" rows="2"
                                              placeholder="Kühlung, manuelle Stabilisierung, Vakuumschiene"><?= htmlspecialchars($formData['mass_alternativen']) ?></textarea>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="mass_durchfuehrung" class="form-label">Durchführung</label>
                                <textarea name="mass_durchfuehrung" id="mass_durchfuehrung" class="form-control" rows="4"
                                          placeholder="» SAM-Splint an gesunder Extremität anpassen&#10;» Extremität vorsichtig hineinlegen&#10;» Fixierung mittels eng gewickelter Mullbinde&#10;» ggf. Kühlpack mit einwickeln"><?= htmlspecialchars($formData['mass_durchfuehrung']) ?></textarea>
                            </div>
                        </div>

                        <!-- General Content (CKEditor) -->
                        <div class="intra__tile p-4 mb-4">
                            <h4 class="mb-3">Zusätzlicher Inhalt</h4>
                            <p class="text-muted small">Optionaler Freitext für weitere Informationen (mit Formatierung)</p>
                            
                            <textarea name="content" id="content" class="form-control" rows="2"><?= htmlspecialchars($formData['content']) ?></textarea>
                        </div>

                        <?php if ($isEdit): ?>
                        <!-- Admin Options (only when editing) -->
                        <div class="intra__tile p-4 mb-4">
                            <h4 class="mb-3"><i class="fa-solid fa-cog"></i> Optionen</h4>
                            
                            <div class="row">
                                <?php if (Permissions::check(['admin', 'kb.edit'])): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" name="is_pinned" id="is_pinned" value="1"
                                               <?= !empty($entry['is_pinned']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="is_pinned">
                                            <i class="fa-solid fa-thumbtack"></i> Eintrag anpinnen
                                            <small class="text-muted d-block">Angepinnte Einträge werden oben in der Liste angezeigt</small>
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (Permissions::check(['admin'])): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" name="hide_editor" id="hide_editor" value="1"
                                               <?= !empty($entry['hide_editor']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="hide_editor">
                                            <i class="fa-solid fa-eye-slash"></i> Bearbeiter ausblenden
                                            <small class="text-muted d-block">Name des Erstellers/Bearbeiters wird nicht angezeigt</small>
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-between">
                            <a href="<?= BASE_PATH ?>wissensdb/index.php" class="btn btn-ghost">
                                <i class="fa-solid fa-arrow-left"></i> Abbrechen
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fa-solid fa-save"></i> <?= $isEdit ? 'Änderungen speichern' : 'Eintrag erstellen' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../assets/components/footer.php"; ?>

    <script type="importmap">
    {
        "imports": {
            "ckeditor5": "<?= BASE_PATH ?>assets/_ext/ckeditor5/ckeditor5.js",
            "ckeditor5/": "<?= BASE_PATH ?>assets/_ext/ckeditor5/"
        }
    }
    </script>
    <script type="module">
        import {
            ClassicEditor,
            Essentials,
            Bold,
            Italic,
            Underline,
            Strikethrough,
            Heading,
            Link,
            List,
            Paragraph,
            BlockQuote,
            Table,
            TableToolbar
        } from 'ckeditor5';

        // Store editor instances
        const editorInstances = {};

        // CKEditor configuration for rich text fields
        const fullEditorConfig = {
            licenseKey: 'GPL',
            plugins: [
                Essentials, Bold, Italic, Underline, Strikethrough,
                Heading, Link, List, Paragraph, BlockQuote, Table, TableToolbar
            ],
            toolbar: {
                items: [
                    'heading', '|',
                    'bold', 'italic', 'underline', 'strikethrough', '|',
                    'link', 'bulletedList', 'numberedList', '|',
                    'blockQuote', 'insertTable', '|',
                    'undo', 'redo'
                ]
            },
            table: {
                contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
            },
            language: 'de'
        };

        // Simpler config for medication/measure fields
        const simpleEditorConfig = {
            licenseKey: 'GPL',
            plugins: [
                Essentials, Bold, Italic, Underline, Link, List, Paragraph
            ],
            toolbar: {
                items: [
                    'bold', 'italic', 'underline', '|',
                    'link', 'bulletedList', 'numberedList', '|',
                    'undo', 'redo'
                ]
            },
            language: 'de'
        };

        // Initialize CKEditor on main content field
        ClassicEditor
            .create(document.querySelector('#content'), fullEditorConfig)
            .then(editor => {
                editorInstances['content'] = editor;
            })
            .catch(error => {
                console.error('Error initializing content editor:', error);
            });

        // Medication field IDs
        const medicationFields = [
            'med_wirkmechanismus',
            'med_indikationen',
            'med_kontraindikationen',
            'med_uaw',
            'med_dosierung',
            'med_besonderheiten'
        ];

        // Measure field IDs
        const measureFieldIds = [
            'mass_wirkprinzip',
            'mass_indikationen',
            'mass_kontraindikationen',
            'mass_risiken',
            'mass_alternativen',
            'mass_durchfuehrung'
        ];

        // Initialize CKEditor on all medication fields
        medicationFields.forEach(fieldId => {
            const element = document.querySelector('#' + fieldId);
            if (element) {
                ClassicEditor
                    .create(element, simpleEditorConfig)
                    .then(editor => {
                        editorInstances[fieldId] = editor;
                    })
                    .catch(error => {
                        console.error('Error initializing ' + fieldId + ' editor:', error);
                    });
            }
        });

        // Initialize CKEditor on all measure fields
        measureFieldIds.forEach(fieldId => {
            const element = document.querySelector('#' + fieldId);
            if (element) {
                ClassicEditor
                    .create(element, simpleEditorConfig)
                    .then(editor => {
                        editorInstances[fieldId] = editor;
                    })
                    .catch(error => {
                        console.error('Error initializing ' + fieldId + ' editor:', error);
                    });
            }
        });

        // Toggle type-specific fields
        const typeSelect = document.getElementById('type');
        const medicationFieldsDiv = document.getElementById('medication-fields');
        const measureFieldsDiv = document.getElementById('measure-fields');

        function updateTypeFields() {
            const type = typeSelect.value;
            
            medicationFieldsDiv.classList.remove('active');
            measureFieldsDiv.classList.remove('active');
            
            if (type === 'medication') {
                medicationFieldsDiv.classList.add('active');
            } else if (type === 'measure') {
                measureFieldsDiv.classList.add('active');
            }
        }

        typeSelect.addEventListener('change', updateTypeFields);
        updateTypeFields(); // Initial state
    </script>
</body>

</html>
