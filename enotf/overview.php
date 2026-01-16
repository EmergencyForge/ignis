<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    // Entferne CSP Header - .htaccess kümmert sich um den Rest
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');
    // KEIN neuer CSP wird gesetzt!
}
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';
require_once __DIR__ . '/../assets/functions/enotf/user_auth_middleware.php';
require_once __DIR__ . '/../assets/functions/enotf/pin_middleware.php';

use App\Integrations\DiscordWebhook;

$prot_url = "https://" . SYSTEM_URL . "/enotf/index.php";

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

if (!isset($_SESSION['fahrername']) || !isset($_SESSION['protfzg'])) {
    header("Location: " . BASE_PATH . "enotf/loggedout.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
    try {
        $freigeber_name = $_SESSION['fahrername'];
        if (!empty($_SESSION['beifahrername'])) {
            $freigeber_name .= ', ' . $_SESSION['beifahrername'];
        }

        $stmt = $pdo->prepare("
            UPDATE intra_edivi 
            SET hidden_user = 1,
                freigeber_name = :freigeber_name,
                last_edit = NOW(),
                freigegeben = 1
            WHERE freigegeben = 0 
            AND (fzg_transp = :fzg_transp OR fzg_na = :fzg_na) 
            AND hidden = 0 
            AND hidden_user = 0
        ");
        $stmt->bindValue(':freigeber_name', $freigeber_name, PDO::PARAM_STR);
        $stmt->bindValue(':fzg_transp', $_SESSION['protfzg'], PDO::PARAM_STR);
        $stmt->bindValue(':fzg_na', $_SESSION['protfzg'], PDO::PARAM_STR);
        $stmt->execute();

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Fehler beim Löschen der Protokolle.";
        error_log("Fehler beim Löschen der Protokolle: " . $e->getMessage());
    }
}

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    $SITE_TITLE = "eNOTF";
    include __DIR__ . '/../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" id="edivi__login" data-pin-enabled="<?= $pinEnabled ?>">
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <div class="col" id="edivi__content">
                    <div class="row border-bottom edivi__header-overview" style="--bs-border-color: #333333">
                        <div class="col"></div>
                        <div class="col border-start" style="--bs-border-color: #333333">
                            <div class="row border-bottom" style="--bs-border-color: #333333">
                                <div class="col">Angemeldet:</div>
                            </div>
                            <div class="row">
                                <div class="col"><?= $_SESSION['fahrername'] ?? 'Fehler Fehler' ?></div>
                                <div class="col border-start" style="--bs-border-color: #333333"><?= $_SESSION['beifahrername'] ?? 'Fehler Fehler' ?></div>
                            </div>
                        </div>
                        <div class="col-2 border-start" style="padding:0;--bs-border-color: #333333">
                            <a href="loggedout.php" class="edivi__nidabutton-primary w-100 h-100 d-flex justify-content-center align-content-center">abmelden</a>
                        </div>
                    </div>
                    <div class="hr my-2" style="color:transparent"></div>
                    <div class="row">
                        <div class="col-8">
                            <div class="row">
                                <div class="col d-flex justify-content-start align-items-center">
                                    <h4 class="fw-bold">Einsatzprotokolle</h4>
                                </div>
                                <div class="col d-flex justify-content-end align-items-center gap-2">
                                    <button type="button" class="edivi__nidabutton" style="display:inline-block" onclick="window.location.reload();" title="Seite neu laden"><i class="fa-solid fa-rotate-right"></i></button>
                                    <a href="create.php" class="edivi__nidabutton" style="display:inline-block"><i class="fa-solid fa-plus" title="Neuen Einsatz erstellen"></i></a>
                                </div>
                            </div>
                            <div class="row ps-3">
                                <div class="col edivi__box p-4" style="overflow-x: hidden; overflow-y:auto; height: 70vh;">
                                    <?php
                                    $stmt = $pdo->prepare("SELECT patname, patgebdat, edatum, ezeit, enr, prot_by, freigegeben, pfname FROM intra_edivi WHERE freigegeben = 0 AND (fzg_transp = :fzg_transp OR fzg_na = :fzg_na) AND hidden = 0 AND hidden_user = 0 ORDER BY created_at ASC");
                                    $stmt->bindValue(':fzg_transp', $_SESSION['protfzg'], PDO::PARAM_STR);
                                    $stmt->bindValue(':fzg_na',     $_SESSION['protfzg'], PDO::PARAM_STR);
                                    $stmt->execute();
                                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    function protLabelFromIndex(int $i): string
                                    {
                                        $repeat  = intdiv($i, 26) + 1;
                                        $charIdx = $i % 26;
                                        $letter  = chr(65 + $charIdx);
                                        return str_repeat($letter, $repeat);
                                    }

                                    $rank = 0;

                                    foreach ($result as $row) {
                                        $label = protLabelFromIndex($rank++);

                                        if (!empty($row['edatum'])) {
                                            $row['edatum'] = (new DateTime($row['edatum']))->format('d.m.Y');
                                        } else {
                                            $row['edatum'] = '—';
                                        }

                                        if (!empty($row['ezeit'])) {
                                            $row['ezeit'] = (new DateTime($row['ezeit']))->format('H:i');
                                        } else {
                                            $row['ezeit'] = '—';
                                        }

                                        if (!empty($row['patgebdat'])) {
                                            $row['patgebdat'] = (new DateTime($row['patgebdat']))->format('d.m.Y');
                                        } else {
                                            $row['patgebdat'] = '—';
                                        }

                                        if (empty($row['patname'])) {
                                            $row['patname'] = '—';
                                        }

                                        if (empty($row['pfname'])) {
                                            $row['pfname'] = '—';
                                        }

                                        if ($row['prot_by'] == 1) {
                                    ?>
                                            <div class="edivi__einsatz-container">
                                                <a href="protokoll/index.php?enr=<?= $row['enr'] ?>" class="edivi__einsatz-link">
                                                    <div class="row edivi__einsatz edivi__einsatz-set">
                                                        <div class="col-2 edivi__einsatz-type"><span><?= htmlspecialchars($label) ?></span></div>
                                                        <div class="col edivi__einsatz-enr"><span>#<?= $row['enr'] ?> <span class="edivi__einsatz-cat">NA</span></span><br><?= $row['edatum'] ?> <?= $row['ezeit'] ?> Uhr</div>
                                                        <div class="col edivi__einsatz-name"><span>Patient:</span><br><?= $row['patname'] ?> * <?= $row['patgebdat'] ?></div>
                                                        <div class="col edivi__einsatz-freigeber"><span>Protokollant:</span><br><?= $row['pfname'] ?></div>
                                                    </div>
                                                </a>
                                            </div>
                                        <?php
                                        } else {
                                        ?>
                                            <div class="edivi__einsatz-container">
                                                <a href="protokoll/index.php?enr=<?= $row['enr'] ?>" class="edivi__einsatz-link">
                                                    <div class="row edivi__einsatz edivi__einsatz-set">
                                                        <div class="col-2 edivi__einsatz-type"><span><?= htmlspecialchars($label) ?></span></div>
                                                        <div class="col edivi__einsatz-enr"><span>#<?= $row['enr'] ?> <span class="edivi__einsatz-cat">RD</span></span><br><?= $row['edatum'] ?> <?= $row['ezeit'] ?> Uhr</div>
                                                        <div class="col edivi__einsatz-name"><span>Patient:</span><br><?= $row['patname'] ?> * <?= $row['patgebdat'] ?></div>
                                                        <div class="col edivi__einsatz-freigeber"><span>Protokollant:</span><br><?= $row['pfname'] ?></div>
                                                    </div>
                                                </a>
                                            </div>
                                    <?php
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="row ps-3">
                                <div class="col p-0" style="margin: 10px 0;">
                                    <button type="submit" class="edivi__nidabutton w-100" name="delete_all">alle löschen</button>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <?php
                            // Load all active categories
                            $stmt_categories = $pdo->prepare("SELECT * FROM intra_enotf_categories WHERE active = 1 ORDER BY sort_order ASC");
                            $stmt_categories->execute();
                            $all_categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);

                            foreach ($all_categories as $index => $category):
                                $accordion_id = 'accordion' . $category['id'];
                                $heading_id = 'heading' . $category['id'];
                                $collapse_id = 'collapse' . $category['id'];
                            ?>
                                <div class="row <?= $index > 0 ? 'mt-2' : '' ?>">
                                    <div class="col">
                                        <div class="accordion" id="<?= $accordion_id ?>" data-theme="dark">
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="<?= $heading_id ?>">
                                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapse_id ?>" aria-expanded="true" aria-controls="<?= $collapse_id ?>">
                                                        <?= htmlspecialchars($category['name']) ?>
                                                    </button>
                                                </h2>
                                                <div id="<?= $collapse_id ?>" class="accordion-collapse collapse show" aria-labelledby="<?= $heading_id ?>" data-bs-parent="#<?= $accordion_id ?>">
                                                    <div class="accordion-body">
                                                        <?php
                                                        // Load links for this category
                                                        $stmt = $pdo->prepare("SELECT * FROM intra_enotf_quicklinks WHERE category_slug = :slug AND active = 1 ORDER BY sort_order ASC");
                                                        $stmt->execute([':slug' => $category['slug']]);
                                                        $quicklinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                                        $currentRow = [];
                                                        foreach ($quicklinks as $link) {
                                                            // Start a new row if current row is empty
                                                            if (empty($currentRow)) {
                                                                echo '<div class="row">';
                                                            }

                                                            $currentRow[] = $link;

                                                            // Output the link
                                                            echo '<div class="' . htmlspecialchars($link['col_width']) . ' p-2">';
                                                            echo '<a href="' . htmlspecialchars($link['url']) . '" class="w-100 edivi__nidabutton" style="display:inline-block;text-align:center">';
                                                            echo '<i class="' . htmlspecialchars($link['icon']) . '"></i> ' . htmlspecialchars($link['title']);
                                                            echo '</a>';
                                                            echo '</div>';

                                                            // Close row after 2 items or if it's the last item
                                                            if (count($currentRow) >= 2 || $link === end($quicklinks)) {
                                                                echo '</div>';
                                                                $currentRow = [];
                                                            }
                                                        }

                                                        // If no links found, show a message
                                                        if (empty($quicklinks)) {
                                                            echo '<p class="text-muted">Keine Links verfügbar.</p>';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
    </form>
    <?php
    // Include Share Modals
    if (!defined('SHARE_MODALS_INCLUDED')) {
        define('SHARE_MODALS_INCLUDED', true);
        include __DIR__ . '/../assets/components/enotf/share-modals.php';
    }
    ?>
    <script>
        var modalCloseButton = document.querySelector('#myModal4 .btn-close');
        var freigeberInput = document.getElementById('freigeber');

        if (modalCloseButton && freigeberInput) {
            modalCloseButton.addEventListener('click', function() {
                freigeberInput.value = '';
            });
        }
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>