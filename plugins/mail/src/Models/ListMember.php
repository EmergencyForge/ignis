<?php

declare(strict_types=1);

namespace Plugin\Mail\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_mail_list_members`: Mitglied eines statischen
 * Verteilers.
 *
 * @property int $id
 * @property int $list_id
 * @property int $mailbox_id
 */
class ListMember extends Model
{
    protected $table = 'intra_mail_list_members';

    /** @var array<string,string> */
    protected $casts = [
        'id'         => 'integer',
        'list_id'    => 'integer',
        'mailbox_id' => 'integer',
    ];
}
