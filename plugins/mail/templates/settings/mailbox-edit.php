<?php
/**
 * View: Adresse eines Postfachs ändern (`mail.admin`, Domain nur mit
 * `mail.domain.choose`). Der Inhalt des Postfachs kommt hier nicht vor.
 *
 * @var \Plugin\Mail\Models\Mailbox        $mailbox
 * @var string|null                        $owner     Name des Mitarbeiters, null = gelöscht
 * @var array{local:string, domain:string} $form
 * @var list<string>                       $domains   wählbar, die bisherige eingeschlossen
 * @var bool                               $canChoose
 * @var bool                               $isOwn     das eigene Postfach: nur Hinweis, kein Speichern
 */

use Plugin\Mail\MailAddressRules;

$layout     = 'admin';
$bodyId     = 'settings';
$SITE_TITLE = 'Adresse ändern';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item"><a href="<?= $base ?>settings/mail/mailboxes">Postfächer</a></span> <span class="ignis-breadcrumb__item is-active"><?= htmlspecialchars($mailbox->address) ?></span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1>Adresse ändern</h1>
                    <p class="twplus-page-header__description">
                        Postfach von <?= htmlspecialchars($owner ?? $mailbox->display_name) ?>, bisher <span class="ignis-mono"><?= htmlspecialchars($mailbox->address) ?></span>.
                        Die alte Adresse nimmt danach keine Mails mehr an und bleibt für dieses Postfach reserviert: kein anderes Postfach und kein Verteiler bekommt sie.
                    </p>
                </div>
            </div>

            <?php if ($isOwn): ?>
                <div class="ignis-alert ignis-alert--info mb-4" role="note">
                    <i class="fa-solid fa-circle-info ignis-alert__icon" aria-hidden="true"></i>
                    <div class="ignis-alert__body">Das ist dein eigenes Postfach. Seine Adresse ändert eine andere Person mit Postfachverwaltung.</div>
                </div>
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
                            <select id="mailbox-domain" name="domain" class="ignis-input"<?= $isOwn ? ' disabled' : '' ?>>
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
        </div>
    </div>
