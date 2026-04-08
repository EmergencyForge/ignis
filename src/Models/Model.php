<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Base-Model für alle intraRP-Eloquent-Models.
 *
 * Setzt projektweite Defaults:
 *
 *   - $timestamps = false: Die meisten Legacy-Tabellen haben nur `created_at`
 *     (mit DB-Default CURRENT_TIMESTAMP), kein `updated_at`. Eloquent-Models
 *     können das individuell überschreiben, wenn sie beide Spalten haben.
 *
 *   - guarded = []: Mass-Assignment-Schutz wird bewusst nicht verwendet.
 *     Stattdessen kontrollieren die Controller / Form-Requests welche Felder
 *     gespeichert werden dürfen (Phase 3 — Validation-Schicht).
 *
 *   - $perPage = 25: Default für Pagination.
 */
abstract class Model extends EloquentModel
{
    public $timestamps = false;

    protected $guarded = [];

    protected $perPage = 25;
}
