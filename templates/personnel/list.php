<?php
/**
 * View: Mitarbeiter-Übersicht
 *
 * Sortierung, Suche, Filter und Seiten laufen über den Server
 * (App\Support\ListQuery); der CSV-Export nimmt denselben Filterstand
 * (`?export=csv`).
 *
 * @var \Illuminate\Support\Collection<int, \App\Models\Personnel>            $mitarbeiter  Zeilen der aktuellen Seite
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Rank>      $dienstgrade  (aktive, sortiert)
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\AmbSkill>  $rdQualis
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\FdSkill>   $fwQualis
 * @var bool                                                                 $showArchive
 * @var \App\Support\ListQuery                                               $list
 */

use App\Auth\Gate;

$layout = 'admin';
$bodyId = 'mitarbeiter';
$SITE_TITLE = 'Mitarbeiter';

$pgPath  = 'personnel/list';
$pgLabel = 'Mitarbeiter';
?>
    <div class="container-full relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="twplus-page">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 mb-5 px-3">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item is-active">Mitarbeiter</span></nav>
                    <div class="page-header twplus-page-header mb-4">
                        <div class="twplus-page-header__copy">
                            <p class="twplus-page-header__eyebrow">Personal</p>
                            <h1><?= $showArchive ? 'Archivierte Mitarbeiter' : 'Mitarbeiterübersicht' ?></h1>
                            <p class="twplus-page-header__description">Mitarbeiter, Dienstgrade und Qualifikationen zentral verwalten.</p>
                        </div>
                        <div class="header-actions twplus-page-header__actions">
                            <?php if ($showArchive): ?>
                                <a href="<?= BASE_PATH ?>personnel/list" class="ignis-btn ignis-btn--secondary">Aktive Mitarbeiter</a>
                            <?php else: ?>
                                <a href="<?= BASE_PATH ?>personnel/list?archiv=1" class="ignis-btn ignis-btn--secondary">Archiv</a>
                            <?php endif; ?>
                            <?php if (Gate::allows('personnel.create') && !$showArchive): ?>
                                <button type="button" class="ignis-btn ignis-btn--primary" onclick="openCreateMitarbeiterModal()">
                                    <i class="fa-solid fa-plus mr-1"></i>Neuer Mitarbeiter
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form class="ignis-list-toolbar" method="get" action="<?= BASE_PATH . $pgPath ?>" id="mitarbeiterFilter" role="search">
                        <?php if ($showArchive): ?>
                            <input type="hidden" name="archiv" value="1">
                        <?php endif; ?>
                        <?php if ($list->sort !== 'einstdatum' || $list->dir !== 'asc'): ?>
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($list->sort) ?>">
                            <input type="hidden" name="dir" value="<?= htmlspecialchars($list->dir) ?>">
                        <?php endif; ?>
                        <label class="ignis-list-toolbar__search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input class="ignis-input" type="search" name="q" value="<?= htmlspecialchars($list->q) ?>" placeholder="Name oder Dienstnummer" aria-label="Mitarbeiter suchen">
                        </label>
                        <label class="ignis-field" for="filterDienstgrad">
                            <span class="ignis-field__label text-sm">Dienstgrad</span>
                            <select class="ignis-input" name="dg" id="filterDienstgrad">
                                <option value="">Alle</option>
                                <?php foreach ($dienstgrade as $dg): ?>
                                    <option value="<?= (int) $dg->id ?>"<?= $list->filter('dg') === (string) $dg->id ? ' selected' : '' ?>><?= htmlspecialchars($dg->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="ignis-field" for="filterRDQuali">
                            <span class="ignis-field__label text-sm">RD-Qualifikation</span>
                            <select class="ignis-input" name="rd" id="filterRDQuali">
                                <option value="">Alle</option>
                                <?php foreach ($rdQualis as $rd): ?>
                                    <?php if (!$rd->none): ?>
                                        <option value="<?= (int) $rd->id ?>"<?= $list->filter('rd') === (string) $rd->id ? ' selected' : '' ?>><?= htmlspecialchars($rd->name) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="ignis-field" for="filterFWQuali">
                            <span class="ignis-field__label text-sm">FW-Qualifikation</span>
                            <select class="ignis-input" name="fw" id="filterFWQuali">
                                <option value="">Alle</option>
                                <?php foreach ($fwQualis as $fw): ?>
                                    <?php if (!$fw->none): ?>
                                        <option value="<?= (int) $fw->id ?>"<?= $list->filter('fw') === (string) $fw->id ? ' selected' : '' ?>><?= htmlspecialchars($fw->name) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--sm">Filtern</button>
                        <?php if ($list->q !== '' || $list->filter('dg') !== '' || $list->filter('rd') !== '' || $list->filter('fw') !== ''): ?>
                            <a class="ignis-btn ignis-btn--ghost ignis-btn--sm" href="<?= htmlspecialchars($list->url($pgPath, ['q' => null, 'dg' => null, 'rd' => null, 'fw' => null, 'page' => null])) ?>">
                                <i class="fa-solid fa-rotate-left"></i> Zurücksetzen
                            </a>
                        <?php endif; ?>
                        <span class="ignis-list-toolbar__spacer"></span>
                        <a class="ignis-btn ignis-btn--ghost ignis-btn--sm" href="<?= htmlspecialchars($list->url($pgPath, ['export' => 'csv', 'page' => null])) ?>" data-ignis-tooltip="Gefilterte Liste als CSV exportieren">
                            <i class="fa-solid fa-file-csv"></i> CSV-Export
                        </a>
                    </form>

                    <div class="twplus-table-card">
                        <div class="twplus-table-card__scroll">
                        <table class="ignis-table" id="mitarbeiterTable">
                            <thead>
                                <tr>
                                    <?= $list->th('dienstnr', 'Dienstnummer', $pgPath) ?>
                                    <?= $list->th('name', 'Name', $pgPath) ?>
                                    <?= $list->th('dienstgrad', 'Dienstgrad', $pgPath) ?>
                                    <?= $list->th('rd', 'RD-Quali', $pgPath) ?>
                                    <?= $list->th('fw', 'FW-Quali', $pgPath) ?>
                                    <?= $list->th('einstdatum', 'Einstellungsdatum', $pgPath) ?>
                                    <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($mitarbeiter->isEmpty()): ?>
                                    <tr><td colspan="7" class="ignis-table-empty">Keine Mitarbeiter gefunden.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($mitarbeiter as $m):
                                    $einstellungsdatum = $m->einstdatum->format('d.m.Y');
                                    $rd        = $m->rdQualiModel;
                                    $fw        = $m->fwQualiModel;
                                    $fwShort   = $fw !== null ? $fw->shortname : '-';
                                    $fwName    = $fw !== null ? $fw->name : '';
                                    $isRdNone  = $rd === null || $rd->none;
                                    $isFwNone  = $fw === null || $fw->none;
                                    $badgeImg  = $m->dienstgradModel?->badge;
                                    $profileUrl = BASE_PATH . 'personnel/profile?id=' . (int) $m->id;
                                ?>
                                    <tr>
                                        <td><span class="ignis-mono"><?= htmlspecialchars($m->dienstnr) ?></span></td>
                                        <td>
                                            <a href="<?= $profileUrl ?>" data-mitarbeiter-card="<?= (int) $m->id ?>" class="no-underline">
                                                <?= htmlspecialchars($m->fullname) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if (!empty($badgeImg)): ?>
                                                <img src="<?= htmlspecialchars($badgeImg) ?>" height="16" width="auto" style="padding-right:5px" alt="Dienstgrad" loading="lazy" />
                                            <?php endif; ?>
                                            <?= htmlspecialchars($m->dienstgradLabel()) ?>
                                        </td>
                                        <td>
                                            <?php if (!$isRdNone): ?>
                                                <span class="ignis-chip ignis-chip--warn"><?= htmlspecialchars($m->rdQualiLabel()) ?></span>
                                            <?php else: ?>
                                                <span class="text-[var(--text-3)]">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$isFwNone): ?>
                                                <span class="ignis-chip ignis-chip--danger"><?= htmlspecialchars($fwShort) ?></span> <small><?= htmlspecialchars($fwName) ?></small>
                                            <?php else: ?>
                                                <span class="text-[var(--text-3)]">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($einstellungsdatum) ?></td>
                                        <td class="ignis-table__actions">
                                            <div class="ignis-row-actions">
                                                <a href="<?= $profileUrl ?>" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="Profil ansehen" aria-label="Profil ansehen">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Die Auswahl in den Filtern schickt das Formular ab; ohne JS bleibt der Knopf.
        document.querySelectorAll('#mitarbeiterFilter select').forEach(function (select) {
            select.addEventListener('change', function () { select.form.requestSubmit(); });
        });
    </script>

    <?php if (Gate::allows('personnel.create')): ?>
    <!-- Form-Body als <template>; Dialog wird in JS programmatisch geoeffnet. -->
    <template id="createMitarbeiterFormTemplate">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <div class="form-floating">
                                    <input class="ignis-input" type="text" name="fullname" id="cm_fullname" placeholder="Vor- und Zuname" required>
                                    <label for="cm_fullname">Vor- und Zuname</label>
                                    <div class="invalid-feedback">Pflichtfeld</div>
                                </div>
                            </div>
                            <div>
                                <div class="form-floating">
                                    <input class="ignis-input" type="date" name="gebdatum" id="cm_gebdatum" min="1900-01-01" placeholder="Geburtsdatum" required>
                                    <label for="cm_gebdatum">Geburtsdatum</label>
                                    <div class="invalid-feedback">Pflichtfeld</div>
                                </div>
                            </div>
                            <div>
                                <div class="form-floating">
                                    <select class="form-select" name="dienstgrad" id="cm_dienstgrad" required>
                                        <option value="" selected hidden>Bitte wählen</option>
                                        <?php foreach ($dienstgrade as $dg): ?>
                                            <option value="<?= (int) $dg->id ?>"><?= htmlspecialchars($dg->name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="cm_dienstgrad">Dienstgrad</label>
                                    <div class="invalid-feedback">Pflichtfeld</div>
                                </div>
                            </div>
                            <div>
                                <div class="form-floating">
                                    <select name="geschlecht" id="cm_geschlecht" class="form-select" required>
                                        <option value="" selected hidden>Bitte wählen</option>
                                        <option value="0">Männlich</option>
                                        <option value="1">Weiblich</option>
                                        <option value="2">Divers</option>
                                    </select>
                                    <label for="cm_geschlecht">Geschlecht</label>
                                    <div class="invalid-feedback">Pflichtfeld</div>
                                </div>
                            </div>
                            <?php if (defined('CHAR_ID') && CHAR_ID): ?>
                                <div>
                                    <div class="form-floating">
                                        <input class="ignis-input" type="text" name="charakterid" id="cm_charakterid" placeholder="ABC12345" pattern="[a-zA-Z]{3}[0-9]{5}" required>
                                        <label for="cm_charakterid">Charakter-ID</label>
                                        <div class="invalid-feedback">Format: ABC12345</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="form-floating">
                                    <input class="ignis-input" type="text" inputmode="numeric" name="discordtag" id="cm_discordtag" pattern="[0-9]{17,20}" maxlength="20" placeholder="Discord-ID" required>
                                    <label for="cm_discordtag">Discord-ID</label>
                                    <div class="invalid-feedback">17-20 Ziffern</div>
                                </div>
                            </div>
                            <div>
                                <div class="form-floating">
                                    <input class="ignis-input" type="text" name="telefonnr" id="cm_telefonnr" placeholder="Telefonnummer" value="0176 00 00 00 0">
                                    <label for="cm_telefonnr">Telefonnummer</label>
                                </div>
                            </div>
                            <div class="dienstnr-container">
                                <div class="form-floating">
                                    <input class="ignis-input" type="text" name="dienstnr" id="dienstnr"
                                        pattern="^(?=.*[0-9])[A-Za-z0-9\-]+$" title="z.B. RD-001, BF01" placeholder="Dienstnummer" required>
                                    <label for="dienstnr">Dienstnummer</label>
                                    <div id="dienstnr-status" class="dienstnr-status"></div>
                                    <div class="invalid-feedback">Mindestens eine Zahl (z.B. RD-001)</div>
                                    <div id="dienstnr-feedback" class="text-[#d46b6b] text-sm" style="display: none;"></div>
                                </div>
                            </div>
                            <div>
                                <div class="form-floating">
                                    <input class="ignis-input" type="date" name="einstdatum" id="cm_einstdatum" min="2022-01-01" placeholder="Einstellungsdatum" required>
                                    <label for="cm_einstdatum">Einstellungsdatum</label>
                                    <div class="invalid-feedback">Pflichtfeld</div>
                                </div>
                            </div>
                        </div>
    </template>
    <?php endif; ?>
    <?php if (Gate::allows('personnel.create')): ?>
    <script src="<?= BASE_PATH ?>assets/js/dienstnr-check.js"></script>
    <script>
        // Form-Reset entfaellt: jeder Open-Aufruf klont das <template> frisch,
        // sodass das Form leer und ohne validierungs-Flags startet.
        function openCreateMitarbeiterModal() {
            var tpl = document.getElementById('createMitarbeiterFormTemplate');
            if (!tpl) return;

            var form = document.createElement('form');
            form.id = 'createMitarbeiterForm';
            form.noValidate = true;
            form.appendChild(tpl.content.cloneNode(true));

            var dlg = new Dialog({
                title:   'Neuer Mitarbeiter',
                size:    'lg',
                body:    form,
                actions: [
                    { label: 'Abbrechen', variant: 'ghost', onClick: function (d) { d.close(null); } },
                    {
                        labelHtml: '<i class="fa-solid fa-plus mr-1"></i>Mitarbeiter erstellen',
                        variant:   'success',
                        primary:   true,
                        onClick:   function (d) { submitForm(form, d); },
                    },
                ],
                onOpen: function () {
                    // Dienstnr-Live-Validation an die geklonten Felder binden.
                    initDienstnrCheck({ basePath: '<?= BASE_PATH ?>' });
                },
            });
            dlg.open();
        }

        function submitForm(form, dlg) {
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }

            // Action-Button im Footer (zweiter ignis-dialog__action)
            var submitBtn = dlg.element.querySelector('[data-dialog-primary="true"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Wird erstellt...';
            }

            var formData = new FormData(form);
            fetch('<?= BASE_PATH ?>personnel/create', {
                method: 'POST',
                body:   formData,
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(function () { window.location.href = data.redirect; }, 500);
                } else {
                    showToast(data.message || 'Fehler beim Erstellen', 'danger');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fa-solid fa-plus mr-1"></i>Mitarbeiter erstellen';
                    }
                }
            })
            .catch(function () {
                showToast('Verbindungsfehler', 'danger');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fa-solid fa-plus mr-1"></i>Mitarbeiter erstellen';
                }
            });
        }
    </script>
    <?php endif; ?>
