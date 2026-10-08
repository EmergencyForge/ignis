<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Logging\Logger;
use App\Utils\HttpClient;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Discord-Webhooks: Meldungen in einen Discord-Kanal, ohne Bot. Die URLs
 * stehen in intra_config (System-Konfiguration › Webhooks), je eine für
 * freigegebene eNOTF-Protokolle, freigegebene fireTab-Protokolle und
 * Klinik-Voranmeldungen. Aufgerufen wird das aus SendDiscordWebhookJob.
 *
 * Jede notify*-Methode liefert true, wenn Discord die Nachricht
 * angenommen hat, und false, wenn für die Art keine URL eingetragen ist.
 * Scheitert das Senden, wirft sie DiscordWebhookException; retryable()
 * sagt, ob ein neuer Versuch lohnt.
 *
 * Discord lehnt eine ganze Nachricht ab, wenn ein Feld leer oder zu lang
 * ist oder ein Link keine gültige URL ist. Deshalb gehen alle Werte durch
 * field(), und Links gibt es nur mit eingetragener SYSTEM_URL.
 */
class DiscordWebhook
{
    private const KEYS = [
        'enotf_protocol' => 'DISCORD_WEBHOOK_ENOTF_PROTOCOL',
        'fire_protocol'  => 'DISCORD_WEBHOOK_FIRE_PROTOCOL',
        'enotf_prereg'   => 'DISCORD_WEBHOOK_ENOTF_PREREG',
    ];

    /** Discords Grenze für den Wert eines Embed-Felds. */
    private const FIELD_LIMIT = 1024;

    /** @var array<string, string>|null */
    private static ?array $webhookCache = null;

    /** @var (\Closure(string, string): (array{status:int, body:string}|null))|null */
    private static ?\Closure $transport = null;

    /**
     * Ersetzt die HTTP-Anbindung, für Tests. Bekommt URL und JSON-Body,
     * liefert Status und Antworttext; null spielt einen Netzfehler.
     *
     * @param (\Closure(string, string): (array{status:int, body:string}|null))|null $transport
     */
    public static function fake(?\Closure $transport): void
    {
        self::$transport = $transport;
    }

    /**
     * @return array<string, string> Art => URL ('' = nicht eingetragen)
     */
    private function loadWebhooks(): array
    {
        if (self::$webhookCache !== null) {
            return self::$webhookCache;
        }

        $webhooks = array_fill_keys(array_keys(self::KEYS), '');
        try {
            $rows = Capsule::table('intra_config')->whereIn('config_key', array_values(self::KEYS))->pluck('config_value', 'config_key')->all();
        } catch (\Throwable $e) {
            Logger::error('Discord-Webhooks nicht geladen: ' . $e->getMessage());
            return $webhooks;
        }
        foreach (self::KEYS as $type => $key) {
            $webhooks[$type] = trim((string) ($rows[$key] ?? ''));
        }

        return self::$webhookCache = $webhooks;
    }

    /**
     * eNOTF-Protokoll fürs QM freigegeben.
     *
     * @param array<string, mixed> $protocolData enr, last_edit
     */
    public function notifyEnotfProtocolReleased(array $protocolData): bool
    {
        $enr = self::text($protocolData['enr'] ?? null);

        return $this->send('enotf_protocol', [
            'title'       => '📋 eNOTF-Protokoll freigegeben',
            'description' => 'Ein neues eNOTF-Protokoll wurde fürs QM freigegeben.',
            'color'       => 3447003,
            'fields'      => [
                self::field('Einsatznummer', $enr !== '' ? '**#' . $enr . '**' : null),
                self::field('Zeitstempel', $protocolData['last_edit'] ?? date('Y-m-d H:i:s')),
            ],
            'url'         => $enr !== '' ? self::link('enotf/p/' . rawurlencode($enr)) : null,
        ]);
    }

    /**
     * fireTab-Protokoll zur QM-Sichtung freigegeben.
     *
     * @param array<string, mixed> $incidentData id, incident_number, location, keyword, started_at, leader_name
     */
    public function notifyFireProtocolReleased(array $incidentData): bool
    {
        $id     = (int) ($incidentData['id'] ?? 0);
        $number = self::text($incidentData['incident_number'] ?? null);

        return $this->send('fire_protocol', [
            'title'       => '🚒 fireTab-Protokoll freigegeben',
            'description' => 'Ein fireTab-Protokoll wurde zur QM-Sichtung freigegeben.',
            'color'       => 15158332,
            'fields'      => [
                self::field('Einsatznummer', $number !== '' ? '**' . $number . '**' : null),
                self::field('Einsatzort', $incidentData['location'] ?? null),
                self::field('Stichwort', $incidentData['keyword'] ?? null),
                self::field('Einsatzbeginn', $incidentData['started_at'] ?? null),
                self::field('Einsatzleiter', $incidentData['leader_name'] ?? null),
            ],
            'url'         => $id > 0 ? self::link('firetab/view?id=' . $id) : null,
        ]);
    }

