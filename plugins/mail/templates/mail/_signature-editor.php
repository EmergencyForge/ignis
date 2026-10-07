<?php
/**
 * Partial: Signatur-Editor mit Platzhaltern und Vorschau, geteilt von der
 * Standard-Signatur (Einstellungen › Mail) und der eigenen Signatur eines
 * Postfachs (mail/signature.php).
 *
 * Die Chips nennen, wofür sie stehen (wie in den Dokumentvorlagen), der
 * Schlüssel `{{schlüssel}}` steht im Tooltip; einfügen lassen sie sich mit
 * `{{` im Text oder über die Knopfleiste an der Schreibmarke. Darunter die Vorschau: die
 * Signatur mit den Angaben aus `$sigValues` (dem angemeldeten Konto), nach
 * denselben Regeln wie beim Verfassen (SignatureTemplate::resolve(): leere
 * Platzhalter bleiben leer, eine Zeile ohne Text danach fällt weg). Was
 * dabei leer bleibt, steht unter der Vorschau, sonst wundert man sich über
 * eine fehlende Zeile. Das
 * Editor-JSON steht nach jeder Änderung im versteckten Feld `$sigField`.
 *
 * @var string                                         $sigPrefix  Präfix der IDs
 * @var string                                         $sigField   Name des versteckten Felds
 * @var string                                         $sigLabel   Beschriftung über dem Editor
 * @var array<string,mixed>                            $sigDoc     Vorlage als Editor-JSON
 * @var array<string,list<array<string,mixed>>>        $sigValues  Werte für die Vorschau (Inline-Knoten)
 */

use Plugin\Mail\SignatureTemplate;

