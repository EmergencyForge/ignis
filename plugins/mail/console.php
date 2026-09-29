<?php

/**
 * Mail — Console-Commands (Zeitplan für mail:cleanup per Migration).
 */

return [
    \Plugin\Mail\Console\MailBackfillCommand::class,
    \Plugin\Mail\Console\MailCleanupCommand::class,
];
