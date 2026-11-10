<?php
/**
 * Rechte Hälfte der Anmeldung, auch auf /verify. Reine Deko: die Kontur
 * ist das Zeichen aus ignis-mark.svg, die erste Teilfigur ist die
 * Außenkontur, danach kommen die Buchstaben. Markup und Aussehen:
 * login-stage() im UI-Paket, Funken aus assets/js/ui/login-stage.js.
 *
 * Läuft im Scope des Aufrufers, deshalb nur Variablen mit dem Präfix `stage`.
 */

preg_match('~\sd="([^"]+)"~', (string) file_get_contents(dirname(__DIR__, 2) . '/assets/img/ignis-mark.svg'), $stageMark);
$stageMark = $stageMark[1] ?? '';
$stageShapes = preg_split('~(?<=Z)~', $stageMark, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$stageOperator = trim(RP_ORGTYPE . ' ' . SERVER_CITY);
?>
<aside class="twplus-login__visual" aria-hidden="true">
    <div class="ignis-login-stage">
        <div class="ignis-login-stage__heat"></div>
        <canvas class="ignis-login-stage__sparks" data-ignis-login-sparks></canvas>
        <div class="ignis-login-stage__emblem">
            <div class="ignis-login-stage__mark">
                <svg class="ignis-login-stage__glow" viewBox="0 0 96 96">
                    <?php foreach ($stageShapes as $shape): ?><path class="ignis-login-stage__line" pathLength="1" d="<?= htmlspecialchars($shape, ENT_QUOTES) ?>"/><?php endforeach; ?>
                </svg>
                <svg viewBox="0 0 96 96">
                    <linearGradient id="ignis-login-stage-trace" x1="0" y1="1" x2="1" y2="0"><stop offset="0"/><stop offset="1"/></linearGradient>
                    <path class="ignis-login-stage__fill" d="<?= htmlspecialchars($stageMark, ENT_QUOTES) ?>"/>
                    <?php foreach ($stageShapes as $shape): ?><path class="ignis-login-stage__line" pathLength="1" d="<?= htmlspecialchars($shape, ENT_QUOTES) ?>"/><?php endforeach; ?>
                </svg>
            </div>
            <p class="ignis-login-stage__caption">
                <?php if ($stageOperator !== ''): ?>
                    <span class="ignis-login-stage__org"><?= htmlspecialchars($stageOperator) ?></span>
                    <span class="ignis-login-stage__dot"></span>
                <?php endif; ?>
                <span class="ignis-login-stage__product">&#305;gn&#305;s</span>
            </p>
        </div>
    </div>
</aside>
