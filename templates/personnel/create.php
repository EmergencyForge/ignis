<?php
/**
 * View: Mitarbeiter anlegen. Als Seite oder, mit `X-Requested-With: fragment`,
 * als Inhalt des Drawers (assets/js/ui/drawer-form.js). Die Dienstnummer
 * wird beim Tippen gegen /api/personnel/check-dienstnr geprüft
 * (assets/js/dienstnr-check.js); das Skript wird nachgeladen, wenn die
 * Seite es noch nicht hat, damit es auch im Drawer läuft.
 *
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Rank> $dienstgrade
 */

$layout = 'admin';
$bodyId = 'mitarbeiter';
$SITE_TITLE = 'Mitarbeiter anlegen';

// Über constant(), damit PHPStan den Wert nicht aus config.php rät.
$charIdRequired = defined('CHAR_ID') ? (bool) constant('CHAR_ID') : false;
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>personnel/list">Mitarbeiter</a></span> <span class="ignis-breadcrumb__item is-active">Anlegen</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Personal</p><h1>Mitarbeiter anlegen</h1><p class="twplus-page-header__description">Stammdaten, Dienstgrad und Dienstnummer; Qualifikationen kommen danach im Profil.</p></div>
            </div>

            <form method="POST" action="<?= BASE_PATH ?>personnel/create" class="ignis-card ignis-form-card" data-ignis-form="personnel-create">
                <div class="ignis-card__body">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label for="cm_fullname" class="ignis-field__label">Vor- und Zuname</label>
                            <input class="ignis-input" type="text" name="fullname" id="cm_fullname" value="<?= htmlspecialchars((string) old('fullname')) ?>" placeholder="Max Mustermann" required autofocus>
                        </div>
                        <div>
                            <label for="cm_gebdatum" class="ignis-field__label">Geburtsdatum</label>
                            <input class="ignis-input" type="date" name="gebdatum" id="cm_gebdatum" min="1900-01-01" value="<?= htmlspecialchars((string) old('gebdatum')) ?>" required>
                        </div>
                        <div>
                            <label for="cm_dienstgrad" class="ignis-field__label">Dienstgrad</label>
                            <select class="ignis-input" name="dienstgrad" id="cm_dienstgrad" required>
                                <option value="" hidden<?= old('dienstgrad', '') === '' ? ' selected' : '' ?>>Bitte wählen</option>
                                <?php foreach ($dienstgrade as $dg): ?>
                                    <option value="<?= (int) $dg->id ?>"<?= (string) old('dienstgrad', '') === (string) $dg->id ? ' selected' : '' ?>><?= htmlspecialchars((string) $dg->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="cm_geschlecht" class="ignis-field__label">Geschlecht</label>
                            <select name="geschlecht" id="cm_geschlecht" class="ignis-input" required>
                                <option value="" hidden<?= old('geschlecht', '') === '' ? ' selected' : '' ?>>Bitte wählen</option>
                                <?php foreach (['Männlich', 'Weiblich', 'Divers'] as $genderKey => $genderLabel): ?>
                                    <option value="<?= $genderKey ?>"<?= (string) old('geschlecht', '') === (string) $genderKey ? ' selected' : '' ?>><?= $genderLabel ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($charIdRequired): ?>
                            <div>
                                <label for="cm_charakterid" class="ignis-field__label">Charakter-ID <small class="form-hint">(Format ABC12345)</small></label>
                                <input class="ignis-input ignis-mono" type="text" name="charakterid" id="cm_charakterid" placeholder="ABC12345" pattern="[a-zA-Z]{3}[0-9]{5}" value="<?= htmlspecialchars((string) old('charakterid')) ?>" required>
                            </div>
                        <?php endif; ?>
                        <div>
                            <label for="cm_discordtag" class="ignis-field__label">Discord-ID <small class="form-hint">(17 bis 20 Ziffern)</small></label>
                            <input class="ignis-input ignis-mono" type="text" inputmode="numeric" name="discordtag" id="cm_discordtag" pattern="[0-9]{17,20}" maxlength="20" value="<?= htmlspecialchars((string) old('discordtag')) ?>" placeholder="123456789012345678" required>
                        </div>
                        <div>
                            <label for="cm_telefonnr" class="ignis-field__label">Telefonnummer</label>
                            <input class="ignis-input" type="text" name="telefonnr" id="cm_telefonnr" value="<?= htmlspecialchars((string) old('telefonnr', '0176 00 00 00 0')) ?>">
                        </div>
                        <div class="dienstnr-container">
                            <label for="dienstnr" class="ignis-field__label">Dienstnummer <small class="form-hint">(z.B. RD-001, BF01)</small></label>
                            <input class="ignis-input ignis-mono" type="text" name="dienstnr" id="dienstnr" pattern="^(?=.*[0-9])[A-Za-z0-9\-]+$" title="z.B. RD-001, BF01" placeholder="RD-001" value="<?= htmlspecialchars((string) old('dienstnr')) ?>" required>
                            <div id="dienstnr-status" class="dienstnr-status"></div>
                            <div id="dienstnr-feedback" class="ignis-field__error" style="display: none;"></div>
                        </div>
                        <div>
                            <label for="cm_einstdatum" class="ignis-field__label">Einstellungsdatum</label>
                            <input class="ignis-input" type="date" name="einstdatum" id="cm_einstdatum" min="2022-01-01" value="<?= htmlspecialchars((string) old('einstdatum', date('Y-m-d'))) ?>" required>
                        </div>
                    </div>
                </div>
                <div class="ignis-card__footer ignis-form-card__footer">
                    <a href="<?= BASE_PATH ?>personnel/list" class="ignis-btn ignis-btn--ghost" data-ignis-drawer-cancel>Abbrechen</a>
                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Mitarbeiter anlegen</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Live-Prüfung der Dienstnummer. Im Drawer ist das Skript der Seite
        // nicht unbedingt da, deshalb bei Bedarf nachladen und dann binden.
        (function () {
            var basePath = <?= json_encode(BASE_PATH) ?>;
            function bind() { if (window.initDienstnrCheck) window.initDienstnrCheck({ basePath: basePath }); }
            if (window.initDienstnrCheck) { bind(); return; }
            var s = document.createElement('script');
            s.src = basePath + 'assets/js/dienstnr-check.js';
            s.onload = bind;
            document.head.appendChild(s);
        })();
    </script>
