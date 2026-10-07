<?php
/**
 * View: Mail-Einstellungen (`mail.admin`), Standard-Domain, erlaubte
 * Domains, Adressmuster, Sendepause und Standard-Signatur. Domain und Muster gelten
 * für neue Postfächer; bestehende Adressen ändert die Postfachverwaltung.
 *
 * Die Standard-Signatur steht im Editor mit den Platzhaltern aus
 * SignatureTemplate::catalog() und einer Vorschau mit den Angaben des
 * angemeldeten Kontos (mail/_signature-editor.php); der Editor schreibt
 * sein JSON ins versteckte Feld `signature`.
 *
 * @var array{domain:string, pattern:string, allowed:string, signature:array<string,mixed>, cooldown:string} $form
 * @var array<string,list<array<string,mixed>>> $previewValues  Werte für die Vorschau
 */

$layout        = 'admin';
$bodyId        = 'settings';
$SITE_TITLE    = 'Mail-Einstellungen';
$layoutHead    = '<link rel="stylesheet" href="' . htmlspecialchars(asset('assets/dist/editor.css')) . '">';
$base          = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Mail</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Mail</p>
                    <h1>Mail-Einstellungen</h1>
                    <p class="twplus-page-header__description">Domain und Muster gelten für neue Postfächer. Bestehende Adressen bleiben, ändern lassen sie sich unter Postfächer.</p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= $base ?>settings/mail/mailboxes" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-envelopes-bulk" aria-hidden="true"></i> Postfächer</a>
                </div>
            </div>

            <form method="post" action="<?= $base ?>settings/mail" class="ignis-card">
                <?= csrf_field() ?>
                <div class="ignis-card__body grid gap-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="mail-domain" class="ignis-field__label">Standard-Domain <span class="ignis-field__required">*</span></label>
                            <input type="text" id="mail-domain" name="domain" class="ignis-input ignis-mono" required maxlength="100" autocapitalize="none" spellcheck="false" value="<?= htmlspecialchars($form['domain']) ?>">
                            <p class="ignis-field__hint">Zum Beispiel ignis.ef oder feuerwehr-musterstadt.de.</p>
                        </div>
                        <div>
                            <label for="mail-pattern" class="ignis-field__label">Adressmuster</label>
                            <select id="mail-pattern" name="pattern" class="ignis-input" data-custom-dropdown="true">
                                <option value="initial_dot_last"<?= $form['pattern'] !== 'first_dot_last' ? ' selected' : '' ?>>v.nachname (m.mueller, bei Dopplung ma.mueller)</option>
                                <option value="first_dot_last"<?= $form['pattern'] === 'first_dot_last' ? ' selected' : '' ?>>vorname.nachname (max.mueller, bei Dopplung max.mueller2)</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="mail-allowed" class="ignis-field__label">Weitere erlaubte Domains</label>
                        <input type="text" id="mail-allowed" name="allowed" class="ignis-input ignis-mono" autocapitalize="none" spellcheck="false" value="<?= htmlspecialchars($form['allowed']) ?>">
                        <p class="ignis-field__hint">Durch Komma getrennt. Wer „Domain eines Postfachs wählen“ darf, stellt Postfächer und Verteiler darauf um.</p>
                    </div>
                    <div>
                        <label for="mail-cooldown" class="ignis-field__label">Sendepause je Postfach (Sekunden)</label>
                        <input type="number" id="mail-cooldown" name="cooldown" class="ignis-input" required min="0" max="<?= \Plugin\Mail\Controllers\MailAdminController::MAX_SEND_COOLDOWN ?>" step="1" value="<?= htmlspecialchars($form['cooldown']) ?>">
                        <p class="ignis-field__hint">Ein Postfach sendet höchstens eine Mail in dieser Zeit. 0 schaltet die Pause ab, Entwürfe speichern zählt nicht.</p>
                    </div>
                    <div>
                        <?php
                        $sigPrefix = 'mail-default-signature';
                        $sigField  = 'signature';
                        $sigLabel  = 'Standard-Signatur';
                        $sigDoc    = $form['signature'];
                        $sigValues = $previewValues;
                        require dirname(__DIR__) . '/mail/_signature-editor.php';
                        ?>
                        <p class="ignis-field__hint">Gilt für jedes Postfach ohne eigene Signatur, auch für Gruppenpostfächer. Leer gespeichert gilt wieder die eingebaute Vorlage.</p>
                    </div>
                </div>
                <div class="ignis-card__footer" data-form-actions>
                    <a href="<?= $base ?>settings/index" class="ignis-btn ignis-btn--ghost">Abbrechen</a>
                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-check" aria-hidden="true"></i> Speichern</button>
                </div>
            </form>
        </div>
    </div>
