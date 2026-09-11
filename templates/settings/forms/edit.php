<?php
/**
 * View: Antragstyp bearbeiten. Grundeinstellungen als Formularkarte, die
 * Felder als ignis-Tabelle mit Sortierung je Zeile und Löschen als
 * Zeilenaktion; ein neues Feld kommt über den Dialog (Dialog.form).
 *
 * @var int                            $id
 * @var array<string,mixed>            $typ
 * @var array<int,array<string,mixed>> $felder
 */


$layout = 'admin';
$bodyId = 'antragstyp-edit';
$SITE_TITLE = 'Antragstyp bearbeiten';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/forms/list">Antragstypen</a></span> <span class="ignis-breadcrumb__item is-active"><?= htmlspecialchars($typ['name']) ?></span></nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Formularbaukasten</p><h1><?= htmlspecialchars($typ['name']) ?> bearbeiten</h1><p class="twplus-page-header__description">Grundeinstellungen und Formularfelder des Antragstyps verwalten.</p></div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= BASE_PATH ?>settings/forms/list" class="ignis-btn ignis-btn--ghost"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Übersicht</a>
                    <button type="button" class="ignis-btn ignis-btn--primary" onclick="openAddFeldModal()">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Feld hinzufügen
                    </button>
                </div>
            </div>

            <div class="ignis-detail__groups">
                <!-- Grundeinstellungen -->
                <form method="post" class="ignis-card ignis-form-card" data-ignis-form="antragstyp-edit">
                    <div class="ignis-card__header"><h2 class="ignis-card__title">Grundeinstellungen</h2></div>
                    <div class="ignis-card__body">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="name" class="ignis-field__label">Name <span class="ignis-field__required">*</span></label>
                                <input type="text" class="ignis-input" id="name" name="name"
                                    value="<?= htmlspecialchars($typ['name']) ?>" placeholder="z.B. Urlaubsantrag" required>
                            </div>
                            <div>
                                <label for="sortierung" class="ignis-field__label">Sortierung</label>
                                <input type="number" class="ignis-input" id="sortierung" name="sortierung"
                                    value="<?= (int)$typ['sortierung'] ?>" min="0">
                            </div>
                        </div>

                        <div class="mt-4 mb-4">
                            <label for="beschreibung" class="ignis-field__label">Beschreibung</label>
                            <textarea class="ignis-input" id="beschreibung" name="beschreibung"
                                rows="2" placeholder="Kurze Erklärung, wofür dieser Antrag verwendet wird"><?= htmlspecialchars($typ['beschreibung']) ?></textarea>
                        </div>

                        <label class="ignis-switch" for="aktiv"><input type="checkbox" id="aktiv" name="aktiv"
                                <?= $typ['aktiv'] ? 'checked' : '' ?>><span><strong>Antragstyp aktiviert</strong></span></label>
                    </div>
                    <div class="ignis-card__footer ignis-form-card__footer">
                        <button type="submit" name="update_typ" class="ignis-btn ignis-btn--primary">
                            <i class="fa-solid fa-save" aria-hidden="true"></i> Speichern
                        </button>
                    </div>
                </form>

                <!-- Felder Verwaltung -->
                <section class="ignis-card">
                    <div class="ignis-card__header">
                        <h2 class="ignis-card__title"><i class="fa-solid fa-list mr-2" aria-hidden="true"></i>Formularfelder (<?= count($felder) ?>)</h2>
                        <div class="ignis-card__actions">
                            <button type="button" class="ignis-btn ignis-btn--secondary ignis-btn--sm" onclick="openAddFeldModal()">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i> Feld hinzufügen
                            </button>
                        </div>
                    </div>

                    <?php if (empty($felder)): ?>
                        <div class="ignis-table-empty">Noch keine Felder definiert. Füge jetzt das erste Feld hinzu.</div>
                    <?php else: ?>
                        <form method="post">
                            <div class="twplus-table-card__scroll">
                                <table class="ignis-table" id="table-antragsfelder">
                                    <thead>
                                        <tr>
                                            <th scope="col" class="w-20">Sort.</th>
                                            <th scope="col">Feldname</th>
                                            <th scope="col">Label</th>
                                            <th scope="col">Typ</th>
                                            <th scope="col">Breite</th>
                                            <th scope="col">Pflicht</th>
                                            <th scope="col">Readonly</th>
                                            <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($felder as $feld): ?>
                                            <tr>
                                                <td>
                                                    <label class="sr-only" for="feld-sort-<?= (int) $feld['id'] ?>">Sortierung <?= htmlspecialchars($feld['label']) ?></label>
                                                    <input type="number"
                                                        name="feld_sortierung[<?= (int)$feld['id'] ?>]"
                                                        id="feld-sort-<?= (int) $feld['id'] ?>"
                                                        value="<?= (int)$feld['sortierung'] ?>"
                                                        class="ignis-input ignis-input--sm ignis-table__num">
                                                </td>
                                                <td><code class="ignis-mono"><?= htmlspecialchars($feld['feldname']) ?></code></td>
                                                <td><?= htmlspecialchars($feld['label']) ?></td>
                                                <td>
                                                    <span class="ignis-chip ignis-chip--secondary"><?= htmlspecialchars($feld['feldtyp']) ?></span>
                                                    <?php if ($feld['auto_fill']): ?>
                                                        <span class="ignis-chip ignis-chip--info" title="Auto-Fill: <?= htmlspecialchars($feld['auto_fill']) ?>">
                                                            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Auto
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="ignis-chip ignis-chip--<?= $feld['breite'] === 'full' ? 'info' : 'warn' ?>">
                                                        <?= $feld['breite'] === 'full' ? 'Voll' : 'Halb' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= $feld['pflichtfeld'] ? '<i class="fa-solid fa-check text-[var(--ok)]" aria-hidden="true"></i><span class="sr-only">Ja</span>' : '<i class="fa-solid fa-xmark text-[var(--text-3)]" aria-hidden="true"></i><span class="sr-only">Nein</span>' ?>
                                                </td>
                                                <td>
                                                    <?= $feld['readonly'] ? '<i class="fa-solid fa-lock text-[var(--warn)]" aria-hidden="true"></i><span class="sr-only">Ja</span>' : '<i class="fa-solid fa-lock-open text-[var(--text-3)]" aria-hidden="true"></i><span class="sr-only">Nein</span>' ?>
                                                </td>
                                                <td class="ignis-table__actions">
                                                    <div class="ignis-row-actions">
                                                        <a href="?id=<?= (int)$id ?>&delete_feld=<?= (int)$feld['id'] ?>"
                                                            class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger ignis-btn--icon" data-ignis-tooltip="Feld löschen" aria-label="Feld löschen"
                                                            onclick="event.preventDefault(); showConfirm('Feld wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Feld löschen'}).then(result => { if(result) window.location.href = this.href; });">
                                                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="ignis-list-footer">
                                <p class="ignis-list-meta"><?= count($felder) ?> Felder</p>
                                <button type="submit" name="update_felder_sortierung" class="ignis-btn ignis-btn--sm ignis-btn--secondary">
                                    <i class="fa-solid fa-save" aria-hidden="true"></i> Sortierung speichern
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>

    <!-- Form-Body als inertes <template>; Dialog wird in JS programmatisch erstellt -->
    <template id="addFeldFormTemplate">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label for="feldname" class="ignis-field__label">Feldname (technisch) <span class="ignis-field__required">*</span></label>
                <input type="text" class="ignis-input" id="feldname" name="feldname" placeholder="z.B. von_datum, grund" required>
                <small class="form-hint block">Nur Kleinbuchstaben, Zahlen und Unterstriche</small>
            </div>
            <div>
                <label for="label" class="ignis-field__label">Label (Anzeige) <span class="ignis-field__required">*</span></label>
                <input type="text" class="ignis-input" id="label" name="label" placeholder="z.B. Urlaub von" required>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label for="feldtyp" class="ignis-field__label">Feldtyp <span class="ignis-field__required">*</span></label>
                <select class="ignis-input" id="feldtyp" name="feldtyp" required>
                    <option value="text">Text (einzeilig)</option>
                    <option value="textarea">Textarea (mehrzeilig)</option>
                    <option value="number">Zahl</option>
                    <option value="date">Datum</option>
                    <option value="time">Uhrzeit</option>
                    <option value="email">E-Mail</option>
                    <option value="tel">Telefon</option>
                    <option value="select">Auswahlfeld</option>
                    <option value="checkbox">Checkbox</option>
                </select>
            </div>
            <div>
                <label for="breite" class="ignis-field__label">Feldbreite</label>
                <select class="ignis-input" id="breite" name="breite">
                    <option value="full">Volle Breite</option>
                    <option value="half">Halbe Breite</option>
                </select>
            </div>
            <div>
                <label for="auto_fill" class="ignis-field__label">Auto-Fill</label>
                <select class="ignis-input" id="auto_fill" name="auto_fill">
                    <option value="">Kein Auto-Fill</option>
                    <option value="fullname_dienstnr">Name + Dienstnr.</option>
                    <option value="fullname">Name</option>
                    <option value="dienstnr">Dienstnummer</option>
                    <option value="dienstgrad">Dienstgrad</option>
                    <option value="discordtag">Discord-Tag</option>
                </select>
                <small class="form-hint block">Automatisch ausfüllen</small>
            </div>
        </div>

        <div class="mt-4">
            <label for="platzhalter" class="ignis-field__label">Platzhalter-Text</label>
            <input type="text" class="ignis-input" id="platzhalter" name="platzhalter" placeholder="z.B. TT.MM.JJJJ">
        </div>

        <div class="mt-4" id="optionen-container" hidden>
            <label for="optionen" class="ignis-field__label">Optionen (für Select)</label>
            <textarea class="ignis-input" id="optionen" name="optionen" rows="3" placeholder="Eine Option pro Zeile"></textarea>
            <small class="form-hint block">Jede Zeile wird zu einer Auswahloption</small>
        </div>

        <div class="mt-4">
            <label for="hinweistext" class="ignis-field__label">Hinweistext</label>
            <textarea class="ignis-input" id="hinweistext" name="hinweistext" rows="2" placeholder="Optionaler Hinweis, der unter dem Feld angezeigt wird"></textarea>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <label class="ignis-checkbox" for="pflichtfeld"><input type="checkbox" id="pflichtfeld" name="pflichtfeld"><span>
                    <strong>Pflichtfeld</strong>
                </span></label>
            <label class="ignis-checkbox" for="readonly"><input type="checkbox" id="readonly" name="readonly"><span>
                    <strong>Nur lesbar (Readonly)</strong>
                </span></label>
        </div>
    </template>

    <script>
        function openAddFeldModal() {
            Dialog.form({
                title:        'Neues Feld hinzufügen',
                template:     'addFeldFormTemplate',
                size:         'lg',
                formAction:   '',
                // add_feld als Hidden, weil der Submit ueber Dialog-Action
                // laeuft und kein <button name="add_feld"> mehr existiert
                // (PHP-Controller wertet $_POST['add_feld'] aus).
                hiddenFields: { add_feld: '1' },
                submitLabel:  'Feld hinzufügen',
                submitIcon:   'fa-solid fa-plus',
                submitVariant:'primary',
                onOpen: function (dlg) {
                    // Optionen nur zeigen, wenn Feldtyp = select. Body wird pro
                    // Open neu gebaut, also Handler hier binden.
                    var typ = dlg.element.querySelector('#feldtyp');
                    var optionen = dlg.element.querySelector('#optionen-container');
                    typ.addEventListener('change', function () {
                        optionen.hidden = this.value !== 'select';
                    });
                },
            });
        }
    </script>
