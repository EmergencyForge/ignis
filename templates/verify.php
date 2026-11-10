<?php
/**
 * Öffentliche Echtheitsprüfung, im Aufbau der Anmeldeseite.
 *
 * @var string                     $input   Eingabe wie getippt
 * @var string|null                $state   null (noch nichts geprüft), malformed, limited, unknown, revoked, expired, valid
 * @var string|null                $date    Widerrufs- oder Ablaufdatum
 * @var array<string,string>|null  $details nur bei valid
 */

use App\Http\Controllers\VerifyController;

$date ??= null;
$details ??= null;
?>
<!DOCTYPE html>
<html lang="de" class="h-full">

<head>
    <?php
    $SITE_TITLE = 'Echtheit prüfen';
    include dirname(__DIR__) . '/assets/components/_base/admin/head.php'; ?>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/preferences.js"></script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/login-stage.js"></script>
</head>

<body id="alogin" class="relative" data-ui-skin="core">
    <div class="twplus-login">
        <section class="twplus-login__panel">
            <div class="twplus-login__content">
                <?php require __DIR__ . '/partials/login-brand.php'; ?>
                <div class="twplus-login__well">
                    <h1 class="twplus-login__title">Echtheit prüfen</h1>
                    <p class="twplus-login__lead">Gib den Prüfcode von der Urkunde ein. Er steht unten auf dem Dokument, meist neben einem QR-Code.</p>

                    <form method="get" action="<?= BASE_PATH ?>verify">
                        <label class="ignis-field__label" for="verify-code">Prüfcode</label>
                        <input type="text" class="ignis-input font-mono uppercase" id="verify-code" name="code" required
                               maxlength="20" placeholder="K7QM-4X9T-P2" autocomplete="off" autocapitalize="characters" spellcheck="false"
                               value="<?= htmlspecialchars($input) ?>"<?= $state === 'malformed' ? ' aria-invalid="true" aria-describedby="verify-error"' : '' ?>>
                        <?php if ($state === 'malformed'): ?>
                            <p class="ignis-field__error" id="verify-error">Der Code stimmt so nicht. Prüf ihn auf Tippfehler: er hat zehn Zeichen, ohne 0, O, 1, I und L.</p>
                        <?php endif; ?>
                        <div class="twplus-login__actions mt-3">
                            <button type="submit" class="ignis-btn ignis-btn--primary ignis-btn--lg block w-full">Prüfen</button>
                        </div>
                    </form>

                    <?php if ($state === 'valid' && $details !== null): ?>
                        <div class="mt-4" role="status">
                            <div class="ignis-alert ignis-alert--ok">
                                <i class="fa-solid fa-circle-check ignis-alert__icon" aria-hidden="true"></i>
                                <div class="ignis-alert__body"><strong>Gültig.</strong> Diese Urkunde ist echt.</div>
                            </div>
                            <dl class="ignis-detail__dl mt-3">
                                <?php foreach ($details as $label => $value): ?>
                                    <dt><?= htmlspecialchars($label) ?></dt>
                                    <dd><?= htmlspecialchars($value) ?></dd>
                                <?php endforeach; ?>
                            </dl>
                        </div>
                    <?php elseif ($state === 'revoked'): ?>
                        <div class="ignis-alert ignis-alert--danger mt-4" role="status">
                            <i class="fa-solid fa-ban ignis-alert__icon" aria-hidden="true"></i>
                            <div class="ignis-alert__body"><strong>Widerrufen am <?= htmlspecialchars((string) $date) ?>.</strong> Diese Urkunde gilt nicht mehr.</div>
                        </div>
                    <?php elseif ($state === 'expired'): ?>
                        <div class="ignis-alert ignis-alert--warn mt-4" role="status">
                            <i class="fa-solid fa-clock ignis-alert__icon" aria-hidden="true"></i>
                            <div class="ignis-alert__body"><strong>Abgelaufen am <?= htmlspecialchars((string) $date) ?>.</strong> Diese Urkunde gilt nicht mehr.</div>
                        </div>
                    <?php elseif ($state === 'unknown'): ?>
                        <div class="ignis-alert ignis-alert--danger mt-4" role="status">
                            <i class="fa-solid fa-circle-xmark ignis-alert__icon" aria-hidden="true"></i>
                            <div class="ignis-alert__body"><strong>Ungültig.</strong> Zu diesem Prüfcode gibt es keine Urkunde.</div>
                        </div>
                    <?php elseif ($state === 'limited'): ?>
                        <div class="ignis-alert ignis-alert--warn mt-4" role="status">
                            <i class="fa-solid fa-hourglass-half ignis-alert__icon" aria-hidden="true"></i>
                            <div class="ignis-alert__body"><strong>Zu viele Abfragen.</strong> Von deinem Anschluss kamen in der letzten Stunde <?= VerifyController::LIMIT_PER_HOUR ?> Abfragen. Versuch es später noch einmal.</div>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="twplus-login__foot"><span>&#305;gn&#305;s ·</span><span>EmergencyForge</span></p>
            </div>
        </section>
        <?php require __DIR__ . '/partials/login-stage.php'; ?>
    </div>
</body>

</html>
