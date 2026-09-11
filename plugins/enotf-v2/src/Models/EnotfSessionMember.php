<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Models;

use App\Models\Model;

/**
 * `intra_enotf_session_members` — ein Browser/Gerät pro Crew-Mitglied.
 *
 * FK `session_id` → intra_enotf_sessions.id (CASCADE). `session_token`
 * (UNIQUE, 64 Hex-Zeichen) identifiziert das Mitglied und liegt
 * clientseitig in `$_SESSION['enotf_session_token']`.
 * `position`: fahrer | beifahrer | praktikant.
 */
class EnotfSessionMember extends Model
{
    protected $table = 'intra_enotf_session_members';

    public const POSITIONS = ['fahrer', 'beifahrer', 'praktikant'];

    public function session()
    {
        return $this->belongsTo(EnotfSession::class, 'session_id');
    }
}
