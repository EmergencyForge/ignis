<?php
/**
 * View: System-Konfiguration bearbeiten
 *
 * @var \PDO $pdo
 */

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Config\ConfigManager;
use App\Utils\AuditLogger;

$configManager = new ConfigManager($pdo);
$auditLogger = new AuditLogger($pdo);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $updates = [];
    $changes = [];

    // Get all configs to check types
    $allConfigs = $configManager->getAllConfig();
    $configTypes = [];
    foreach ($allConfigs as $config) {
        $configTypes[$config['config_key']] = $config['config_type'];
    }

    // Process POST data
    foreach ($allConfigs as $config) {
        if (!$config['is_editable']) continue;

        $key = $config['config_key'];

        // Get the raw database value (string) instead of converted value
        $oldValue = $config['config_value'];

        // Handle different input types
        if ($config['config_type'] === 'boolean') {
            // Checkboxes/switches send 'on' when checked, nothing when unchecked
            $value = isset($_POST[$key]) && $_POST[$key] === 'on' ? 'true' : 'false';
        } else {
            // Skip if not in POST
            if (!isset($_POST[$key])) continue;
            $value = $_POST[$key];
        }

        // Only update if value changed (strict comparison for type safety)
        if ($oldValue !== $value) {
            $updates[$key] = $value;
            $changes[] = [
                'key' => $key,
                'old' => $oldValue,
                'new' => $value
            ];
        }
    }

    if (!empty($updates)) {
        $result = $configManager->updateMultiple($updates, $_SESSION['userid']);

        if ($result['success']) {
            // Log each change in audit log
            foreach ($changes as $change) {
                $auditLogger->log(
                    $_SESSION['userid'],
                    'Config ' . $change['key'] . ' bearbeitet',
                    'Altere Wert: ' . $change['old'] . ', Neuer Wert: ' . $change['new'],
                    'System',
                    1  // Config updates are global
                );
            }

            Flash::set('success', 'Konfiguration erfolgreich aktualisiert.');
        } else {
            Flash::set('error', 'Fehler beim Aktualisieren der Konfiguration.');
        }
    } else {
        Flash::set('info', 'Keine Änderungen vorgenommen.');
    }

    header("Location: " . BASE_PATH . "settings/system/config.php");
    exit();
}

