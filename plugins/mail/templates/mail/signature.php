<?php
/**
 * View: eigene Signatur des Postfachs, als Drawer aus der Ordnerleiste
 * (drawer-form.js schickt das Formular ab) oder als Seite. Der Editor im
 * Mailmodus schreibt sein JSON vor dem Absenden ins versteckte Feld. Ohne
 * eigene steht die geltende Signatur als Vorlage im Editor: die
 * Standard-Signatur der Instanz oder die aus dem Mitarbeiterprofil.
 *
 * @var array<string,mixed> $bodyJson
 * @var bool                $hasOwn
 */

$layout     = 'admin';
$bodyId     = 'mail';
$SITE_TITLE = 'Signatur';
$layoutHead = '<link rel="stylesheet" href="' . htmlspecialchars(asset('assets/dist/editor.css')) . '">';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
<link rel="stylesheet" href="<?= htmlspecialchars(asset('assets/dist/editor.css')) ?>">
<div class="twplus-page">
    <form method="post" action="<?= htmlspecialchars($base . 'mail/signature') ?>" id="mail-signature-form" class="ignis-card"
          data-editor-src="<?= htmlspecialchars(asset('assets/dist/editor.iife.js')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="body_json" id="mail-signature-json">
        <div class="ignis-card__body">
            <p class="ignis-field__hint">
                Steht in neuen Mails nach einer Leerzeile unter dem Text und lässt sich dort noch ändern oder löschen.
                <?= $hasOwn ? 'Leer speichern heißt: keine Signatur.' : 'Solange du keine eigene speicherst, gilt die Standard-Signatur oder die aus deinem Mitarbeiterprofil.' ?>
            </p>
            <div class="ignis-mail-compose__editor">
                <div id="mail-signature-toolbar"></div>
                <div id="mail-signature-editor" class="efe-page ignis-mail__editor" data-efe-content="<?= htmlspecialchars((string) json_encode($bodyJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>"></div>
            </div>
        </div>
        <div class="ignis-card__footer" data-form-actions>
            <a href="<?= htmlspecialchars($base . 'mail') ?>" class="ignis-btn ignis-btn--ghost">Abbrechen</a>
            <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-check" aria-hidden="true"></i> Speichern</button>
        </div>
    </form>
</div>
<script>
(function () {
    'use strict';
    var form = document.getElementById('mail-signature-form');
    var mount = document.getElementById('mail-signature-editor');
    var hidden = document.getElementById('mail-signature-json');
    if (!form || !mount || !hidden) return;

    // Das Editor-Bundle gehört nicht zur Hülle. Im Drawer ersetzt
    // drawer-form.js die <script>-Tags neu, zwei externe Skripte hätten dann
    // keine feste Reihenfolge; deshalb lädt dieser Block es selbst nach.
    function mountEditor() {
        var content;
        try { content = JSON.parse(mount.getAttribute('data-efe-content') || ''); } catch (e) { content = { type: 'doc', content: [] }; }
        var editor = window.EmergencyForgeEditor.createEditor(mount, {
            content: content,
            toolbar: document.getElementById('mail-signature-toolbar'),
            toolbarItems: ['bold', 'italic', 'link', 'undo', 'redo'],
            pages: false,
            links: true,
        });
        hidden.value = JSON.stringify(editor.getJSON());
        editor.on('update', function () {
            hidden.value = JSON.stringify(editor.getJSON());
            form.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }

    if (window.EmergencyForgeEditor) {
        mountEditor();
        return;
    }
    var script = document.createElement('script');
    script.src = form.getAttribute('data-editor-src') || '';
    script.onload = mountEditor;
    document.body.appendChild(script);
})();
</script>
