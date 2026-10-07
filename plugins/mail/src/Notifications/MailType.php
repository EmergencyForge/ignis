<?php

declare(strict_types=1);

namespace Plugin\Mail\Notifications;

use App\Auth\Permissions;
use App\Notifications\NotificationTypeInterface;
use Plugin\Mail\Controllers\MailController;
use Plugin\Mail\Models\Delivery;
use Plugin\Mail\Models\Mailbox;

/**
 * Benachrichtigung „Neue Mail“, angelegt bei der Zustellung
 * (MailController::notifyRecipients()). Gespeichert wird immer der Link
 * in den Posteingang (MailController::notificationLink()), er ist der
 * Schlüssel des Eintrags: Lesen setzt ihn auf gelesen, Papierkorb und
 * endgültiges Löschen entfernen ihn. Geöffnet wird die Mail dort, wo sie
 * gerade liegt.
 *
 * Sehen darf die Einträge, wer Mail nutzen darf und ein offenes Postfach
 * hat; ein gesperrtes oder stillgelegtes Postfach meldet nichts.
 */
final class MailType implements NotificationTypeInterface
{
    public function key(): string
    {
        return 'mail';
    }

    public function label(): string
    {
        return 'Mail';
    }

    public function icon(): string
    {
        return 'fa-solid fa-envelope';
    }

    public function allowed(): bool
    {
        return Permissions::check(['admin', 'mail.use']) && Mailbox::accessible() !== [];
    }

    public function link(array $row): ?string
    {
        $link = $row['link'] ?? null;
        if (!is_string($link) || preg_match('~mail/inbox/(\d+)$~', $link, $m) !== 1) {
            return null;
        }
        $mailboxIds = Mailbox::accessibleIds();
        if ($mailboxIds === []) {
            return null;
        }

        // Die Mail liegt im eigenen Postfach oder in einem Gruppenpostfach;
        // der Link nennt das Postfach, damit Mail es gleich öffnet.
        $copies = Delivery::query()->where('message_id', (int) $m[1])->where(static fn ($q) => $q->whereIn('mailbox_id', $mailboxIds))
            ->where('deleted_at', null)->get(['mailbox_id', 'role', 'folder']);
        $copy = $copies->first(static fn (Delivery $d): bool => $d->role !== 'sender') ?? $copies->first();

        return $copy !== null ? MailController::basePath() . 'mail/' . $copy->folder . '/' . (int) $m[1] . '?postfach=' . $copy->mailbox_id : null;
    }
}
