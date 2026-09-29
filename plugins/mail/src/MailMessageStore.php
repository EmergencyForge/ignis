<?php

declare(strict_types=1);

namespace Plugin\Mail;

use EmergencyForge\Mail\DeliveryRole;
use EmergencyForge\Mail\Folder;
use EmergencyForge\Mail\MailboxRef;
use EmergencyForge\Mail\MessageStore;
use EmergencyForge\Mail\NewMessage;
use Plugin\Mail\Models\Delivery;
use Plugin\Mail\Models\Message;

/**
 * Setzt den Paket-Port MessageStore auf `intra_mail_messages` und
 * `intra_mail_deliveries` um.
 *
 * `status` und `sent_at` kennt das Paket nicht; sie folgen aus `bodyHtml`:
 * NULL ist ein Entwurf, gesetzt ist gesendet (Mailer rendert vor dem
 * Speichern). `header_json` ist der Empfänger-Schnappschuss mit BCC.
 */
final class MailMessageStore implements MessageStore
{
    public function saveMessage(NewMessage $message): int
    {
        $row = new Message();
        $this->fill($row, $message);
        $row->save();

        return $row->id;
    }

    public function updateMessage(int $messageId, NewMessage $message): void
    {
        $row = Message::query()->findOrFail($messageId);
        $this->fill($row, $message);
        $row->save();
    }

    /**
     * `role` gehört mit ins Suchkriterium (Unique-Index je Rolle): an sich
     * selbst adressiert bekommt dasselbe Postfach eine Empfänger- und eine
     * Absenderzeile, sonst überschriebe die zweite die erste.
     */
    public function deliver(int $messageId, MailboxRef $mailbox, DeliveryRole $role, Folder $folder): void
    {
        Delivery::query()->updateOrCreate(
            ['message_id' => $messageId, 'mailbox_id' => $mailbox->id, 'role' => self::roleValue($role)],
            ['folder' => self::folderValue($folder), 'deleted_at' => null],
        );
    }

    public function move(int $messageId, MailboxRef $mailbox, Folder $folder): void
    {
        Delivery::query()->where('message_id', $messageId)->where('mailbox_id', $mailbox->id)
            ->update(['folder' => self::folderValue($folder)]);
    }

    public function markRead(int $messageId, MailboxRef $mailbox, bool $read = true): void
    {
        Delivery::query()->where('message_id', $messageId)->where('mailbox_id', $mailbox->id)
            ->update(['read_at' => $read ? date('Y-m-d H:i:s') : null]);
    }

    /** Weicher Vermerk: nur die Kopie dieses Postfachs verschwindet. */
    public function delete(int $messageId, MailboxRef $mailbox): void
    {
        Delivery::query()->where('message_id', $messageId)->where('mailbox_id', $mailbox->id)
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /** @return list<int> */
    public function thread(string $threadId): array
    {
        return array_values(array_map('intval', Message::query()->where('thread_id', $threadId)->orderBy('id')->pluck('id')->all()));
    }

    private function fill(Message $row, NewMessage $message): void
    {
        $now = date('Y-m-d H:i:s');
        $row->sender_mailbox_id = (int) $message->sender->id;
        $row->subject           = $message->subject;
        $row->body_json         = $message->bodyJson;
        $row->body_html         = $message->bodyHtml;
        $row->header_json       = [
            'to'  => self::addresses($message->recipients->to),
            'cc'  => self::addresses($message->recipients->cc),
            'bcc' => self::addresses($message->recipients->bcc),
        ];
        $row->thread_id   = $message->threadId;
        $row->in_reply_to = $message->inReplyTo;
        $row->status      = $message->bodyHtml === null ? 'draft' : 'sent';
        $row->setAttribute('sent_at', $message->bodyHtml === null ? null : $now);
        $row->setAttribute('updated_at', $now);
    }

    /**
     * @param list<\EmergencyForge\Mail\Recipient> $recipients
     * @return list<string>
     */
    private static function addresses(array $recipients): array
    {
        return array_map(static fn ($r): string => $r->address->value, $recipients);
    }

    public static function roleValue(DeliveryRole $role): string
    {
        return match ($role) {
            DeliveryRole::To     => 'to',
            DeliveryRole::Cc     => 'cc',
            DeliveryRole::Bcc    => 'bcc',
            DeliveryRole::Sender => 'sender',
        };
    }

    public static function folderValue(Folder $folder): string
    {
        return match ($folder) {
            Folder::Inbox   => 'inbox',
            Folder::Sent    => 'sent',
            Folder::Drafts  => 'drafts',
            Folder::Archive => 'archive',
            Folder::Trash   => 'trash',
        };
    }
}
