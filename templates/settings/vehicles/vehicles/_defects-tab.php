<?php
/**
 * Register „Mängel" der Fahrzeugseite (show.php): alle Meldungen des
 * Fahrzeugs, offene zuerst, jede verlinkt auf ihren Anker in der
 * Mängelliste (#defect-ID).
 *
 *   @var list<array<string,mixed>> $defects
 *   @var int                       $openDefects
 *   @var string                    $defectsUrl
 *   @var bool                      $canReport
 *   @var int                       $vehicleId
 *   @var string                    $basePath
 */

use App\Helpers\DateTimeHelper;
use App\Models\VehicleDefect;
?>
<?php if ($defects === []): ?>
    <div class="ignis-table-empty">
        <p class="mb-2">Keine Mängel gemeldet.</p>
        <?php if ($canReport): ?>
            <a href="<?= htmlspecialchars($basePath . 'settings/vehicles/defects/create?vehicle=' . $vehicleId) ?>" class="ignis-btn ignis-btn--sm ignis-btn--secondary" data-ignis-drawer><i class="fa-solid fa-wrench" aria-hidden="true"></i> Mangel melden</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <p class="ignis-list-meta mb-2"><?= $openDefects === 0 ? 'Keine offenen Mängel' : ($openDefects === 1 ? '1 offener Mangel' : $openDefects . ' offene Mängel') ?>, <?= count($defects) ?> insgesamt · <a href="<?= htmlspecialchars($defectsUrl) ?>">Mängelliste des Fahrzeugs</a></p>
    <div class="twplus-table-card">
        <div class="twplus-table-card__scroll">
            <table class="ignis-table">
                <thead>
                    <tr>
                        <th scope="col">Mangel</th>
                        <th scope="col">Kategorie</th>
                        <th scope="col">Status</th>
                        <th scope="col">Gemeldet</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($defects as $defect): ?>
                        <?php
                        [$statusLabel, $statusChip] = VehicleDefect::STATUS_LABELS[(string) $defect['status']] ?? [(string) $defect['status'], 'secondary'];
                        $resolved = (string) $defect['status'] === 'resolved';
                        ?>
                        <tr<?= $resolved ? ' class="is-muted"' : '' ?>>
                            <td>
                                <a href="<?= htmlspecialchars($defectsUrl . '#defect-' . (int) $defect['id']) ?>"><?= htmlspecialchars((string) $defect['title']) ?></a>
                                <?php if ((int) ($defect['vehicle_operable'] ?? 1) === 0 && !$resolved): ?>
                                    <span class="ignis-chip ignis-chip--danger"><i class="fa-solid fa-ban" aria-hidden="true"></i> Nicht einsatzfähig</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(VehicleDefect::CATEGORY_LABELS[(string) $defect['category']] ?? (string) $defect['category']) ?></td>
                            <td><span class="ignis-chip ignis-chip--dot ignis-chip--<?= $statusChip ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                            <td>
                                <?= htmlspecialchars(DateTimeHelper::formatShortLocal((string) $defect['created_at'])) ?>
                                <?php if (!empty($defect['reporter_name'])): ?><span class="ignis-detail__muted">von <?= htmlspecialchars((string) $defect['reporter_name']) ?></span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
