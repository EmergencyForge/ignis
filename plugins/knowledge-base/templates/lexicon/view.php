<?php
use App\Auth\Permissions;
use App\Helpers\Flash;
use Plugin\KnowledgeBase\KBHelper;

$layout = 'admin';
$bodyId = 'lexicon';
$SITE_TITLE = htmlspecialchars($entry['title']) . ' - Wissensdatenbank';
?>
<?php ob_start(); ?>
    <style>
        /* Typ-Badge oben rechts, getrennt von der Freigabefarbe */
        .kb-category-badge {
            position: absolute;
            top: -10px;
            right: 15px;
            padding: 5px 12px;
            font-size: 0.8rem;
            font-weight: 600;
            border-radius: var(--radius-1);
            border: 1px solid var(--border-subtle);
            background: var(--surface-3);
            color: var(--text);
            z-index: 10;
        }
        .kb-content-wrapper {
            padding: 20px 0;
        }
        .kb-pinned {
            background: var(--accent-soft);
            border-left-color: var(--accent);
        }
        .kb-pinned > i {
            color: var(--accent);
        }
        .kb-entry-table {
            width: 100%;
            border-collapse: collapse;
        }
        .kb-entry-table th,
        .kb-entry-table td {
            padding: 12px 15px;
            border: 1px solid var(--border-subtle);
            vertical-align: top;
        }
        .kb-entry-table th {
            width: 180px;
            font-weight: 600;
            background: var(--fill-2);
            color: var(--text);
        }
        .kb-entry-table td {
            background: var(--fill-1);
        }
        .kb-entry-table td > :last-child {
            margin-bottom: 0;
        }
        /* Abschnitte: Bedeutungsfarbe nur am Rand und im Kopf, der Inhalt
           bleibt auf der Kartenfläche gut lesbar. */
        .kb-section {
            margin-bottom: 12px;
            border-radius: var(--radius-2);
            border: 1px solid var(--border-subtle);
            overflow: hidden;
        }
        .kb-section-header {
            margin: 0;
            padding: 8px 15px;
            font-weight: 600;
            font-size: 0.82rem;
            letter-spacing: 0.02em;
            border-bottom: 1px solid var(--border-subtle);
            background: var(--fill-2);
            color: var(--text-2);
        }
        .kb-section-yellow { border-color: var(--warn-line); }
        .kb-section-yellow .kb-section-header { background: var(--warn-soft); color: var(--warn-text); border-color: var(--warn-line); }
        .kb-section-blue { border-color: var(--info-line); }
        .kb-section-blue .kb-section-header { background: var(--info-soft); color: var(--info-text); border-color: var(--info-line); }
        .kb-section-red { border-color: var(--danger-line); }
        .kb-section-red .kb-section-header { background: var(--danger-soft); color: var(--danger-text); border-color: var(--danger-line); }
        .kb-section-content {
            padding: 12px 15px;
        }
        .kb-section-content > :last-child {
            margin-bottom: 0;
        }
        .edit-info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 15px;
            padding: 15px 0;
            margin-top: 20px;
            border-top: 1px solid var(--border-subtle);
        }
        .edit-info-text {
            font-size: 0.85rem;
            color: var(--text-3);
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-2);
            padding: 8px 0;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: var(--accent);
        }
        .kb-header {
            position: relative;
            padding: 20px;
            border-radius: var(--radius-3);
            margin-bottom: 20px;
        }
        .kb-header-content {
            padding: 15px;
            border-radius: var(--radius-3);
            background: var(--fill-2);
        }
        .kb-header h2 {
            margin: 0;
            color: var(--text);
        }
        .kb-header .subtitle {
            color: var(--text-2);
            margin: 5px 0 0 0;
        }
        .kb-freigabe-badge {
            padding: 8px 15px;
            font-weight: bold;
            font-size: 1rem;
            border-radius: var(--radius-1);
            display: inline-block;
        }
        /* Freitext aus dem Editor */
        .content-area {
            padding: 15px;
            background: var(--fill-1);
            border-radius: var(--radius-1);
        }
        .content-area > :last-child {
            margin-bottom: 0;
        }
        .content-area a {
            color: var(--accent);
        }
        .content-area a:hover {
            text-decoration: underline;
        }
        .content-area figure {
            margin: 1rem 0;
        }
        .content-area img {
            max-width: 100%;
            height: auto;
            border-radius: var(--radius-1);
        }
        .content-area .efe-figure img {
            display: block;
        }
        .content-area table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        /* Der Editor legt Tabellen in figure.table, das zieht sonst die
           Listentabelle aus admin.css an (Hover, Schriftgröße, letzte Zeile). */
        .content-area .table {
            color: inherit;
            --bs-table-hover-bg: transparent;
            --bs-table-hover-color: currentColor;
        }
        #lexicon .content-area :is(th, td) {
            padding: 6px 10px;
            border: 1px solid var(--border-subtle);
            vertical-align: top;
            font-size: inherit;
        }
        .content-area th {
            background: var(--fill-2);
            font-weight: 600;
        }
        .content-area :is(th, td) p {
            margin: 0;
        }
        .content-area blockquote {
            margin: 1rem 0;
            padding-left: 12px;
            border-left: 3px solid var(--border-strong);
            color: var(--text-2);
        }
        .content-area hr {
            margin: 1rem 0;
            border: 0;
            border-top: 1px solid var(--border-subtle);
        }
    </style>
