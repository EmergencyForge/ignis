<?php
/**
 * View: Globaler Audit-Log
 *
 * Sortierung, Suche, Modul-Filter und Seiten laufen über den Server
 * (App\Support\ListQuery, UserController::auditlog).
 *
 * @var \Illuminate\Support\Collection<int, \stdClass> $entries  Zeilen der aktuellen Seite (mit username aus dem JOIN)
 * @var list<string>                                   $modules  Module, die im Log vorkommen
 * @var \App\Support\ListQuery                         $list
 */

$layout = 'admin';
$bodyId = 'benutzer';
$SITE_TITLE = 'Audit-Log';

$pgPath  = 'users/audit-log';
$pgLabel = 'Einträge';
?>
    <div class="container-full relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="twplus-page">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 mb-5 px-3">
                    <nav class="ignis-breadcrumb">
                        <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span>
                        <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>users/list">Benutzer</a></span>
                        <span class="ignis-breadcrumb__item is-active">Audit Log</span>
                    </nav>
                    <div class="twplus-page-header mb-5">
                        <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Sicherheit</p><h1>Audit Log</h1><p class="twplus-page-header__description">Systemweite Änderungen mit Zeitpunkt, Modul und verantwortlichem Benutzer.</p></div>
                    </div>

                    <form class="ignis-list-toolbar" method="get" action="<?= BASE_PATH . $pgPath ?>" id="auditFilter" role="search">
                        <?php if ($list->sort !== 'zeit' || $list->dir !== 'desc'): ?>
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($list->sort) ?>">
                            <input type="hidden" name="dir" value="<?= htmlspecialchars($list->dir) ?>">
                        <?php endif; ?>
                        <label class="ignis-list-toolbar__search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input class="ignis-input" type="search" name="q" value="<?= htmlspecialchars($list->q) ?>" placeholder="Aktion, Details oder Benutzer" aria-label="Log durchsuchen">
                        </label>
                        <label class="ignis-field" for="filterModul">
                            <span class="ignis-field__label text-sm">Modul</span>
                            <select class="ignis-input" name="modul" id="filterModul">
                                <option value="">Alle</option>
                                <?php foreach ($modules as $module): ?>
                                    <option value="<?= htmlspecialchars($module) ?>"<?= $list->filter('modul') === $module ? ' selected' : '' ?>><?= htmlspecialchars($module) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--sm">Filtern</button>
                        <?php if ($list->q !== '' || $list->filter('modul') !== ''): ?>
                            <a class="ignis-btn ignis-btn--ghost ignis-btn--sm" href="<?= htmlspecialchars($list->url($pgPath, ['q' => null, 'modul' => null, 'page' => null])) ?>">Zurücksetzen</a>
                        <?php endif; ?>
                    </form>

                    <div class="twplus-table-card">
                        <div class="twplus-table-card__scroll">
                        <table class="ignis-table" id="table-audit">
                            <thead>
                                <tr>
                                    <?= $list->th('zeit', 'Zeitstempel', $pgPath) ?>
                                    <?= $list->th('modul', 'Modul', $pgPath) ?>
                                    <?= $list->th('aktion', 'Aktion', $pgPath) ?>
                                    <th scope="col">Details</th>
                                    <?= $list->th('user', 'Benutzer', $pgPath) ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($entries->isEmpty()): ?>
                                    <tr><td colspan="5" class="ignis-table-empty">Keine Einträge gefunden.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($entries as $entry):
                                    $userId   = (int) ($entry->user ?? 0);
                                    $username = $entry->username ?? 'Unbekannt';
                                    $date     = (new DateTime($entry->timestamp))->format('d.m.Y  H:i:s');
                                ?>
                                    <tr>
                                        <td class="ignis-mono"><?= htmlspecialchars($date) ?></td>
                                        <td class="font-bold"><?= htmlspecialchars($entry->module ?? '') ?></td>
                                        <td style="overflow-wrap:anywhere"><?= htmlspecialchars($entry->action ?? '') ?></td>
                                        <td style="overflow-wrap:anywhere"><?= htmlspecialchars($entry->details ?? '') ?></td>
                                        <td>
                                            <a href="<?= BASE_PATH ?>users/edit?id=<?= $userId ?>"
                                               data-user-card="<?= $userId ?>">
                                                <?= htmlspecialchars($username) ?> (ID: <?= $userId ?>)
                                            </a>
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
        document.querySelectorAll('#auditFilter select').forEach(function (select) {
            select.addEventListener('change', function () { select.form.requestSubmit(); });
        });
    </script>
