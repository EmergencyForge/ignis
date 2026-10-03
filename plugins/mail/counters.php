<?php

/**
 * Mail-Zähler für die Sidebar (App\Support\NavigationCounters): die
 * ungelesenen Mails im Posteingang des eigenen Postfachs.
 */

declare(strict_types=1);

use Plugin\Mail\Controllers\MailController;
use Plugin\Mail\Models\Mailbox;

return [
    'mail' => static function (): ?int {
        $mailbox = Mailbox::current();

        return $mailbox === null ? null : (MailController::unreadCounts($mailbox)['inbox'] ?? 0);
    },
];
