<?php
/**
 * View: MANV-Aktionslog
 *
 * @var array<string,mixed>            $lage
 * @var array<int,array<string,mixed>> $logEntries
 * @var \PDO                           $pdo
 */

$lageId     = (int) $lage['id'];
$SITE_TITLE = 'Aktionslog - ' . htmlspecialchars($lage['einsatznummer']);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" id="manv-log" data-page="edivi">
    <?php include __DIR__ . '/../../assets/components/navbar.php'; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1>Aktionslog</h1>
                    <p class="text-gray-400">MANV-Lage: <?= htmlspecialchars($lage['einsatznummer']) ?></p>
                </div>
                <div class="md:text-right">
                    <a href="<?= BASE_PATH ?>manv/board?id=<?= $lageId ?>" class="ignis-btn ignis-btn--ghost no-underline hover:no-underline">
                        <i class="fas fa-arrow-left mr-2"></i>Zurück
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (empty($logEntries)): ?>
                        <p class="text-gray-400">Keine Logeinträge vorhanden.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Zeitpunkt</th>
                                        <th>Aktion</th>
                                        <th>Beschreibung</th>
                                        <th>Benutzer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logEntries as $entry): ?>
                                        <tr>
                                            <td><small><?= date('d.m.Y H:i:s', strtotime($entry['timestamp'])) ?></small></td>
                                            <td>
                                                <span class="ignis-chip ignis-chip--primary">
                                                    <?= htmlspecialchars($entry['aktion']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($entry['beschreibung'] ?? '-') ?></td>
                                            <td><small><?= htmlspecialchars($entry['benutzer_name'] ?? 'System') ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../assets/components/footer.php'; ?>
</body>

</html>
