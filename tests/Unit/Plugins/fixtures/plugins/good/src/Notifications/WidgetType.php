<?php

declare(strict_types=1);

namespace GoodPluginFixture\Notifications;

use App\Auth\Permissions;
use App\Notifications\NotificationTypeInterface;

/**
 * Fixture-Benachrichtigungstyp für das Manifest-Feld `notifications`:
 * sichtbar nur mit `good.view`, der Link wird aus dem Text abgeleitet,
 * wenn keiner gespeichert ist (Widget #<id>).
 */
final class WidgetType implements NotificationTypeInterface
{
    public function key(): string
    {
        return 'widget';
    }

    public function label(): string
    {
        return 'Widgets';
    }

    public function icon(): string
    {
        return 'fa-solid fa-puzzle-piece';
    }

    public function allowed(): bool
    {
        return Permissions::check(['good.view']);
    }

    public function link(array $row): ?string
    {
        $link = $row['link'] ?? null;
        if (is_string($link) && $link !== '') {
            return $link;
        }
        if (preg_match('~Widget #(\d+)~', (string) ($row['message'] ?? ''), $m) === 1) {
            return '/good/widgets/' . $m[1];
        }

        return null;
    }
}
