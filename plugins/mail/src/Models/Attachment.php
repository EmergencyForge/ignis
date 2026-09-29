<?php

declare(strict_types=1);

namespace Plugin\Mail\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_mail_attachments`. Die Datei liegt privat
 * unter storage/private/mail-attachments/ (AttachmentStorage).
 *
 * @property int    $id
 * @property int    $message_id
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int    $size
 */
class Attachment extends Model
{
    protected $table = 'intra_mail_attachments';

    /** @var array<string,string> */
    protected $casts = [
        'id'         => 'integer',
        'message_id' => 'integer',
        'size'       => 'integer',
    ];
}
