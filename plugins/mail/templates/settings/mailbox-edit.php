<?php
/**
 * View: Adresse eines Postfachs ändern (`mail.admin`, Domain nur mit
 * `mail.domain.choose`), bei einem Gruppenpostfach dazu Name und
 * Mitglieder. Der Inhalt des Postfachs kommt hier nicht vor.
 *
 * @var \Plugin\Mail\Models\Mailbox        $mailbox
 * @var string|null                        $owner     Name des Mitarbeiters, null = gelöscht
 * @var array{local:string, domain:string} $form
 * @var list<string>                       $domains   wählbar, die bisherige eingeschlossen
 * @var bool                               $canChoose
 * @var bool                               $isOwn     das eigene Postfach: nur Hinweis, kein Speichern
 * @var string|null                        $account   Benutzername des Kontos, dem das Postfach gehört
 * @var array<int,string>                  $candidates Konten, denen es gehören darf (Id => Benutzername)
 * @var list<array{id:int, name:string, active:bool}> $members  Gruppenpostfach: Mitglieder
 * @var array<int,string>                  $memberCandidates  Gruppenpostfach: Konten zum Aufnehmen (Id => Name)
 */

use Plugin\Mail\MailAddressRules;

$layout     = 'admin';
$bodyId     = 'settings';
$isGroup    = $mailbox->isGroup();
$SITE_TITLE = $isGroup ? $mailbox->display_name : 'Adresse ändern';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item"><a href="<?= $base ?>settings/mail/mailboxes">Postfächer</a></span> <span class="ignis-breadcrumb__item" aria-current="page"><?= htmlspecialchars($mailbox->address) ?></span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1><?= $isGroup ? htmlspecialchars($mailbox->display_name) : 'Adresse ändern' ?></h1>
                    <p class="twplus-page-header__description">
                        <?php if ($isGroup): ?>
                            Gruppenpostfach <span class="ignis-mono"><?= htmlspecialchars($mailbox->address) ?></span><?= $mailbox->locked ? ', gesperrt' : '' ?>. Alle Mitglieder lesen dieselben Mails und senden unter dieser Adresse.
                        <?php else: ?>
                            Postfach von <?= htmlspecialchars($owner ?? $mailbox->display_name) ?>, bisher <span class="ignis-mono"><?= htmlspecialchars($mailbox->address) ?></span>.
                        <?php endif; ?>
                        Die alte Adresse nimmt nach einer Änderung keine Mails mehr an und bleibt für dieses Postfach reserviert: kein anderes Postfach und kein Verteiler bekommt sie.
                    </p>
                </div>
            </div>

            <?php if ($isOwn): ?>
                <div class="ignis-alert ignis-alert--info mb-4" role="note">
                    <i class="fa-solid fa-circle-info ignis-alert__icon" aria-hidden="true"></i>
                    <div class="ignis-alert__body">Das ist dein eigenes Postfach. Seine Adresse ändert eine andere Person mit Postfachverwaltung.</div>
                </div>
            <?php endif; ?>

            <?php if ($isGroup): ?>
            <form method="post" action="<?= $base ?>settings/mail/mailboxes/<?= (int) $mailbox->id ?>/name" class="ignis-card mb-4">
                <?= csrf_field() ?>
                <div class="ignis-card__header">
                    <h2 class="ignis-card__title">Name</h2>
                </div>
                <div class="ignis-card__body">
                    <label for="mailbox-name" class="ignis-field__label">Name <span class="ignis-field__required">*</span></label>
                    <input type="text" id="mailbox-name" name="name" class="ignis-input" required maxlength="150" value="<?= htmlspecialchars($mailbox->display_name) ?>">
                    <p class="ignis-field__hint">So erscheint das Gruppenpostfach als Absender und im Adressbuch, etwa „Leitstelle“ oder „Wache 1“.</p>
                </div>
                <div class="ignis-card__footer" data-form-actions>
                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-check" aria-hidden="true"></i> Umbenennen</button>
                </div>
            </form>
            <?php endif; ?>

            <form method="post" action="<?= $base ?>settings/mail/mailboxes/<?= (int) $mailbox->id ?>" class="ignis-card">
                <?= csrf_field() ?>
                <div class="ignis-card__body grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="mailbox-local" class="ignis-field__label">Adresse <span class="ignis-field__required">*</span></label>
                        <input type="text" id="mailbox-local" name="local" class="ignis-input ignis-mono" required maxlength="<?= MailAddressRules::MAX_LOCAL_LENGTH ?>" autocapitalize="none" spellcheck="false" value="<?= htmlspecialchars($form['local']) ?>"<?= $isOwn ? ' disabled' : '' ?>>
                        <p class="ignis-field__hint">Der Teil vor dem @: Kleinbuchstaben, Ziffern, Punkt, Bindestrich, Unterstrich.</p>
                    </div>
                    <div>
                        <?php if ($canChoose): ?>
                            <label for="mailbox-domain" class="ignis-field__label">Domain</label>
                            <select id="mailbox-domain" name="domain" class="ignis-input" data-custom-dropdown="true"<?= $isOwn ? ' disabled' : '' ?>>
                                <?php foreach ($domains as $domain): ?>
                                    <option value="<?= htmlspecialchars($domain) ?>"<?= $form['domain'] === $domain ? ' selected' : '' ?>>@<?= htmlspecialchars($domain) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="ignis-field__hint">Zur Wahl stehen die Standard-Domain und die erlaubten Domains aus den Mail-Einstellungen.</p>
                        <?php else: ?>
                            <label for="mailbox-domain-fixed" class="ignis-field__label">Domain</label>
                            <input type="text" id="mailbox-domain-fixed" class="ignis-input" value="@<?= htmlspecialchars($mailbox->domain) ?>" readonly>
                            <p class="ignis-field__hint">Die Domain wechselt nur, wer das Recht „Domain eines Postfachs wählen“ hat.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ignis-card__footer" data-form-actions>
                    <a href="<?= $base ?>settings/mail/mailboxes" class="ignis-btn ignis-btn--ghost">Abbrechen</a>
                    <button type="submit" class="ignis-btn ignis-btn--primary"<?= $isOwn ? ' disabled' : '' ?>><i class="fa-solid fa-check" aria-hidden="true"></i> Speichern</button>
                </div>
            </form>

            <?php if ($isGroup): ?>
            <div class="ignis-card mt-4">
                <div class="ignis-card__header">
                    <h2 class="ignis-card__title">Mitglieder</h2>
                </div>
                <div class="ignis-card__body grid gap-3">
                    <p class="ignis-field__hint">Mitglieder sehen das Gruppenpostfach in Mail neben ihrem eigenen und schreiben in seinem Namen. Sie brauchen das Recht „Mail nutzen“. Dich selbst nimmt eine andere Person mit Postfachverwaltung auf.</p>
                    <?php if ($members === []): ?>
                        <?php $empty = ['variant' => 'sm', 'tone' => 'neutral', 'icon' => 'fa-users', 'title' => 'Noch keine Mitglieder', 'text' => 'Ohne Mitglieder liest niemand dieses Postfach.']; require dirname(__DIR__, 4) . '/templates/partials/empty.php'; ?>
                    <?php else: ?>
                        <ul class="ignis-preview__list">
                            <?php foreach ($members as $member): ?>
                                <li>
                                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                                    <span><?= htmlspecialchars($member['name']) ?></span>
                                    <?php if (!$member['active']): ?><span class="ignis-chip ignis-chip--secondary">Konto inaktiv</span><?php endif; ?>
                                    <form method="post" action="<?= $base ?>settings/mail/mailboxes/<?= (int) $mailbox->id ?>/members/<?= (int) $member['id'] ?>/delete" class="inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger" aria-label="<?= htmlspecialchars($member['name']) ?> entfernen">Entfernen</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <form method="post" action="<?= $base ?>settings/mail/mailboxes/<?= (int) $mailbox->id ?>/members" class="grid gap-3">
                        <?= csrf_field() ?>
                        <div>
                            <label for="mailbox-member" class="ignis-field__label">Konto aufnehmen</label>
                            <select id="mailbox-member" name="user_id" class="ignis-input" data-custom-dropdown="true" required>
                                <option value="">Konto wählen</option>
                                <?php foreach ($memberCandidates as $userId => $name): ?>
                                    <option value="<?= (int) $userId ?>"><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Aufnehmen</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <form method="post" action="<?= $base ?>settings/mail/mailboxes/<?= (int) $mailbox->id ?>/account" class="ignis-card mt-4">
                <?= csrf_field() ?>
                <div class="ignis-card__header">
                    <h2 class="ignis-card__title">Konto zuordnen</h2>
                </div>
                <div class="ignis-card__body grid gap-3">
                    <p class="ignis-field__hint">
                        Das Postfach gehört fest einem Konto<?= $account !== null ? ', zurzeit <b>' . htmlspecialchars($account) . '</b>' : '; zurzeit keinem' ?>.
                        Eine geänderte Discord-ID in der Personalakte hängt es nicht um. Zur Wahl stehen aktive Konten mit der Discord-ID des Mitarbeiters, die noch kein Postfach haben; das bisherige Konto bekommt eine Benachrichtigung.
                    </p>
                    <div>
                        <label for="mailbox-account" class="ignis-field__label">Konto</label>
                        <select id="mailbox-account" name="user_id" class="ignis-input" data-custom-dropdown="true"<?= $isOwn ? ' disabled' : '' ?>>
                            <option value="">Keinem Konto zuordnen</option>
                            <?php if ($mailbox->user_id !== null && !isset($candidates[$mailbox->user_id])): ?>
                                <option value="<?= (int) $mailbox->user_id ?>" selected><?= htmlspecialchars($account ?? ('Konto #' . $mailbox->user_id)) ?> (bisher)</option>
                            <?php endif; ?>
                            <?php foreach ($candidates as $userId => $username): ?>
                                <option value="<?= (int) $userId ?>"<?= $userId === $mailbox->user_id ? ' selected' : '' ?>><?= htmlspecialchars($username) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($candidates === []): ?>
                            <p class="ignis-field__hint">Kein passendes Konto: die Discord-ID des Mitarbeiters gehört zu keinem aktiven Konto ohne Postfach.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ignis-card__footer" data-form-actions>
                    <button type="submit" class="ignis-btn ignis-btn--secondary"<?= $isOwn ? ' disabled' : '' ?>><i class="fa-solid fa-user-check" aria-hidden="true"></i> Zuordnung speichern</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
