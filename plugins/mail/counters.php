<?php

/**
 * Mail-Zähler für die Sidebar (App\Support\NavigationCounters): die
 * ungelesenen Mails im Posteingang des eigenen Postfachs und der
 * Gruppenpostfächer, in denen das Konto Mitglied ist.
 */

declare(strict_types=1);

use Plugin\Mail\Controllers\MailController;
use Plugin\Mail\Models\Mailbox;

return [
    'mail' => static function (): ?int {
        $mailboxIds = Mailbox::accessibleIds();

        return $mailboxIds === [] ? null : array_sum(MailController::inboxUnread($mailboxIds));
    },
];
