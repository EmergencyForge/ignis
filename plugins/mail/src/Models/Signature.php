<?php

declare(strict_types=1);

namespace Plugin\Mail\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_mail_signatures`: die eigene Signatur eines
 * Postfachs als Editor-JSON.
 *
 * @property int                 $id
 * @property int                 $mailbox_id
 * @property array<string,mixed> $body_json
 */
class Signature extends Model
{
    protected $table = 'intra_mail_signatures';

    /** @var array<string,string> */
    protected $casts = [
        'id'         => 'integer',
        'mailbox_id' => 'integer',
        'body_json'  => 'array',
    ];
}
