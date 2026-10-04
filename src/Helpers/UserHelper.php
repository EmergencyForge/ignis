<?php

namespace App\Helpers;

use App\Models\Personnel;
use App\Models\User;
use App\Personnel\AccountLink;

class UserHelper
{
    /**
     * Name des angemeldeten Kontos: der verknüpfte Mitarbeiter, sonst der
     * Name am Konto. 'Unknown', wenn beides fehlt.
     */
    public function getCurrentUserFullname(): string
    {
        return $this->currentName() ?? 'Unknown';
    }

    /**
     * Name für Protokolle und Benachrichtigungen; ohne Namen 'Admin #ID',
     * damit das Konto trotzdem arbeiten kann.
     */
    public function getCurrentUserFullnameForAction(): string
    {
        return $this->currentName() ?? 'Admin #' . ($_SESSION['userid'] ?? 'Unknown');
    }

    /**
     * Ist das angemeldete Konto mit einem Mitarbeiter verknüpft?
     */
    public function hasLinkedProfile(): bool
    {
        return AccountLink::current() !== null;
    }

    private function currentName(): ?string
    {
        $fullname = AccountLink::current()?->fullname;
        if (!empty($fullname)) {
            return $fullname;
        }
        $userId = (int) ($_SESSION['userid'] ?? 0);
        $fullname = $userId > 0 ? User::query()->whereKey($userId)->value('fullname') : null;

        return !empty($fullname) ? (string) $fullname : null;
    }

    /**
     * Check if the system is new (no Mitarbeiter profiles exist)
     *
     * @return bool True if system is new, false otherwise
     */
    public function isNewSystem(): bool
    {
        return Personnel::count() === 0;
    }
}
