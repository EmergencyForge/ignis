<?php
/**
 * View: eNOTF Loggedout
 *
 * Logout-Side-Effects (DB-Writes, Session-Cleanup) liegen im EnotfController.
 *
 * @var string $pinEnabled
 * @var \PDO   $pdo
 */

$prot_url = "https://" . SYSTEM_URL . "/enotf/index.php";

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "eNOTF";
    include __DIR__ . '/../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" data-pin-enabled="<?= $pinEnabled ?>">
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="w-full" id="edivi__container">
            <div class="h-full">
                <div id="edivi__content">
                    <div class="edivi__login-buttons">
                        <div class="flex flex-wrap -mx-3">
                            <div class="flex-1 px-3">
                                Sie sind nicht angemeldet!
                            </div>
                            <div class="w-3/12 px-3">
                                <a href="login.php">anmelden</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </form>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>
