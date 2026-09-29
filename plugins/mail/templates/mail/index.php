<?php
/**
 * View: Mail-Arbeitsbereich — Ordner links, Liste in der Mitte, Lesebereich
 * rechts (ab 1200px immer sichtbar, siehe assets/plugin.css und mail.js).
 * Das Workbench-Muster aus dem UI-Paket (assets/js/ui/workbench.js) lädt
 * die gewählte Mail per GET …/{id}/preview, ↑/↓ wählen, Enter öffnet die
 * Mail als eigene Seite, Escape schließt. Ohne Sammelaktionen.
 *
 * Kein Schreiben auf GET: „gelesen“ setzt mail.js per POST, sobald der
 * Lesebereich die Mail zeigt (data-mail-mark-read).
 *
 * @var string                                   $folder
 * @var array<string, array{label:string, icon:string}> $folders
 * @var \Plugin\Mail\Models\Mailbox              $mailbox
 * @var list<\Plugin\Mail\Models\Delivery>       $deliveries
 * @var array<string,int>                        $unreadCounts
 * @var array<string,mixed>|null                 $readingPane  Variablen für _reading-pane.php
 * @var bool                                     $canManageLists
 */

$layout     = 'admin';
$bodyId     = 'mail';
$SITE_TITLE = 'Mail · ' . $folders[$folder]['label'];
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$isOutgoing = in_array($folder, ['sent', 'drafts'], true);

