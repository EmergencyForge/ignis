<?php
/**
 * View: Gruppenpostfach anlegen (`mail.admin`, andere Domain nur mit
 * `mail.domain.choose`). Mitglieder kommen danach, auf der Bearbeiten-Seite.
 *
 * @var array{name:string, local:string, domain:string} $form
 * @var list<string>                                     $domains  Standard-Domain zuerst
 * @var bool                                             $canChoose
 */

use Plugin\Mail\MailAddressRules;

$layout     = 'admin';
$bodyId     = 'settings';
$SITE_TITLE = 'Gruppenpostfach anlegen';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item"><a href="<?= $base ?>settings/mail/mailboxes">Postfächer</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Gruppenpostfach anlegen</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1>Gruppenpostfach anlegen</h1>
                    <p class="twplus-page-header__description">
                        Ein Postfach für eine Wache, eine Abteilung oder ein Team. Alle Mitglieder lesen dieselben Mails und senden unter seiner Adresse.
                        Soll eine Adresse Mails nur an mehrere Postfächer weitergeben, ist ein <a href="<?= $base ?>mail/lists">Verteiler</a> das Richtige.
                    </p>
                </div>
            </div>

            <form method="post" action="<?= $base ?>settings/mail/mailboxes/groups" class="ignis-card">
                <?= csrf_field() ?>
                <div class="ignis-card__body grid gap-3">
                    <div>
                        <label for="group-name" class="ignis-field__label">Name <span class="ignis-field__required">*</span></label>
                        <input type="text" id="group-name" name="name" class="ignis-input" required maxlength="150" placeholder="Leitstelle" value="<?= htmlspecialchars($form['name']) ?>">
                        <p class="ignis-field__hint">So erscheint das Postfach als Absender und im Adressbuch.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="group-local" class="ignis-field__label">Adresse <span class="ignis-field__required">*</span></label>
                            <input type="text" id="group-local" name="local" class="ignis-input ignis-mono" required maxlength="<?= MailAddressRules::MAX_LOCAL_LENGTH ?>" placeholder="leitstelle" autocapitalize="none" spellcheck="false" value="<?= htmlspecialchars($form['local']) ?>">
                            <p class="ignis-field__hint">Der Teil vor dem @: Kleinbuchstaben, Ziffern, Punkt, Bindestrich, Unterstrich.</p>
                        </div>
                        <div>
                            <?php if ($canChoose): ?>
                                <label for="group-domain" class="ignis-field__label">Domain</label>
                                <select id="group-domain" name="domain" class="ignis-input" data-custom-dropdown="true">
                                    <?php foreach ($domains as $domain): ?>
                                        <option value="<?= htmlspecialchars($domain) ?>"<?= $form['domain'] === $domain ? ' selected' : '' ?>>@<?= htmlspecialchars($domain) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <label for="group-domain-fixed" class="ignis-field__label">Domain</label>
                                <input type="text" id="group-domain-fixed" class="ignis-input" value="@<?= htmlspecialchars($form['domain']) ?>" readonly>
                                <p class="ignis-field__hint">Eine andere Domain wählt, wer das Recht „Domain eines Postfachs wählen“ hat.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="ignis-card__footer" data-form-actions>
                    <a href="<?= $base ?>settings/mail/mailboxes" class="ignis-btn ignis-btn--ghost">Abbrechen</a>
                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Anlegen</button>
                </div>
            </form>
        </div>
    </div>
