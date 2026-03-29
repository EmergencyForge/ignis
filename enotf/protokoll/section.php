<?php
/**
 * Dynamische Protokoll-Sektion
 * Ersetzt alle statischen sektionsspezifischen index.php-Dateien
 * wenn ENOTF_MODULAR_FORMS aktiv ist.
 *
 * URL-Pattern (via .htaccess):
 *   /enotf/protokoll/{enr}/{section}
 *   /enotf/protokoll/section.php?enr=...&section=...
 */

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');
}

require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../assets/functions/enotf/user_auth_middleware.php';
require_once __DIR__ . '/../../assets/functions/enotf/pin_middleware.php';

use App\Enotf\ProtocolTypeService;
use App\Enotf\ProtocolDataService;
use App\Enotf\FormRenderer;
use App\Enotf\ValidationEngine;

// ──── Parameter auslesen ────
$enr = $_GET['enr'] ?? '';
$sectionSlug = $_GET['section'] ?? '';

if (empty($enr)) {
    header("Location: " . BASE_PATH . "enotf/");
    exit();
}

// ──── Services initialisieren ────
$typeService = new ProtocolTypeService($pdo);
$dataService = new ProtocolDataService($pdo, $typeService);
$formRenderer = new FormRenderer($pdo, $typeService, BASE_PATH);
$validationEngine = new ValidationEngine($pdo, $typeService);

// ──── Protokolldaten laden ────
$daten = $dataService->getFullProtocolData($enr);
if (!$daten) {
    header("Location: " . BASE_PATH . "enotf/");
    exit();
}

$ist_freigegeben = ($daten['freigegeben'] == 1);
$daten['last_edit'] = !empty($daten['last_edit']) ? (new DateTime($daten['last_edit']))->format('d.m.Y H:i') : null;

// ──── Protokolltyp und Sektionen laden ────
$typeId = (int)($daten['protocol_type_id'] ?? 1);
$type = $typeService->getType($typeId);
$sections = $typeService->getSectionsForType($typeId);

// ──── Aktive Sektion bestimmen ────
$activeSection = null;
if (empty($sectionSlug) && !empty($sections)) {
    // Redirect zur ersten Sektion
    header("Location: " . BASE_PATH . "enotf/protokoll/" . urlencode($enr) . "/" . $sections[0]['slug']);
    exit();
}

foreach ($sections as $section) {
    if ($section['slug'] === $sectionSlug) {
        $activeSection = $section;
        break;
    }
}

if (!$activeSection) {
    header("Location: " . BASE_PATH . "enotf/protokoll/" . urlencode($enr) . "/" . ($sections[0]['slug'] ?? 'rettdaten'));
    exit();
}

$sectionId = (int)$activeSection['id'];
$prot_url = "https://" . SYSTEM_URL . "/enotf/protokoll/" . urlencode($enr) . "/" . $sectionSlug;

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';

// ──── Render Section Content ────
$sectionHtml = '';
$componentTemplate = $activeSection['component_template'] ?? null;

if ($componentTemplate) {
    // Spezial-Widget: Include des Widget-Partials
    $widgetPath = __DIR__ . '/../../assets/components/enotf/widgets/' . $componentTemplate . '.php';
    if (file_exists($widgetPath)) {
        ob_start();
        $fieldDef = null;
        $basePath = BASE_PATH;
        include $widgetPath;
        $sectionHtml = ob_get_clean();
    } else {
        $sectionHtml = '<div class="text-muted p-3">Widget "' . htmlspecialchars($componentTemplate) . '" nicht gefunden.</div>';
    }
} else {
    // Standard: Dynamisch gerenderte Felder
    $sectionHtml = $formRenderer->renderSection($typeId, $sectionId, $daten, $ist_freigegeben);
}
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="<?= htmlspecialchars($sectionSlug) ?>" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php include __DIR__ . '/../../assets/components/enotf/topbar.php'; ?>

    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <?php include __DIR__ . '/../../assets/components/enotf/nav_dynamic.php'; ?>

                <div class="col" id="edivi__content" style="padding-left: 0">
                    <?php if ($activeSection['has_subsections']): ?>
                        <!-- Subsection navigation for sections with subsections (Erstbefund, Massnahmen) -->
                        <div class="row" style="margin-left: 0">
                            <?php
                            $sectionFields = $typeService->getFieldsForSection($typeId, $sectionId);
                            // Group by group_key for subsection navigation
                            $groups = [];
                            foreach ($sectionFields as $f) {
                                $gk = $f['group_key'] ?? '_default';
                                if (!isset($groups[$gk])) {
                                    $groups[$gk] = ['label' => $f['group_label'] ?? $gk, 'fields' => []];
                                }
                                $groups[$gk]['fields'][] = $f;
                            }

                            $activeGroup = $_GET['group'] ?? array_key_first($groups);
                            ?>
                            <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                                <?php foreach ($groups as $gk => $group): ?>
                                    <a href="?enr=<?= urlencode($enr) ?>&section=<?= $sectionSlug ?>&group=<?= urlencode($gk) ?>"
                                       class="<?= $gk === $activeGroup ? 'active' : '' ?>"
                                       data-requires="<?php
                                           $reqFields = [];
                                           foreach ($group['fields'] as $gf) {
                                               if ($gf['is_required']) $reqFields[] = $gf['field_key'];
                                           }
                                           echo htmlspecialchars(implode(',', $reqFields));
                                       ?>">
                                        <span><?= htmlspecialchars($group['label']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <div class="col">
                                <?php
                                // Render only the active group's fields
                                if (isset($groups[$activeGroup])) {
                                    foreach ($groups[$activeGroup]['fields'] as $field) {
                                        echo '<div class="d-flex flex-column edivi__interactbutton">';
                                        echo $formRenderer->renderField($field, $daten, $ist_freigegeben);
                                        echo '</div>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Simple section: render all fields -->
                        <div class="row" style="margin-left: 0">
                            <div class="col">
                                <?= $sectionHtml ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>

    <?php
    include __DIR__ . '/../../assets/functions/enotf/notify.php';
    include __DIR__ . '/../../assets/functions/enotf/field_checks.php';
    include __DIR__ . '/../../assets/functions/enotf/clock.php';
    ?>

    <?php if ($ist_freigegeben): ?>
        <script>
            document.querySelectorAll('input, textarea').forEach(function(el) { el.setAttribute('readonly', 'readonly'); });
            document.querySelectorAll('select').forEach(function(el) { el.setAttribute('disabled', 'disabled'); });
            document.querySelectorAll('.btn-check, .form-check-input').forEach(function(el) { el.setAttribute('disabled', 'disabled'); });
        </script>
    <?php endif; ?>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>
