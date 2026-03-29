<?php
/**
 * Modularer Redirect Helper
 * Inkludiert am Anfang der statischen Sektions-Seiten.
 * Leitet auf die dynamische section.php um, wenn ENOTF_MODULAR_FORMS aktiv ist.
 *
 * Verwendung in bestehenden statischen Dateien:
 *   require_once __DIR__ . '/../../../../assets/functions/enotf/modular_redirect.php';
 *   enotf_modular_redirect('erstbefund');
 */

function enotf_modular_redirect(string $sectionSlug): void
{
    if (!defined('ENOTF_MODULAR_FORMS') || ENOTF_MODULAR_FORMS !== true) {
        return;
    }

    $enr = $_GET['enr'] ?? '';
    if (empty($enr)) {
        return;
    }

    header("Location: " . BASE_PATH . "enotf/protokoll/" . urlencode($enr) . "/" . $sectionSlug);
    exit();
}
