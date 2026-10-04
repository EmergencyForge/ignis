<?php
/**
 * View: Mail-Einstellungen (`mail.admin`), Standard-Domain, erlaubte
 * Domains, Adressmuster, Sendepause und Standard-Signatur. Domain und Muster gelten
 * für neue Postfächer; bestehende Adressen ändert die Postfachverwaltung.
 *
 * Die Standard-Signatur steht im Editor (Mailmodus) mit den Platzhaltern
 * aus SignatureTemplate::catalog(); der Editor schreibt sein JSON vor dem
 * Absenden ins versteckte Feld `signature`.
 *
 * @var array{domain:string, pattern:string, allowed:string, signature:array<string,mixed>, cooldown:string} $form
 */

use Plugin\Mail\SignatureTemplate;

$layout        = 'admin';
$bodyId        = 'settings';
$SITE_TITLE    = 'Mail-Einstellungen';
$layoutHead    = '<link rel="stylesheet" href="' . htmlspecialchars(asset('assets/dist/editor.css')) . '">';
$base          = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$signatureJson = (string) json_encode($form['signature'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
                        <span id="mail-default-signature-label" class="ignis-field__label">Standard-Signatur</span>
                        <input type="hidden" name="signature" id="mail-default-signature-json" value="<?= htmlspecialchars($signatureJson) ?>">
                        <div class="ignis-mail-compose__editor">
                            <div id="mail-default-signature-toolbar"></div>
                            <div id="mail-default-signature-editor" class="efe-page ignis-mail__editor" aria-labelledby="mail-default-signature-label"
                                 data-efe-content="<?= htmlspecialchars($signatureJson) ?>"
                                 data-editor-src="<?= htmlspecialchars(asset('assets/dist/editor.iife.js')) ?>"
                                 data-efe-variables="<?= htmlspecialchars((string) json_encode(SignatureTemplate::catalog(), JSON_UNESCAPED_UNICODE)) ?>"></div>
                        </div>
                        <div class="ignis-mail-signature__variables" role="group" aria-label="Platzhalter einfügen">
                            <span class="ignis-field__hint">Platzhalter einfügen:</span>
                            <?php foreach (SignatureTemplate::catalog() as $key => $label): ?>
                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost" data-mail-variable="<?= htmlspecialchars($key) ?>" disabled><?= htmlspecialchars($label) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <p class="ignis-field__hint">Gilt für alle ohne eigene Signatur. Platzhalter füllt ignis beim Verfassen mit den Angaben des Absenders; eine Zeile, deren Platzhalter alle leer bleiben, fällt weg. Im Text lassen sie sich auch mit <span class="ignis-mono">{{</span> einfügen. Leer gespeichert gilt wieder die eingebaute Vorlage.</p>
                    </div>
                </div>
                <div class="ignis-card__footer" data-form-actions>
                    <a href="<?= $base ?>settings/index" class="ignis-btn ignis-btn--ghost">Abbrechen</a>
                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-check" aria-hidden="true"></i> Speichern</button>
                </div>
            </form>
        </div>
    </div>
<script>
(function () {
    'use strict';
    var mount = document.getElementById('mail-default-signature-editor');
    var hidden = document.getElementById('mail-default-signature-json');
    if (!mount || !hidden) return;

    function read(attr, fallback) {
        try { return JSON.parse(mount.getAttribute(attr) || ''); } catch (e) { return fallback; }
    }

    function mountEditor() {
        // Wie in den Dokumentvorlagen: Chips zeigen {{schlüssel}}, die
        // Beschriftung steht im Tooltip und in der Auswahl nach {{.
        var catalog = read('data-efe-variables', {});
        var variables = {};
        Object.keys(catalog).forEach(function (key) { variables[key] = { label: catalog[key] }; });

        var editor = window.EmergencyForgeEditor.createEditor(mount, {
            content: read('data-efe-content', { type: 'doc', content: [] }),
            toolbar: document.getElementById('mail-default-signature-toolbar'),
            toolbarItems: ['bold', 'italic', 'link', 'undo', 'redo'],
            pages: false,
            links: true,
            variables: variables,
        });
        hidden.value = JSON.stringify(editor.getJSON());
        editor.on('update', function () {
            hidden.value = JSON.stringify(editor.getJSON());
        });

        document.querySelectorAll('[data-mail-variable]').forEach(function (button) {
            button.disabled = false;
            button.addEventListener('click', function () {
                editor.chain().focus().insertVariable(button.getAttribute('data-mail-variable')).run();
            });
        });
    }

    if (window.EmergencyForgeEditor) {
        mountEditor();
        return;
    }
    var script = document.createElement('script');
    script.src = mount.getAttribute('data-editor-src') || '';
    script.onload = mountEditor;
    document.body.appendChild(script);
})();
</script>
