<?php
/**
 * Partial: Lesebereich einer Mail. Kommt per GET …/{id}/preview in den
 * Arbeitsbereich (workbench.js) und steht auf der Einzelseite
 * /mail/{folder}/{id} (schmale Schirme, ohne JS).
 *
 * Die Knöpfe tragen data-mail-action; mail.js wertet sie per Klick-
 * Delegation am document aus, weil dieses Partial per innerHTML getauscht
 * wird und ein eingebettetes <script> dabei nicht liefe.
 *
 * BCC: die Absenderkopie sieht die ganze Liste, ein BCC-Empfänger nur
 * sich selbst, alle anderen nichts (MailController::visibleHeader()).
 *
 * @var string                                  $folder
 * @var \Plugin\Mail\Models\Message             $message
 * @var bool                                    $isDraft
 * @var array{to:list<string>,cc:list<string>,bcc:list<string>} $header
 * @var string                                  $bodyHtml   Momentaufnahme aus dem Renderer
 * @var bool                                    $needsMarkRead
 * @var bool                                    $flagged     die eigene Kopie ist markiert
 */

$base   = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$sender = $message->senderMailbox;
$id     = (int) $message->id;
$list   = static fn (array $addresses): string => $addresses === [] ? '—' : htmlspecialchars(implode(', ', $addresses));
?>
<div class="ignis-mail__reading" data-mail-folder="<?= htmlspecialchars($folder) ?>"<?= $needsMarkRead ? ' data-mail-mark-read="' . $id . '"' : '' ?>>
    <h3 class="ignis-preview__title"><?= htmlspecialchars($message->subject !== '' ? $message->subject : '(kein Betreff)') ?></h3>

    <dl class="ignis-preview__dl">
        <dt>Von</dt>
        <dd><?= htmlspecialchars($sender->display_name) ?> <span class="ignis-preview__muted">&lt;<?= htmlspecialchars($sender->address) ?>&gt;</span></dd>
        <dt>An</dt>
        <dd><?= $list($header['to']) ?></dd>
        <?php if ($header['cc'] !== []): ?>
            <dt>CC</dt>
            <dd><?= $list($header['cc']) ?></dd>
        <?php endif; ?>
        <?php if ($header['bcc'] !== []): ?>
            <dt>BCC</dt>
            <dd><?= $list($header['bcc']) ?></dd>
        <?php endif; ?>
        <dt>Datum</dt>
        <dd><?= $message->sent_at !== null ? htmlspecialchars($message->sent_at->format('d.m.Y H:i')) : 'Entwurf' ?></dd>
    </dl>

    <div class="ignis-preview__section ignis-mail__body"><?= $bodyHtml ?></div>

    <?php if ($message->attachments->isNotEmpty()): ?>
        <div class="ignis-preview__section">
            <h4>Anhänge</h4>
            <ul class="ignis-preview__list">
                <?php foreach ($message->attachments as $attachment): ?>
                    <li>
                        <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                        <a href="<?= htmlspecialchars($base . 'mail/attachments/' . $attachment->id) ?>" download><?= htmlspecialchars($attachment->original_name) ?></a>
                        <span class="ignis-preview__muted"><?= htmlspecialchars(number_format($attachment->size / 1024, 0, ',', '.')) ?> KB</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="ignis-preview__actions">
        <?php if ($isDraft): ?>
            <a href="<?= htmlspecialchars($base . 'mail/compose/draft/' . $id) ?>" class="ignis-btn ignis-btn--sm ignis-btn--primary" data-ignis-drawer>
                <i class="fa-solid fa-pen" aria-hidden="true"></i> Weiter bearbeiten
            </a>
            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger" data-mail-action="discard" data-mail-id="<?= $id ?>">
                <i class="fa-solid fa-trash" aria-hidden="true"></i> Verwerfen
            </button>
        <?php else: ?>
            <a href="<?= htmlspecialchars($base . 'mail/compose/reply/' . $id) ?>" class="ignis-btn ignis-btn--sm ignis-btn--primary" data-ignis-drawer>
                <i class="fa-solid fa-reply" aria-hidden="true"></i> Antworten
            </a>
            <?php if (count($header['to']) + count($header['cc']) > 1): ?>
                <a href="<?= htmlspecialchars($base . 'mail/compose/reply-all/' . $id) ?>" class="ignis-btn ignis-btn--sm ignis-btn--secondary" data-ignis-drawer>
                    <i class="fa-solid fa-reply-all" aria-hidden="true"></i> Allen antworten
                </a>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($base . 'mail/compose/forward/' . $id) ?>" class="ignis-btn ignis-btn--sm ignis-btn--secondary" data-ignis-drawer>
                <i class="fa-solid fa-share" aria-hidden="true"></i> Weiterleiten
            </a>
            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost" data-mail-action="flag" data-mail-flagged="<?= $flagged ? '1' : '0' ?>" data-mail-id="<?= $id ?>" aria-pressed="<?= $flagged ? 'true' : 'false' ?>">
                <i class="fa-solid fa-flag" aria-hidden="true"></i> Markieren
            </button>
            <?php if ($folder === 'trash'): ?>
                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--secondary" data-mail-action="move" data-mail-target="restore" data-mail-id="<?= $id ?>">
                    <i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i> Wiederherstellen
                </button>
                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger" data-mail-action="delete" data-mail-id="<?= $id ?>">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i> Endgültig löschen
                </button>
            <?php else: ?>
                <?php if ($folder !== 'archive'): ?>
                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost" data-mail-action="move" data-mail-target="archive" data-mail-id="<?= $id ?>">
                        <i class="fa-solid fa-box-archive" aria-hidden="true"></i> Archivieren
                    </button>
                <?php endif; ?>
                <?php if ($folder === 'inbox' || $folder === 'archive'): ?>
                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost" data-mail-action="unread" data-mail-id="<?= $id ?>">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i> Als ungelesen
                    </button>
                <?php endif; ?>
                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger" data-mail-action="move" data-mail-target="trash" data-mail-id="<?= $id ?>">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i> In den Papierkorb
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
