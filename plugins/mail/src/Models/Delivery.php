<?php

declare(strict_types=1);

namespace Plugin\Mail\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_mail_deliveries` — die eigene Kopie einer
 * Nachricht in einem Postfach. `deleted_at` ist der weiche Vermerk für
 * „endgültig gelöscht“: nur diese Kopie verschwindet, die der anderen
 * Beteiligten bleibt.
 *
 * @property int         $id
 * @property int         $message_id
 * @property int         $mailbox_id
 * @property string      $role    sender|to|cc|bcc
 * @property string      $folder  inbox|sent|drafts|archive|trash
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property bool        $flagged
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Message $message
 */
class Delivery extends Model
{
    protected $table = 'intra_mail_deliveries';

    /** @var array<string,string> */
    protected $casts = [
        'id'         => 'integer',
        'message_id' => 'integer',
        'mailbox_id' => 'integer',
        'flagged'    => 'boolean',
        'read_at'    => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }
}
