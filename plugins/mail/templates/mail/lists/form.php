<?php
/**
 * View: Verteiler anlegen oder bearbeiten. Beide Bereiche (Mitglieder,
 * Regel) stehen im Formular, sichtbar ist nur der zur gewählten Art
 * (`hidden`, serverseitig gesetzt und beim Wechsel der Art umgeschaltet);
 * gespeichert wird ebenfalls nur dieser.
 *
 * @var \Plugin\Mail\Models\MailList|null $list      null = neu
 * @var array<string,mixed>               $form      name, local, domain, kind, senders, members, *_ids
 * @var list<string>                      $domains
 * @var list<\Plugin\Mail\Models\Mailbox> $mailboxes zur Wahl: zustellbare plus bisherige Mitglieder
 * @var array<string, array<int,string>>  $criteria  role_ids|rank_ids|rd_quali_ids|fw_quali_ids => Id => Name
 */

use Plugin\Mail\MailAddressRules;
use Plugin\Mail\Models\MailList;

$layout     = 'admin';
$bodyId     = 'mail';
$SITE_TITLE = $list === null ? 'Verteiler anlegen' : 'Verteiler bearbeiten';
$isDynamic  = $form['kind'] === 'dynamic';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';

$options = array_map(static fn ($m): array => [
    'value' => (int) $m->id,
    'label' => $m->display_name . ' <' . $m->address . '>' . ($m->active && !$m->locked ? '' : ' (stellt nicht zu)'),
], $mailboxes);

