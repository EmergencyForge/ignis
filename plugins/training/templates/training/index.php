<?php
/**
 * Ausbildung: eigene Lehrgänge mit Stand, darunter die Lehrgänge mit
 * offener Anmeldung.
 *
 * @var list<array{participation: \Plugin\Training\Models\Participation, percent: ?int}> $mine
 * @var list<array{course: \Plugin\Training\Models\Course, missing: list<string>, closed: bool}> $open
 * @var bool $linked
 * @var bool $canTeach
 * @var bool $canManage
 */

use Plugin\Training\View;

$layout     = 'admin';
$bodyId     = 'training';
$SITE_TITLE = 'Ausbildung';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Personal</p>
                    <h1>Ausbildung</h1>
                    <p class="twplus-page-header__description">Deine Lehrgänge mit Unterlagen und Stand, darunter die Lehrgänge, zu denen du dich gerade anmelden kannst.</p>
                </div>
                <?php if ($canTeach || $canManage): ?>
                    <div class="header-actions twplus-page-header__actions">
                        <?php if ($canManage): ?>
                            <a href="<?= $base ?>training/types" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Lehrgangsarten</a>
                        <?php endif; ?>
                        <a href="<?= $base ?>training/manage" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i> Lehrgänge leiten</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$linked): ?>
                <div class="ignis-card mb-4"><div class="ignis-card__body">
                    <p class="m-0">Dein Konto ist mit keinem Mitarbeiter verknüpft. Anmelden und lernen geht erst, wenn die Personalverwaltung die Verknüpfung setzt.</p>
                </div></div>
            <?php endif; ?>

            <h2 class="ignis-card__title mb-2">Meine Lehrgänge</h2>
            <?php if ($mine === []): ?>
                <div class="ignis-card mb-6"><div class="ignis-card__body">
                    <p class="ignis-detail__muted m-0">Du bist in keinem Lehrgang. Offene Lehrgänge stehen weiter unten.</p>
                </div></div>
            <?php else: ?>
                <div class="twplus-table-card mb-6">
                    <div class="twplus-table-card__scroll">
                        <table class="ignis-table" data-ignis-mobile-table>
                            <thead>
                                <tr>
                                    <th scope="col">Lehrgang</th>
                                    <th scope="col">Zeitraum</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Stand</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mine as $row): $p = $row['participation']; $c = $p->course; ?>
                                    <tr>
                                        <td data-label="Lehrgang" data-mobile-primary>
                                            <a href="<?= $base ?>training/courses/<?= (int) $c->id ?>"><?= htmlspecialchars($c->title) ?></a>
                                            <div class="ignis-detail__muted"><?= htmlspecialchars($c->type->name) ?></div>
                                        </td>
                                        <td data-label="Zeitraum"><?= htmlspecialchars($c->period()) ?></td>
                                        <td data-label="Status"><span class="ignis-chip <?= View::participationChip($p->state()) ?>"><?= htmlspecialchars($p->state()->label()) ?></span></td>
                                        <td data-label="Stand">
                                            <?php if ($row['percent'] !== null): ?>
                                                <div class="flex items-center gap-2" style="min-width: 140px">
                                                    <div class="ignis-progress ignis-progress--sm flex-1" role="progressbar" aria-valuenow="<?= (int) $row['percent'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Fortschritt"><div class="ignis-progress__bar" style="width: <?= (int) $row['percent'] ?>%"></div></div>
                                                    <span><?= (int) $row['percent'] ?> %</span>
                                                </div>
                                            <?php elseif ($p->state()->value === 'waitlisted'): ?>
                                                <span class="ignis-detail__muted">Platz <?= (int) $p->waitlist_position ?> auf der Warteliste</span>
                                            <?php else: ?>
                                                <span class="ignis-detail__muted">Unterlagen ab Lehrgangsbeginn</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <h2 class="ignis-card__title mb-2">Anmeldung offen</h2>
            <?php if ($open === []): ?>
                <div class="ignis-card"><div class="ignis-card__body">
                    <p class="ignis-detail__muted m-0">Gerade nimmt kein Lehrgang Anmeldungen an.</p>
                </div></div>
            <?php else: ?>
                <div class="twplus-table-card">
                    <div class="twplus-table-card__scroll">
                        <table class="ignis-table" data-ignis-mobile-table>
                            <thead>
                                <tr>
                                    <th scope="col">Lehrgang</th>
                                    <th scope="col">Zeitraum</th>
                                    <th scope="col">Anmeldung bis</th>
                                    <th scope="col">Voraussetzung</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($open as $row): $c = $row['course']; ?>
                                    <tr>
                                        <td data-label="Lehrgang" data-mobile-primary>
                                            <a href="<?= $base ?>training/courses/<?= (int) $c->id ?>"><?= htmlspecialchars($c->title) ?></a>
                                            <div class="ignis-detail__muted"><?= htmlspecialchars($c->type->name) ?> · <?= htmlspecialchars($c->type->enrollment()->label()) ?></div>
                                        </td>
                                        <td data-label="Zeitraum"><?= htmlspecialchars($c->period()) ?></td>
                                        <td data-label="Anmeldung bis"><?= $c->enroll_until !== null ? htmlspecialchars($c->enroll_until->format('d.m.Y, H:i')) : 'offen' ?></td>
                                        <td data-label="Voraussetzung">
                                            <?php if ($row['closed']): ?>
                                                <span class="ignis-chip ignis-chip--secondary">Anmeldeschluss vorbei</span>
                                            <?php elseif ($row['missing'] !== []): ?>
                                                <span class="ignis-chip ignis-chip--warn">Setzt <?= htmlspecialchars(implode(' und ', $row['missing'])) ?> voraus</span>
                                            <?php else: ?>
                                                <span class="ignis-chip ignis-chip--ok">Erfüllt</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
