<?php

declare(strict_types=1);

namespace Plugin\Mail;

use App\Config\ConfigManager;
use App\Logging\Logger;
use App\Models\Personnel;
use App\Models\Rank;
use EmergencyForge\Mail\AddressGenerator;
use EmergencyForge\Mail\AddressPattern;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
use Plugin\Mail\Models\Mailbox;

/**
 * Legt Postfächer für Mitarbeiter an und legt sie still.
 *
 * Regel (Konzept, Entscheidung 1):
 * - Mitarbeiter im Dienst: Postfach anlegen oder wieder aktivieren, den
 *   Anzeigenamen dem formellen Namen mit Titel nachziehen. Die Adresse
 *   entsteht weiter aus `fullname`, damit der Titel nie in ihr landet.
 * - Mitarbeiter im Archiv-Dienstgrad (ausgeschieden): Postfach inaktiv.
 * - Mitarbeiter gelöscht: Postfach inaktiv, es bleibt mit Adresse und
 *   Namen für die alten Mails stehen.
 *
 * Die Sperre der Administration (`locked`) fasst die Provisionierung nie
 * an: ein gesperrtes Postfach bleibt gesperrt, egal wie oft der
 * Mitarbeiter gespeichert wird. Ebenso das Konto (`user_id`): gebunden
 * wird nur ein freies Postfach (Mailbox::autoBind()), nie umgehängt.
 *
 * Aufgerufen über die Events PersonnelSaved/PersonnelDeleted
 * (Listeners\SyncMailbox) und von `mail:backfill`. Ein Fehler bei der
 * Adressvergabe wird protokolliert und schluckt den Aufruf: das Speichern
 * des Mitarbeiters ist die wichtigere Aktion.
 */
final class MailboxProvisioner
{
    /** Wie oft bei einer Adress-Kollision unter Konkurrenz neu generiert wird. */
    private const MAX_ADDRESS_ATTEMPTS = 3;

    public function __construct(
        private readonly ConfigManager $config,
        private readonly MailAddressRules $rules,
    ) {}

    public function sync(int $mitarbeiterId): ?Mailbox
    {
        $mitarbeiter = Personnel::query()->find($mitarbeiterId);
        if ($mitarbeiter === null) {
            $this->deactivateOrphans($mitarbeiterId);

            return null;
        }

        $mailbox = Mailbox::query()->where('mitarbeiter_id', $mitarbeiter->id)->first();

        if ($this->hasLeft($mitarbeiter)) {
            if ($mailbox !== null && $mailbox->active) {
                $mailbox->active = false;
                $this->touch($mailbox)->save();
            }

            return $mailbox;
        }

        if ($mailbox === null) {
            $mailbox = $this->create($mitarbeiter);
        } else {
            $name = $this->displayName($mitarbeiter);
            if (!$mailbox->active || ($name !== '' && $mailbox->display_name !== $name)) {
                $mailbox->active = true;
                if ($name !== '') {
                    $mailbox->display_name = $name;
                }
                $this->touch($mailbox)->save();
            }
        }

        // Ein freies Postfach bekommt sein Konto, wenn genau eins passt; ein
        // gebundenes behält seins, egal was an der Discord-ID geändert wurde.
        $mailbox?->autoBind();

        return $mailbox;
    }

    /**
     * Postfächer ohne Mitarbeiter (gelöscht, der Fremdschlüssel hat
     * `mitarbeiter_id` auf NULL gesetzt) stellen nichts mehr zu.
     */
    public function deactivateOrphans(?int $mitarbeiterId = null): int
    {
        $query = Mailbox::query()->where('active', true)->where(static function ($q) use ($mitarbeiterId): void {
            $q->whereNull('mitarbeiter_id');
            if ($mitarbeiterId !== null) {
                $q->orWhere('mitarbeiter_id', $mitarbeiterId);
            }
        });

        return $query->update(['active' => false, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** Ausgeschieden heißt in ignis: im Archiv-Dienstgrad. */
    private function hasLeft(Personnel $mitarbeiter): bool
    {
        return (bool) Rank::query()->whereKey($mitarbeiter->dienstgrad)->value('archive');
    }

    private function create(Personnel $mitarbeiter): ?Mailbox
    {
        $domain  = $this->rules->defaultDomain();
        $pattern = $this->config->get('MAIL_ADDRESS_PATTERN', 'initial_dot_last') === 'first_dot_last'
            ? AddressPattern::FirstDotLast
            : AddressPattern::InitialDotLast;
        $name  = $this->displayName($mitarbeiter);
        $parts = AddressGenerator::splitFullname($this->addressName($mitarbeiter));

        for ($attempt = 1; $attempt <= self::MAX_ADDRESS_ATTEMPTS; $attempt++) {
            try {
                $address = AddressGenerator::generate(
                    $parts['first'],
                    $parts['last'],
                    $domain,
                    $pattern,
                    // Postfächer, Verteiler und reservierte frühere Adressen.
                    isTaken: static fn (string $candidate): bool => MailAddressRules::isTaken($candidate),
                );
            } catch (InvalidArgumentException $e) {
                Logger::error('Postfach-Anlage fehlgeschlagen: ' . $e->getMessage(), ['mitarbeiter_id' => $mitarbeiter->id]);

                return null;
            }

            $mailbox = new Mailbox();
            $mailbox->mitarbeiter_id = $mitarbeiter->id;
            $mailbox->address        = $address;
            $mailbox->display_name   = $name !== '' ? $name : $address;
            $mailbox->domain         = $domain;
            $mailbox->active         = true;
            $mailbox->locked         = false;

            try {
                $mailbox->save();

                return $mailbox;
            } catch (UniqueConstraintViolationException) {
                // Ein paralleler Request hat die Adresse zwischen Prüfung und
                // Insert belegt: neu generieren, isTaken() sieht sie jetzt.
                // Hat er stattdessen das Postfach selbst angelegt, gilt seins.
                $existing = Mailbox::query()->where('mitarbeiter_id', $mitarbeiter->id)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }
        }

        Logger::error('Postfach-Anlage: Adress-Kollision auch nach Wiederholung.', ['mitarbeiter_id' => $mitarbeiter->id]);

        return null;
    }

    private function displayName(Personnel $mitarbeiter): string
    {
        return $this->normalize($mitarbeiter->formalName());
    }

    /** Die Adresse entsteht ohne Titel, sonst hieße Dr. Max Muster "d.max-muster". */
    private function addressName(Personnel $mitarbeiter): string
    {
        return $this->normalize((string) $mitarbeiter->fullname);
    }

    private function normalize(string $name): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $name)), 0, 150);
    }

    private function touch(Mailbox $mailbox): Mailbox
    {
        $mailbox->setAttribute('updated_at', date('Y-m-d H:i:s'));

        return $mailbox;
    }
}
