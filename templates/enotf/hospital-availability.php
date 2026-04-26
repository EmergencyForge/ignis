<?php
/**
 * View: eNOTF Hospital Availability
 *
 * Public-Page (kein Login erforderlich, aber wenn angemeldet werden Permissions geprüft).
 *
 * @var array<int,array<string,mixed>> $hospitals
 * @var \PDO                           $pdo
 */

use App\Helpers\EnotfUrl;
use App\Helpers\Redirects;

// Status configuration
$status_config = [
    'not_staffed' => [
        'label' => 'Nicht besetzt',
        'color' => '#6c757d',
        'bg_color' => 'rgba(108, 117, 125, 0.2)',
        'icon' => 'fa-circle-minus'
    ],
    'available' => [
        'label' => 'Verfügbar',
        'color' => '#28a745',
        'bg_color' => 'rgba(40, 167, 69, 0.2)',
        'icon' => 'fa-circle-check'
    ],
    'partially_available' => [
        'label' => 'Hohe Auslastung',
        'color' => '#ffc107',
        'bg_color' => 'rgba(255, 193, 7, 0.2)',
        'icon' => 'fa-circle-exclamation'
    ],
    'full' => [
        'label' => 'Abgemeldet',
        'color' => '#dc3545',
        'bg_color' => 'rgba(220, 53, 69, 0.2)',
        'icon' => 'fa-circle-xmark'
    ]
];

// Default redirect URL for back button
$defaultUrl = EnotfUrl::page('overview');
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "Krankenhaus-Verfügbarkeit";
    include __DIR__ . '/../../assets/components/enotf/_head.php';
    ?>
    <style>
        .hospital-card {
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .hospital-card:hover {
            background: #333;
            border-color: #555;
        }

        .hospital-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #444;
        }

        .hospital-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.25rem;
        }

        .hospital-address {
            color: #a2a2a2;
            font-size: 0.9rem;
        }

        .hospital-type-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            background: rgba(209, 0, 0, 0.2);
            color: var(--main-color, #d10000);
            border: 1px solid rgba(209, 0, 0, 0.3);
        }

        .departments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }

        .department-item {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 6px;
            padding: 1rem;
            transition: all 0.3s ease;
        }

        .department-item:hover {
            background: #222;
            border-color: #444;
        }

        .department-name {
            font-weight: 600;
            color: #fff;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-indicator i {
            font-size: 1.1rem;
        }

        .department-footer {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #333;
            font-size: 0.75rem;
            color: #666;
        }

        .no-departments {
            text-align: center;
            padding: 2rem;
            color: #666;
            font-style: italic;
        }

        .refresh-info {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid #444;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .refresh-info i {
            color: var(--main-color, #d10000);
            font-size: 1.5rem;
        }

        .filter-section {
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .refresh-info {
            background: rgba(42, 42, 42, 0.5);
            border: 1px solid #444;
            border-radius: 6px;
            padding: 1rem;
            margin: 1.5rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .refresh-info i {
            color: var(--main-color, #d10000);
            font-size: 1.2rem;
        }
    </style>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden">
    <div class="w-full" id="edivi__container">
        <div class="flex flex-wrap -mx-3 h-full">
            <div class="flex-1 px-3" id="edivi__content">
                <h2 class="text-center my-3">
                    <i class="fa-solid fa-hospital mr-2"></i>
                    Krankenhaus-Verfügbarkeit
                </h2>

            <div class="refresh-info">
                <i class="fa-solid fa-info-circle"></i>
                <div class="flex-1">
                    <strong>Live-Übersicht</strong><br>
                    <small>Die Verfügbarkeiten werden von den Krankenhäusern selbst aktualisiert. Letzte Aktualisierung dieser Seite: <?= date('d.m.Y H:i:s') ?> Uhr</small>
                </div>
            </div>

            <!-- Hospitals List -->
            <?php if (empty($hospitals)): ?>
                <div class="hospital-card">
                    <div class="no-departments">
                        <i class="fa-solid fa-hospital fa-3x mb-3"></i>
                        <p>Keine Krankenhäuser konfiguriert.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($hospitals as $hospital): ?>
                    <div class="hospital-card" data-hospital-id="<?= $hospital['id'] ?>">
                        <div class="hospital-header">
                            <div>
                                <div class="hospital-name">
                                    <i class="fa-solid fa-hospital mr-2"></i>
                                    <?= htmlspecialchars($hospital['name']) ?>
                                </div>
                                <div class="hospital-address">
                                    <?php if ($hospital['address']): ?>
                                        <?= htmlspecialchars($hospital['address']) ?>,
                                    <?php endif; ?>
                                    <?= htmlspecialchars($hospital['city']) ?>
                                    <?php if ($hospital['district']): ?>
                                        (<?= htmlspecialchars($hospital['district']) ?>)
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="hospital-type-badge">
                                <?= htmlspecialchars($hospital['type']) ?>
                            </div>
                        </div>

                        <?php if (empty($hospital['departments'])): ?>
                            <div class="no-departments">
                                <i class="fa-solid fa-circle-info mr-2"></i>
                                Keine Fachrichtungen konfiguriert
                            </div>
                        <?php else: ?>
                            <div class="departments-grid">
                                <?php foreach ($hospital['departments'] as $dept): ?>
                                    <?php $status_info = $status_config[$dept['status']]; ?>
                                    <div class="department-item">
                                        <div class="department-name">
                                            <i class="fa-solid fa-bed-pulse mr-2"></i>
                                            <?= htmlspecialchars($dept['name']) ?>
                                        </div>

                                        <div class="status-indicator" style="color: <?= $status_info['color'] ?>; background: <?= $status_info['bg_color'] ?>; border: 1px solid <?= $status_info['color'] ?>;">
                                            <i class="fa-solid <?= $status_info['icon'] ?>"></i>
                                            <?= $status_info['label'] ?>
                                        </div>

                                        <?php if ($dept['updated_at']): ?>
                                            <div class="department-footer">
                                                <i class="fa-solid fa-clock mr-1"></i>
                                                Aktualisiert: <?= \App\Helpers\DateTimeHelper::formatShortLocal($dept['updated_at']) ?> Uhr
                                                <?php if ($dept['updated_by']): ?>
                                                    <br><i class="fa-solid fa-user mr-1"></i> <?= htmlspecialchars($dept['updated_by']) ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

                <!-- Back Button -->
                <div class="edivi__freigabe-buttons mt-4">
                    <div class="flex flex-wrap -mx-3">
                        <div class="flex-1 px-3">
                            <a href="<?= Redirects::getRedirectUrl($defaultUrl); ?>">zurück</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Auto-refresh every 5 minutes
            setInterval(function() {
                location.reload();
            }, 5 * 60 * 1000);
        });
    </script>
</body>

</html>
