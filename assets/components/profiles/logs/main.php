<?php
require __DIR__ . '/../../../../assets/config/database.php';

use App\Personnel\PersonalLogManager;

$logsPerPage = 6;
$logPage = isset($_GET['logpage']) ? (int)$_GET['logpage'] : 1;

$logManager = new PersonalLogManager($pdo);
$result = $logManager->getSystemLogs($_GET['id'], $logPage, $logsPerPage);
$logs = $result['entries'];
$totalLogs = $result['total'];

$typeIcons = [
    'rank' => 'fa-bolt',
    'modify' => 'fa-pen',
    'document' => 'fa-file-lines',
    'created' => 'fa-circle-plus',
    'note' => 'fa-sticky-note',
    'positive' => 'fa-circle-check',
    'negative' => 'fa-circle-xmark',
];

if (empty($logs)): ?>
    <div class="text-center text-[var(--text-dimmed,#818189)] py-3" style="font-size: var(--font-size-sm);">
        <i class="fa-solid fa-clipboard-list" style="font-size: 1.5rem; opacity: 0.3;"></i>
        <p class="mb-0 mt-2">Keine Protokolleinträge vorhanden</p>
    </div>
<?php else: ?>
    <?php foreach ($logs as $log):
        $logType = PersonalLogManager::getTypeName($log['type']);
        $logtime = date("d.m.Y H:i", strtotime($log['datetime']));
        $icon = $typeIcons[$logType] ?? 'fa-circle-info';
    ?>
        <div class="comment-item comment-item--<?= $logType ?>">
            <div class="comment-item__indicator"></div>
            <div class="comment-item__body">
                <div class="comment-item__content"><?= $log['content'] ?></div>
                <div class="comment-item__meta">
                    <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($log['paneluser']) ?></span>
                    <span><i class="fa-solid fa-clock"></i> <?= $logtime ?></span>
                </div>
            </div>
            <div class="comment-item__type-icon" title="<?= ucfirst($logType) ?>">
                <i class="fa-solid <?= $icon ?>"></i>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif;

// Pagination
$totalPages = ceil($totalLogs / $logsPerPage);
if ($totalPages > 1):
    $baseParams = ['id' => $_GET['id']];
    if (isset($_GET['page'])) $baseParams['page'] = $_GET['page'];
?>
    <nav aria-label="Systemprotokoll-Seiten" class="mt-3">
        <ul class="pagination pagination-sm justify-center mb-0">
            <?php foreach (\App\Helpers\Pagination::pages((int) $logPage, (int) $totalPages) as $entry):
                if ($entry === null): ?>
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php else:
                    $baseParams['logpage'] = $entry;
                    $url = '?' . http_build_query($baseParams);
                ?>
                    <li class="page-item <?= $entry === (int) $logPage ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $url ?>"><?= $entry ?></a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </nav>
<?php endif; ?>
