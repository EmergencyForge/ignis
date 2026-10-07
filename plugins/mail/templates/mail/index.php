<?php
/**
 * View: Mail-Arbeitsbereich, Ordner links, Liste in der Mitte, Lesebereich
 * rechts (ab 1200px immer sichtbar, siehe assets/plugin.css und mail.js).
 * Das Workbench-Muster aus dem UI-Paket (assets/js/ui/workbench.js) lädt
 * die gewählte Mail per GET …/{id}/preview, ↑/↓ wählen, Enter öffnet die
 * Mail als eigene Seite, Escape schließt. Ohne Sammelaktionen.
 *
 * Kein Schreiben auf GET: „gelesen“ setzt mail.js per POST, sobald der
 * Lesebereich die Mail zeigt (data-mail-mark-read).
 *
 * Die per URL gewählte Mail trägt in der Liste aria-selected="true"
 * (workbench.js pflegt das Attribut danach selbst). Der Lesebereich
 * bekommt seine Variablen in einem eigenen Scope; die Schleifenvariablen
 * der Liste dürfen ihn nicht überdecken.
 *
 * @var string                                   $folder
 * @var array<string, array{label:string, icon:string}> $folders
 * Darf das Konto mehrere Postfächer lesen (das eigene und
 * Gruppenpostfächer), steht über den Ordnern der Wechsel. Links und
 * Anfragen dieser Seite nennen das gewählte Postfach (`?postfach=`,
 * `data-mail-mailbox` für mail.js), damit ein zweiter Tab mit einem anderen
 * Postfach nichts durcheinanderbringt.
 *
 * @var \Plugin\Mail\Models\Mailbox              $mailbox      das gewählte Postfach
 * @var list<\Plugin\Mail\Models\Mailbox>        $mailboxes    alle, die das Konto lesen darf
 * @var array<int,int>                           $inboxUnread  Postfach-ID → ungelesen im Posteingang
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
$selectedId = $readingPane !== null ? (int) $readingPane['message']->id : null;
$inBox      = '?postfach=' . (int) $mailbox->id;

$snippet = static function (?string $html): string {
    $text = \Plugin\Mail\MailBodyRenderer::plainText($html);

    return mb_strlen($text) > 90 ? mb_substr($text, 0, 89) . '…' : $text;
};
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow"><?= htmlspecialchars($mailbox->kindLabel()) ?> · <?= htmlspecialchars($mailbox->address) ?></p>
                    <h1><?= $mailbox->isGroup() ? htmlspecialchars($mailbox->display_name) : 'Mail' ?></h1>
                    <p class="twplus-page-header__description"><?= $mailbox->isGroup() ? 'Alle Mitglieder sehen dieselben Mails und senden unter dieser Adresse.' : 'Internes Postfach. Keine Nachricht verlässt das System.' ?></p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= htmlspecialchars($base . 'mail/compose' . $inBox) ?>" class="ignis-btn ignis-btn--primary" data-ignis-drawer>
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Neue Mail
                    </a>
                </div>
            </div>

            <div class="ignis-workbench ignis-mail" data-ignis-workbench data-mail-base="<?= htmlspecialchars($base) ?>" data-mail-mailbox="<?= (int) $mailbox->id ?>"
                 data-ignis-preview-url="<?= htmlspecialchars($base . 'mail/' . $folder . '/{id}/preview' . $inBox) ?>">
                <nav class="ignis-mail__folders" aria-label="Ordner">
                    <?php if (count($mailboxes) > 1): ?>
                        <p class="ignis-field__label">Postfächer</p>
                        <ul aria-label="Postfach wechseln">
                            <?php foreach ($mailboxes as $box): ?>
                                <?php $isCurrent = $box->id === $mailbox->id; $boxUnread = (int) ($inboxUnread[$box->id] ?? 0); ?>
                                <li>
                                    <a href="<?= htmlspecialchars($base . 'mail/inbox?postfach=' . (int) $box->id) ?>" class="ignis-mail__folder<?= $isCurrent ? ' is-active' : '' ?>"<?= $isCurrent ? ' aria-current="true"' : '' ?> data-ignis-tooltip="<?= htmlspecialchars($box->kindLabel() . ': ' . $box->address) ?>">
                                        <i class="fa-solid <?= $box->isGroup() ? 'fa-users' : 'fa-user' ?>" aria-hidden="true"></i>
                                        <span><?= htmlspecialchars($box->isGroup() ? $box->display_name : 'Mein Postfach') ?><span class="ignis-sr-only">, <?= htmlspecialchars($box->kindLabel()) ?></span></span>
                                        <?php if ($boxUnread > 0): ?>
                                            <span class="ignis-chip ignis-chip--count"><?= $boxUnread ?><span class="ignis-sr-only"> ungelesen</span></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="ignis-field__label">Ordner</p>
                    <?php endif; ?>
                    <ul>
                        <?php foreach ($folders as $key => $meta): ?>
                            <?php $unread = (int) ($unreadCounts[$key] ?? 0); ?>
                            <li>
                                <a href="<?= htmlspecialchars($base . 'mail/' . $key . $inBox) ?>" class="ignis-mail__folder<?= $folder === $key ? ' is-active' : '' ?>"<?= $folder === $key ? ' aria-current="page"' : '' ?>>
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
                        <a href="<?= htmlspecialchars($base . 'mail/signature' . $inBox) ?>" class="ignis-mail__tool" data-ignis-drawer>
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
                            $empty['actions'] = [['label' => 'Neue Mail', 'href' => $base . 'mail/compose' . $inBox, 'style' => 'secondary', 'icon' => 'fa-pen-to-square', 'attrs' => ['data-ignis-drawer' => '']]];
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
                                        <th scope="col" class="ignis-table__actions"><span class="ignis-sr-only">Aktionen</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deliveries as $row):
                                        $rowMessage = $row->message;
                                        $rowId      = (int) $rowMessage->id;
                                        $unread     = $row->read_at === null && !$isOutgoing;
                                        $party      = $isOutgoing
                                            ? implode(', ', (array) ($rowMessage->header_json['to'] ?? []))
                                            : $rowMessage->senderMailbox->display_name;
                                        $date       = $rowMessage->sent_at ?? $rowMessage->updated_at ?? $rowMessage->created_at;
                                        $href       = $base . 'mail/' . $folder . '/' . $rowId . $inBox;
                                        $subject    = $rowMessage->subject !== '' ? $rowMessage->subject : '(kein Betreff)';
                                        ?>
                                        <tr data-ignis-row="<?= $rowId ?>" data-href="<?= htmlspecialchars($href) ?>" tabindex="0"<?= $unread ? ' class="is-unread"' : '' ?><?= $rowId === $selectedId ? ' aria-selected="true"' : '' ?>>
                                            <td data-label="<?= $isOutgoing ? 'An' : 'Von' ?>" data-mobile-primary class="ignis-mail__party">
                                                <a href="<?= htmlspecialchars($href) ?>"><?= htmlspecialchars($party !== '' ? $party : '-') ?></a>
                                                <?php if ($unread): ?><span class="ignis-sr-only">(ungelesen)</span><?php endif; ?>
                                            </td>
                                            <td data-label="Betreff" data-mobile-context class="ignis-mail__subject">
                                                <span class="ignis-mail__subject-line">
                                                    <span class="ignis-mail__subject-text"><?= htmlspecialchars($subject) ?></span>
                                                    <?php if ($row->flagged): ?>
                                                        <span class="ignis-mail__flag"><i class="fa-solid fa-flag" aria-hidden="true"></i><span class="ignis-sr-only">markiert</span></span>
                                                    <?php endif; ?>
                                                    <?php if ($rowMessage->attachments->isNotEmpty()): ?>
                                                        <i class="fa-solid fa-paperclip" aria-hidden="true"></i><span class="ignis-sr-only">mit Anhang</span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="ignis-mail__snippet"><?= htmlspecialchars($snippet($rowMessage->body_html)) ?></span>
                                            </td>
                                            <td data-label="Datum" data-mobile-context class="ignis-mail__date">
                                                <?= $date !== null ? htmlspecialchars($date->format('d.m.Y H:i')) : '-' ?>
                                            </td>
                                            <td class="ignis-table__actions">
                                                <button type="button" class="ignis-btn ignis-btn--secondary ignis-btn--sm" data-ignis-preview-open>Vorschau</button>
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
                        <?php (static function (array $pane): void {
                            extract($pane);
                            require __DIR__ . '/_reading-pane.php';
                        })($readingPane); ?>
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
