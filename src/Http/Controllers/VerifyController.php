<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EditorDocument;
use App\Security\SecretBox;
use DateTimeImmutable;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use EmergencyForge\Training\VerificationCode;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * GET /verify: öffentliche Echtheitsprüfung einer Urkunde per Prüfcode,
 * ohne Anmeldung. Angaben gibt es nur zu einer gültigen Urkunde; bei einer
 * widerrufenen oder abgelaufenen steht nur das Datum, damit ein fremder
 * Code nicht verrät, wem er gehört.
 *
 * Alles kommt aus den beim Ausstellen eingefrorenen Werten, also das, was
 * auch auf der Urkunde steht. Noten und das Dokument selbst bleiben intern.
 */
final class VerifyController extends Controller
{
    public const LIMIT_PER_HOUR = 20;
    private const TABLE = 'intra_verification_lookups';

    public function show(Request $request): Response
    {
        $input  = trim((string) ($request->query['code'] ?? ''));
        $status = 200;
        $data   = ['input' => $input, 'state' => null];

        if ($input !== '') {
            $code = VerificationCode::normalize($input);
            if ($code === null) {
                // Tippfehler fallen über die Prüfziffer auf, ohne Abfrage und ohne gegen die Grenze zu zählen.
                $data['state'] = 'malformed';
            } else {
                $data['input'] = VerificationCode::format($code);
                if ($this->allow((string) ($request->server['REMOTE_ADDR'] ?? ''))) {
                    $data = array_merge($data, self::lookup($code));
                } else {
                    $data['state'] = 'limited';
                    $status        = 429;
                }
            }
        }

        ob_start();
        $this->renderView('verify', $data);

        return new Response($status, (string) ob_get_clean(), [
            'Content-Type'  => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag'  => 'noindex',
        ]);
    }

    /**
     * @return array{state: string, date?: string, details?: array<string,string>}
     */
    private static function lookup(string $code): array
    {
        $document = EditorDocument::query()
            ->with('mitarbeiter')
            ->where('verification_code', $code)
            ->where('status', EditorDocument::STATUS_ISSUED)
            ->first();
        if ($document === null) {
            return ['state' => 'unknown'];
        }
        if ($document->revoked_at !== null) {
            return ['state' => 'revoked', 'date' => $document->revoked_at->format('d.m.Y')];
        }

        $values = $document->frozen_values ?? [];
        $until  = (string) ($values['qualifikation.gueltig_bis'] ?? '');
        $end    = $until !== '' ? DateTimeImmutable::createFromFormat('!d.m.Y', $until) : false;
        if ($end !== false && $end < new DateTimeImmutable('today')) {
            return ['state' => 'expired', 'date' => $until];
        }

        // Wie im Kopf der System-Urkunden: Art und Stadt, sonst der Name.
        $issuer = trim(($values['organisation.art'] ?? '') . ' ' . ($values['organisation.stadt'] ?? ''));

        return ['state' => 'valid', 'details' => [
            'Name'            => (string) ($values['mitarbeiter.name'] ?? $document->mitarbeiter->fullname ?? ''),
            'Lehrgang'        => (string) (($values['lehrgang.titel'] ?? '') !== '' ? $values['lehrgang.titel'] : $document->title),
            'Ausgestellt am'  => $document->issued_at?->format('d.m.Y') ?? '',
            'Gültig bis'      => $until !== '' ? $until : 'unbefristet',
            'Ausgestellt von' => $issuer !== '' ? $issuer : (string) ($values['organisation.name'] ?? ''),
        ]];
    }

    /**
     * Zählt die Abfrage, wenn der Anschluss noch unter der Grenze liegt.
     * Gespeichert wird nur ein HMAC der Adresse.
     *
     * ponytail: hinter einem Reverse-Proxy ohne mod_remoteip teilen sich
     * alle dieselbe REMOTE_ADDR und damit eine Grenze.
     */
    private function allow(string $ip): bool
    {
        $hash  = hash_hmac('sha256', $ip, SecretBox::derive('verify-rate-limit'));
        $since = date('Y-m-d H:i:s', time() - 3600);
        Capsule::table(self::TABLE)->where('created_at', '<', $since)->delete();
        if (Capsule::table(self::TABLE)->where('ip_hash', $hash)->where('created_at', '>=', $since)->count() >= self::LIMIT_PER_HOUR) {
            return false;
        }
        Capsule::table(self::TABLE)->insert(['ip_hash' => $hash, 'created_at' => date('Y-m-d H:i:s')]);

        return true;
    }
}
