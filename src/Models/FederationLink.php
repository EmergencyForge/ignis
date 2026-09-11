<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Eloquent-Model für `intra_federation_links` — Verknüpfungen zu anderen
 * intraRP-Instanzen (Instanzvernetzung/Federation).
 *
 * Pro Link: API-Keys in beide Richtungen, Consume-/Provide-Flags für
 * Personal, eNOTF und Feuerwehr-Einsätze sowie Sync-Intervall und -Status.
 * `created_at`/`updated_at` werden von der DB gepflegt (DEFAULT/ON UPDATE
 * CURRENT_TIMESTAMP), daher kein Eloquent-Timestamp-Handling.
 *
 * @property int         $id
 * @property string      $instance_id        UUID der Remote-Instanz
 * @property string      $instance_name
 * @property string      $instance_url
 * @property string      $api_key_outgoing   Key, den wir beim Abruf mitsenden
 * @property string      $api_key_incoming   Key, den die Gegenseite senden muss
 * @property int         $consume_personnel
 * @property int         $consume_enotf
 * @property int         $consume_fire
 * @property int         $provide_personnel
 * @property int         $provide_enotf
 * @property int         $provide_fire
 * @property int         $sync_interval_minutes
 * @property string|null $last_sync_at
 * @property string      $last_sync_status   enum: success|error|pending
 * @property string|null $last_sync_error
 * @property int         $is_active
 */
class FederationLink extends Model
{
    protected $table = 'intra_federation_links';
}
