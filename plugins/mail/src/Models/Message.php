<?php

declare(strict_types=1);

namespace Plugin\Mail\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_mail_messages`. `body_html` ist die beim
 * Senden gerenderte Momentaufnahme (NULL beim Entwurf), `header_json` der
 * Empfänger-Schnappschuss mit BCC. Wer BCC sehen darf, entscheidet
 * MailController::visibleHeader().
 *
 * @property int                      $id
 * @property int                      $sender_mailbox_id
 * @property string                   $subject
 * @property array<string,mixed>      $body_json
 * @property string|null              $body_html
 * @property array<string,list<string>>|null $header_json
 * @property string                   $thread_id
 * @property int|null                 $in_reply_to
 * @property string                   $status  draft|sent
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Mailbox $senderMailbox
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Attachment> $attachments
 */
class Message extends Model
{
    protected $table = 'intra_mail_messages';

    /** @var array<string,string> */
    protected $casts = [
        'id'                => 'integer',
        'sender_mailbox_id' => 'integer',
        'in_reply_to'       => 'integer',
        'body_json'         => 'array',
        'header_json'       => 'array',
        'sent_at'           => 'datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    /** @return BelongsTo<Mailbox, $this> */
    public function senderMailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class, 'sender_mailbox_id');
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'message_id');
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'message_id');
    }
}
