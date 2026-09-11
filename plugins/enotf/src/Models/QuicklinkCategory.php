<?php

declare(strict_types=1);

namespace Plugin\Enotf\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_enotf_categories` — Kategorien für die
 * eNOTF-Quicklinks; referenziert werden sie von `intra_enotf_quicklinks`
 * über den Slug.
 */
class QuicklinkCategory extends Model
{
    protected $table = 'intra_enotf_categories';
}