    /**
     * Neue Klinik-Voranmeldung im eNOTF.
     *
     * @param array<string, mixed> $preregData priority (0-2), arrival, fahrzeug, diagnose, ziel, enr, intubiert, kreislauf
     */
    public function notifyEnotfPreregistration(array $preregData): bool
    {
        $priorityRaw = self::text($preregData['priority'] ?? null);
        [$priority, $emoji, $color] = match ($priorityRaw) {
            '2'     => ['Sofort', '🔴', 15158332],
            '1'     => ['Dringlich', '🟡', 16776960],
            '0'     => ['Nicht dringlich', '🟢', 5763719],
            default => [$priorityRaw !== '' ? $priorityRaw : 'Unbekannt', '⚪', 10181046],
        };
        $arrival = strtotime(self::text($preregData['arrival'] ?? null));
        $enr     = self::text($preregData['enr'] ?? null);

        return $this->send('enotf_prereg', [
            'title'       => '🏥 Neue Klinik-Voranmeldung',
            'description' => 'Eine neue Voranmeldung wurde im eNOTF-System erfasst.',
            'color'       => $color,
            'fields'      => [
                self::field('Priorität', $emoji . ' **' . $priority . '**'),
                self::field('Ankunft', $arrival !== false ? date('d.m.Y H:i', $arrival) : null),
                self::field('Fahrzeug', $preregData['fahrzeug'] ?? null),
                self::field('Diagnose', $preregData['diagnose'] ?? null, false),
                self::field('Zielklinik', $preregData['ziel'] ?? null),
                self::field('Intubiert', self::yesNo($preregData['intubiert'] ?? null, '✅', '❌')),
                self::field('Kreislauf', self::yesNo($preregData['kreislauf'] ?? null, 'Stabil', 'Instabil')),
            ],
            'url'         => $enr !== '' ? self::link('enotf/schnittstelle/voranmeldung?enr=' . rawurlencode($enr)) : null,
        ]);
    }

    public static function clearCache(): void
    {
        self::$webhookCache = null;
    }

    /**
     * @param array<string, mixed> $embed
     */
    private function send(string $type, array $embed): bool
    {
        $url = $this->loadWebhooks()[$type] ?? '';
        if ($url === '') {
            return false;
        }
        if (preg_match('#^https://[^/\s]+/\S+$#i', $url) !== 1) {
            throw new DiscordWebhookException('Die Webhook-URL für „' . $type . '“ ist keine gültige https-Adresse.', 400);
        }

        $embed = array_filter($embed + ['timestamp' => date('c')], static fn (mixed $value): bool => $value !== null);
        $body  = json_encode(['embeds' => [$embed], 'allowed_mentions' => ['parse' => []]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = self::$transport !== null
            ? (self::$transport)($url, $body)
            : HttpClient::request($url, [
                'method'  => 'POST',
                'headers' => ['Content-Type: application/json'],
                'body'    => $body,
                'timeout' => 10,
            ]);
        if ($response === null) {
            throw new DiscordWebhookException('Discord ist nicht erreichbar.', 0);
        }

        $status = (int) $response['status'];
        if ($status >= 200 && $status < 300) {
            return true;
        }

        throw new DiscordWebhookException('Discord lehnt den Webhook „' . $type . '“ ab (HTTP ' . $status . '): ' . mb_substr($response['body'], 0, 300), $status);
    }

    /**
     * Ein Embed-Feld. Discord verlangt einen nicht leeren Text von höchstens
     * 1024 Zeichen; fehlt der Wert, steht „Unbekannt“ da.
     *
     * @return array{name:string, value:string, inline:bool}
     */
    private static function field(string $name, mixed $value, bool $inline = true): array
    {
        $text = self::text($value);
        if ($text === '') {
            $text = 'Unbekannt';
        }
        if (mb_strlen($text) > self::FIELD_LIMIT) {
            $text = mb_substr($text, 0, self::FIELD_LIMIT - 1) . '…';
        }

        return ['name' => $name, 'value' => $text, 'inline' => $inline];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function yesNo(mixed $value, string $yes, string $no): ?string
    {
        return match (self::text($value)) {
            '1'     => $yes,
            '0'     => $no,
            default => null,
        };
    }

    /**
     * Ein Link in die Installation. Nur mit eingetragener SYSTEM_URL: im
     * Queue-Worker gibt es keinen Host, und eine ungültige URL lässt
     * Discord die ganze Nachricht ablehnen.
     */
    private static function link(string $path): ?string
    {
        $system = defined('SYSTEM_URL') ? trim((string) SYSTEM_URL) : '';
        if ($system === '' || $system === 'CHANGE_ME') {
            return null;
        }
        if (preg_match('#^https?://#i', $system) !== 1) {
            $system = 'https://' . $system;
        }
        $system = rtrim($system, '/');
        if (filter_var($system, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';

        return $system . '/' . ltrim(rtrim($base, '/') . '/' . $path, '/');
    }
}
