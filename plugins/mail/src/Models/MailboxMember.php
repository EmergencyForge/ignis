<?php

declare(strict_types=1);

namespace Plugin\Mail\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_mail_mailbox_members`: ein Konto, das ein
 * Gruppenpostfach lesen und aus ihm senden darf. Gepflegt nur in der
 * Postfachverwaltung (`mail.admin`, MailAdminController).
 *
 * @property int $id
 * @property int $mailbox_id
 * @property int $user_id
 */
class MailboxMember extends Model
{
    protected $table = 'intra_mail_mailbox_members';

    /** @var array<string,string> */
    protected $casts = [
        'id'         => 'integer',
        'mailbox_id' => 'integer',
        'user_id'    => 'integer',
    ];
}
