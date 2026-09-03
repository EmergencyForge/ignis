<?php

declare(strict_types=1);

namespace Plugin\KnowledgeBase\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_kb_entry_relations` — "Siehe auch"-Verknüpfungen
 * zwischen zwei Wissensdatenbank-Einträgen.
 *
 * Die Paare werden normalisiert gespeichert (entry_id < related_entry_id),
 * damit jede Beziehung nur einmal existiert — beim Abfragen müssen daher
 * immer beide Richtungen berücksichtigt werden.
 *
 * @property int $entry_id          FK → intra_kb_entries
 * @property int $related_entry_id  FK → intra_kb_entries
 */
class KbEntryRelation extends Model
{
    protected $table = 'intra_kb_entry_relations';

    /**
     * Composite Primary Key (entry_id, related_entry_id) — Eloquent kann
     * damit nicht per find()/save() umgehen; Zugriffe laufen über
     * where()-Queries bzw. insertOrIgnore().
     */
    protected $primaryKey = null;

    public $incrementing = false;
}