$groups = [
    'role_ids'     => ['Rollen', 'Noch keine Rollen angelegt.'],
    'rank_ids'     => ['Dienstgrade', 'Noch keine Dienstgrade angelegt.'],
    'rd_quali_ids' => ['RD-Qualifikationen', 'Noch keine RD-Qualifikationen angelegt.'],
    'fw_quali_ids' => ['FW-Qualifikationen', 'Noch keine FW-Qualifikationen angelegt.'],
];
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>mail">Mail</a></span> <span class="ignis-breadcrumb__item"><a href="<?= $base ?>mail/lists">Verteiler</a></span> <span class="ignis-breadcrumb__item is-active"><?= $list === null ? 'Neu' : htmlspecialchars($list->name) ?></span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1><?= htmlspecialchars($SITE_TITLE) ?></h1>
                </div>
            </div>

            <form method="post" action="<?= $base ?>mail/lists<?= $list !== null ? '/' . (int) $list->id : '' ?>" class="ignis-card">
                <?= csrf_field() ?>
                <div class="ignis-card__body grid gap-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="list-name" class="ignis-field__label">Name <span class="ignis-field__required">*</span></label>
                            <input type="text" id="list-name" name="name" class="ignis-input" required maxlength="150" value="<?= htmlspecialchars((string) $form['name']) ?>">
                        </div>
                        <div>
                            <label for="list-kind" class="ignis-field__label">Art</label>
                            <select id="list-kind" name="kind" class="ignis-input">
                                <option value="static"<?= $form['kind'] !== 'dynamic' ? ' selected' : '' ?>>Statisch: feste Mitglieder</option>
                                <option value="dynamic"<?= $form['kind'] === 'dynamic' ? ' selected' : '' ?>>Dynamisch: Regel aus Rolle, Dienstgrad, Qualifikation</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="list-local" class="ignis-field__label">Adresse <span class="ignis-field__required">*</span></label>
                            <input type="text" id="list-local" name="local" class="ignis-input ignis-mono" required maxlength="<?= MailAddressRules::MAX_LOCAL_LENGTH ?>" autocapitalize="none" spellcheck="false" placeholder="wache1" value="<?= htmlspecialchars((string) $form['local']) ?>">
                            <p class="ignis-field__hint">Der Teil vor dem @: Kleinbuchstaben, Ziffern, Punkt, Bindestrich, Unterstrich.</p>
                        </div>
                        <div>
                            <label for="list-domain" class="ignis-field__label">Domain</label>
                            <select id="list-domain" name="domain" class="ignis-input">
                                <?php foreach ($domains as $domain): ?>
                                    <option value="<?= htmlspecialchars($domain) ?>"<?= $form['domain'] === $domain ? ' selected' : '' ?>>@<?= htmlspecialchars($domain) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="ignis-field__hint">Die Adresse ist über alle Postfächer und Verteiler einmalig.</p>
                        </div>
                    </div>

                    <div>
                        <label for="list-senders" class="ignis-field__label">Wer darf schreiben</label>
                        <select id="list-senders" name="senders" class="ignis-input">
                            <option value="<?= MailList::SENDERS_ALL ?>"<?= $form['senders'] !== MailList::SENDERS_MANAGERS ? ' selected' : '' ?>>Alle mit Mail-Zugang</option>
                            <option value="<?= MailList::SENDERS_MANAGERS ?>"<?= $form['senders'] === MailList::SENDERS_MANAGERS ? ' selected' : '' ?>>Nur Verteiler-Verwaltung (Recht mail.lists.manage)</option>
                        </select>
                        <p class="ignis-field__hint">Empfangen können die Mitglieder immer. Ein eingeschränkter Verteiler fehlt im Adressbuch aller anderen.</p>
                    </div>

                    <div data-list-kind="static"<?= $isDynamic ? ' hidden' : '' ?>>
                        <span class="ignis-field__label" id="list-members-label">Mitglieder</span>
                        <div data-ignis-multi-select data-name="members[]" aria-labelledby="list-members-label" data-placeholder="Postfach suchen"
                             data-options="<?= htmlspecialchars((string) json_encode($options, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"
                             data-value="<?= htmlspecialchars(implode(',', array_map('intval', (array) $form['members']))) ?>"></div>
                        <p class="ignis-field__hint">Neu hinzufügen lassen sich nur aktive, nicht gesperrte Postfächer. Ein gesperrtes Mitglied bleibt in der Liste, bekommt aber nichts.</p>
                    </div>

                    <fieldset class="ignis-field" data-list-kind="dynamic"<?= $isDynamic ? '' : ' hidden' ?>>
                        <legend class="ignis-field__label">Regel</legend>
                        <p class="ignis-field__hint mb-2">Mitglied ist, auf wen mindestens ein Kriterium zutrifft. Wer neu dazukommt oder wechselt, ist ab der nächsten Mail dabei.</p>
                        <div class="grid gap-4">
                            <?php foreach ($groups as $key => [$label, $emptyText]): ?>
                                <div>
                                    <p class="ignis-field__label"><?= $label ?></p>
                                    <?php if (($criteria[$key] ?? []) === []): ?>
                                        <?php $empty = ['variant' => 'inline', 'icon' => 'fa-circle-info', 'text' => $emptyText]; require dirname(__DIR__, 5) . '/templates/partials/empty.php'; ?>
                                    <?php else: ?>
                                        <div class="ignis-mail-rule__options">
                                            <?php foreach ($criteria[$key] as $id => $name): ?>
                                                <div class="ignis-checkbox">
                                                    <input type="checkbox" id="list-<?= $key ?>-<?= (int) $id ?>" name="<?= $key ?>[]" value="<?= (int) $id ?>"<?= in_array((int) $id, array_map('intval', (array) $form[$key]), true) ? ' checked' : '' ?>>
                                                    <label for="list-<?= $key ?>-<?= (int) $id ?>"><?= htmlspecialchars($name) ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                </div>
                <div class="ignis-card__footer" data-form-actions>
                    <a href="<?= $base ?>mail/lists" class="ignis-btn ignis-btn--ghost">Abbrechen</a>
                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-check" aria-hidden="true"></i> <?= $list === null ? 'Anlegen' : 'Speichern' ?></button>
                </div>
            </form>
        </div>
    </div>
    <script type="module" src="<?= $base ?>assets/js/ui/multi-select.js"></script>
    <script>
    (function () {
        'use strict';
        // Nur der Bereich der gewählten Art ist zu sehen.
        var kind = document.getElementById('list-kind');
        if (!kind) return;
        kind.addEventListener('change', function () {
            document.querySelectorAll('[data-list-kind]').forEach(function (block) {
                block.hidden = block.getAttribute('data-list-kind') !== kind.value;
            });
        });
    })();
    </script>
