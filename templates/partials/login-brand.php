<?php
/**
 * Logo-Platz der Anmeldung, auch auf den Fehlerseiten (templates/errors/_shell.php).
 *
 * SYSTEM_LOGO, wenn der Betreiber eines hinterlegt hat (dann als schlichtes
 * <img>, currentColor greift dort nicht), sonst das mitgelieferte Lockup
 * inline. Dieselbe Logik wie in topbar.php.
 *
 * Die Konstanten nur über defined(): die Fehlerseiten tragen auch ohne
 * Konfiguration. Läuft im Scope des Aufrufers, deshalb nur Variablen mit
 * dem Präfix `brand`.
 */

$brandBase = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
<div class="twplus-login__brand">
    <?php if (systemLogoIsDefault()): ?>
        <?php // Zeichen und Schriftzug als Masken, wie in der Topbar (siehe _shell.scss). ?>
        <span class="ignis-lockup ignis-lockup--login" role="img" aria-label="ignis"><span class="ignis-lockup__mark" style="--logo: url('<?= htmlspecialchars($brandBase . 'assets/img/ignis-mark.svg', ENT_QUOTES) ?>')"></span><span class="ignis-lockup__word" style="--logo: url('<?= htmlspecialchars($brandBase . 'assets/img/ignis-wordmark.svg', ENT_QUOTES) ?>')"></span></span>
    <?php else: ?>
        <img src="<?= systemLogoUrl() ?>" alt="<?= htmlspecialchars(defined('SYSTEM_NAME') ? (string) SYSTEM_NAME : 'ignis', ENT_QUOTES) ?>">
    <?php endif; ?>
</div>
