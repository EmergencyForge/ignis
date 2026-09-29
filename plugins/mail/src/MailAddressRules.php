<?php

declare(strict_types=1);

namespace Plugin\Mail;

use App\Config\ConfigManager;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Regeln für Mail-Adressen: erlaubte Domains, Form handgesetzter Adressen
 * (Verteiler, Korrektur durch die Postfachverwaltung) und die Frage, ob
 * eine Adresse schon vergeben ist.
 *
 * Postfächer, Verteiler und frühere Adressen teilen sich einen Adressraum.
 * Die Unique-Indizes je Tabelle fangen nur Kollisionen innerhalb einer
 * Tabelle ab, isTaken() prüft über alle drei — auch die Provisionierung
 * fragt hier, nicht nur bei den Postfächern.
 */
final class MailAddressRules
{
    /** Grenze aus RFC 5321 für den Teil vor dem `@`, wie im Paket-AddressGenerator. */
    public const MAX_LOCAL_LENGTH = 64;

    public const DEFAULT_DOMAIN = 'ignis.ef';

    private const LOCAL_PATTERN  = '/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/';
    private const DOMAIN_PATTERN = '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/';

    public function __construct(private readonly ConfigManager $config) {}

    public function defaultDomain(): string
    {
        $domain = self::normalize((string) $this->config->get('MAIL_DOMAIN', self::DEFAULT_DOMAIN));

        return self::isDomain($domain) ? $domain : self::DEFAULT_DOMAIN;
    }

    /**
     * Die wählbaren Domains, Standard-Domain zuerst, ohne Doppelte.
     *
     * @return list<string>
     */
    public function allowedDomains(): array
    {
        return self::parseDomains($this->defaultDomain() . ',' . (string) $this->config->get('MAIL_ALLOWED_DOMAINS', ''));
    }

    /**
     * Zerlegt eine Domain-Liste (Komma, Semikolon, Leerraum) in gültige,
     * kleingeschriebene, eindeutige Domains. Ungültige fallen weg.
     *
     * @return list<string>
     */
    public static function parseDomains(string $raw): array
    {
        $domains = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $entry) {
            $entry = self::normalize($entry);
            if ($entry !== '' && self::isDomain($entry) && !in_array($entry, $domains, true)) {
                $domains[] = $entry;
            }
        }

        return $domains;
    }

    public static function isDomain(string $domain): bool
    {
        return strlen($domain) <= 100 && preg_match(self::DOMAIN_PATTERN, $domain) === 1;
    }

    public static function normalize(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }

    public static function compose(string $local, string $domain): string
    {
        return self::normalize($local) . '@' . self::normalize($domain);
    }

    /**
     * Prüft eine handgesetzte Adresse; `null` heißt in Ordnung.
     * `$keepDomain` lässt die bisherige Domain gelten, auch wenn sie nicht
     * mehr erlaubt ist (nur ein Wechsel ist an die Liste gebunden).
     * `$exceptMailboxId`/`$exceptListId` nehmen die bearbeitete Zeile aus.
     */
    public function validate(string $address, ?int $exceptMailboxId = null, ?int $exceptListId = null, ?string $keepDomain = null): ?string
    {
        $at = strrpos($address, '@');
        if ($at === false || $at === 0) {
            return 'Die Adresse braucht einen Teil vor dem @ und eine Domain.';
        }

        $local  = substr($address, 0, $at);
        $domain = substr($address, $at + 1);

        if (strlen($local) > self::MAX_LOCAL_LENGTH) {
            return 'Der Teil vor dem @ darf höchstens ' . self::MAX_LOCAL_LENGTH . ' Zeichen lang sein.';
        }
        if (preg_match(self::LOCAL_PATTERN, $local) !== 1 || str_contains($local, '..')) {
            return 'Der Teil vor dem @ darf nur Kleinbuchstaben, Ziffern, Punkt, Bindestrich und Unterstrich enthalten und muss mit einem Buchstaben oder einer Ziffer beginnen und enden.';
        }

        $keepDomain = $keepDomain !== null ? self::normalize($keepDomain) : null;
        if (!in_array($domain, $this->allowedDomains(), true) && $domain !== $keepDomain) {
            return 'Die Domain „' . $domain . '“ ist nicht erlaubt. Erlaubt: ' . implode(', ', $this->allowedDomains()) . '.';
        }

        if (self::isTaken($address, $exceptMailboxId, $exceptListId)) {
            return 'Die Adresse ' . $address . ' ist bereits vergeben oder für ein anderes Postfach reserviert.';
        }

        return null;
    }

    /**
     * Vergeben ist eine Adresse, die ein Postfach oder ein Verteiler trägt
     * oder die ein Postfach früher hatte. Eine frühere Adresse bleibt ihrem
     * Postfach vorbehalten: nur `$exceptMailboxId` gleich diesem Postfach
     * bekommt sie zurück, sonst gingen Antworten auf alte Mails an jemand
     * anderen.
     */
    public static function isTaken(string $address, ?int $exceptMailboxId = null, ?int $exceptListId = null): bool
    {
        $address = self::normalize($address);

        return Capsule::table('intra_mail_mailboxes')->where('address', $address)
                ->when($exceptMailboxId !== null, static fn ($q) => $q->where('id', '!=', $exceptMailboxId))
                ->exists()
            || Capsule::table('intra_mail_lists')->where('address', $address)
                ->when($exceptListId !== null, static fn ($q) => $q->where('id', '!=', $exceptListId))
                ->exists()
            || Capsule::table('intra_mail_address_history')->where('address', $address)
                ->when($exceptMailboxId !== null, static fn ($q) => $q->where('mailbox_id', '!=', $exceptMailboxId))
                ->exists();
    }

    /** Merkt sich die frühere Adresse eines Postfachs, siehe isTaken(). */
    public static function reserve(string $address, int $mailboxId): void
    {
        Capsule::table('intra_mail_address_history')->updateOrInsert(
            ['address' => $address],
            ['mailbox_id' => $mailboxId, 'released_at' => date('Y-m-d H:i:s')],
        );
    }
}
