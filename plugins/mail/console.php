<?php

/**
 * Mail: Console-Commands (nächtlicher Zeitplan für beide per Migration).
 */

return [
    \Plugin\Mail\Console\MailBackfillCommand::class,
    \Plugin\Mail\Console\MailCleanupCommand::class,
];
