<?php

declare(strict_types=1);

namespace Plugin\Mail;

use EmergencyForge\Mail\DirectoryPort;
use EmergencyForge\Mail\ListRef;
use EmergencyForge\Mail\MailboxRef;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Mail\Models\Mailbox;
use Plugin\Mail\Models\MailList;

/**
 * Das Adressbuch des Mailmoduls für den Paket-RecipientResolver.
 *
 * Gefunden werden nur zustellbare Postfächer (aktiv und nicht gesperrt);
 * ein stillgelegtes Postfach sieht für den Resolver aus wie eine
 * unbekannte Adresse. Das gilt auch innerhalb von Verteilern: ein
 * gesperrtes Mitglied bekommt nichts.
 *
 * Dynamische Verteiler (`kind = dynamic`) tragen eine Regel aus vier
 * Listen, jede ein ODER-Kriterium auf den Mitarbeiter hinter dem Postfach:
 *
 *   role_ids      Rolle des verknüpften Kontos (intra_users.role)
 *   rank_ids      Dienstgrad (intra_mitarbeiter.dienstgrad)
 *   rd_quali_ids  RD-Qualifikation (intra_mitarbeiter.qualird)
 *   fw_quali_ids  FW-Qualifikation (intra_mitarbeiter.qualifw2)
 *
 * Verteiler enthalten nur Postfächer, nie andere Verteiler: die Liste ist
 * flach, einen Zyklenschutz braucht es nicht.
 */
final class MailDirectory implements DirectoryPort
{
    public const RULE_KEYS = ['role_ids', 'rank_ids', 'rd_quali_ids', 'fw_quali_ids'];

    public function findMailbox(string $address): ?MailboxRef
    {
        $mailbox = Mailbox::query()
            ->where('address', MailAddressRules::normalize($address))
            ->where('active', true)
            ->where('locked', false)
            ->first();

        return $mailbox === null ? null : self::ref($mailbox);
    }

    public function findList(string $address): ?ListRef
    {
        $list = MailList::query()->where('address', MailAddressRules::normalize($address))->first();

        return $list === null ? null : new ListRef($list->id, $list->address, $list->name);
    }

    /** @return list<MailboxRef> */
    public function listMembers(ListRef $list): array
    {
        $model = MailList::query()->find($list->id);
        if ($model === null) {
            return [];
        }

        $ids = $model->kind === 'dynamic'
            ? Capsule::table('intra_mail_mailboxes')->whereIn('mitarbeiter_id', self::dynamicMitarbeiterIds($model->rule ?? []))->pluck('id')->all()
            : $model->members()->pluck('mailbox_id')->all();
        if ($ids === []) {
            return [];
        }

        $mailboxes = Mailbox::query()->whereKey($ids)->where('active', true)->where('locked', false)->get()->all();

        return array_values(array_map(self::ref(...), $mailboxes));
    }

    /**
     * Mitarbeiter, auf die mindestens ein Kriterium der Regel zutrifft.
     *
     * @param array<string,mixed> $rule
     * @return list<int>
     */
    public static function dynamicMitarbeiterIds(array $rule): array
    {
        $ids = [];
        foreach (self::RULE_KEYS as $key) {
            $ids[$key] = array_values(array_filter(array_map('intval', (array) ($rule[$key] ?? [])), static fn (int $v): bool => $v > 0));
        }
        if ($ids['role_ids'] === [] && $ids['rank_ids'] === [] && $ids['rd_quali_ids'] === [] && $ids['fw_quali_ids'] === []) {
            return [];
        }

        $rows = Capsule::table('intra_mitarbeiter as m')
            ->where(static function ($q) use ($ids): void {
                if ($ids['rank_ids'] !== []) {
                    $q->orWhereIn('m.dienstgrad', $ids['rank_ids']);
                }
                if ($ids['rd_quali_ids'] !== []) {
                    $q->orWhereIn('m.qualird', $ids['rd_quali_ids']);
                }
                if ($ids['fw_quali_ids'] !== []) {
                    $q->orWhereIn('m.qualifw2', $ids['fw_quali_ids']);
                }
                if ($ids['role_ids'] !== []) {
                    // Das Konto hängt wie überall über die Discord-ID am
                    // Mitarbeiter, ersatzweise über intra_users.aktenid.
                    $q->orWhereExists(static function ($sub) use ($ids): void {
                        $sub->selectRaw('1')->from('intra_users as u')
                            ->whereIn('u.role', $ids['role_ids'])
                            ->where(static function ($link): void {
                                $link->whereColumn('u.discord_id', 'm.discordtag')->orWhereColumn('u.aktenid', 'm.id');
                            });
                    });
                }
            })
            ->pluck('m.id')
            ->all();

        return array_values(array_map('intval', $rows));
    }

    public static function ref(Mailbox $mailbox): MailboxRef
    {
        return new MailboxRef($mailbox->id, $mailbox->address, $mailbox->display_name);
    }
}
