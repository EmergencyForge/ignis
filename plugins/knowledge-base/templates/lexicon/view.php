<?php
use App\Auth\Permissions;
use App\Helpers\Flash;
use App\KnowledgeBase\KBHelper;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    $SITE_TITLE = htmlspecialchars($entry['title']) . ' - Wissensdatenbank';
    include dirname(__DIR__, 4) . "/assets/components/_base/admin/head.php";
    ?>
    <style>
        .competency-header {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .competency-label {
            font-weight: bold;
            font-size: 1.2rem;
        }
        /* Category badge - positioned separately to avoid color collision */
        .kb-category-badge {
            position: absolute;
            top: -10px;
            right: 15px;
            padding: 5px 12px;
            font-size: 0.8rem;
            font-weight: bold;
            border-radius: 4px;
            z-index: 10;
        }
        /* Main content wrapper - transparent to keep dark design */
        .kb-content-wrapper {
            padding: 20px 0;
        }
        /* Table styling with dark theme */
        .kb-entry-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            color: #e0e0e0;
            background-color: transparent;
        }
        .kb-entry-table th,
        .kb-entry-table td {
            padding: 12px 15px;
            border: 1px solid #444;
            vertical-align: top;
            color: #e0e0e0;
            background-color: transparent;
        }
        .kb-entry-table th {
            width: 180px;
            font-weight: bold;
            background-color: rgba(255,255,255,0.1);
            color: #ffffff;
        }
        .kb-entry-table td {
            background-color: rgba(255,255,255,0.05);
        }
        /* Entry row styling with dark theme */
        .kb-entry-row {
            display: flex;
            border: 1px solid #444;
            border-bottom: none;
            color: #e0e0e0;
            background-color: transparent;
        }
        .kb-entry-row:last-child {
            border-bottom: 1px solid #444;
        }
        .kb-entry-row .kb-icon {
            width: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            background-color: rgba(255,255,255,0.1);
            border-right: 1px solid #444;
            color: #e0e0e0;
        }
        .kb-entry-row .kb-label {
            width: 160px;
            padding: 12px 15px;
            font-weight: bold;
            background-color: rgba(255,255,255,0.1);
            border-right: 1px solid #444;
            display: flex;
            align-items: center;
            color: #ffffff;
        }
        .kb-entry-row .kb-content {
            flex: 1;
            padding: 12px 15px;
            background-color: rgba(255,255,255,0.05);
            color: #e0e0e0;
        }
        /* Section styling — dezente getönte Cards im Dark-Theme.
           Die Akzentfarbe bleibt als Border + Header-Bottom-Tint sichtbar,
           der Body hat einen subtilen Tint, kein voll-saturiertes Pur-Gelb. */
        .kb-section {
            margin-bottom: 12px;
            border-radius: var(--radius-md, 6px);
            border: 1px solid var(--darkgray, #2a2a2a);
            background-color: rgba(255, 255, 255, 0.02);
            color: #e0e0e0;
            overflow: hidden;
        }
        .kb-section-yellow {
            background-color: rgba(255, 193, 7, 0.06);
            border-color: rgba(255, 193, 7, 0.35);
        }
        .kb-section-yellow .kb-section-header {
            background-color: rgba(255, 193, 7, 0.18);
            color: #ffd966;
        }
        .kb-section-blue {
            background-color: rgba(13, 110, 253, 0.06);
            border-color: rgba(13, 110, 253, 0.35);
        }
        .kb-section-blue .kb-section-header {
            background-color: rgba(13, 110, 253, 0.18);
            color: #6ea8fe;
        }
        .kb-section-red {
            background-color: rgba(192, 0, 0, 0.06);
            border-color: rgba(192, 0, 0, 0.45);
        }
        .kb-section-red .kb-section-header {
            background-color: rgba(192, 0, 0, 0.22);
            color: #ff8a8a;
        }
        .kb-section-gray {
            background-color: rgba(255, 255, 255, 0.03);
            border-color: var(--darkgray, #2a2a2a);
        }
        .kb-section-gray .kb-section-header {
            background-color: rgba(255, 255, 255, 0.04);
            color: var(--text-dimmed, #a0a0a0);
        }
        .kb-section-header {
            margin: 0;
            padding: 8px 15px;
            font-weight: 600;
            font-size: 0.82rem;
            letter-spacing: 0.02em;
            border-bottom: 1px solid var(--darkgray, #2a2a2a);
        }
        .kb-section-content {
            padding: 12px 15px;
            color: #e0e0e0;
        }
        .edit-info {
            font-size: 0.85rem;
            color: #aaaaaa;
            padding: 10px 0;
            margin-top: 20px;
            border-top: 1px solid #444;
        }
        /* Inline Action Buttons - gray with hover tooltip */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            background-color: #555;
            color: #fff;
            position: relative;
        }
        .action-btn:hover {
            background-color: #666;
            color: #fff;
        }
        .action-btn .tooltip-text {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #000;
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            pointer-events: none;
            margin-bottom: 5px;
        }
        .action-btn:hover .tooltip-text {
            opacity: 1;
            visibility: visible;
        }
        /* Edit info row with actions */
        .edit-info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 15px;
            padding: 15px 0;
            margin-top: 20px;
            border-top: 1px solid #444;
        }
        .edit-info-text {
            font-size: 0.85rem;
            color: #aaaaaa;
        }
        /* Back link styling */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #e0e0e0;
            text-decoration: none;
            padding: 8px 0;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: #0d6efd;
        }
        /* Header section styling */
        .kb-header {
            position: relative;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .kb-header-content {
            padding: 15px;
            border-radius: 8px;
            background-color: rgba(0,0,0,0.3);
        }
        .kb-header h2 {
            margin: 0;
            color: #ffffff;
        }
        .kb-header .subtitle {
            color: #aaaaaa;
            margin: 5px 0 0 0;
        }
        .kb-freigabe-badge {
            padding: 8px 15px;
            font-weight: bold;
            font-size: 1rem;
            border-radius: 4px;
            display: inline-block;
        }
        /* Content area for CKEditor content */
        .content-area {
            color: #e0e0e0;
            padding: 15px;
            background-color: rgba(255,255,255,0.05);
            border-radius: 4px;
        }
        .content-area h1, .content-area h2, .content-area h3, 
        .content-area h4, .content-area h5, .content-area h6 {
            color: #ffffff;
        }
        .content-area p, .content-area li, .content-area td, .content-area th {
            color: #e0e0e0;
        }
        .content-area a {
            color: #6ea8fe;
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="lexicon">
    <?php if ($isLoggedIn): ?>
        <?php include dirname(__DIR__, 4) . "/assets/components/navbar.php"; ?>
    <?php else: ?>
        <nav class="navbar navbar-expand-lg bg-body-tertiary mb-4">
            <div class="container">
                <a class="navbar-brand" href="<?= BASE_PATH ?>">
                    <img src="<?php echo SYSTEM_LOGO ?>" alt="<?php echo SYSTEM_NAME ?>" style="height:48px;width:auto">
                </a>
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="<?= BASE_PATH ?>login.php">Anmelden</a>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    
                    <!-- Back Link -->
                    <a href="<?= BASE_PATH ?>lexicon/index" class="back-link mb-3">
                        <i class="fa-solid fa-arrow-left"></i> Zurück zur Übersicht
                    </a>

                    <?php if (!empty($entry['is_pinned'])): ?>
                        <div class="alert mt-3" style="background-color: <?= SYSTEM_COLOR ?>20; border-color: <?= SYSTEM_COLOR ?>; color: #e0e0e0;">
                            <i class="fa-solid fa-thumbtack" style="color: <?= SYSTEM_COLOR ?>;"></i> Dieser Eintrag ist angepinnt und wird oben in der Liste angezeigt.
                        </div>
                    <?php endif; ?>

                    <?php if ($entry['is_archived']): ?>
                        <div class="alert alert-warning">
                            <i class="fa-solid fa-archive"></i> Dieser Eintrag ist archiviert.
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($entry['category_name']) || !empty($entryTags)): ?>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <?php if (!empty($entry['category_name'])): ?>
                                <span class="text-muted small">
                                    <i class="fa-solid fa-folder"></i>
                                    <?php if (!empty($entry['parent_category_name'])): ?>
                                        <?php if (!empty($entry['parent_category_icon'])): ?><i class="<?= htmlspecialchars($entry['parent_category_icon']) ?>"></i> <?php endif; ?>
                                        <a href="<?= BASE_PATH ?>lexicon/index?category=<?= (int)$entry['category_id'] ?>" class="text-muted"><?= htmlspecialchars($entry['parent_category_name']) ?></a>
                                        <i class="fa-solid fa-chevron-right" style="font-size: 0.6rem;"></i>
                                    <?php endif; ?>
                                    <?php if (!empty($entry['category_icon'])): ?><i class="<?= htmlspecialchars($entry['category_icon']) ?>"></i> <?php endif; ?>
                                    <a href="<?= BASE_PATH ?>lexicon/index?category=<?= (int)$entry['category_id'] ?>" class="text-muted"><?= htmlspecialchars($entry['category_name']) ?></a>
                                </span>
                            <?php endif; ?>
                            <?php foreach ($entryTags as $etag): ?>
                                <a href="<?= BASE_PATH ?>lexicon/index?tag=<?= (int)$etag['id'] ?>" class="badge text-decoration-none" style="background-color: <?= htmlspecialchars($etag['color']) ?>; color: #fff;"><?= htmlspecialchars($etag['name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Entry Content -->
                    <div class="intra__tile p-4">
                        <!-- Header with Title and Competency -->
                        <?php if ($competency): ?>
                            <div class="kb-header position-relative" style="background-color: <?= $competency['bg'] ?>;">
                                <!-- Category badge positioned in top right -->
                                <span class="kb-category-badge bg-dark" style="color: #ffffff;">
                                    <?= KBHelper::getTypeLabel($entry['type']) ?>
                                </span>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="kb-header-content flex-grow-1 me-3">
                                        <h2><?= htmlspecialchars($entry['title']) ?></h2>
                                        <?php if (!empty($entry['subtitle'])): ?>
                                            <p class="subtitle"><?= htmlspecialchars($entry['subtitle']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <div class="kb-freigabe-badge" style="background-color: <?= $competency['color'] ?>; color: <?= KBHelper::competencyNeedsDarkText($entry['competency_level']) ? '#000' : '#fff' ?>;">
                                            Freigabe: <?= $competency['label'] ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="kb-header-content mb-4 position-relative">
                                <span class="kb-category-badge bg-dark" style="color: #ffffff; top: 0; right: 0;">
                                    <?= KBHelper::getTypeLabel($entry['type']) ?>
                                </span>
                                <h2><?= htmlspecialchars($entry['title']) ?></h2>
                                <?php if (!empty($entry['subtitle'])): ?>
                                    <p class="subtitle"><?= htmlspecialchars($entry['subtitle']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="kb-content-wrapper">
                        <?php if ($entry['type'] === 'medication'): ?>
                            <!-- Medication Layout -->
                            <div class="row">
                                <div class="col-12">
                                    <!-- Basic Info Table -->
                                    <table class="kb-entry-table mb-4">
                                        <tbody>
                                            <?php if (!empty($entry['med_wirkstoff'])): ?>
                                                <tr>
                                                    <th>Wirkstoff:</th>
                                                    <td><?= htmlspecialchars($entry['med_wirkstoff']) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($entry['med_wirkstoffgruppe'])): ?>
                                                <tr>
                                                    <th>Wirkstoffgruppe:</th>
                                                    <td><?= htmlspecialchars($entry['med_wirkstoffgruppe']) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($entry['med_wirkmechanismus'])): ?>
                                                <tr>
                                                    <th>Wirkmechanismus:</th>
                                                    <td><?= KBHelper::sanitizeContent($entry['med_wirkmechanismus']) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>

                                    <?php if (!empty($entry['med_indikationen'])): ?>
                                        <div class="kb-section kb-section-yellow">
                                            <div class="kb-section-header">Indikationen:</div>
                                            <div class="kb-section-content"><?= KBHelper::sanitizeContent($entry['med_indikationen']) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($entry['med_kontraindikationen'])): ?>
                                        <div class="kb-section kb-section-yellow">
                                            <div class="kb-section-header">Kontraindikationen:</div>
                                            <div class="kb-section-content"><?= KBHelper::sanitizeContent($entry['med_kontraindikationen']) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($entry['med_uaw'])): ?>
                                        <div class="kb-section kb-section-gray">
                                            <div class="kb-section-header">Unerwünschte Arzneimittelwirkungen (UAW):</div>
                                            <div class="kb-section-content"><?= KBHelper::sanitizeContent($entry['med_uaw']) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($entry['med_dosierung'])): ?>
                                        <div class="kb-section kb-section-blue">
                                            <div class="kb-section-header">Dosierung:</div>
                                            <div class="kb-section-content"><?= KBHelper::sanitizeContent($entry['med_dosierung']) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($entry['med_besonderheiten'])): ?>
                                        <div class="kb-section kb-section-red">
                                            <div class="kb-section-header">Besonderheiten / CAVE:</div>
                                            <div class="kb-section-content"><?= KBHelper::sanitizeContent($entry['med_besonderheiten']) ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                        <?php elseif ($entry['type'] === 'measure'): ?>
                            <!-- Measure Layout - Same table style as Medication -->
                            <div class="row">
                                <div class="col-12">
                                    <!-- Basic Info Table -->
                                    <table class="kb-entry-table mb-4">
                                        <tbody>
                                            <?php if (!empty($entry['mass_wirkprinzip'])): ?>
                                                <tr>
                                                    <th>Wirkprinzip:</th>
                                                    <td><?= KBHelper::sanitizeContent($entry['mass_wirkprinzip']) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>

                                    <?php if (!empty($entry['mass_indikationen'])): ?>
                                        <div class="kb-section kb-section-yellow">
                                            <div class="kb-section-header">Indikationen:</div>
                                            <div class="kb-section-content"><?= KBHelper::sanitizeContent($entry['mass_indikationen']) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($entry['mass_kontraindikationen'])): ?>
                                        <div class="kb-section kb-section-yellow">
                                            <div class="kb-section-header">Kontraindikationen:</div>
                                            <div class="kb-section-content"><?= KBHelper::sanitizeContent($entry['mass_kontraindikationen']) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($entry['mass_risiken'])): ?>
                                        <div class="kb-section kb-section-gray">
                                            <div class="kb-section-header">Risiken:</div>
                                            <div class="kb-section-content"><?= KBHelper::sanitizeContent($entry['mass_risiken']) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($entry['mass_alternativen'])): ?>
                                        <div class="kb-section kb-section-blue">
                                            <div class="kb-section-header">Alternativen:</div>
                                            <div class="kb-section-content"><?= KBHelper::sanitizeContent($entry['mass_alternativen']) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($entry['mass_durchfuehrung'])): ?>
                                        <div class="kb-section kb-section-red">
                                            <div class="kb-section-header">Durchführung:</div>
                                            <div class="kb-section-content"><?= KBHelper::sanitizeContent($entry['mass_durchfuehrung']) ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- General Content (for all types) -->
                        <?php if (!empty($entry['content'])): ?>
                            <div class="mt-4">
                                <?php if ($entry['type'] !== 'general'): ?>
                                    <h5>Weitere Informationen:</h5>
                                <?php endif; ?>
                                <div class="content-area">
                                    <?= KBHelper::sanitizeContent($entry['content']) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($relatedEntries)): ?>
                        <!-- Verknüpfte Einträge -->
                        <div class="mt-4">
                            <h5><i class="fa-solid fa-link"></i> Verknüpfte Einträge</h5>
                            <div class="row row-cols-1 row-cols-md-2 g-3 mt-1">
                                <?php foreach ($relatedEntries as $rel):
                                    $relComp = KBHelper::getCompetencyInfo($rel['competency_level']);
                                ?>
                                    <div class="col">
                                        <a href="<?= BASE_PATH ?>lexicon/view?id=<?= $rel['id'] ?>" class="text-decoration-none">
                                            <div class="d-flex align-items-center p-3 rounded" style="background-color: rgba(255,255,255,0.05); border: 1px solid #444; transition: background-color 0.2s;"
                                                 onmouseover="this.style.backgroundColor='rgba(255,255,255,0.1)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.05)'">
                                                <div class="me-3">
                                                    <i class="fa-solid fa-<?= $rel['type'] === 'medication' ? 'pills' : ($rel['type'] === 'measure' ? 'hand-holding-medical' : 'file-lines') ?> fa-lg" style="color: <?= KBHelper::getTypeColor($rel['type']) ?>;"></i>
                                                </div>
                                                <div class="flex-grow-1 min-width-0">
                                                    <div class="fw-bold text-white"><?= htmlspecialchars($rel['title']) ?></div>
                                                    <?php if (!empty($rel['subtitle'])): ?>
                                                        <small class="text-muted"><?= htmlspecialchars(mb_strimwidth($rel['subtitle'], 0, 80, '...')) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="ms-2 d-flex flex-column gap-1 align-items-end">
                                                    <span class="badge" style="background-color: <?= KBHelper::getTypeColor($rel['type']) ?>; font-size: 0.65rem;"><?= KBHelper::getTypeLabel($rel['type']) ?></span>
                                                    <?php if ($relComp): ?>
                                                        <span class="badge" style="background-color: <?= $relComp['bg'] ?>; color: <?= $relComp['text'] ?? '#fff' ?>; font-size: 0.65rem;"><?= $relComp['label'] ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Edit Info with Action Buttons -->
                        <div class="edit-info-row">
                            <div class="edit-info-text">
                                <i class="fa-solid fa-clock"></i>
                                Erstellt am <?= date('d.m.Y H:i', strtotime($entry['created_at'])) ?>
                                <?php if ($entry['creator_name'] && empty($entry['hide_editor'])): ?>
                                    von <?= htmlspecialchars($entry['creator_name']) ?>
                                <?php endif; ?>
                                <?php if ($entry['updated_at']): ?>
                                    <br>
                                    <i class="fa-solid fa-edit"></i>
                                    Zuletzt bearbeitet am <?= date('d.m.Y H:i', strtotime($entry['updated_at'])) ?>
                                    <?php if ($entry['updater_name'] && empty($entry['hide_editor'])): ?>
                                        von <?= htmlspecialchars($entry['updater_name']) ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($isLoggedIn): ?>
                                <div class="action-buttons">
                                    <?php if (Permissions::check(['admin', 'kb.edit'])): ?>
                                        <a href="<?= BASE_PATH ?>lexicon/edit?id=<?= $entry['id'] ?>" class="action-btn">
                                            <i class="fa-solid fa-pen"></i>
                                            <span class="tooltip-text">Bearbeiten</span>
                                        </a>
                                        
                                        <form method="POST" action="<?= BASE_PATH ?>lexicon/pin" style="margin: 0; display: inline;">
                                            <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                                            <input type="hidden" name="action" value="<?= !empty($entry['is_pinned']) ? 'unpin' : 'pin' ?>">
                                            <button type="submit" class="action-btn">
                                                <i class="fa-solid fa-thumbtack"></i>
                                                <span class="tooltip-text"><?= !empty($entry['is_pinned']) ? 'Lösen' : 'Anpinnen' ?></span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if (Permissions::check(['admin', 'kb.archive'])): ?>
                                        <?php if ($entry['is_archived']): ?>
                                            <form method="POST" action="<?= BASE_PATH ?>lexicon/archive" style="margin: 0; display: inline;">
                                                <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                                                <input type="hidden" name="action" value="restore">
                                                <button type="submit" class="action-btn">
                                                    <i class="fa-solid fa-rotate-left"></i>
                                                    <span class="tooltip-text">Wiederherstellen</span>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="<?= BASE_PATH ?>lexicon/archive" style="margin: 0; display: inline;">
                                                <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                                                <input type="hidden" name="action" value="archive">
                                                <button type="submit" class="action-btn">
                                                    <i class="fa-solid fa-box-archive"></i>
                                                    <span class="tooltip-text">Archivieren</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        </div><!-- /.kb-content-wrapper -->
                    </div><!-- /.intra__tile -->
                </div>
            </div>
        </div>
    </div>

    <?php include dirname(__DIR__, 4) . "/assets/components/footer.php"; ?>
</body>

</html>
