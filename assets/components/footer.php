<!-- Footer für das Intranet-System -->
<footer class="footer mt-auto py-3 text-light">
    <div class="container">
        <div class="row d-flex align-items-end justify-content-between">
            <div class="col-md-4">
                <img src="/assets/img/defaultLogo.webp" alt="Logo" height="48px" width="auto">
                <p class="small">Verwaltungsportal der <?php echo RP_ORGTYPE . " " . SERVER_CITY ?></p>
            </div>
            <div class="col-md-4 text-center">
                <p class="small">&copy; 2024-<?php echo date("Y") ?> <a href="https://emergencyforge.de" target="_blank" rel="nofollow">EmergencyForge</a>. Alle Rechte vorbehalten.</p>
            </div>
            <div class="col-md-4 text-end">
                <?php
                /** @phpstan-ignore-next-line Constants loaded from DB at runtime */
                $impressumUrl = defined('LEGAL_IMPRESSUM_URL') ? LEGAL_IMPRESSUM_URL : '';
                /** @phpstan-ignore-next-line Constants loaded from DB at runtime */
                $datenschutzUrl = defined('LEGAL_DATENSCHUTZ_URL') ? LEGAL_DATENSCHUTZ_URL : '';
                ?>
                <?php if ($impressumUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($impressumUrl) ?>" target="_blank" class="text-light small">Impressum</a>
                <?php endif; ?>
                <?php if ($datenschutzUrl !== ''): ?>
                    <?php if ($impressumUrl !== ''): ?>
                        <span class="text-light small mx-1">|</span>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($datenschutzUrl) ?>" target="_blank" class="text-light small">Datenschutz</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>