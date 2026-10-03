<?php

declare(strict_types=1);

namespace Plugin\Mail\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_mail_lists`: Verteiler, statisch (feste
 * Mitglieder) oder dynamisch (`rule`, siehe MailDirectory). `senders`
 * sagt, wer an den Verteiler schreiben darf: alle mit Mail-Zugang oder
 * nur die Verteiler-Verwaltung.
 *
 * @property int         $id
 * @property string      $address
 * @property string      $name
 * @property string      $kind     static|dynamic
 * @property array<string, list<int>>|null $rule
 * @property string      $senders  all|managers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ListMember> $members
 */
class MailList extends Model
{
    public const SENDERS_ALL      = 'all';
    public const SENDERS_MANAGERS = 'managers';

    protected $table = 'intra_mail_lists';

    /** @var array<string,string> */
    protected $casts = [
        'id'   => 'integer',
        'rule' => 'array',
    ];

    /** @return HasMany<ListMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(ListMember::class, 'list_id');
    }
}
