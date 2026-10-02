<?php
/**
 * View: Benutzer-Liste
 *
 * Sortierung, Suche und Seiten laufen über den Server (App\Support\ListQuery):
 * die Kopfzellen sind Links, das Suchfeld ein GET-Formular, der Status-Filter
 * drei Links. Erwartet im Scope (gesetzt vom UserController via extract()):
 *   @var \Illuminate\Support\Collection<int, \App\Models\User> $users  Zeilen der aktuellen Seite
 *   @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles  (keyBy id)
 *   @var \App\Support\ListQuery $list
 *   @var array<string,int> $counts  Zeilen je Status für die Segmente, '' sind alle
 */

use App\Auth\Gate;

$layout = 'admin';
$bodyId = 'benutzer';
$SITE_TITLE = 'Benutzer';

$pgPath  = 'users/list';
$pgLabel = 'Benutzer';
?>
    <div class="container-full relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="twplus-page">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 min-w-0 mb-5 px-3">
                    <nav class="ignis-breadcrumb">
                        <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span>
                        <span class="ignis-breadcrumb__item" aria-current="page">Benutzer</span>
                    </nav>
                    <div class="page-header twplus-page-header mb-4">
                        <div class="twplus-page-header__copy">
                            <p class="twplus-page-header__eyebrow">Zugriffsverwaltung</p>
                            <h1>Benutzerübersicht</h1>
                            <p class="twplus-page-header__description">Konten, Rollen und Zugriffsstatus auf einen Blick.</p>
                        </div>
                    </div>

                    <form class="ignis-list-toolbar" method="get" action="<?= BASE_PATH . $pgPath ?>" role="search">
                        <?php if ($list->sort !== 'name' || $list->dir !== 'asc'): ?>
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($list->sort) ?>">
                            <input type="hidden" name="dir" value="<?= htmlspecialchars($list->dir) ?>">
                        <?php endif; ?>
                        <?php if ($list->filter('status') !== ''): ?>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($list->filter('status')) ?>">
                        <?php endif; ?>
                        <label class="ignis-list-toolbar__search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input class="ignis-input" type="search" name="q" value="<?= htmlspecialchars($list->q) ?>" placeholder="Benutzer suchen" aria-label="Benutzer suchen">
                        </label>
                        <button type="submit" class="ignis-btn ignis-btn--secondary">Suchen</button>
                        <?php if ($list->q !== ''): ?>
                            <a class="ignis-btn ignis-btn--ghost" href="<?= htmlspecialchars($list->url($pgPath, ['q' => null, 'page' => null])) ?>">Zurücksetzen</a>
                        <?php endif; ?>
                        <span class="ignis-list-toolbar__spacer"></span>
                        <nav class="ignis-segmented" aria-label="Status">
                            <?php foreach (['' => 'Alle', 'active' => 'Aktiv', 'inactive' => 'Deaktiviert'] as $statusKey => $statusLabel): ?>
                                <a href="<?= htmlspecialchars($list->url($pgPath, ['status' => $statusKey === '' ? null : $statusKey, 'page' => null])) ?>"<?= $list->filter('status') === $statusKey ? ' class="is-active" aria-current="true"' : '' ?>><?= $statusLabel ?> <span class="ignis-segmented__count"><?= $counts[$statusKey] ?? 0 ?></span></a>
                            <?php endforeach; ?>
                        </nav>
                    </form>

                    <div class="twplus-table-card">
                        <div class="twplus-table-card__scroll">
                        <table class="ignis-table" id="userTable">
                            <thead>
                                <tr>
                                    <?= $list->th('id', 'UID', $pgPath, 'ignis-table__num') ?>
                                    <?= $list->th('name', 'Name (Benutzername)', $pgPath) ?>
                                    <?= $list->th('role', 'Rolle/Gruppe', $pgPath) ?>
                                    <?= $list->th('status', 'Status', $pgPath) ?>
                                    <?= $list->th('created', 'Angelegt am', $pgPath) ?>
                                    <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($users->isEmpty()): ?>
                                    <?php
                                    $empty = $list->q !== '' || $list->filter('status') !== ''
                                        ? [
                                            'variant' => 'sm',
                                            'icon'    => 'fa-magnifying-glass',
                                            'title'   => 'Keine Benutzer gefunden',
                                            'text'    => $list->q !== '' ? 'Mit dieser Suche passt kein Konto.' : 'Mit diesem Filter passt kein Konto.',
                                            'query'   => [
                                                'term'    => $list->q,
                                                'filters' => $list->filter('status') !== ''
                                                    ? [['label' => 'Status: ' . ($list->filter('status') === 'active' ? 'Aktiv' : 'Deaktiviert'), 'removeHref' => $list->url($pgPath, ['status' => null, 'page' => null])]]
                                                    : [],
                                            ],
                                            'actions' => [['label' => 'Suche zurücksetzen', 'href' => $list->url($pgPath, ['q' => null, 'status' => null, 'page' => null]), 'style' => 'secondary']],
                                        ]
                                        : [
                                            'variant' => 'sm',
                                            'icon'    => 'fa-users',
                                            'title'   => 'Noch keine Benutzer',
                                            'text'    => 'Konten entstehen bei der ersten Anmeldung per Discord.',
                                        ];
                                    ?>
                                    <tr><td colspan="6"><?php require dirname(__DIR__) . '/partials/empty.php'; ?></td></tr>
                                <?php endif; ?>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                    if ($user->full_admin) {
                                        $roleColor = 'danger';
                                        $roleName  = 'Admin+';
                                    } else {
                                        $role      = $roles->get($user->role);
                                        $roleColor = $role->color ?? 'secondary';
                                        $roleName  = $role->name ?? 'Unbekannt';
                                    }

                                    $isActive = (bool) $user->is_active;

                                    // role.color speichert weiter die Bootstrap-Namen (bestehende
                                    // Datensätze bleiben gültig); nur der CSS-Klassenname folgt dem
                                    // neuen Chip-Vokabular (success→ok, warning→warn).
                                    $chipVariants  = ['primary', 'success', 'warning', 'danger', 'info', 'secondary'];
                                    $chipClassFor  = ['success' => 'ok', 'warning' => 'warn'];
                                    $roleChipMod   = in_array($roleColor, $chipVariants, true)
                                        ? ' ignis-chip--' . ($chipClassFor[$roleColor] ?? $roleColor)
                                        : '';

                                    $createdAt = $user->created_at;
                                    $dateFmt   = $createdAt instanceof \DateTimeInterface
                                        ? $createdAt->format('d.m.Y | H:i')
                                        : '';
                                    ?>
                                    <tr<?= $isActive ? '' : ' class="is-muted"' ?>>
                                        <td class="ignis-table__num"><?= (int) $user->id ?></td>
                                        <td>
                                            <span data-user-card="<?= (int) $user->id ?>" style="cursor:help;">
                                                <?= htmlspecialchars($user->mitarbeiter_fullname ?? 'Kein Profil verbunden') ?>
                                                (<strong><?= htmlspecialchars($user->username) ?></strong>)
                                            </span>
                                        </td>
                                        <td><span class="ignis-chip<?= $roleChipMod ?>"><?= htmlspecialchars($roleName) ?></span></td>
                                        <td>
                                            <?php if ($isActive): ?>
                                                <span class="ignis-chip ignis-chip--dot ignis-chip--ok">Aktiv</span>
                                            <?php else: ?>
                                                <span class="ignis-chip ignis-chip--dot ignis-chip--danger">Deaktiviert</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($dateFmt) ?></td>
                                        <td class="ignis-table__actions">
                                            <?php if (Gate::allows('user.update', $user)): ?>
                                                <div class="ignis-row-actions">
                                                    <a href="<?= BASE_PATH ?>users/edit?id=<?= (int) $user->id ?>"
                                                       class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon"
                                                       data-ignis-tooltip="Bearbeiten" aria-label="Bearbeiten">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
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
