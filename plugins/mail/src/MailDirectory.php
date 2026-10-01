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
 * Dynamische Verteiler (`kind = dynamic`) tragen eine Regel aus fünf
 * Listen, jede ein ODER-Kriterium auf den Mitarbeiter hinter dem Postfach:
 *
 *   role_ids        Rolle des Kontos, dem das Postfach gehört (intra_users.role)
 *   rank_ids        Dienstgrad (intra_mitarbeiter.dienstgrad)
 *   rd_quali_ids    RD-Qualifikation (intra_mitarbeiter.qualird)
 *   fw_quali_ids    FW-Qualifikation (intra_mitarbeiter.qualifw2)
 *   fachdienst_ids  Fachdienst (intra_mitarbeiter_fdquali.id; am Mitarbeiter
 *                   stehen die Sachgebietsnummern als JSON in `fachdienste`)
 *
 * Verteiler enthalten nur Postfächer, nie andere Verteiler: die Liste ist
 * flach, einen Zyklenschutz braucht es nicht.
 */
final class MailDirectory implements DirectoryPort
{
    public const RULE_KEYS = ['role_ids', 'rank_ids', 'rd_quali_ids', 'fw_quali_ids', 'fachdienst_ids'];

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
        if (array_merge(...array_values($ids)) === []) {
            return [];
        }
        $bySpecialty = self::mitarbeiterIdsWithSpecialties($ids['fachdienst_ids']);

        $rows = Capsule::table('intra_mitarbeiter as m')
            ->where(static function ($q) use ($ids, $bySpecialty): void {
                if ($ids['rank_ids'] !== []) {
                    $q->orWhereIn('m.dienstgrad', $ids['rank_ids']);
                }
                if ($ids['rd_quali_ids'] !== []) {
                    $q->orWhereIn('m.qualird', $ids['rd_quali_ids']);
                }
                if ($ids['fw_quali_ids'] !== []) {
                    $q->orWhereIn('m.qualifw2', $ids['fw_quali_ids']);
                }
                if ($ids['fachdienst_ids'] !== []) {
                    // Leer wird daraus `0 = 1`, nicht „ohne Bedingung“.
                    $q->orWhereIn('m.id', $bySpecialty);
                }
                if ($ids['role_ids'] !== []) {
                    // Die Rolle des Kontos, dem das Postfach gehört (user_id),
                    // nicht die eines Kontos mit passender Discord-ID.
                    $q->orWhereExists(static function ($sub) use ($ids): void {
                        $sub->selectRaw('1')->from('intra_mail_mailboxes as mb')
                            ->join('intra_users as u', 'u.id', '=', 'mb.user_id')
                            ->whereColumn('mb.mitarbeiter_id', 'm.id')
                            ->whereIn('u.role', $ids['role_ids']);
                    });
                }
            })
            ->pluck('m.id')
            ->all();

        return array_values(array_map('intval', $rows));
    }

    /**
     * Mitarbeiter mit mindestens einem der Fachdienste. `fachdienste` ist
     * JSON-Text mit Sachgebietsnummern, in alten Daten auch als Zahl, daher
     * der Abgleich in PHP.
     *
     * ponytail: liest alle Mitarbeiter mit Fachdiensten; JSON_TABLE, falls das je zu viele werden.
     *
     * @param list<int> $specialtyIds intra_mitarbeiter_fdquali.id
     * @return list<int>
     */
    private static function mitarbeiterIdsWithSpecialties(array $specialtyIds): array
    {
        if ($specialtyIds === []) {
            return [];
        }
        $numbers = array_map('strval', Capsule::table('intra_mitarbeiter_fdquali')->whereIn('id', $specialtyIds)->pluck('sgnr')->all());

        $ids = [];
        foreach (Capsule::table('intra_mitarbeiter')->whereNotNull('fachdienste')->pluck('fachdienste', 'id') as $id => $json) {
            $values = json_decode((string) $json, true);
            if (is_array($values) && array_intersect(array_map('strval', array_filter($values, 'is_scalar')), $numbers) !== []) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    public static function ref(Mailbox $mailbox): MailboxRef
    {
        return new MailboxRef($mailbox->id, $mailbox->address, $mailbox->display_name);
    }
}
