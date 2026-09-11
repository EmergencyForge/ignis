<?php

namespace App\Helpers;

use App\Models\Personnel;
use App\Models\User;

class UserHelper
{
    /**
     * Get user's fullname from linked Mitarbeiter profile based on Discord ID
     * Falls back to intra_users.fullname if no Mitarbeiter profile is linked
     *
     * @param string $discordId The Discord ID of the user
     * @return string|null The fullname or null if not found
     */
    public function getFullnameByDiscordId(string $discordId): ?string
    {
        // First try to get fullname from Mitarbeiter profile
        $fullname = Personnel::where('discordtag', $discordId)->value('fullname');

        if (!empty($fullname)) {
            return $fullname;
        }

        // Fallback to intra_users table
        return User::where('discord_id', $discordId)->value('fullname');
    }

    /**
     * Get user's fullname from session
     * This is a convenience method that uses the Discord ID from session
     *
     * @return string The fullname, defaults to 'Unknown' if not found
     */
    public function getCurrentUserFullname(): string
    {
        if (!isset($_SESSION['discordtag'])) {
            return 'Unknown';
        }

        $fullname = $this->getFullnameByDiscordId($_SESSION['discordtag']);
        return $fullname ?? 'Unknown';
    }

    /**
     * Get user's fullname for actions/operations
     * Returns 'Admin #ID' if no profile is linked, allowing the user to continue working
     *
     * @return string The fullname or 'Admin #ID' if not found
     */
    public function getCurrentUserFullnameForAction(): string
    {
        if (!isset($_SESSION['discordtag'])) {
            $userId = $_SESSION['userid'] ?? 'Unknown';
            return 'Admin #' . $userId;
        }

        $fullname = $this->getFullnameByDiscordId($_SESSION['discordtag']);

        if ($fullname === null) {
            $userId = $_SESSION['userid'] ?? 'Unknown';
            return 'Admin #' . $userId;
        }

        return $fullname;
    }

    /**
     * Check if current user has a linked Mitarbeiter profile
     *
     * @return bool True if user has linked profile, false otherwise
     */
    public function hasLinkedProfile(): bool
    {
        if (!isset($_SESSION['discordtag'])) {
            return false;
        }

        return Personnel::where('discordtag', $_SESSION['discordtag'])->exists();
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
