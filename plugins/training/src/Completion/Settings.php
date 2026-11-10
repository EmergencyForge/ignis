<?php

declare(strict_types=1);

namespace Plugin\Training\Completion;

use Plugin\Training\Models\CourseType;

/**
 * Was Bekanntgabe und Folgeaufgabe brauchen, aus
 * `completion_settings` der Lehrgangsart. Welche Schritte überhaupt laufen,
 * steht in `completion_plan` (CompletionPlan des Pakets), Vorlage und
 * Unterschrift in eigenen Spalten.
 */
final class Settings
{
    public const SIGNER_LEAD   = 'lead';
    public const SIGNER_FIXED  = 'fixed';
    public const SIGNER_GRADER = 'grader';

    public const SIGNERS = [
        self::SIGNER_LEAD   => 'Lehrgangsleitung',
        self::SIGNER_FIXED  => 'Feste Person',
        self::SIGNER_GRADER => 'Wer zuletzt bewertet hat',
    ];

    public const CHANNELS = [
        'dashboard' => 'Hinweis auf dem Dashboard',
        'discord'   => 'Discord-Kanal',
    ];

    public const ANNOUNCE_DAYS = 7;

    /** Discord und seine Ableger, damit die URL nicht irgendwohin zeigt. */
    public const WEBHOOK_PATTERN = '#^https://(?:ptb\.|canary\.)?discord(?:app)?\.com/api/webhooks/\d+/[\w-]+$#';

    /**
     * @return array{announce_channel: string, announce_webhook: string, announce_text: string, follow_up_target: string, follow_up_title: string, follow_up_days: int}
     */
    public static function of(CourseType $type): array
    {
        $raw = is_array($type->completion_settings) ? $type->completion_settings : [];

        return [
            'announce_channel' => isset(self::CHANNELS[$raw['announce_channel'] ?? '']) ? (string) $raw['announce_channel'] : 'dashboard',
            'announce_webhook' => (string) ($raw['announce_webhook'] ?? ''),
            'announce_text'    => (string) ($raw['announce_text'] ?? '{person.name} hat den {lehrgang.titel} bestanden.'),
            'follow_up_target' => (string) ($raw['follow_up_target'] ?? ''),
            'follow_up_title'  => (string) ($raw['follow_up_title'] ?? 'Beförderung von {person.name} prüfen'),
            'follow_up_days'   => max(0, (int) ($raw['follow_up_days'] ?? 14)),
        ];
    }
}
