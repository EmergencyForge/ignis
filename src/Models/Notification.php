<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Eloquent-Model für `intra_notifications` — In-App-Benachrichtigungen.
 *
 * Typen: antrag, protokoll, dokument, system, fire_protocol
 * (validiert im NotificationManager, nicht im Model).
 *
 * @property int         $id
 * @property int         $user_id
 * @property string      $type
 * @property string      $title
 * @property string|null $message
 * @property string|null $link
 * @property int         $is_read
 * @property string|null $read_at
 * @property string      $created_at
 */
class Notification extends Model
{
    protected $table = 'intra_notifications';

    protected $casts = [
        'id'      => 'integer',
        'user_id' => 'integer',
        'is_read' => 'integer',
    ];
}
