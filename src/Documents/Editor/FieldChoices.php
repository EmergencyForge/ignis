<?php

declare(strict_types=1);

namespace App\Documents\Editor;

use App\Models\AmbSkill;
use App\Models\Rank;
use EmergencyForge\Editor\Nodes\DocField;

/**
 * Auswahlfelder, deren Liste aus den Stammdaten kommt.
 *
 * Dienstgrade pflegt jede Organisation selbst, deshalb steht in der Vorlage
 * nur die Quelle (`optionsSource`). Die Optionen werden jedes Mal frisch
 * eingesetzt: beim Anzeigen und vor dem SectionGuard, der gegen die Liste
 * der Vorlage prüft.
 */
final class FieldChoices
{
    public const RANKS = 'dienstgrade';

    public const AMB_SKILLS = 'rd_qualifikationen';

    /** Quelle auf Beschriftung im Vorlagen-Editor. */
    public const SOURCES = [
        self::RANKS      => 'Dienstgrade',
        self::AMB_SKILLS => 'RD-Qualifikationen',
    ];

    /**
     * @param  array<string,mixed>  $document
     * @param  array<string,mixed>  $stored  Gespeicherter Stand, siehe apply()
     * @return array<string,mixed>
     */
    public static function fill(array $document, array $stored = []): array
    {
        return self::apply($document, self::fromDatabase(...), $stored);
    }

    /**
     * Ein gespeicherter Wert bleibt wählbar, auch wenn er nicht in der Liste
     * steht: der SectionGuard verwirft sonst beim nächsten Autosave still
     * einen Entwurf, dessen Dienstgrad umbenannt oder von Hand getippt wurde.
     *
     * @param  array<string,mixed>                                     $document
     * @param  callable(string): list<array<string,string>>            $list
     * @param  array<string,mixed>                                     $stored
     * @return array<string,mixed>
     */
    public static function apply(array $document, callable $list, array $stored = []): array
    {
        $values = [];
        self::collectValues($stored, $values);
        $lists = [];

        return self::walk($document, $list, $values, $lists);
    }

    /**
     * @param  array<mixed>                                  $node
     * @param  callable(string): list<array<string,string>>  $list
     * @param  array<string,list<string>>                    $values
     * @param  array<string,list<array<string,string>>>      $lists
     * @return array<mixed>
     */
    private static function walk(array $node, callable $list, array $values, array &$lists): array
    {
        $source = ($node['type'] ?? null) === 'docField' ? ($node['attrs']['optionsSource'] ?? null) : null;

        if (is_string($source) && isset(self::SOURCES[$source])) {
            $options = $lists[$source] ??= $list($source);
            $known   = DocField::allowedValues(['options' => $options]) ?? [];

            foreach ($values[$node['attrs']['fieldId'] ?? ''] ?? [] as $value) {
                if (!in_array($value, $known, true)) {
                    $options[] = ['label' => $value];
                }
            }

            // Ohne Stammdaten bleibt das Feld Freitext.
            $node['attrs']['options'] = $options !== [] ? $options : null;

            return $node;
        }

        if (isset($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $i => $child) {
                if (is_array($child)) {
                    $node['content'][$i] = self::walk($child, $list, $values, $lists);
                }
            }
        }

        return $node;
    }

    /**
     * @param  array<mixed>                $node
     * @param  array<string,list<string>>  $values
     */
    private static function collectValues(array $node, array &$values): void
    {
        if (($node['type'] ?? null) === 'docField') {
            $id    = $node['attrs']['fieldId'] ?? null;
            $value = $node['attrs']['value'] ?? null;
            if (is_string($id) && is_string($value) && $value !== '') {
                $values[$id][] = $value;
            }

            return;
        }

        foreach ($node['content'] ?? [] as $child) {
            if (is_array($child)) {
                self::collectValues($child, $values);
            }
        }
    }

    /** @return list<array<string,string>> */
    private static function fromDatabase(string $source): array
    {
        $entries = match ($source) {
            self::RANKS      => Rank::active()->get(),
            self::AMB_SKILLS => AmbSkill::real()->get(),
            default          => [],
        };

        $options = [];
        foreach ($entries as $entry) {
            if ($entry->name === '') {
                continue;
            }
            $options[] = array_filter(
                ['label' => $entry->name, 'male' => $entry->name_m, 'female' => $entry->name_w],
                static fn (?string $form): bool => $form !== null && $form !== '',
            );
        }

        return $options;
    }
}
