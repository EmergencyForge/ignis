<?php
/**
 * View: Antragstypen verwalten. Liste als ignis-Tabelle mit der Sortierung
 * als Eingabefeld je Zeile (ein Formular, Speichern in der Fußleiste),
 * Zeilenaktionen Bearbeiten, Aktivieren/Deaktivieren und Löschen.
 *
 * @var array<int,array<string,mixed>> $typen
 */


$layout = 'admin';
$bodyId = 'antragstypen';
$SITE_TITLE = 'Antragstypen';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item is-active">Antragstypen</span></nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Formularbaukasten</p><h1>Antragstypen verwalten</h1><p class="twplus-page-header__description">Formulare, Felder, Status und Sortierung konfigurieren.</p></div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= BASE_PATH ?>forms/admin/list" class="ignis-btn ignis-btn--secondary">
                        <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> Antragsübersicht
                    </a>
                    <a href="<?= BASE_PATH ?>settings/forms/create" class="ignis-btn ignis-btn--primary">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Neuer Antragstyp
                    </a>
                </div>
            </div>


            <?php if (empty($typen)): ?>
                <div class="twplus-empty">
                    <i class="fa-solid fa-file-circle-plus twplus-empty__icon" aria-hidden="true"></i>
                    <h2 class="twplus-empty__title">Noch keine Antragstypen vorhanden</h2>
                    <p class="twplus-empty__description">Erstelle den ersten Antragstyp und füge anschließend die benötigten Felder hinzu.</p>
                    <a href="<?= BASE_PATH ?>settings/forms/create" class="ignis-btn ignis-btn--primary twplus-empty__action"><i class="fa-solid fa-plus" aria-hidden="true"></i> Neuer Antragstyp</a>
                </div>
            <?php else: ?>
                <form method="post" action="">
                    <div class="twplus-table-card">
                        <div class="twplus-table-card__scroll">
                            <table class="ignis-table" id="table-antragstypen">
                                <thead>
                                    <tr>
                                        <th scope="col" class="w-20">Sort.</th>
                                        <th scope="col" class="w-12"><span class="sr-only">Icon</span></th>
                                        <th scope="col">Name</th>
                                        <th scope="col">Beschreibung</th>
                                        <th scope="col" class="ignis-table__num">Felder</th>
                                        <th scope="col" class="ignis-table__num">Anträge</th>
                                        <th scope="col">Status</th>
                                        <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($typen as $typ):
                                        $editUrl = BASE_PATH . 'settings/forms/edit?id=' . (int) $typ['id'];
                                        $beschreibung = (string) ($typ['beschreibung'] ?? '');
                                    ?>
                                        <tr<?= $typ['aktiv'] ? '' : ' class="is-muted"' ?>>
                                            <td>
                                                <label class="sr-only" for="sort-<?= (int) $typ['id'] ?>">Sortierung <?= htmlspecialchars($typ['name']) ?></label>
                                                <input type="number"
                                                    name="sortierung[<?= (int)$typ['id'] ?>]"
                                                    id="sort-<?= (int) $typ['id'] ?>"
                                                    value="<?= (int)$typ['sortierung'] ?>"
                                                    class="ignis-input ignis-input--sm ignis-table__num">
                                            </td>
                                            <td class="text-center">
                                                <i class="<?= htmlspecialchars($typ['icon']) ?> text-xl" aria-hidden="true"></i>
                                            </td>
                                            <td><a href="<?= htmlspecialchars($editUrl) ?>"><strong><?= htmlspecialchars($typ['name']) ?></strong></a></td>
                                            <td class="text-[var(--text-3)]">
                                                <?= htmlspecialchars(mb_substr($beschreibung, 0, 80)) ?><?= mb_strlen($beschreibung) > 80 ? '…' : '' ?>
                                            </td>
                                            <td class="ignis-table__num"><?= (int)$typ['anzahl_felder'] ?></td>
                                            <td class="ignis-table__num"><?= (int)$typ['anzahl_antraege'] ?></td>
                                            <td>
                                                <?php if ($typ['aktiv']): ?>
                                                    <span class="ignis-chip ignis-chip--dot ignis-chip--ok">Aktiv</span>
                                                <?php else: ?>
                                                    <span class="ignis-chip ignis-chip--dot ignis-chip--secondary">Inaktiv</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="ignis-table__actions">
                                                <div class="ignis-row-actions">
                                                    <a href="<?= htmlspecialchars($editUrl) ?>" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="Bearbeiten" aria-label="Bearbeiten">
                                                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                                    </a>
                                                    <a href="?toggle=<?= (int)$typ['id'] ?>" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="<?= $typ['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?>" aria-label="<?= $typ['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                                        <i class="fa-solid fa-power-off" aria-hidden="true"></i>
                                                    </a>
                                                    <?php if ((int)$typ['anzahl_antraege'] === 0): ?>
                                                        <a href="?delete=<?= (int)$typ['id'] ?>" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger ignis-btn--icon" data-ignis-tooltip="Löschen" aria-label="Löschen"
                                                            onclick="event.preventDefault(); showConfirm('Antragstyp wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Antragstyp löschen'}).then(result => { if(result) window.location.href = this.href; });">
                                                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="ignis-list-footer">
                            <p class="ignis-list-meta"><?= count($typen) ?> Antragstypen</p>
                            <button type="submit" name="update_sortierung" class="ignis-btn ignis-btn--sm ignis-btn--secondary">
                                <i class="fa-solid fa-save" aria-hidden="true"></i> Sortierung speichern
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
