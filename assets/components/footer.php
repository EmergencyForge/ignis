<!-- Footer für das Intranet-System -->
<footer class="footer mt-auto py-3 text-white">
    <div class="container mx-auto">
        <div class="grid grid-cols-1 gap-3 items-end md:grid-cols-3">
            <div>
                <img src="/assets/img/defaultLogo.webp" alt="Logo" height="48px" width="auto">
                <p class="text-sm">Verwaltungsportal der <?php echo RP_ORGTYPE . " " . SERVER_CITY ?></p>
            </div>
            <div class="text-center">
                <p class="text-sm">&copy; 2024-<?php echo date("Y") ?> <em><strong>ıgnıs</strong></em> by <a href="https://emergencyforge.de" target="_blank" rel="nofollow">EmergencyForge</a>. Alle Rechte vorbehalten.</p>
            </div>
            <div class="md:text-right">
                <?php
                $impressumUrl = defined('LEGAL_IMPRESSUM_URL') ? LEGAL_IMPRESSUM_URL : '';
                $datenschutzUrl = defined('LEGAL_DATENSCHUTZ_URL') ? LEGAL_DATENSCHUTZ_URL : '';
                ?>
                <?php if ($impressumUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($impressumUrl) ?>" target="_blank" class="text-white text-sm">Impressum</a>
                <?php endif; ?>
                <?php if ($datenschutzUrl !== ''): ?>
                    <?php if ($impressumUrl !== ''): ?>
                        <span class="text-white text-sm mx-1">|</span>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($datenschutzUrl) ?>" target="_blank" class="text-white text-sm">Datenschutz</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>