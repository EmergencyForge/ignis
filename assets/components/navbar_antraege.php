<?php

use App\Auth\Permissions; ?>

<div class="cirs-nav">
    <h6>Anträge</h6>
    <div class="cirs-link">
        <a href="<?= BASE_PATH ?>antrag/befoerderung.php" class="no-underline">Neuen Beförderungsantrag stellen </i></a>
    </div>
    <?php
    if (isset($_SESSION['userid']) && isset($_SESSION['permissions'])) {
        if (Permissions::check(['admin', 'application.view'])) { ?>
            <hr class="my-3">
            <h6>Verwaltung</h6>
            <div class="cirs-link mb-2">
                <a href="<?= BASE_PATH ?>antrag/admin/list.php" class="no-underline">Übersicht</i></a>
            </div>
            <div class="cirs-link mb-2">
                <a href="<?= BASE_PATH ?>index.php" class="no-underline">Zurück zum Dashboard</i></a>
            </div>
            <div class="cirs-link mb-2">
                <a href="<?= BASE_PATH ?>logout.php" class="no-underline">Abmelden</a>
            </div>
        <?php }
    } else { ?>
        <div class="cirs-login">
            <a href="<?= BASE_PATH ?>login.php" class="no-underline"><i class="fa-solid fa-user"></i> Login</a>
        </div>
    <?php } ?>
</div>