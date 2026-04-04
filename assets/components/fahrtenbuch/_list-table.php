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
 */

$canEdit = $canEdit ?? false;
$canDelete = $canDelete ?? false;
$actionsUrl = $actionsUrl ?? '';

$fahrttypBadges = [
    'einsatzfahrt'   => 'danger',
    'bewegungsfahrt' => 'info',
    'werkstattfahrt' => 'warning',
    'uebungsfahrt'   => 'success',
    'dienstfahrt'    => 'primary',
    'sonstige'       => 'secondary',
];
?>

<?php if (empty($entries)): ?>
    <div class="text-center py-4 <?= $context === 'admin' ? 'text-muted' : 'text-light' ?>" style="opacity:0.6;">
        <i class="fa-solid fa-book fa-2x mb-2"></i>
        <div>Keine Fahrtenbuch-Einträge vorhanden</div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table <?= $context === 'admin' ? 'intra__table' : 'table-dark-custom' ?> table-sm mb-0" id="fahrtenbuchTable">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Abfahrt</th>
                    <th>Ankunft</th>
                    <?php if ($context === 'admin'): ?><th>Fahrzeug</th><?php endif; ?>
                    <th>Fahrer</th>
                    <th>Fahrttyp</th>
                    <th>km</th>
                    <th>Grund</th>
                    <?php if ($canEdit || $canDelete): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $e):
                    $typSlug = $e['fahrttyp'] ?? '';
                    $typLabel = $fahrttypen[$typSlug] ?? $typSlug;
                    $typBadge = $fahrttypBadges[$typSlug] ?? 'secondary';
                ?>
                    <tr>
                        <td><?= date('d.m.Y', strtotime($e['datum'])) ?></td>
                        <td><?= date('H:i', strtotime($e['abfahrt'])) ?></td>
                        <td><?= $e['ankunft'] ? date('H:i', strtotime($e['ankunft'])) : '<span class="text-muted">—</span>' ?></td>
                        <?php if ($context === 'admin'): ?>
                            <td><?= htmlspecialchars($e['vehicle_name'] ?? $e['vehicle_identifier']) ?></td>
                        <?php endif; ?>
                        <td><?= htmlspecialchars($e['fahrer_name']) ?></td>
                        <td><span class="badge text-bg-<?= $typBadge ?>"><?= htmlspecialchars($typLabel) ?></span></td>
                        <td><?= $e['kilometer'] !== null ? number_format((float)$e['kilometer'], 1, ',', '.') : '—' ?></td>
                        <td class="text-truncate" style="max-width:200px;" title="<?= htmlspecialchars($e['grund'] ?? '') ?>">
                            <?= htmlspecialchars($e['grund'] ?? '') ?: '<span class="text-muted">—</span>' ?>
                        </td>
                        <?php if ($canEdit || $canDelete): ?>
                            <td class="text-end text-nowrap">
                                <?php if ($canEdit): ?>
                                    <button type="button" class="btn btn-sm btn-ghost fb-edit-btn"
                                            data-id="<?= $e['id'] ?>"
                                            data-datum="<?= htmlspecialchars($e['datum']) ?>"
                                            data-abfahrt="<?= date('H:i', strtotime($e['abfahrt'])) ?>"
                                            data-ankunft="<?= $e['ankunft'] ? date('H:i', strtotime($e['ankunft'])) : '' ?>"
                                            data-vehicle-id="<?= (int)($e['vehicle_id'] ?? 0) ?>"
                                            data-vehicle-identifier="<?= htmlspecialchars($e['vehicle_identifier']) ?>"
                                            data-fahrer-name="<?= htmlspecialchars($e['fahrer_name']) ?>"
                                            data-fahrttyp="<?= htmlspecialchars($e['fahrttyp']) ?>"
                                            data-kilometer="<?= htmlspecialchars($e['kilometer'] ?? '') ?>"
                                            data-stationierungsort="<?= htmlspecialchars($e['stationierungsort'] ?? '') ?>"
                                            data-grund="<?= htmlspecialchars($e['grund'] ?? '') ?>"
                                            title="Bearbeiten">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <form method="POST" action="<?= htmlspecialchars($actionsUrl) ?>" class="d-inline"
                                          onsubmit="return confirm('Eintrag wirklich löschen?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($context) ?>">
                                        <button type="submit" class="btn btn-sm btn-ghost text-danger" title="Löschen">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
