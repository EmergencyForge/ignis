<?php
/**
 * Öffentliche Prüfseite für Urkunden, im Aufbau der Anmeldeseite.
 *
 * @var string                $input  Eingabe, bei gültiger Form formatiert
 * @var string|null           $state  null (noch nichts geprüft), limited, unknown, revoked, expired, valid
 * @var string                $date   Widerrufs- oder Ablaufdatum
 * @var array<string,string>  $facts  Angaben von der Urkunde
 * @var bool                  $pdf    Urkunde liegt als PDF vor und darf gezeigt werden
 */

use App\Http\Controllers\VerifyController;

$e = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de" class="h-full">

<head>
    <?php
    $SITE_TITLE = 'Urkunde prüfen';
    include dirname(__DIR__) . '/assets/components/_base/admin/head.php'; ?>
    <meta name="robots" content="noindex">
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/preferences.js"></script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/login-stage.js"></script>
</head>

<body id="alogin" class="relative" data-ui-skin="core">
    <div class="twplus-login">
        <section class="twplus-login__panel">
            <div class="twplus-login__content">
                <?php require __DIR__ . '/partials/login-brand.php'; ?>
                <div class="twplus-login__well">
                    <h1 class="twplus-login__title">Urkunde prüfen</h1>
                    <p class="twplus-login__lead">Mit dem Prüfcode lässt sich feststellen, ob eine Urkunde echt ist und noch gilt.</p>

                    <?php if ($state !== null): ?>
                        <div class="mb-4" role="status">
                            <?php if ($state === 'valid'): ?>
                                <div class="ignis-alert ignis-alert--ok"><i class="fa-solid fa-circle-check ignis-alert__icon" aria-hidden="true"></i><div class="ignis-alert__body"><strong>Gültig.</strong> Zu diesem Prüfcode ist eine gültige Urkunde hinterlegt.</div></div>
                            <?php elseif ($state === 'expired'): ?>
                                <div class="ignis-alert ignis-alert--warn"><i class="fa-solid fa-hourglass-end ignis-alert__icon" aria-hidden="true"></i><div class="ignis-alert__body"><strong>Abgelaufen.</strong> Die Urkunde ist echt, ihre Gültigkeit endete am <?= $e($date) ?>.</div></div>
                            <?php elseif ($state === 'revoked'): ?>
                                <div class="ignis-alert ignis-alert--danger"><i class="fa-solid fa-ban ignis-alert__icon" aria-hidden="true"></i><div class="ignis-alert__body"><strong>Widerrufen.</strong> Die Urkunde wurde am <?= $e($date) ?> widerrufen und ist nicht mehr gültig.</div></div>
                            <?php elseif ($state === 'limited'): ?>
                                <div class="ignis-alert ignis-alert--warn"><i class="fa-solid fa-clock ignis-alert__icon" aria-hidden="true"></i><div class="ignis-alert__body"><strong>Zu viele Abfragen.</strong> Von diesem Anschluss sind in der letzten Stunde <?= VerifyController::LIMIT_PER_HOUR ?> Prüfungen eingegangen. Bitte später erneut versuchen.</div></div>
                            <?php else: ?>
                                <div class="ignis-alert ignis-alert--warn"><i class="fa-solid fa-triangle-exclamation ignis-alert__icon" aria-hidden="true"></i><div class="ignis-alert__body"><strong>Prüfcode unbekannt.</strong> Zu diesem Prüfcode ist keine Urkunde hinterlegt. Bitte die Eingabe prüfen.</div></div>
                            <?php endif; ?>
                            <?php if ($facts !== []): ?>
                                <dl class="ignis-detail__dl mt-3">
                                    <?php foreach ($facts as $label => $value): ?>
                                        <dt><?= $e($label) ?></dt>
                                        <dd><?= $e($value) ?></dd>
                                    <?php endforeach; ?>
                                </dl>
                            <?php endif; ?>
                            <?php if ($pdf): ?>
                                <a class="ignis-btn ignis-btn--secondary ignis-btn--block mt-3" href="<?= $e(BASE_PATH . 'verify/pdf?code=' . rawurlencode($input)) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Urkunde ansehen</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form method="get" action="<?= BASE_PATH ?>verify">
                        <label class="ignis-field__label" for="verify-code">Prüfcode</label>
                        <input type="text" class="ignis-input font-mono uppercase" id="verify-code" name="code" required aria-describedby="verify-hint"
                               maxlength="20" placeholder="XXXX-XXXX-XX" autocomplete="off" autocapitalize="characters" spellcheck="false"
                               value="<?= $e($input) ?>">
                        <p class="ignis-field__hint" id="verify-hint">Der Prüfcode steht unten auf der Urkunde neben dem QR-Code.</p>
                        <div class="twplus-login__actions mt-3">
                            <button type="submit" class="ignis-btn ignis-btn--primary ignis-btn--lg block w-full">Prüfen</button>
                        </div>
                    </form>
                </div>
                <p class="twplus-login__foot"><span>&#305;gn&#305;s ·</span><span>EmergencyForge</span></p>
            </div>
        </section>
        <?php require __DIR__ . '/partials/login-stage.php'; ?>
    </div>
</body>

</html>
