<?php
/**
 * View: Admin-Antragsübersicht
 *
 * Sortierung, Suche und Seiten laufen über den Server (App\Support\ListQuery).
 *
 * @var \Illuminate\Support\Collection<int, \App\Models\Form>      $antraege       Zeilen der aktuellen Seite
 * @var array<int,array{class:string,text:string,icon:string}>    $statusDisplay
 * @var \App\Support\ListQuery                                     $list
 */

use App\Auth\Gate;

$SITE_TITLE = 'Antragsübersicht';

$layout = 'admin';
$bodyId = 'mitarbeiter';

$pgPath  = 'forms/admin/list';
$pgLabel = 'Anträge';

// Chip-Semantik der Statusfarben (STATUS_DISPLAY nennt die alten Namen).
$chipFor = ['info' => 'info', 'danger' => 'danger', 'warning' => 'warn', 'success' => 'ok'];
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="mb-5">
                <div class="twplus-page-header mb-4">
                    <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Workflow</p><h1>
                        <i class="fa-solid fa-clipboard-list mr-2"></i>Antragsübersicht
                    </h1><p class="twplus-page-header__description">Eingereichte Anträge prüfen, priorisieren und bearbeiten.</p></div>
                    <div class="twplus-page-header__actions">
                    <?php if (Gate::allows('forms.decide')): ?>
                        <a href="<?= BASE_PATH ?>settings/forms/list" class="ignis-btn ignis-btn--secondary">
                            <i class="fa-solid fa-gear mr-2"></i>Antragstypen verwalten
                        </a>
                    <?php endif; ?>
                    </div>
                </div>

                    <form class="ignis-list-toolbar" method="get" action="<?= BASE_PATH . $pgPath ?>" role="search">
                        <?php if ($list->sort !== 'datum' || $list->dir !== 'desc'): ?>
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($list->sort) ?>">
                            <input type="hidden" name="dir" value="<?= htmlspecialchars($list->dir) ?>">
                        <?php endif; ?>
                        <?php if ($list->filter('status') !== ''): ?>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($list->filter('status')) ?>">
                        <?php endif; ?>
                        <label class="ignis-list-toolbar__search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input class="ignis-input" type="search" name="q" value="<?= htmlspecialchars($list->q) ?>" placeholder="Nummer, Name oder Typ" aria-label="Anträge suchen">
                        </label>
                        <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--sm">Suchen</button>
                        <?php if ($list->q !== ''): ?>
                            <a class="ignis-btn ignis-btn--ghost ignis-btn--sm" href="<?= htmlspecialchars($list->url($pgPath, ['q' => null, 'page' => null])) ?>">Zurücksetzen</a>
                        <?php endif; ?>
                        <span class="ignis-list-toolbar__spacer"></span>
                        <nav class="ignis-filter-links" aria-label="Status">
                            <a href="<?= htmlspecialchars($list->url($pgPath, ['status' => null, 'page' => null])) ?>"<?= $list->filter('status') === '' ? ' class="is-active" aria-current="true"' : '' ?>>Alle</a>
                            <?php foreach ($statusDisplay as $statusValue => $statusMeta): ?>
                                <a href="<?= htmlspecialchars($list->url($pgPath, ['status' => (string) $statusValue, 'page' => null])) ?>"<?= $list->filter('status') === (string) $statusValue ? ' class="is-active" aria-current="true"' : '' ?>><?= htmlspecialchars($statusMeta['text']) ?></a>
                            <?php endforeach; ?>
                        </nav>
                    </form>

                    <div class="twplus-table-card">
                        <div class="twplus-table-card__scroll">
                        <table class="ignis-table" id="table-antrag">
                            <thead>
                                <tr>
                                    <?= $list->th('nr', 'Nr.', $pgPath) ?>
                                    <?= $list->th('typ', 'Typ', $pgPath) ?>
                                    <?= $list->th('von', 'Von', $pgPath) ?>
                                    <?= $list->th('status', 'Status', $pgPath) ?>
                                    <?= $list->th('datum', 'Datum', $pgPath) ?>
                                    <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($antraege->isEmpty()): ?>
                                    <tr><td colspan="6" class="ignis-table-empty">Keine Anträge gefunden.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($antraege as $antrag):
                                    $status  = $statusDisplay[$antrag->cirs_status] ?? ['class' => 'secondary', 'text' => 'Unbekannt', 'icon' => ''];
                                    $viewUrl = BASE_PATH . "forms/view?antrag=" . urlencode($antrag->uniqueid);
                                ?>
                                    <tr>
                                        <td><a class="ignis-mono" href="<?= $viewUrl ?>"><?= htmlspecialchars($antrag->uniqueid) ?></a></td>
                                        <td>
                                            <i class="<?= htmlspecialchars($antrag->typ->icon ?? 'fa-solid fa-file') ?> mr-1"></i>
                                            <span class="text-sm"><?= htmlspecialchars($antrag->typ->name ?? '') ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($antrag->name_dn) ?></td>
                                        <td>
                                            <span class="ignis-chip ignis-chip--dot ignis-chip--<?= $chipFor[$status['class']] ?? 'secondary' ?>"><?= htmlspecialchars($status['text']) ?></span>
                                        </td>
                                        <td><?= $antrag->time_added->format('d.m.Y | H:i') ?></td>
                                        <td class="ignis-table__actions">
                                            <div class="ignis-row-actions">
                                                <a class="ignis-btn ignis-btn--secondary ignis-btn--sm" href="<?= $viewUrl ?>">
                                                    <i class="fa-solid fa-eye mr-1"></i>Öffnen
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
                    </div>
            </div>
        </div>
    </div>
