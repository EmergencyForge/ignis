<?php

declare(strict_types=1);

namespace Plugin\Enotf\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_enotf_session_members` — ein Crew-Mitglied
 * innerhalb einer eNOTF-Session, identifiziert über den Browser-Token
 * `session_token` (liegt clientseitig in `$_SESSION['enotf_session_token']`).
 *
 * `position` ist ein Enum (fahrer | beifahrer | praktikant), `created_at`
 * hat DB-Default CURRENT_TIMESTAMP.
 */
class EnotfSessionMember extends Model
{
    protected $table = 'intra_enotf_session_members';
}