<?php $layoutHead = ob_get_clean(); ?>
    <?php if ($isLoggedIn): ?>
    <?php else: ?>
        <nav class="mb-4">
            <div class="mx-auto flex items-center justify-between px-4 py-3">
                <a href="<?= BASE_PATH ?>">
                    <img src="<?= systemLogoUrl() ?>" alt="<?= htmlspecialchars((string) SYSTEM_NAME, ENT_QUOTES) ?>" style="height:48px;width:auto">
                </a>
                <a class="ignis-btn ignis-btn--ghost" href="<?= BASE_PATH ?>login">Anmelden</a>
            </div>
        </nav>
    <?php endif; ?>

    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="mb-5">
                    
                    <!-- Back Link -->
                    <a href="<?= BASE_PATH ?>lexicon/index" class="back-link mb-3">
                        <i class="fa-solid fa-arrow-left"></i> Zurück zur Übersicht
                    </a>

                    <?php if (!empty($entry['is_pinned'])): ?>
                        <div class="ignis-alert kb-pinned mb-3">
                            <i class="fa-solid fa-thumbtack"></i> Dieser Eintrag ist angepinnt und wird oben in der Liste angezeigt.
                        </div>
                    <?php endif; ?>

                    <?php if ($entry['is_archived']): ?>
                        <div class="ignis-alert ignis-alert--warn mb-3">
                            <i class="fa-solid fa-archive"></i> Dieser Eintrag ist archiviert.
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($entry['category_name']) || !empty($entryTags)): ?>
                        <div class="flex flex-wrap items-center gap-2 mb-3">
                            <?php if (!empty($entry['category_name'])): ?>
                                <span class="text-tertiary-text text-sm">
                                    <i class="fa-solid fa-folder"></i>
                                    <?php if (!empty($entry['parent_category_name'])): ?>
                                        <?php if (!empty($entry['parent_category_icon'])): ?><i class="<?= htmlspecialchars($entry['parent_category_icon']) ?>"></i> <?php endif; ?>
                                        <a href="<?= BASE_PATH ?>lexicon/index?category=<?= (int)$entry['category_id'] ?>" class="text-tertiary-text hover:underline"><?= htmlspecialchars($entry['parent_category_name']) ?></a>
                                        <i class="fa-solid fa-chevron-right" style="font-size: 0.6rem;"></i>
                                    <?php endif; ?>
                                    <?php if (!empty($entry['category_icon'])): ?><i class="<?= htmlspecialchars($entry['category_icon']) ?>"></i> <?php endif; ?>
                                    <a href="<?= BASE_PATH ?>lexicon/index?category=<?= (int)$entry['category_id'] ?>" class="text-tertiary-text hover:underline"><?= htmlspecialchars($entry['category_name']) ?></a>
                                </span>
                            <?php endif; ?>
                            <?php foreach ($entryTags as $etag): ?>
                                <a href="<?= BASE_PATH ?>lexicon/index?tag=<?= (int)$etag['id'] ?>" class="ignis-chip no-underline" style="background-color: <?= htmlspecialchars($etag['color']) ?>; color: var(--white);"><?= htmlspecialchars($etag['name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Entry Content -->
                    <article class="twplus-section-card p-4">
                        <!-- Header with Title and Competency -->
                        <?php if ($competency): ?>
                            <div class="kb-header twplus-detail-hero relative" style="background-color: <?= $competency['bg'] ?>;">
                                <!-- Category badge positioned in top right -->
                                <span class="kb-category-badge">
                                    <?= KBHelper::getTypeLabel($entry['type']) ?>
                                </span>
                                <div class="flex justify-between items-center">
                                    <div class="kb-header-content grow mr-3">
                                        <h2><?= htmlspecialchars($entry['title']) ?></h2>
                                        <?php if (!empty($entry['subtitle'])): ?>
                                            <p class="subtitle"><?= htmlspecialchars($entry['subtitle']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right">
                                        <div class="kb-freigabe-badge" style="background-color: <?= $competency['color'] ?>; color: <?= KBHelper::competencyNeedsDarkText($entry['competency_level']) ? 'var(--black)' : 'var(--white)' ?>;">
                                            Freigabe: <?= $competency['label'] ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="kb-header-content twplus-detail-hero mb-4 relative">
                                <span class="kb-category-badge" style="top: 0; right: 0;">
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
                            <div>
                                <div>
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
                            <div>
                                <div>
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
                            <div class="twplus-link-grid mt-3">
                                <?php foreach ($relatedEntries as $rel):
                                    $relComp = KBHelper::getCompetencyInfo($rel['competency_level']);
                                ?>
                                    <div>
                                        <a href="<?= BASE_PATH ?>lexicon/view?id=<?= $rel['id'] ?>" class="twplus-link-card">
                                                <div class="twplus-link-card__icon">
                                                    <i class="fa-solid fa-<?= $rel['type'] === 'medication' ? 'pills' : ($rel['type'] === 'measure' ? 'hand-holding-medical' : 'file-lines') ?> fa-lg" style="color: <?= KBHelper::getTypeColor($rel['type']) ?>;"></i>
                                                </div>
                                                <div class="twplus-link-card__body">
                                                    <div class="twplus-link-card__title"><?= htmlspecialchars($rel['title']) ?></div>
                                                    <?php if (!empty($rel['subtitle'])): ?>
                                                        <small class="text-tertiary-text"><?= htmlspecialchars(mb_strimwidth($rel['subtitle'], 0, 80, '...')) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="ml-2 flex flex-col gap-1 items-end">
                                                    <span class="ignis-chip" style="background-color: <?= KBHelper::getTypeColor($rel['type']) ?>; color: var(--white); font-size: 0.65rem;"><?= KBHelper::getTypeLabel($rel['type']) ?></span>
                                                    <?php if ($relComp): ?>
                                                        <span class="ignis-chip" style="background-color: <?= $relComp['bg'] ?>; color: <?= $relComp['text'] ?? 'var(--white)' ?>; font-size: 0.65rem;"><?= $relComp['label'] ?></span>
                                                    <?php endif; ?>
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
                                <div class="flex flex-wrap gap-2 twplus-mobile-actions">
                                    <?php if (Permissions::check(['admin', 'kb.edit'])): ?>
                                        <a href="<?= BASE_PATH ?>lexicon/edit?id=<?= $entry['id'] ?>" class="ignis-btn ignis-btn--secondary ignis-btn--icon ignis-btn--sm" data-ignis-tooltip="Bearbeiten" aria-label="Bearbeiten">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        
                                        <form method="POST" action="<?= BASE_PATH ?>lexicon/pin" style="margin: 0; display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                                            <input type="hidden" name="action" value="<?= !empty($entry['is_pinned']) ? 'unpin' : 'pin' ?>">
                                            <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--icon ignis-btn--sm" data-ignis-tooltip="<?= !empty($entry['is_pinned']) ? 'Lösen' : 'Anpinnen' ?>" aria-label="<?= !empty($entry['is_pinned']) ? 'Lösen' : 'Anpinnen' ?>">
                                                <i class="fa-solid fa-thumbtack"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if (Permissions::check(['admin', 'kb.archive'])): ?>
                                        <?php if ($entry['is_archived']): ?>
                                            <form method="POST" action="<?= BASE_PATH ?>lexicon/archive" style="margin: 0; display: inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                                                <input type="hidden" name="action" value="restore">
                                                <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--icon ignis-btn--sm" data-ignis-tooltip="Wiederherstellen" aria-label="Wiederherstellen">
                                                    <i class="fa-solid fa-rotate-left"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="<?= BASE_PATH ?>lexicon/archive" style="margin: 0; display: inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                                                <input type="hidden" name="action" value="archive">
                                                <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--icon ignis-btn--sm" data-ignis-tooltip="Archivieren" aria-label="Archivieren">
                                                    <i class="fa-solid fa-box-archive"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        </div><!-- /.kb-content-wrapper -->
                    </article>
                </div>
        </div>
    </div>
