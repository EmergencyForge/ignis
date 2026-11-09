<?php

declare(strict_types=1);

namespace Plugin\Training;

use App\Models\AmbSkill;
use App\Models\FdSkill;
use App\Personnel\PersonnelQualifications;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Qualifikationen als Text-Schlüssel: `rd:<id>` (RD-Stufe), `fw:<id>`
 * (FW-Stufe) oder `fd:<sgnr>` (Fachdienst). So kann eine Voraussetzung
 * aus jedem der drei Kataloge kommen, ohne eigene Spalten je Katalog.
 */
final class Qualifications
{
    /**
     * Ziel einer Lehrgangsart darf nur eine RD-Stufe mit „Zertifiziert?“
     * sein, damit niemand per Lehrgang eine Stufe vergibt, die die
     * Fraktion nicht als Ausbildung vorgesehen hat.
     *
     * @return array<string, string> Schlüssel => Name
     */
    public static function targets(): array
    {
        $options = [];
        foreach (AmbSkill::query()->where('trainable', true)->where('none', false)->orderBy('priority')->get() as $skill) {
            $options['rd:' . $skill->id] = $skill->name;
        }

        return $options;
    }

    /**
     * Alles, was als Voraussetzung taugt, nach Katalog gruppiert.
     *
     * @return array<string, array<string, string>>
     */
    public static function options(): array
    {
        $groups = ['Rettungsdienst' => [], 'Feuerwehr' => [], 'Fachdienste' => []];
        foreach (AmbSkill::query()->where('none', false)->orderBy('priority')->get() as $skill) {
            $groups['Rettungsdienst']['rd:' . $skill->id] = $skill->name;
        }
        foreach (FdSkill::query()->where('none', false)->orderBy('priority')->get() as $skill) {
            $groups['Feuerwehr']['fw:' . $skill->id] = $skill->name;
        }
        foreach (Capsule::table('intra_mitarbeiter_fdquali')->orderBy('sgname')->get(['sgnr', 'sgname']) as $row) {
            $groups['Fachdienste']['fd:' . $row->sgnr] = (string) $row->sgname;
        }

        return array_filter($groups);
    }

    public static function label(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }
        [$area, $value] = self::split($key);

        return match ($area) {
            'rd'    => AmbSkill::query()->find((int) $value)?->name,
            'fw'    => FdSkill::query()->find((int) $value)?->name,
            'fd'    => ($name = Capsule::table('intra_mitarbeiter_fdquali')->where('sgnr', $value)->value('sgname')) !== null ? (string) $name : null,
            default => null,
        };
    }

    /** Kurzname für Hinweise wie „Setzt RettSan voraus“. */
    public static function short(string $key): string
    {
        [$area, $value] = self::split($key);
        $short = match ($area) {
            'rd'    => AmbSkill::query()->find((int) $value)?->abkuerzung,
            'fw'    => FdSkill::query()->find((int) $value)?->shortname,
            default => null,
        };

        return $short !== null && trim($short) !== '' ? trim($short) : (self::label($key) ?? $key);
    }

    /** Hat der Mitarbeiter die Qualifikation oder eine höhere Stufe? */
    public static function holds(int $personnelId, string $key): bool
    {
        $row = Capsule::table('intra_mitarbeiter')->where('id', $personnelId)->first(['qualird', 'qualifw2', 'fachdienste']);
        if ($row === null) {
            return false;
        }
        [$area, $value] = self::split($key);

        if ($area === 'fd') {
            $list = json_decode((string) ($row->fachdienste ?? ''), true);

            return is_array($list) && in_array($value, array_map('strval', $list), true);
        }

        $model    = $area === 'rd' ? AmbSkill::class : FdSkill::class;
        $required = $model::query()->find((int) $value);
        $current  = $model::query()->find((int) ($area === 'rd' ? $row->qualird : $row->qualifw2));

        return $required !== null && $current !== null && $current->priority >= $required->priority;
    }

    /**
     * Kurznamen der Voraussetzungen, die fehlen.
     *
     * @param list<string> $keys
     * @return list<string>
     */
    public static function missing(int $personnelId, array $keys): array
    {
        $missing = [];
        foreach ($keys as $key) {
            if (!self::holds($personnelId, $key)) {
                $missing[] = self::short($key);
            }
        }

        return $missing;
    }

    /**
     * Bereich für PersonnelQualifications und Wert, oder null.
     *
     * @return array{0: string, 1: int|string}|null
     */
    public static function grantable(?string $key): ?array
    {
        if ($key === null || $key === '') {
            return null;
        }
        [$area, $value] = self::split($key);

        return match ($area) {
            'rd'    => [PersonnelQualifications::AREA_RD, (int) $value],
            'fw'    => [PersonnelQualifications::AREA_FW, (int) $value],
            'fd'    => [PersonnelQualifications::AREA_DEPARTMENT, $value],
            default => null,
        };
    }

    /** @return array{0: string, 1: string} */
    private static function split(string $key): array
    {
        $parts = explode(':', $key, 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