$snippet = static function (?string $html): string {
    $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $html)));

    return mb_strlen($text) > 90 ? mb_substr($text, 0, 89) . '…' : $text;
};
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow"><?= htmlspecialchars($mailbox->address) ?></p>
                    <h1>Mail</h1>
                    <p class="twplus-page-header__description">Internes Postfach. Keine Nachricht verlässt das System.</p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= htmlspecialchars($base . 'mail/compose') ?>" class="ignis-btn ignis-btn--primary" data-ignis-drawer>
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Neue Mail
                    </a>
                </div>
            </div>

            <div class="ignis-workbench ignis-mail" data-ignis-workbench data-mail-base="<?= htmlspecialchars($base) ?>"
                 data-ignis-preview-url="<?= htmlspecialchars($base . 'mail/' . $folder . '/{id}/preview') ?>">
                <nav class="ignis-mail__folders" aria-label="Ordner">
                    <ul>
                        <?php foreach ($folders as $key => $meta): ?>
                            <?php $unread = (int) ($unreadCounts[$key] ?? 0); ?>
                            <li>
                                <a href="<?= htmlspecialchars($base . 'mail/' . $key) ?>" class="ignis-mail__folder<?= $folder === $key ? ' is-active' : '' ?>"<?= $folder === $key ? ' aria-current="page"' : '' ?>>
                                    <i class="fa-solid <?= htmlspecialchars($meta['icon']) ?>" aria-hidden="true"></i>
                                    <span><?= htmlspecialchars($meta['label']) ?></span>
                                    <?php if ($unread > 0): ?>
                                        <span class="ignis-chip ignis-chip--count"><?= $unread ?><span class="ignis-sr-only"> ungelesen</span></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="ignis-mail__tools">
                        <a href="<?= htmlspecialchars($base . 'mail/signature') ?>" class="ignis-mail__tool" data-ignis-drawer>
                            <i class="fa-solid fa-signature" aria-hidden="true"></i> Signatur
                        </a>
                        <?php if ($canManageLists): ?>
                            <a href="<?= htmlspecialchars($base . 'mail/lists') ?>" class="ignis-mail__tool">
                                <i class="fa-solid fa-people-group" aria-hidden="true"></i> Verteiler
                            </a>
                        <?php endif; ?>
                    </div>
                </nav>

                <div class="twplus-table-card ignis-mail__list">
                    <?php if ($deliveries === []): ?>
                        <?php
                        $empty = [
                            'inbox'   => ['icon' => 'fa-inbox', 'title' => 'Noch keine Mails', 'text' => 'Hier landet, was andere Postfächer an dich schicken.'],
                            'sent'    => ['icon' => 'fa-paper-plane', 'title' => 'Noch nichts gesendet', 'text' => 'Gesendete Mails erscheinen hier.'],
                            'drafts'  => ['icon' => 'fa-file-pen', 'title' => 'Keine Entwürfe', 'text' => 'Eine angefangene Mail wird beim Schreiben automatisch als Entwurf gespeichert.'],
                            'archive' => ['icon' => 'fa-box-archive', 'title' => 'Das Archiv ist leer', 'text' => 'Archivierte Mails erscheinen hier.'],
                            'trash'   => ['icon' => 'fa-trash', 'title' => 'Der Papierkorb ist leer', 'text' => 'Gelöschte Mails liegen hier, bis du sie endgültig entfernst.'],
                        ][$folder] + ['variant' => 'sm', 'tone' => 'neutral'];
                        if ($folder === 'inbox' || $folder === 'drafts') {
                            $empty['actions'] = [['label' => 'Neue Mail', 'href' => $base . 'mail/compose', 'style' => 'secondary', 'icon' => 'fa-pen-to-square', 'attrs' => ['data-ignis-drawer' => '']]];
                        }
                        require dirname(__DIR__, 4) . '/templates/partials/empty.php';
                        ?>
                    <?php else: ?>
                        <div class="twplus-table-card__scroll">
                            <table class="ignis-table" data-ignis-mobile-table>
                                <thead>
                                    <tr>
                                        <th scope="col"><?= $isOutgoing ? 'An' : 'Von' ?></th>
                                        <th scope="col">Betreff</th>
                                        <th scope="col">Datum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deliveries as $delivery):
                                        $message = $delivery->message;
                                        $unread  = $delivery->read_at === null && !$isOutgoing;
                                        $party   = $isOutgoing
                                            ? implode(', ', (array) ($message->header_json['to'] ?? []))
                                            : $message->senderMailbox->display_name;
                                        $date    = $message->sent_at ?? $message->updated_at ?? $message->created_at;
                                        $href    = $base . 'mail/' . $folder . '/' . $message->id;
                                        $subject = $message->subject !== '' ? $message->subject : '(kein Betreff)';
                                        ?>
                                        <tr data-ignis-row="<?= (int) $message->id ?>" data-href="<?= htmlspecialchars($href) ?>" tabindex="0"<?= $unread ? ' class="is-unread"' : '' ?>>
                                            <td data-label="<?= $isOutgoing ? 'An' : 'Von' ?>" data-mobile-primary class="ignis-mail__party">
                                                <a href="<?= htmlspecialchars($href) ?>"><?= htmlspecialchars($party !== '' ? $party : '—') ?></a>
                                                <?php if ($unread): ?><span class="ignis-sr-only">(ungelesen)</span><?php endif; ?>
                                            </td>
                                            <td data-label="Betreff" data-mobile-context class="ignis-mail__subject">
                                                <span class="ignis-mail__subject-line">
                                                    <span class="ignis-mail__subject-text"><?= htmlspecialchars($subject) ?></span>
                                                    <?php if ($message->attachments->isNotEmpty()): ?>
                                                        <i class="fa-solid fa-paperclip" aria-hidden="true"></i><span class="ignis-sr-only">mit Anhang</span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="ignis-mail__snippet"><?= htmlspecialchars($snippet($message->body_html)) ?></span>
                                            </td>
                                            <td data-label="Datum" data-mobile-context class="ignis-mail__date">
                                                <?= $date !== null ? htmlspecialchars($date->format('d.m.Y H:i')) : '—' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="ignis-preview ignis-mail__pane" data-ignis-preview aria-live="polite">
                    <?php if ($readingPane !== null): ?>
                        <?php extract($readingPane, EXTR_SKIP); require __DIR__ . '/_reading-pane.php'; ?>
                    <?php else: ?>
                        <div class="ignis-preview__empty">
                            <i class="fa-solid fa-envelope-open" aria-hidden="true"></i>
                            <b>Keine Mail gewählt</b>
                            Zeile anklicken oder mit <kbd>↑</kbd> <kbd>↓</kbd> wählen, <kbd>Enter</kbd> öffnet die Mail.
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </div>

    <script src="<?= htmlspecialchars(asset('plugins/mail/assets/mail.js')) ?>"></script>
