<?php

declare(strict_types=1);

namespace Plugin\Enotf\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_enotf_quicklinks` — konfigurierbare
 * Schnellzugriff-Links auf der eNOTF-Startseite. Die Zuordnung zur
 * Kategorie läuft über `category_slug` (siehe Migration
 * ..._alter_intra_enotf_quicklinks_29122025_category_slug).
 */
class Quicklink extends Model
{
    protected $table = 'intra_enotf_quicklinks';
}
