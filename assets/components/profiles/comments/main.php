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
    <div class="text-center text-[var(--text-dimmed,#818189)] py-3" style="font-size: var(--font-size-sm);">
        <i class="fa-solid fa-comments" style="font-size: 1.5rem; opacity: 0.3;"></i>
        <p class="mb-0 mt-2">Keine Kommentare vorhanden</p>
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
    <nav aria-label="Kommentar-Seiten" class="mt-3">
        <ul class="pagination pagination-sm justify-center mb-0">
            <?php foreach (\App\Helpers\Pagination::pages((int) $page, (int) $totalPages) as $entry):
                if ($entry === null): ?>
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php else:
                    $baseParams['page'] = $entry;
                    $url = '?' . http_build_query($baseParams);
                ?>
                    <li class="page-item <?= $entry === (int) $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $url ?>"><?= $entry ?></a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </nav>
<?php endif; ?>