$sigJson    = static fn (mixed $value): string => htmlspecialchars((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES);
$sigCatalog = SignatureTemplate::catalog();
?>
<span id="<?= htmlspecialchars($sigPrefix) ?>-label" class="ignis-field__label"><?= htmlspecialchars($sigLabel) ?></span>
<input type="hidden" name="<?= htmlspecialchars($sigField) ?>" id="<?= htmlspecialchars($sigPrefix) ?>-json" value="<?= $sigJson($sigDoc) ?>">
<div class="efe-frame">
    <div id="<?= htmlspecialchars($sigPrefix) ?>-toolbar"></div>
    <div id="<?= htmlspecialchars($sigPrefix) ?>-editor" class="efe-page ignis-mail__editor" aria-labelledby="<?= htmlspecialchars($sigPrefix) ?>-label"
         data-efe-content="<?= $sigJson($sigDoc) ?>"
         data-efe-variables="<?= $sigJson($sigCatalog) ?>"
         data-signature-values="<?= $sigJson((object) $sigValues) ?>"
         data-editor-src="<?= htmlspecialchars(asset('assets/dist/editor.iife.js')) ?>"></div>
</div>
<div class="ignis-mail-signature__variables" role="group" aria-label="Platzhalter einfügen">
    <span class="ignis-field__hint">Platzhalter einfügen:</span>
    <?php foreach ($sigCatalog as $key => $label): ?>
        <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost" data-mail-variable="<?= htmlspecialchars($key) ?>" data-ignis-tooltip="{{<?= htmlspecialchars($key) ?>}}" disabled><?= htmlspecialchars($label) ?></button>
    <?php endforeach; ?>
</div>
<p class="ignis-field__hint">Platzhalter füllt ignis beim Verfassen mit den Angaben des Absenders; eine Zeile, deren Platzhalter alle leer bleiben, fällt weg. Im Text lassen sie sich auch mit <span class="ignis-mono">{{</span> einfügen.</p>

<span class="ignis-field__label" id="<?= htmlspecialchars($sigPrefix) ?>-preview-label">Vorschau mit deinen Angaben</span>
<div class="efe-frame">
    <div id="<?= htmlspecialchars($sigPrefix) ?>-preview" class="efe-page ignis-mail__editor" aria-labelledby="<?= htmlspecialchars($sigPrefix) ?>-preview-label" aria-live="polite"></div>
</div>
<p class="ignis-field__hint" id="<?= htmlspecialchars($sigPrefix) ?>-preview-empty" hidden>Mit deinen Angaben bliebe die Signatur leer.</p>
<p class="ignis-field__hint" id="<?= htmlspecialchars($sigPrefix) ?>-preview-missing" hidden></p>

<script>
(function () {
    'use strict';
    var prefix = <?= json_encode($sigPrefix) ?>;
    var mount = document.getElementById(prefix + '-editor');
    var jsonField = document.getElementById(prefix + '-json');
    var previewMount = document.getElementById(prefix + '-preview');
    var previewEmpty = document.getElementById(prefix + '-preview-empty');
    var previewMissing = document.getElementById(prefix + '-preview-missing');
    if (!mount || !jsonField || !previewMount) return;

    function read(attr, fallback) {
        try { return JSON.parse(mount.getAttribute(attr) || ''); } catch (e) { return fallback; }
    }

    function hasText(node) {
        if (!node || typeof node !== 'object') return false;
        if (node.type === 'text' && String(node.text || '').trim() !== '') return true;
        return Array.isArray(node.content) && node.content.some(hasText);
    }

    // Dieselben Regeln wie Plugin\Mail\SignatureTemplate::resolve(): jeder
    // Platzhalter wird zu seinen Knoten (Text, beim Dienstgrad das Abzeichen),
    // ein Absatz mit Platzhaltern und ohne Text danach fällt weg.
    function fill(node, values, state) {
        if (!Array.isArray(node.content)) return node;
        var children = [];
        node.content.forEach(function (child) {
            if (!child || typeof child !== 'object') return;
            if (child.type !== 'docVariable') {
                children.push(fill(child, values, state));
                return;
            }
            state.hadVariable = true;
            (values[(child.attrs && child.attrs.name) || ''] || []).forEach(function (inline) {
                var copy = Object.assign({}, inline);
                if (copy.type === 'text' && Array.isArray(child.marks) && child.marks.length) copy.marks = child.marks;
                children.push(copy);
            });
        });
        return Object.assign({}, node, { content: children });
    }

    // Wie SignatureTemplate::fillLines(): Zeilen innerhalb eines Absatzes
    // (getrennt durch Umschalt+Enter) fallen einzeln weg, wenn ihre
    // Platzhalter leer bleiben.
    function fillLines(node, values, state) {
        if (node.type !== 'paragraph' || !Array.isArray(node.content)) return fill(node, values, state);
        var lines = [[]];
        node.content.forEach(function (child) {
            if (child && child.type === 'hardBreak') { lines.push([]); return; }
            lines[lines.length - 1].push(child);
        });
        var content = [];
        var kept = 0;
        lines.forEach(function (line) {
            var lineState = { hadVariable: false };
            var filled = fill({ type: 'paragraph', content: line }, values, lineState).content;
            if (lineState.hadVariable) state.hadVariable = true;
            if (lineState.hadVariable && !hasText({ content: filled })) return;
            if (kept++ > 0) content.push({ type: 'hardBreak' });
            content.push.apply(content, filled);
        });
        return Object.assign({}, node, { content: content });
    }

    function resolve(doc, values) {
        var content = [];
        (doc.content || []).forEach(function (node) {
            if (!node || typeof node !== 'object') return;
            var state = { hadVariable: false };
            var filled = fillLines(node, values, state);
            if (state.hadVariable && !hasText(filled)) return;
            content.push(filled);
        });
        return { type: 'doc', content: content };
    }

    // Beschriftungen der Platzhalter im Entwurf, die mit den Angaben des
    // Kontos leer bleiben, jeder einmal in Reihenfolge des Texts.
    function emptyLabels(doc, values, catalog) {
        var seen = {};
        var labels = [];
        (function walk(node) {
            if (!node || typeof node !== 'object') return;
            if (node.type === 'docVariable') {
                var name = (node.attrs && node.attrs.name) || '';
                if (!seen[name] && !(values[name] || []).some(hasText)) {
                    seen[name] = true;
                    labels.push(catalog[name] || name);
                }
                return;
            }
            (node.content || []).forEach(walk);
        })(doc);
        return labels;
    }

    function mountEditor() {
        var catalog = read('data-efe-variables', {});
        var values = read('data-signature-values', {});
        var variables = {};
        Object.keys(catalog).forEach(function (key) { variables[key] = { label: catalog[key] }; });

        var editor = window.EmergencyForgeEditor.createEditor(mount, {
            content: read('data-efe-content', { type: 'doc', content: [] }),
            toolbar: document.getElementById(prefix + '-toolbar'),
            features: 'signature',
            variables: variables,
        });
        // Das Abzeichen vor dem Dienstgrad ist ein Bild, die Vorschau kennt es deshalb.
        var preview = window.EmergencyForgeEditor.createEditor(previewMount, {
            content: { type: 'doc', content: [] },
            features: window.EmergencyForgeEditor.presets.signature.concat(['image']),
            editable: false,
            bubble: false,
        });

        function sync() {
            var doc = editor.getJSON();
            var resolved = resolve(doc, values);
            jsonField.value = JSON.stringify(doc);
            preview.commands.setContent(resolved);
            if (previewEmpty) previewEmpty.hidden = hasText(resolved);
            if (previewMissing) {
                var missing = emptyLabels(doc, values, catalog);
                previewMissing.textContent = missing.length ? 'Bei dir ohne Wert, darum hier weggelassen: ' + missing.join(', ') + '.' : '';
                previewMissing.hidden = missing.length === 0;
            }
        }
        sync();
        editor.on('update', function () {
            sync();
            jsonField.dispatchEvent(new Event('input', { bubbles: true }));
        });

        var scope = mount.closest('form') || document;
        scope.querySelectorAll('[data-mail-variable]').forEach(function (button) {
            button.disabled = false;
            button.addEventListener('click', function () {
                editor.chain().focus().insertVariable(button.getAttribute('data-mail-variable')).run();
            });
        });
    }

    // Das Editor-Bundle gehört nicht zur Hülle. Im Drawer ersetzt
    // drawer-form.js die <script>-Tags neu, zwei externe Skripte hätten dann
    // keine feste Reihenfolge; deshalb lädt dieser Block es selbst nach.
    if (window.EmergencyForgeEditor) {
        mountEditor();
        return;
    }
    var loader = document.createElement('script');
    loader.src = mount.getAttribute('data-editor-src') || '';
    loader.onload = mountEditor;
    document.body.appendChild(loader);
})();
</script>
