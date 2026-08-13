<?php
require __DIR__ . '/../../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Personnel\PersonalLogManager;

$commentsPerPage = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$logManager = new PersonalLogManager($pdo);
$result = $logManager->getComments($_GET['id'], $page, $commentsPerPage);
$comments = $result['entries'];
$totalComments = $result['total'];

$typeIcons = [
    'note' => 'fa-sticky-note',
    'positive' => 'fa-circle-check',
    'negative' => 'fa-circle-xmark',
];

if (empty($comments)): ?>
    <div class="twplus-empty">
        <i class="fa-solid fa-comments twplus-empty__icon"></i>
        <h3 class="twplus-empty__title">Keine Kommentare vorhanden</h3>
        <p class="twplus-empty__description">Neue Notizen und Rückmeldungen erscheinen hier chronologisch.</p>
    </div>
<?php else: ?>
    <?php foreach ($comments as $comment):
        $commentType = PersonalLogManager::getTypeName($comment['type']);
        $comtime = date("d.m.Y H:i", strtotime($comment['datetime']));
        $icon = $typeIcons[$commentType] ?? 'fa-sticky-note';
        $canDelete = Permissions::check('admin') && $comment['type'] <= 3;
    ?>
        <div class="comment-item comment-item--<?= $commentType ?>" id="comment-<?= $comment['logid'] ?>">
            <div class="comment-item__indicator"></div>
            <div class="comment-item__body">
                <div class="comment-item__content"><?= htmlspecialchars($comment['content']) ?></div>
                <div class="comment-item__meta">
                    <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($comment['paneluser']) ?></span>
                    <span><i class="fa-solid fa-clock"></i> <?= $comtime ?></span>
                </div>
            </div>
            <?php if ($canDelete): ?>
                <button type="button" class="comment-item__delete" title="Löschen"
                    onclick="showConfirm('Kommentar wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Kommentar löschen'}).then(function(ok) { if(ok) window.location.href='<?= BASE_PATH ?>personnel/comment-delete?id=<?= $comment['logid'] ?>&pid=<?= $comment['profilid'] ?>'; });">
                    <i class="fa-solid fa-trash"></i>
                </button>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif;

// Pagination
$totalPages = ceil($totalComments / $commentsPerPage);
if ($totalPages > 1):
    $baseParams = ['id' => $_GET['id']];
    if (isset($_GET['logpage'])) $baseParams['logpage'] = $_GET['logpage'];
?>
    <nav aria-label="Kommentar-Seiten" class="twplus-pagination-wrap">
        <span>Seite <?= (int) $page ?> von <?= (int) $totalPages ?></span>
        <ul class="ignis-pagination ignis-pagination--sm">
            <?php foreach (\App\Helpers\Pagination::pages((int) $page, (int) $totalPages) as $entry):
                if ($entry === null): ?>
                    <li><span class="ignis-pagination__item ignis-pagination__item--ellipsis">…</span></li>
                <?php else:
                    $baseParams['page'] = $entry;
                    $url = '?' . http_build_query($baseParams);
                ?>
                    <li>
                        <a class="ignis-pagination__item <?= $entry === (int) $page ? 'is-active' : '' ?>" href="<?= $url ?>"><?= $entry ?></a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </nav>
<?php endif; ?>
