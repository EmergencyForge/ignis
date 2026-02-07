<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\MANV\MANVLage;
use App\Auth\Permissions;

if (!Permissions::check(['admin', 'manv.manage'])) {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$manvLage = new MANVLage($pdo);

// Status-Filter auslesen (Standard: aktiv)
$statusFilter = $_GET['status'] ?? 'aktiv';
$allowedStatus = ['aktiv', 'abgeschlossen', 'archiviert'];
if (!in_array($statusFilter, $allowedStatus)) {
    $statusFilter = 'aktiv';
}

$lagen = $manvLage->getAll($statusFilter);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = 'MANV-Übersicht';
    include __DIR__ . '/../assets/components/_base/admin/head.php';
    ?>
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
    <?php include __DIR__ . '/../assets/components/navbar.php'; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <hr class="text-light my-3">
            <div class="row mb-5">
                <div class="col-md-8">
                    <?php if ($statusFilter !== 'aktiv'): ?>
                        <a href="<?= BASE_PATH ?>manv/index.php" class="btn btn-ghost mb-3">
                            <i class="fas fa-arrow-left me-2"></i>Zurück zu aktiven Lagen
                        </a>
                    <?php endif; ?>
                    <h1>MANV-Übersicht
                        <?php
                        if ($statusFilter === 'abgeschlossen') {
                            echo '<small class="text-muted ms-2">(Abgeschlossene Lagen)</small>';
                        } elseif ($statusFilter === 'archiviert') {
                            echo '<small class="text-muted ms-2">(Archivierte Lagen)</small>';
                        }
                        ?>
                    </h1>
                    <p class="text-muted">Massenanfall von Verletzten - Lagenverwaltung</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="<?= BASE_PATH ?>manv/create.php" class="btn btn-soft-primary btn-lg">
                        <i class="fas fa-plus me-2"></i>Neue MANV-Lage anlegen
                    </a>
                </div>
            </div>

            <?php if (empty($lagen)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
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
                <div class="row">
                    <?php foreach ($lagen as $lage):
                        $stats = $manvLage->getStatistics($lage['id']);
                    ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card manv-card h-100" onclick="window.location.href='<?= BASE_PATH ?>manv/board.php?id=<?= $lage['id'] ?>'">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($lage['einsatznummer']) ?>
                                    </h5>
                                    <?php
                                    $statusClass = 'bg-success';
                                    $statusText = 'Aktiv';
                                    if ($lage['status'] === 'abgeschlossen') {
                                        $statusClass = 'bg-warning';
                                        $statusText = 'Abgeschlossen';
                                    } elseif ($lage['status'] === 'archiviert') {
                                        $statusClass = 'bg-secondary';
                                        $statusText = 'Archiviert';
                                    }
                                    ?>
                                    <span class="badge <?= $statusClass ?> status-badge"><?= $statusText ?></span>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-3 text-muted">
                                        <?= htmlspecialchars($lage['einsatzort']) ?>
                                    </h6>

                                    <?php if ($lage['einsatzanlass']): ?>
                                        <p class="card-text mb-3">
                                            <small><?= htmlspecialchars(substr($lage['einsatzanlass'], 0, 100)) ?><?= strlen($lage['einsatzanlass']) > 100 ? '...' : '' ?></small>
                                        </p>
                                    <?php endif; ?>

                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <div class="stat-box">
                                                <div class="text-muted small">LNA</div>
                                                <div><strong><?= htmlspecialchars($lage['lna_name'] ?? 'Nicht zugewiesen') ?></strong></div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="stat-box">
                                                <div class="text-muted small">OrgL</div>
                                                <div><strong><?= htmlspecialchars($lage['orgl_name'] ?? 'Nicht zugewiesen') ?></strong></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="stat-box">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">Patienten gesamt:</span>
                                            <span class="badge bg-primary"><?= $stats['total_patienten'] ?></span>
                                        </div>
                                        <div class="row text-center">
                                            <div class="col">
                                                <div class="badge bg-danger w-100">SK1: <?= $stats['sk1'] ?></div>
                                            </div>
                                            <div class="col">
                                                <div class="badge bg-warning w-100">SK2: <?= $stats['sk2'] ?></div>
                                            </div>
                                            <div class="col">
                                                <div class="badge bg-success w-100">SK3: <?= $stats['sk3'] ?></div>
                                            </div>
                                            <div class="col">
                                                <div class="badge bg-info w-100">SK4: <?= $stats['sk4'] ?></div>
                                            </div>
                                        </div>
                                        <div class="mt-2 small text-muted">
                                            Transportiert: <?= $stats['transportiert'] ?> | Wartend: <?= $stats['wartend'] ?>
                                        </div>
                                    </div>

                                    <div class="mt-3 small text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        Beginn: <?= $lage['einsatzbeginn'] ? date('d.m.Y H:i', strtotime($lage['einsatzbeginn'])) : 'Nicht angegeben' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="row mt-4 mb-5">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Archivierte Lagen</h5>
                        </div>
                        <div class="card-body">
                            <a href="<?= BASE_PATH ?>manv/index.php?status=abgeschlossen" class="btn btn-outline-secondary">
                                <i class="fas fa-archive me-2"></i>Abgeschlossene Lagen anzeigen
                            </a>
                            <a href="<?= BASE_PATH ?>manv/index.php?status=archiviert" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-archive me-2"></i>Archivierte Lagen anzeigen
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../assets/components/footer.php'; ?>

    <script>
        // Auto-Refresh alle 30 Sekunden
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>

</html>