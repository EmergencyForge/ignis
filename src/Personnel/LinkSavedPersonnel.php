<?php

declare(strict_types=1);

namespace App\Personnel;

use App\Events\PersonnelSaved;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Nach dem Speichern eines Mitarbeiters: über seine Discord-ID verknüpfen,
 * wenn genau ein freies Konto passt (ADR-0002, Punkt 3).
 */
final class LinkSavedPersonnel
{
    public function handle(PersonnelSaved $event): void
    {
        $tag = Capsule::table('intra_mitarbeiter')->where('id', $event->personnelId)->value('discordtag');
        AccountLink::autoLinkByDiscord(is_string($tag) ? $tag : null);
    }
}
