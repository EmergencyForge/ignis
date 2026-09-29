<?php

declare(strict_types=1);

namespace Plugin\Mail\Listeners;

use App\Events\PersonnelDeleted;
use App\Events\PersonnelSaved;
use Plugin\Mail\MailboxProvisioner;

/**
 * Hält das Postfach eines Mitarbeiters in Stand, siehe
 * MailboxProvisioner::sync(): gespeichert → anlegen oder abgleichen,
 * gelöscht → stilllegen (der Mitarbeiter ist dann nicht mehr zu laden,
 * sync() erkennt das selbst).
 */
final class SyncMailbox
{
    public function __construct(private readonly MailboxProvisioner $provisioner) {}

    public function handle(PersonnelSaved|PersonnelDeleted $event): void
    {
        $this->provisioner->sync($event->personnelId);
    }
}
