<?php

/**
 * Mail — Event-Listener. Die Postfächer folgen den Mitarbeitern: angelegt
 * oder gespeichert → Postfach anlegen bzw. abgleichen, gelöscht →
 * stilllegen (Plugin\Mail\MailboxProvisioner).
 */

return [
    \App\Events\PersonnelSaved::class   => [\Plugin\Mail\Listeners\SyncMailbox::class],
    \App\Events\PersonnelDeleted::class => [\Plugin\Mail\Listeners\SyncMailbox::class],
];
