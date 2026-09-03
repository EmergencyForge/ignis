<?php

/**
 * View: Protokoll nicht gefunden (eNOTF v2)
 *
 * @var string $enr
 */

use Plugin\EnotfV2\Helpers\EnotfV2Url;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

$__title = 'Protokoll nicht gefunden';
ob_start();
?>

<div class="max-w-xl mx-auto mt-12">
    <div class="ignis-card">
        <div class="ignis-card__body text-center">
            <h2 class="text-lg font-semibold mb-2">
                <i class="fa-solid fa-file-circle-question mr-2"></i>Protokoll nicht gefunden
            </h2>
            <p class="text-sm opacity-70 mb-5">
                Zur Einsatznummer <code class="font-mono">#<?= $e($enr) ?></code> existiert kein Protokoll.
            </p>
            <div class="flex flex-col gap-2">
                <a href="<?= $e(EnotfV2Url::page('overview')) ?>" class="ignis-btn ignis-btn--primary">
                    <i class="fa-solid fa-list mr-2"></i>Zur Übersicht
                </a>
                <a href="<?= $e(EnotfV2Url::page('create')) ?>" class="ignis-btn ignis-btn--ghost">
                    <i class="fa-solid fa-plus mr-2"></i>Neues Protokoll anlegen
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$__content = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
