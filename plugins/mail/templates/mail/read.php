<?php
/**
 * View: eine Mail auf eigener Seite, in voller Breite
 * (MailController::messagePage()). Kopf, Text und Knöpfe kommen aus
 * _reading-pane.php wie in der Vorschau; mail.js liest die Daten für seine
 * Anfragen von `data-mail-root`.
 *
 * @var string                                          $folder
 * @var array<string, array{label:string, icon:string}> $folders
 * @var \Plugin\Mail\Models\Message                     $message
 * @var \Plugin\Mail\Models\Mailbox                     $mailbox
 */

$layout     = 'admin';
$bodyId     = 'mailread';
$SITE_TITLE = $message->subject !== '' ? $message->subject : '(kein Betreff)';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page ignis-mail-read" data-mail-root data-mail-base="<?= htmlspecialchars($base) ?>" data-mail-mailbox="<?= (int) $mailbox->id ?>">
            <a class="ignis-mail-read__back" href="<?= htmlspecialchars($base . 'mail/' . $folder . '?mailbox=' . (int) $mailbox->id) ?>">
                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i> <?= htmlspecialchars($folders[$folder]['label'] ?? 'Mail') ?>
            </a>
            <article class="ignis-mail-read__pane">
                <?php require __DIR__ . '/_reading-pane.php'; ?>
            </article>
        </div>
    </div>

    <script src="<?= htmlspecialchars(asset('plugins/mail/assets/mail.js')) ?>"></script>
