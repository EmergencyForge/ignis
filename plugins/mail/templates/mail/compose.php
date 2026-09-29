<?php
/**
 * View: Verfassen — Neu, Antworten, Allen antworten, Weiterleiten und
 * Entwurf bearbeiten teilen sich diese Ansicht. Sie läuft im breiten
 * Drawer (Link mit data-ignis-drawer; mail-compose.js hebt den Drawer auf
 * die breite Variante) und ohne JS als eigene Seite.
 *
 * `data-ignis-drawer-native`: drawer-form.js schickt das Formular nicht
 * selbst ab. Speichern, Senden, Verwerfen und Anhänge laufen über
 * mail-compose.js gegen die JSON-Routen des MailControllers. Der Entwurf
 * entsteht erst mit der ersten Änderung (POST), nicht beim Öffnen.
 *
 * @var string              $title
 * @var int|null            $draftId       null = noch kein Entwurf
 * @var string              $subject
 * @var array{to:list<array{value:string,label:string}>,cc:list<array{value:string,label:string}>,bcc:list<array{value:string,label:string}>} $recipients
 * @var array<string,mixed> $bodyJson
 * @var int|null            $inReplyTo
 * @var int|null            $forwardFrom   Mail, deren Anhänge beim Anlegen kopiert werden
 * @var list<string>        $forwardNames  nur zur Anzeige
 * @var list<\Plugin\Mail\Models\Attachment> $attachments
 */

$layout     = 'admin';
$bodyId     = 'mail';
$SITE_TITLE = $title;
$layoutHead = '<link rel="stylesheet" href="' . htmlspecialchars(asset('assets/dist/editor.css')) . '">';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$json       = static fn (mixed $value): string => htmlspecialchars((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES);
$values     = static fn (array $options): string => htmlspecialchars(implode(',', array_column($options, 'value')), ENT_QUOTES);
?>
<link rel="stylesheet" href="<?= htmlspecialchars(asset('assets/dist/editor.css')) ?>">
<form id="mail-compose-form" class="ignis-mail-compose" data-ignis-drawer-native
      data-draft-id="<?= $draftId !== null ? (int) $draftId : '' ?>"
      data-in-reply-to="<?= $inReplyTo !== null ? (int) $inReplyTo : '' ?>"
      data-forward-from="<?= $forwardFrom !== null ? (int) $forwardFrom : '' ?>"
      data-base="<?= htmlspecialchars($base) ?>"
      data-editor-src="<?= htmlspecialchars(asset('assets/dist/editor.iife.js')) ?>">
    <?= csrf_field() ?>

    <?php foreach (['to' => 'An', 'cc' => 'CC', 'bcc' => 'BCC'] as $field => $label): ?>
        <div class="ignis-field" id="mail-compose-<?= $field ?>-field"<?= $field === 'bcc' && $recipients['bcc'] === [] ? ' hidden' : '' ?>>
            <label class="ignis-field__label" id="mail-compose-<?= $field ?>-label"><?= $label ?></label>
            <div data-ignis-multi-select data-mail-recipients data-name="<?= $field ?>[]" aria-labelledby="mail-compose-<?= $field ?>-label"
                 data-placeholder="Name, Adresse oder Verteiler" data-empty-text="Kein Postfach gefunden"
                 data-options="<?= $json($recipients[$field]) ?>" data-value="<?= $values($recipients[$field]) ?>"></div>
        </div>
    <?php endforeach; ?>
    <?php if ($recipients['bcc'] === []): ?>
        <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm" id="mail-compose-bcc-toggle">BCC hinzufügen</button>
    <?php endif; ?>

    <div class="ignis-field">
        <label class="ignis-field__label" for="mail-compose-subject">Betreff</label>
        <input type="text" class="ignis-input" id="mail-compose-subject" name="subject" maxlength="255" value="<?= htmlspecialchars($subject) ?>">
    </div>

    <div class="ignis-field">
        <span class="ignis-field__label">Text</span>
        <div class="ignis-mail-compose__editor">
            <div id="mail-compose-toolbar"></div>
            <div id="mail-compose-editor" class="efe-page ignis-mail__editor" data-efe-content="<?= $json($bodyJson) ?>"></div>
        </div>
    </div>

    <div class="ignis-field">
        <span class="ignis-field__label">Anhänge</span>
        <?php if ($forwardNames !== []): ?>
            <p class="ignis-field__hint" id="mail-compose-forward-names"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> Wird beim ersten Speichern übernommen: <?= htmlspecialchars(implode(', ', $forwardNames)) ?></p>
        <?php endif; ?>
        <div class="ignis-file ignis-file--dropzone" data-ignis-file>
            <input type="file" id="mail-compose-files" class="ignis-file__input" multiple accept="image/png,image/jpeg,image/webp,image/gif,application/pdf,text/plain">
            <label for="mail-compose-files" class="ignis-file__zone">
                <span class="ignis-file__icon" aria-hidden="true"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                <span class="ignis-file__title">Dateien hierher ziehen oder <span class="ignis-file__link">auswählen</span></span>
                <span class="ignis-file__hint">Bilder, PDF oder Text, höchstens 5 MB je Datei und 10 MB je Mail</span>
            </label>
        </div>
        <ul class="ignis-preview__list" id="mail-compose-attachments">
            <?php foreach ($attachments as $attachment): ?>
                <li data-attachment-id="<?= (int) $attachment->id ?>">
                    <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                    <span class="ignis-preview__muted"><?= htmlspecialchars($attachment->original_name) ?></span>
                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost" data-mail-remove-attachment>Entfernen</button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="ignis-mail-compose__footer">
        <span id="mail-compose-status" role="status"></span>
        <button type="button" class="ignis-btn ignis-btn--ghost-danger" id="mail-compose-discard">
            <i class="fa-solid fa-trash" aria-hidden="true"></i> Verwerfen
        </button>
        <button type="submit" class="ignis-btn ignis-btn--primary" id="mail-compose-send">
            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Senden
        </button>
    </div>
</form>
<script type="module" src="<?= htmlspecialchars($base) ?>assets/js/ui/multi-select.js"></script>
<script src="<?= htmlspecialchars(asset('plugins/mail/assets/mail-compose.js')) ?>"></script>
