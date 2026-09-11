<?php
/**
 * Fahrtenbuch List Table Partial
 *
 * Required variables:
 *   $entries (array)    - Fahrtenbuch entries from DB
 *   $fahrttypen (array) - Trip type labels [slug => label]
 *   $context (string)   - 'enotf', 'firetab', or 'admin'
 *
 * Optional variables:
 *   $canEdit (bool)     - Can edit entries (default: false)
 *   $canDelete (bool)   - Can delete entries (default: false)
 *   $actionsUrl (string)- URL for the actions handler
 *
 * Die Zeilenaktionen sind in der Verwaltung klein (ignis-btn--sm); auf dem
 * Tablet (enotf/firetab) behalten sie die volle Höhe, damit sie sich mit
 * dem Finger treffen lassen.
 */

$canEdit = $canEdit ?? false;
$canDelete = $canDelete ?? false;
$actionsUrl = $actionsUrl ?? '';

// Fahrttyp => Chip-Semantik
$fahrttypChips = [
    'einsatzfahrt'   => 'danger',
    'bewegungsfahrt' => 'info',
    'werkstattfahrt' => 'warn',
    'uebungsfahrt'   => 'ok',
    'dienstfahrt'    => 'primary',
    'sonstige'       => 'secondary',
];
$actionSize = $context === 'admin' ? ' ignis-btn--sm' : '';
?>

<?php if (empty($entries)): ?>
    <div class="ignis-table-empty">
        <i class="fa-solid fa-book" aria-hidden="true"></i> Keine Fahrtenbuch-Einträge vorhanden
    </div>
<?php else: ?>
    <div class="twplus-table-card__scroll">
        <table class="ignis-table" id="fahrtenbuchTable">
            <thead>
                <tr>
                    <th scope="col">Datum</th>
                    <th scope="col">Abfahrt</th>
                    <th scope="col">Ankunft</th>
                    <?php if ($context === 'admin'): ?><th scope="col">Fahrzeug</th><?php endif; ?>
                    <th scope="col">Fahrer</th>
                    <th scope="col">Fahrttyp</th>
                    <th scope="col" class="ignis-table__num">km</th>
                    <th scope="col">Grund</th>
                    <?php if ($canEdit || $canDelete): ?><th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $e):
                    $typSlug  = $e['fahrttyp'] ?? '';
                    $typLabel = $fahrttypen[$typSlug] ?? $typSlug;
                    $typChip  = $fahrttypChips[$typSlug] ?? 'secondary';
                ?>
                    <tr>
                        <td><?= \App\Helpers\DateTimeHelper::formatDateLocal($e['datum']) ?></td>
                        <td><?= \App\Helpers\DateTimeHelper::formatTimeLocal($e['abfahrt']) ?></td>
                        <td><?= $e['ankunft'] ? \App\Helpers\DateTimeHelper::formatTimeLocal($e['ankunft']) : '<span class="text-[var(--text-3)]">—</span>' ?></td>
                        <?php if ($context === 'admin'): ?>
                            <td><?= htmlspecialchars($e['vehicle_name'] ?? $e['vehicle_identifier']) ?></td>
                        <?php endif; ?>
                        <td><?= htmlspecialchars($e['fahrer_name']) ?></td>
                        <td><span class="ignis-chip ignis-chip--<?= $typChip ?>"><?= htmlspecialchars($typLabel) ?></span></td>
                        <td class="ignis-table__num"><?= $e['kilometer'] !== null ? number_format((float)$e['kilometer'], 1, ',', '.') : '—' ?></td>
                        <td class="max-w-[200px] truncate" title="<?= htmlspecialchars($e['grund'] ?? '') ?>">
                            <?= htmlspecialchars($e['grund'] ?? '') ?: '<span class="text-[var(--text-3)]">—</span>' ?>
                        </td>
                        <?php if ($canEdit || $canDelete): ?>
                            <td class="ignis-table__actions">
                                <div class="ignis-row-actions">
                                    <?php if ($canEdit): ?>
                                        <button type="button" class="ignis-btn<?= $actionSize ?> ignis-btn--ghost ignis-btn--icon fb-edit-btn"
                                                data-id="<?= $e['id'] ?>"
                                                data-datum="<?= htmlspecialchars($e['datum']) ?>"
                                                data-abfahrt="<?= \App\Helpers\DateTimeHelper::formatTimeLocal($e['abfahrt']) ?>"
                                                data-ankunft="<?= $e['ankunft'] ? \App\Helpers\DateTimeHelper::formatTimeLocal($e['ankunft']) : '' ?>"
                                                data-vehicle-id="<?= (int)($e['vehicle_id'] ?? 0) ?>"
                                                data-vehicle-identifier="<?= htmlspecialchars($e['vehicle_identifier']) ?>"
                                                data-fahrer-name="<?= htmlspecialchars($e['fahrer_name']) ?>"
                                                data-fahrttyp="<?= htmlspecialchars($e['fahrttyp']) ?>"
                                                data-kilometer="<?= htmlspecialchars($e['kilometer'] ?? '') ?>"
                                                data-stationierungsort="<?= htmlspecialchars($e['stationierungsort'] ?? '') ?>"
                                                data-grund="<?= htmlspecialchars($e['grund'] ?? '') ?>"
                                                title="Bearbeiten" aria-label="Eintrag bearbeiten">
                                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                        <form method="POST" action="<?= htmlspecialchars($actionsUrl) ?>" class="inline"
                                              onsubmit="<?= confirm_attr('Eintrag wirklich löschen?') ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($context) ?>">
                                            <button type="submit" class="ignis-btn<?= $actionSize ?> ignis-btn--ghost-danger ignis-btn--icon" title="Löschen" aria-label="Eintrag löschen">
                                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
