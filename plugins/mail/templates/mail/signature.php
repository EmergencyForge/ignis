<?php
/**
 * View: Signatur des gewählten Postfachs, als Drawer aus der Ordnerleiste
 * (drawer-form.js schickt das Formular ab) oder als Seite. Der Editor mit
 * Platzhaltern und Vorschau ist mail/_signature-editor.php. Ohne eigene
 * steht die Standard-Signatur als Vorlage darin, mit ihren Platzhaltern.
 *
 * `mailbox_id` hält fest, für welches Postfach das Formular geöffnet wurde,
 * auch wenn ein zweiter Tab inzwischen ein anderes zeigt.
 *
 * @var \Plugin\Mail\Models\Mailbox                  $mailbox
 * @var array<string,mixed>                          $bodyJson
 * @var bool                                         $hasOwn
 * @var array<string,list<array<string,mixed>>>      $values   Werte für die Vorschau
 */

$layout     = 'admin';
$bodyId     = 'mail';
$SITE_TITLE = $mailbox->isGroup() ? 'Signatur: ' . $mailbox->display_name : 'Signatur';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
<link rel="stylesheet" href="<?= htmlspecialchars(asset('assets/dist/editor.css')) ?>">
<div class="twplus-page">
    <form method="post" action="<?= htmlspecialchars($base . 'mail/signature') ?>" id="mail-signature-form" class="ignis-card">
        <?= csrf_field() ?>
        <input type="hidden" name="mailbox_id" value="<?= (int) $mailbox->id ?>">
        <div class="ignis-card__body grid gap-3">
            <p class="ignis-field__hint">
                <?php if ($mailbox->isGroup()): ?>
                    Signatur des Gruppenpostfachs <b><?= htmlspecialchars($mailbox->display_name) ?></b>, gilt für alle Mitglieder. Die Platzhalter für den Absender füllt ignis mit den Angaben dessen, der gerade schreibt.
                <?php endif; ?>
                Steht in neuen Mails nach einer Leerzeile unter dem Text und lässt sich dort noch ändern oder löschen.
                <?= $hasOwn ? 'Leer speichern heißt: keine Signatur.' : 'Solange keine eigene gespeichert ist, gilt die Standard-Signatur; sie steht hier als Vorlage.' ?>
            </p>
            <div>
                <?php
                $sigPrefix = 'mail-signature';
                $sigField  = 'body_json';
                $sigLabel  = 'Signatur';
                $sigDoc    = $bodyJson;
                $sigValues = $values;
                require __DIR__ . '/_signature-editor.php';
                ?>
            </div>
        </div>
        <div class="ignis-card__footer" data-form-actions>
            <a href="<?= htmlspecialchars($base . 'mail') ?>" class="ignis-btn ignis-btn--ghost">Abbrechen</a>
            <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-check" aria-hidden="true"></i> Speichern</button>
        </div>
    </form>
</div>
