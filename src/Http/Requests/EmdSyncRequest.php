<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Validation\FormRequest;
use Respect\Validation\Validator as v;

/**
 * Minimal-Validation für POST /api/emd/sync.
 *
 * Der EMD-Sync-Endpoint bekommt ein **hochvariables** Payload-Schema
 * (Type-basiertes Dispatching, v1/v2-Protokoll, optionale Unter-Bereiche
 * wie `vehicle_registry`, `dispatch_data`, `lagemeldungen`, ...). Eine
 * strikte Schema-Validierung würde die Flexibilität kaputt machen —
 * stattdessen prüfen wir nur, dass der Body überhaupt ein JSON-Objekt
 * ist. Alle weiteren Form-Checks passieren im Controller bzw. werden
 * defensiv beim Zugriff gemacht.
 *
 * Die API-Key-Prüfung läuft nicht hier, sondern in der ApiKeyMiddleware
 * am Route-Eingang.
 */
final class EmdSyncRequest extends FormRequest
{
    protected function rules(): array
    {
        return [
            // Wir verlangen nichts konkret — die Middleware hat API-Key
            // schon geprüft. Wenn der Body komplett leer/kaputt ist,
            // entdeckt das der Controller beim ersten Zugriff und
            // antwortet passend. Hier ist bewusst ein leeres Rules-Set.
        ];
    }
}
