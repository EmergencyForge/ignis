<?php
/**
 * View: MANV-Übersicht
 *
 * @var array<int,array<string,mixed>> $lagen
 * @var array<int,array<string,int>>   $statistiken  Lage-ID → ['total_patienten', 'sk1', ..., 'transportiert', 'wartend']
 * @var string                          $statusFilter
 * @var \PDO                            $pdo
 */

$SITE_TITLE = 'MANV-Übersicht';
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
    <style>
        .manv-card {
            transition: transform 0.2s;
            cursor: pointer;
        }

        .manv-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .status-badge {
            font-size: 0.875rem;
            padding: 0.25rem 0.75rem;
        }

        .stat-box {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
        }
    </style>
</head>

<body data-bs-theme="dark" id="manv-overview" data-page="edivi">
    <?php include __DIR__ . '/../../assets/components/navbar.php'; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <?php if ($statusFilter !== 'aktiv'): ?>
                        <a href="<?= BASE_PATH ?>manv/index.php" class="ignis-btn ignis-btn--ghost mb-3 no-underline hover:no-underline">
                            <i class="fas fa-arrow-left mr-2"></i>Zurück zu aktiven Lagen
                        </a>
                    <?php endif; ?>
                    <h1>MANV-Übersicht
                        <?php if ($statusFilter === 'abgeschlossen'): ?>
                            <small class="ml-2 text-gray-400">(Abgeschlossene Lagen)</small>
                        <?php elseif ($statusFilter === 'archiviert'): ?>
                            <small class="ml-2 text-gray-400">(Archivierte Lagen)</small>
                        <?php endif; ?>
                    </h1>
                    <p class="text-gray-400">Massenanfall von Verletzten - Lagenverwaltung</p>
                </div>
                <div class="md:text-right">
                    <a href="<?= BASE_PATH ?>manv/create.php" class="ignis-btn ignis-btn--soft-primary btn-lg no-underline hover:no-underline">
                        <i class="fas fa-plus mr-2"></i>Neue MANV-Lage anlegen
                    </a>
                </div>
            </div>

            <?php if (empty($lagen)): ?>
                <div class="ignis-alert ignis-alert--info">
                    <i class="fas fa-info-circle mr-2"></i>
                    <?php
                    if ($statusFilter === 'abgeschlossen') {
                        echo 'Derzeit sind keine abgeschlossenen MANV-Lagen vorhanden.';
                    } elseif ($statusFilter === 'archiviert') {
                        echo 'Derzeit sind keine archivierten MANV-Lagen vorhanden.';
                    } else {
                        echo 'Derzeit sind keine aktiven MANV-Lagen vorhanden.';
                    }
                    ?>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($lagen as $lage):
                        $stats = $statistiken[$lage['id']] ?? [
                            'total_patienten' => 0, 'sk1' => 0, 'sk2' => 0, 'sk3' => 0, 'sk4' => 0,
                            'transportiert' => 0, 'wartend' => 0,
                        ];

                        $statusClass = 'bg-success';
                        $statusText  = 'Aktiv';
                        if ($lage['status'] === 'abgeschlossen') {
                            $statusClass = 'bg-warning';
                            $statusText  = 'Abgeschlossen';
                        } elseif ($lage['status'] === 'archiviert') {
                            $statusClass = 'bg-secondary';
                            $statusText  = 'Archiviert';
                        }
                    ?>
                        <div class="card manv-card h-full" onclick="window.location.href='<?= BASE_PATH ?>manv/board.php?id=<?= (int) $lage['id'] ?>'">
                            <div class="card-header flex items-center justify-between">
                                <h5 class="mb-0">
                                    <i class="fas fa-map-marker-alt mr-2"></i><?= htmlspecialchars($lage['einsatznummer']) ?>
                                </h5>
                                <span class="badge <?= $statusClass ?> status-badge"><?= $statusText ?></span>
                            </div>
                            <div class="card-body">
                                <h6 class="card-subtitle mb-4 text-gray-400">
                                    <?= htmlspecialchars($lage['einsatzort']) ?>
                                </h6>

                                <?php if (!empty($lage['einsatzanlass'])): ?>
                                    <p class="card-text mb-4">
                                        <small><?= htmlspecialchars(substr($lage['einsatzanlass'], 0, 100)) ?><?= strlen($lage['einsatzanlass']) > 100 ? '...' : '' ?></small>
                                    </p>
                                <?php endif; ?>

                                <div class="mb-4 grid grid-cols-2 gap-3">
                                    <div class="stat-box">
                                        <div class="text-xs text-gray-400">LNA</div>
                                        <div><strong><?= htmlspecialchars($lage['lna_name'] ?? 'Nicht zugewiesen') ?></strong></div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="text-xs text-gray-400">OrgL</div>
                                        <div><strong><?= htmlspecialchars($lage['orgl_name'] ?? 'Nicht zugewiesen') ?></strong></div>
                                    </div>
                                </div>

                                <div class="stat-box">
                                    <div class="mb-2 flex items-center justify-between">
                                        <span class="text-gray-400">Patienten gesamt:</span>
                                        <span class="ignis-chip ignis-chip--primary"><?= (int) $stats['total_patienten'] ?></span>
                                    </div>
                                    <div class="grid grid-cols-4 gap-1 text-center">
                                        <div class="ignis-chip ignis-chip--danger w-full">SK1: <?= (int) $stats['sk1'] ?></div>
                                        <div class="ignis-chip ignis-chip--warning w-full">SK2: <?= (int) $stats['sk2'] ?></div>
                                        <div class="ignis-chip ignis-chip--success w-full">SK3: <?= (int) $stats['sk3'] ?></div>
                                        <div class="ignis-chip ignis-chip--info w-full">SK4: <?= (int) $stats['sk4'] ?></div>
                                    </div>
                                    <div class="mt-2 text-xs text-gray-400">
                                        Transportiert: <?= (int) $stats['transportiert'] ?> | Wartend: <?= (int) $stats['wartend'] ?>
                                    </div>
                                </div>

                                <div class="mt-3 text-xs text-gray-400">
                                    <i class="fas fa-clock mr-1"></i>
                                    Beginn: <?= !empty($lage['einsatzbeginn']) ? \App\Helpers\DateTimeHelper::formatShortLocal($lage['einsatzbeginn']) : 'Nicht angegeben' ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="mt-4 mb-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Archivierte Lagen</h5>
                    </div>
                    <div class="card-body flex flex-wrap gap-2">
                        <a href="<?= BASE_PATH ?>manv/index.php?status=abgeschlossen" class="ignis-btn ignis-btn--outline-secondary no-underline hover:no-underline">
                            <i class="fas fa-archive mr-2"></i>Abgeschlossene Lagen anzeigen
                        </a>
                        <a href="<?= BASE_PATH ?>manv/index.php?status=archiviert" class="ignis-btn ignis-btn--outline-secondary no-underline hover:no-underline">
                            <i class="fas fa-archive mr-2"></i>Archivierte Lagen anzeigen
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../assets/components/footer.php'; ?>

    <script>
        // Auto-Refresh alle 30 Sekunden
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>

</html>