$configByCategory = $configManager->getConfigByCategory();
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    $SITE_TITLE = 'System-Konfiguration';
    include __DIR__ . '/../../../assets/components/_base/admin/head.php';
    ?>
    <style>
        .config-preview {
            padding: 1rem;
            margin-top: 0.5rem;
        }

        .logo-preview,
        .meta-image-preview {
            max-width: 200px;
            max-height: 100px;
            border: 1px solid var(--bs-border-color);
            border-radius: 0;
            padding: 0.5rem;
        }

        .color-input-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .color-input-wrapper input[type="color"] {
            width: 60px;
            height: 40px;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
        }

        .color-input-wrapper input[type="text"] {
            flex: 1;
        }

        .form-label {
            font-weight: 600;
        }

        .form-text {
            font-size: 0.875rem;
        }

        .config-section {
            margin-bottom: 2rem;
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-6">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index.php">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item is-active">System</span></nav>
                    <div class="page-header mb-4">
                        <h1>System-Konfiguration</h1>
                    </div>
                    <?php Flash::render(); ?>

                    <div class="mb-4">
                        <div class="btn-toolbar-group" id="categoryFilter">
                            <button class="btn active" data-category="">Alle</button>
                            <?php foreach ($configByCategory as $category => $configs): ?>
                                <button class="btn" data-category="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($configManager->getCategoryDisplayName($category)) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <form method="post" id="configForm">
                        <?php foreach ($configByCategory as $category => $configs): ?>
                            <div class="config-section" data-config-category="<?= htmlspecialchars($category) ?>">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0"><?= htmlspecialchars($configManager->getCategoryDisplayName($category)) ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <?php foreach ($configs as $config): ?>
                                            <div class="mb-4">
                                                <label for="<?= htmlspecialchars($config['config_key']) ?>" class="ignis-field__label">
                                                    <?= htmlspecialchars($config['description']) ?>
                                                </label>

                                                <?php if ($config['config_key'] === 'API_KEY'): ?>
                                                    <div class="input-group">
                                                        <input
                                                            type="password"
                                                            class="ignis-input"
                                                            id="<?= htmlspecialchars($config['config_key']) ?>"
                                                            value="<?= htmlspecialchars($config['config_value']) ?>"
                                                            readonly>
                                                        <button
                                                            type="button"
                                                            class="ignis-btn ignis-btn--outline-secondary ignis-btn--icon"
                                                            onclick="toggleApiKeyVisibility()"
                                                            title="API-Schlüssel anzeigen/verbergen"
                                                            id="toggleApiKeyBtn">
                                                            <i class="fa-solid fa-eye"></i>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="ignis-btn ignis-btn--outline-primary ignis-btn--icon"
                                                            onclick="copyApiKey()"
                                                            title="API-Schlüssel kopieren">
                                                            <i class="fa-solid fa-copy"></i>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="ignis-btn ignis-btn--soft-warning btn-icon"
                                                            onclick="regenerateApiKey(event)"
                                                            title="API-Schlüssel neu generieren">
                                                            <i class="fa-solid fa-rotate"></i>
                                                        </button>
                                                    </div>
                                                    <div class="ignis-field__hint">Dieser API-Schlüssel wird für externe Schnittstellen verwendet. Ein neuer Schlüssel macht alte Integrationen ungültig.</div>

                                                <?php elseif ($config['is_editable'] && $config['config_type'] === 'boolean'): ?>
                                                    <div class="form-check form-switch">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            role="switch"
                                                            id="<?= htmlspecialchars($config['config_key']) ?>"
                                                            name="<?= htmlspecialchars($config['config_key']) ?>"
                                                            <?= ($config['config_value'] === 'true' || $config['config_value'] === '1') ? 'checked' : '' ?>>
                                                    </div>

                                                <?php elseif ($config['is_editable'] && $config['config_type'] === 'color'): ?>
                                                    <div class="color-input-wrapper">
                                                        <input
                                                            type="color"
                                                            id="<?= htmlspecialchars($config['config_key']) ?>_picker"
                                                            value="<?= htmlspecialchars($config['config_value']) ?>"
                                                            onchange="updateColorValue('<?= htmlspecialchars($config['config_key']) ?>', this.value)">
                                                        <input
                                                            type="text"
                                                            class="ignis-input"
                                                            id="<?= htmlspecialchars($config['config_key']) ?>"
                                                            name="<?= htmlspecialchars($config['config_key']) ?>"
                                                            value="<?= htmlspecialchars($config['config_value']) ?>"
                                                            pattern="^#[0-9A-Fa-f]{6}$"
                                                            placeholder="#000000"
                                                            title="6-stelliger Hex-Farbcode (z.B. #ff0000)"
                                                            oninput="updateColorPicker('<?= htmlspecialchars($config['config_key']) ?>', this.value)">
                                                    </div>
                                                    <div class="ignis-field__hint">Wählen Sie eine Farbe aus oder geben Sie einen Hex-Farbcode ein.</div>

                                                <?php elseif ($config['is_editable'] && $config['config_type'] === 'url' && $config['config_key'] === 'SYSTEM_LOGO'): ?>
                                                    <input
                                                        type="text"
                                                        class="form-control mb-2"
                                                        id="<?= htmlspecialchars($config['config_key']) ?>"
                                                        name="<?= htmlspecialchars($config['config_key']) ?>"
                                                        value="<?= htmlspecialchars($config['config_value']) ?>"
                                                        oninput="updateLogoPreview(this.value)">
                                                    <div class="ignis-field__hint">Relativer Pfad oder vollständige URL zum Logo.</div>
                                                    <div class="config-preview">
                                                        <strong>Vorschau:</strong><br>
                                                        <img
                                                            src="<?= htmlspecialchars($config['config_value']) ?>"
                                                            alt="Logo Preview"
                                                            class="logo-preview"
                                                            id="logo_preview"
                                                            onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22100%22%3E%3Crect fill=%22%23ddd%22 width=%22200%22 height=%22100%22/%3E%3Ctext fill=%22%23999%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22%3EBild nicht gefunden%3C/text%3E%3C/svg%3E'">
                                                    </div>

                                                <?php elseif ($config['is_editable'] && $config['config_type'] === 'url' && $config['config_key'] === 'META_IMAGE_URL'): ?>
                                                    <input
                                                        type="text"
                                                        class="form-control mb-2"
                                                        id="<?= htmlspecialchars($config['config_key']) ?>"
                                                        name="<?= htmlspecialchars($config['config_key']) ?>"
                                                        value="<?= htmlspecialchars($config['config_value']) ?>"
                                                        oninput="updateMetaImagePreview(this.value)">
                                                    <div class="ignis-field__hint">Vollständige URL zum Bild für Link-Vorschau.</div>
                                                    <div class="config-preview">
                                                        <strong>Vorschau:</strong><br>
                                                        <img
                                                            src="<?= htmlspecialchars($config['config_value']) ?>"
                                                            alt="Meta Image Preview"
                                                            class="meta-image-preview"
                                                            id="meta_image_preview"
                                                            onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22100%22%3E%3Crect fill=%22%23ddd%22 width=%22200%22 height=%22100%22/%3E%3Ctext fill=%22%23999%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22%3EBild nicht gefunden%3C/text%3E%3C/svg%3E'">
                                                    </div>

                                                <?php elseif ($config['is_editable'] && $config['config_key'] === 'REGISTRATION_MODE'): ?>
                                                    <select
                                                        class="form-select"
                                                        id="<?= htmlspecialchars($config['config_key']) ?>"
                                                        name="<?= htmlspecialchars($config['config_key']) ?>">
                                                        <option value="open" <?= $config['config_value'] === 'open' ? 'selected' : '' ?>>Offen (für jeden möglich)</option>
                                                        <option value="code" <?= $config['config_value'] === 'code' ? 'selected' : '' ?>>Mit Code (nur mit Registrierungscode)</option>
                                                        <option value="closed" <?= $config['config_value'] === 'closed' ? 'selected' : '' ?>>Geschlossen (keine Registrierung)</option>
                                                    </select>
                                                    <div class="ignis-field__hint"><?= htmlspecialchars($config['description']) ?></div>

                                                <?php elseif ($config['is_editable'] && $config['config_key'] === 'ENOTF_BZ_UNIT'): ?>
                                                    <select
                                                        class="form-select"
                                                        id="<?= htmlspecialchars($config['config_key']) ?>"
                                                        name="<?= htmlspecialchars($config['config_key']) ?>">
                                                        <option value="mg/dl" <?= $config['config_value'] === 'mg/dl' ? 'selected' : '' ?>>mg/dl (Milligramm pro Deziliter)</option>
                                                        <option value="mmol/l" <?= $config['config_value'] === 'mmol/l' ? 'selected' : '' ?>>mmol/l (Millimol pro Liter)</option>
                                                    </select>
                                                    <div class="ignis-field__hint">Blutzuckerwerte werden automatisch umgerechnet (1 mg/dl = 0,0555 mmol/l)</div>

                                                <?php elseif ($config['is_editable']): ?>
                                                    <input
                                                        type="text"
                                                        class="ignis-input"
                                                        id="<?= htmlspecialchars($config['config_key']) ?>"
                                                        name="<?= htmlspecialchars($config['config_key']) ?>"
                                                        value="<?= htmlspecialchars($config['config_value']) ?>">
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="mb-6 flex flex-col gap-2 md:flex-row md:justify-end">
                            <button type="submit" name="save_config" class="ignis-btn ignis-btn--soft-primary btn-lg">
                                <i class="fa-solid fa-save"></i> Änderungen speichern
                            </button>
                        </div>
                    </form>
            </div>
        </div>
    </div>
    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>

    <script>
        // Category segmented control filtering
        document.querySelectorAll('#categoryFilter .btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#categoryFilter .btn').forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');
                var cat = this.dataset.category;
                document.querySelectorAll('.config-section').forEach(function(section) {
                    if (!cat) { section.style.display = ''; return; }
                    section.style.display = (section.dataset.configCategory === cat) ? '' : 'none';
                });
            });
        });

        function updateColorValue(key, value) {
            document.getElementById(key).value = value;
            document.getElementById(key + '_picker').value = value;
        }

        function updateColorPicker(key, value) {
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                document.getElementById(key + '_picker').value = value;
            }
        }

        function updateLogoPreview(value) {
            document.getElementById('logo_preview').src = value;
        }

        function updateMetaImagePreview(value) {
            document.getElementById('meta_image_preview').src = value;
        }

        function toggleApiKeyVisibility() {
            const input = document.getElementById('API_KEY');
            const button = document.getElementById('toggleApiKeyBtn');
            const icon = button.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                button.title = 'API-Schlüssel verbergen';
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                button.title = 'API-Schlüssel anzeigen';
            }
        }

        async function copyApiKey() {
            const input = document.getElementById('API_KEY');
            try {
                await navigator.clipboard.writeText(input.value);
                showToast('API-Schlüssel kopiert', 'success');
            } catch (err) {
                showToast('Fehler beim Kopieren: ' + err, 'danger');
            }
        }

        async function regenerateApiKey(event) {
            const confirmed = await showConfirm(
                'Möchten Sie wirklich einen neuen API-Schlüssel generieren?\n\nWARNUNG: Dies macht alle bestehenden Integrationen ungültig, die den aktuellen API-Schlüssel verwenden!', {
                    title: 'API-Schlüssel neu generieren',
                    confirmText: 'Ja, neu generieren',
                    cancelText: 'Abbrechen',
                    danger: true
                }
            );

            if (!confirmed) {
                return;
            }

            // Show loading indicator
            const button = event.target.closest('button');
            const originalContent = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Wird generiert...';

            // Send request to regenerate API key
            const basePath = <?= json_encode(BASE_PATH) ?>;
            const url = basePath + (basePath.endsWith('/') ? '' : '/') + 'api/system/regenerate-api-key.php';
            fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the input field with new API key
                        document.getElementById('API_KEY').value = data.api_key;
                        // Reset to password type after regeneration for security
                        const input = document.getElementById('API_KEY');
                        const button = document.getElementById('toggleApiKeyBtn');
                        const icon = button.querySelector('i');
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                        button.title = 'API-Schlüssel anzeigen';

                        showAlert('API-Schlüssel wurde erfolgreich neu generiert!', {
                            title: 'Erfolg',
                            type: 'success'
                        });
                    } else {
                        showAlert('Fehler beim Generieren des API-Schlüssels: ' + (data.message || 'Unbekannter Fehler'), {
                            title: 'Fehler',
                            type: 'error'
                        });
                    }
                })
                .catch(error => {
                    showAlert('Fehler beim Generieren des API-Schlüssels: ' + error, {
                        title: 'Fehler',
                        type: 'error'
                    });
                })
                .finally(() => {
                    button.disabled = false;
                    button.innerHTML = originalContent;
                });
        }
    </script>
</body>

</html>