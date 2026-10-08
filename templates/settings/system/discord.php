<?php
/**
 * View: Discord-Bot (Einstellungen › System › Discord-Bot).
 *
 * Oben der Bot, wie Discord ihn meldet, mit Testnachricht und Trennen.
 * Darunter ein Formular für Token, Schalter, Name, Profilbild und die
 * Benachrichtigungen, die auch per Direktnachricht gehen. Das Token steht
 * nie im Formular; ein leeres Feld behält das gespeicherte.
 *
 * @var array{enabled:bool, connected:bool, id:string, name:string, avatarUrl:?string, inviteUrl:?string, dmTypes:list<string>} $bot
 * @var array<string, string> $types Benachrichtigungstyp => Beschriftung
 * @var array{users:int, reachable:int, optedOut:int} $reach
 * @var bool $ownDiscordId
 */

$layout = 'admin';
$bodyId = 'settings';
$SITE_TITLE = 'Discord-Bot';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="mb-6">
                <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Discord-Bot</span></nav>
                <div class="page-header twplus-page-header mb-4">
                    <div class="twplus-page-header__copy">
                        <p class="twplus-page-header__eyebrow">System</p>
                        <h1>Discord-Bot</h1>
                        <p class="twplus-page-header__description">Ein eigener Bot schickt Benachrichtigungen und Einladungen als Direktnachricht. Er braucht nur ein Token, keinen eigenen Server.</p>
                    </div>
                    <?php if ($bot['inviteUrl'] !== null): ?>
                        <div class="header-actions twplus-page-header__actions">
                            <a href="<?= htmlspecialchars($bot['inviteUrl']) ?>" class="ignis-btn ignis-btn--secondary" target="_blank" rel="noopener noreferrer" id="discordInviteBot"><i class="fa-brands fa-discord" aria-hidden="true"></i> Zum Server hinzufügen</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="ignis-card mb-4" id="discordBotStatus">
                    <div class="ignis-card__body flex flex-wrap items-center gap-4">
                        <?php if ($bot['connected']): ?>
                            <?php if ($bot['avatarUrl'] !== null): ?>
                                <img src="<?= htmlspecialchars($bot['avatarUrl']) ?>" alt="" width="56" height="56" class="rounded-full shrink-0" referrerpolicy="no-referrer">
                            <?php endif; ?>
                            <div class="min-w-0 mr-auto">
                                <div class="flex flex-wrap items-center gap-2">
                                    <strong><?= htmlspecialchars($bot['name'] !== '' ? $bot['name'] : 'Discord-Bot') ?></strong>
                                    <?php if ($bot['enabled']): ?>
                                        <span class="ignis-chip ignis-chip--sm ignis-chip--ok"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Aktiv</span>
                                    <?php else: ?>
                                        <span class="ignis-chip ignis-chip--sm ignis-chip--warn"><i class="fa-solid fa-circle-pause" aria-hidden="true"></i> Ausgeschaltet</span>
                                    <?php endif; ?>
                                </div>
                                <div class="ignis-field__hint mt-1">ID <span class="ignis-mono"><?= htmlspecialchars($bot['id']) ?></span> · <?= (int) $reach['reachable'] ?> von <?= (int) $reach['users'] ?> aktiven Konten haben eine Discord-ID<?= $reach['optedOut'] > 0 ? ', ' . (int) $reach['optedOut'] . ' davon wollen keine Direktnachrichten' : '' ?>.</div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <form method="post" action="<?= BASE_PATH ?>settings/system/discord/test">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ignis-btn ignis-btn--secondary" id="discordTestBtn"<?= $ownDiscordId ? '' : ' disabled title="Dein Konto hat keine Discord-ID."' ?>><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Testnachricht an mich</button>
                                </form>
                                <form method="post" action="<?= BASE_PATH ?>settings/system/discord" id="discordDisconnectForm">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="disconnect">
                                    <button type="submit" class="ignis-btn ignis-btn--ghost"><i class="fa-solid fa-link-slash" aria-hidden="true"></i> Trennen</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="ignis-chip ignis-chip--sm"><i class="fa-solid fa-plug-circle-xmark" aria-hidden="true"></i> Nicht eingerichtet</span>
                            <span class="ignis-field__hint">Trag unten das Token deines Bots ein. Name und Bild übernimmt die Seite dann von Discord.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="post" action="<?= BASE_PATH ?>settings/system/discord" enctype="multipart/form-data" id="discordBotForm">
                    <?= csrf_field() ?>

                    <div class="ignis-card mb-4">
                        <div class="ignis-card__header">
                            <h2 class="ignis-card__title">Verbindung</h2>
                        </div>
                        <div class="ignis-card__body grid gap-4">
                            <div>
                                <label for="discord-token" class="ignis-field__label">Bot-Token</label>
                                <input type="password" id="discord-token" name="token" class="ignis-input ignis-mono" autocomplete="new-password" spellcheck="false" maxlength="200"
                                    placeholder="<?= $bot['connected'] ? 'Gespeichert. Für ein neues Token hier einfügen.' : 'Token einfügen' ?>">
                                <ol class="ignis-field__hint mt-2 list-decimal pl-5 grid gap-1">
                                    <li>Im <a href="https://discord.com/developers/applications" target="_blank" rel="noopener noreferrer">Discord Developer Portal</a> eine Anwendung anlegen.</li>
                                    <li>Unter <strong>Bot</strong> auf „Reset Token“ klicken und das Token hier einfügen.</li>
                                    <li>Speichern, dann über „Zum Server hinzufügen“ den Bot auf euren Server holen. Direktnachrichten erreichen nur Personen, die einen Server mit dem Bot teilen.</li>
                                </ol>
                            </div>
                            <div class="flex items-start justify-between gap-4 rounded-md border border-border-subtle bg-surface-2 p-4">
                                <div class="min-w-0">
                                    <label for="discord-enabled" class="font-semibold cursor-pointer">Bot schickt Nachrichten</label>
                                    <div class="ignis-field__hint mt-1">Ausgeschaltet bleibt alles eingerichtet, es geht nur nichts raus.</div>
                                </div>
                                <label class="ignis-switch shrink-0" for="discord-enabled">
                                    <input type="checkbox" id="discord-enabled" name="enabled" value="1"<?= $bot['enabled'] ? ' checked' : '' ?>>
                                    <span></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="ignis-card mb-4">
                        <div class="ignis-card__header">
                            <h2 class="ignis-card__title">Aussehen</h2>
                        </div>
                        <div class="ignis-card__body grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="discord-name" class="ignis-field__label">Name</label>
                                <input type="text" id="discord-name" name="name" class="ignis-input" minlength="2" maxlength="32" value="<?= htmlspecialchars($bot['name']) ?>"<?= $bot['connected'] ? '' : ' disabled' ?>>
                                <p class="ignis-field__hint">So heißt der Bot in Discord. Discord lässt den Namen nur ein paar Mal pro Stunde ändern.</p>
                            </div>
                            <div>
                                <label for="discord-avatar" class="ignis-field__label">Profilbild</label>
                                <input type="file" id="discord-avatar" name="avatar" class="ignis-input" accept="image/png,image/jpeg,image/gif,image/webp"<?= $bot['connected'] ? '' : ' disabled' ?>>
                                <p class="ignis-field__hint">PNG, JPG, GIF oder WebP, höchstens 2 MB. Leer lassen behält das jetzige Bild.</p>
                            </div>
                        </div>
                    </div>

                    <div class="ignis-card mb-4">
                        <div class="ignis-card__header">
                            <h2 class="ignis-card__title">Direktnachrichten</h2>
                        </div>
                        <div class="ignis-card__body">
                            <p class="ignis-field__hint mt-0 mb-3">Diese Benachrichtigungen bekommt jede Person zusätzlich per Discord, wenn ihr Konto oder ihr Mitarbeiter eine Discord-ID hat. Abschalten kann es jeder für sich im Kontomenü oben rechts. Einladungen aus dem Mitarbeiterprofil gehen unabhängig davon.</p>
                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                <?php foreach ($types as $typeKey => $typeLabel): ?>
                                    <label class="ignis-checkbox">
                                        <input type="checkbox" name="dm_types[]" value="<?= htmlspecialchars($typeKey) ?>"<?= in_array($typeKey, $bot['dmTypes'], true) ? ' checked' : '' ?>>
                                        <span><?= htmlspecialchars($typeLabel) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="ignis-field__hint mt-3 mb-0">Verschickt wird über die Warteschlange (Cronjob „queue.work“), also mit bis zu fünf Minuten Verzögerung.</p>
                        </div>
                    </div>

                    <div class="twplus-sticky-actions mb-6">
                        <button type="submit" class="ignis-btn ignis-btn--primary">
                            <i class="fa-solid fa-save" aria-hidden="true"></i> Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        (() => {
            const form = document.getElementById('discordDisconnectForm');
            if (!form) return;
            form.addEventListener('submit', async (event) => {
                if (form.dataset.confirmed === '1' || typeof window.showConfirm !== 'function') return;
                event.preventDefault();
                const ok = await window.showConfirm('Das Token wird gelöscht und der Bot schickt keine Nachrichten mehr. Auf Discord bleibt er bestehen.', {
                    title: 'Discord-Bot trennen',
                    confirmText: 'Trennen',
                });
                if (!ok) return;
                form.dataset.confirmed = '1';
                form.requestSubmit();
            });
        })();
    </script>
