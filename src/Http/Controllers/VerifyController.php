<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Documents\Editor\PdfGenerator;
use App\Http\ErrorPage;
use App\Models\EditorDocument;
use App\Security\SecretBox;
use DateTimeImmutable;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use EmergencyForge\Training\VerificationCode;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * GET /verify: öffentliche Prüfung einer Urkunde per Prüfcode, ohne
 * Anmeldung. Gezeigt wird nur, was ohnehin auf der Urkunde steht, aus den
 * beim Ausstellen eingefrorenen Werten, und solange sie nicht widerrufen
 * ist die Urkunde selbst. Wer den Code hat, hält sie ohnehin in der Hand.
 *
 * Gegen Durchprobieren zählen Abfragen je Anschluss und Stunde; gespeichert
 * wird nur ein HMAC der Adresse.
 */
final class VerifyController extends Controller
{
    public const LIMIT_PER_HOUR = 20;
    private const TABLE = 'intra_verification_lookups';

    public function show(Request $request): Response
    {
        $input = mb_substr(trim((string) ($request->query['code'] ?? '')), 0, 32);
        $data  = ['input' => $input, 'state' => null, 'date' => '', 'facts' => [], 'pdf' => false];

        if ($input !== '') {
            $code          = VerificationCode::normalize($input);
            $data['state'] = 'unknown';
            // Tippfehler fallen über die Prüfziffer auf, ohne Abfrage und ohne gegen die Grenze zu zählen.
            if ($code !== null) {
                $data['input'] = VerificationCode::format($code);
                $data          = $this->allow($request) ? array_merge($data, self::lookup($code)) : array_merge($data, ['state' => 'limited']);
            }
        }

        ob_start();
        $this->renderView('verify', $data);
        $body    = (string) ob_get_clean();
        $headers = ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex'];

        return $data['state'] === 'limited'
            ? new Response(429, $body, $headers + ['Retry-After' => '3600'])
            : new Response(200, $body, $headers);
    }

    /** GET /verify/pdf: die Urkunde selbst, für dieselben Codes, für die die Seite sie anbietet. */
    public function pdf(Request $request): Response
    {
        $code = VerificationCode::normalize(mb_substr(trim((string) ($request->query['code'] ?? '')), 0, 32));
        if ($code === null) {
            return ErrorPage::notFound($request->path);
        }
        if (!$this->allow($request)) {
            return new Response(429, 'Zu viele Abfragen.', ['Content-Type' => 'text/plain; charset=UTF-8', 'Retry-After' => '3600']);
        }
        $document = self::find($code);
        $contents = $document !== null && $document->revoked_at === null ? (new PdfGenerator())->read($document) : null;
        if ($document === null || $contents === null) {
            return ErrorPage::notFound($request->path);
        }

        return new Response(200, $contents, [
            'Content-Type'           => 'application/pdf',
            'Content-Disposition'    => 'inline; filename="Urkunde-' . $document->docid . '.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag'           => 'noindex',
            'Cache-Control'          => 'private, no-store',
        ]);
    }

    private static function find(string $code): ?EditorDocument
    {
        return EditorDocument::query()->where('verification_code', $code)->where('status', EditorDocument::STATUS_ISSUED)->first();
    }

    /**
     * @return array{state: string, date?: string, facts?: array<string,string>, pdf?: bool}
     */
    private static function lookup(string $code): array
    {
        $document = self::find($code);
        if ($document === null) {
            return ['state' => 'unknown'];
        }

        $values = $document->frozen_values ?? [];
        $until  = (string) ($values['qualifikation.gueltig_bis'] ?? '');
        $end    = $until !== '' ? DateTimeImmutable::createFromFormat('!d.m.Y', $until) : false;
        [$state, $date] = match (true) {
            $document->revoked_at !== null                              => ['revoked', $document->revoked_at->format('d.m.Y')],
            $end !== false && $end < new DateTimeImmutable('today') => ['expired', $until],
            default                                                     => ['valid', ''],
        };
        // Wie im Kopf der System-Urkunden: Art und Stadt, sonst der Name.
        $issuer = trim(($values['organisation.art'] ?? '') . ' ' . ($values['organisation.stadt'] ?? ''));
        $course = implode(' · ', array_filter([(string) ($values['lehrgang.art'] ?? ''), (string) ($values['lehrgang.titel'] ?? '')]));

        return [
            'state' => $state,
            'date'  => $date,
            'facts' => array_filter([
                'Name'            => (string) ($values['mitarbeiter.name'] ?? ''),
                'Lehrgang'        => $course !== '' ? $course : $document->title,
                'Ausgestellt'     => $document->issued_at?->format('d.m.Y') ?? '',
                'Gültig bis'      => $until !== '' ? $until : 'unbefristet',
                'Ausgestellt von' => $issuer !== '' ? $issuer : (string) ($values['organisation.name'] ?? ''),
            ], static fn (string $value): bool => $value !== ''),
            'pdf'   => $state !== 'revoked' && (new PdfGenerator())->read($document) !== null,
        ];
    }

    /** Zählt die Abfrage, wenn der Anschluss noch unter der Grenze liegt. */
    private function allow(Request $request): bool
    {
        $hash  = hash_hmac('sha256', self::clientIp($request), SecretBox::derive('verify-rate-limit'));
        $since = date('Y-m-d H:i:s', time() - 3600);
        Capsule::table(self::TABLE)->where('created_at', '<', $since)->delete();
        if (Capsule::table(self::TABLE)->where('ip_hash', $hash)->count() >= self::LIMIT_PER_HOUR) {
            return false;
        }
        Capsule::table(self::TABLE)->insert(['ip_hash' => $hash, 'created_at' => date('Y-m-d H:i:s')]);

        return true;
    }

    /**
     * Hinter einem Reverse-Proxy ist REMOTE_ADDR eine interne Adresse. Dann
     * zählt der letzte Eintrag in X-Forwarded-For, den der Proxy selbst
     * angehängt hat; was der Browser davor schreibt, bleibt außen vor.
     */
    private static function clientIp(Request $request): string
    {
        $remote = (string) ($request->server['REMOTE_ADDR'] ?? '');
        $public = filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        $chain  = array_filter(array_map('trim', explode(',', (string) ($request->server['HTTP_X_FORWARDED_FOR'] ?? ''))));
        $last   = end($chain);

        return !$public && is_string($last) && filter_var($last, FILTER_VALIDATE_IP) !== false ? $last : $remote;
    }
}
