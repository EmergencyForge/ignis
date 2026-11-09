<?php

declare(strict_types=1);

namespace App\Personnel;

use App\Events\EventDispatcher;
use App\Events\PersonnelSaved;
use App\Models\AmbSkill;
use App\Models\FdSkill;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;

/**
 * Setzt die Qualifikationen eines Mitarbeiters: RD-Stufe (`qualird`),
 * FW-Stufe (`qualifw2`) und die Fachdienste. Jede Änderung landet im
 * Verlauf.
 *
 * `set()` und `setDepartments()` feuern kein `PersonnelSaved`, weil das
 * Profilformular mehrere Felder auf einmal speichert und das Ereignis nur
 * einmal will. `grant()` ist ein eigener Vorgang und feuert selbst.
 */
final class PersonnelQualifications
{
    public const AREA_RD = 'RD';
    public const AREA_FW = 'FW';
    public const AREA_DEPARTMENT = 'FD';

    public function __construct(private readonly PersonalLogManager $log = new PersonalLogManager())
    {
    }

    /** RD- oder FW-Stufe setzen. true, wenn sich etwas geändert hat. */
    public function set(int $personnelId, string $area, int $skillId, string $editor): bool
    {
        $column  = $this->column($area);
        $current = (int) Capsule::table('intra_mitarbeiter')->where('id', $personnelId)->value($column);
        if ($current === $skillId) {
            return false;
        }

        Capsule::table('intra_mitarbeiter')->where('id', $personnelId)->update([$column => $skillId]);
        $this->log->logQualificationChange($personnelId, $area, $this->name($area, $current), $this->name($area, $skillId), $editor);

        return true;
    }

    /**
     * Fachdienste als Liste der Nummern (`sgnr`) ersetzen.
     *
     * @param list<string> $numbers
     */
    public function setDepartments(int $personnelId, array $numbers, string $editor): bool
    {
        $json = (string) json_encode($numbers);
        if (Capsule::table('intra_mitarbeiter')->where('id', $personnelId)->value('fachdienste') === $json) {
            return false;
        }

        Capsule::table('intra_mitarbeiter')->where('id', $personnelId)->update(['fachdienste' => $json]);
        $this->log->logDepartmentModification($personnelId, $editor);

        return true;
    }

    /**
     * Qualifikation aus einer Ausbildung eintragen. Setzt nur nach oben:
     * wer schon NotSan ist, bleibt es nach einem RettSan-Lehrgang. Ein
     * Fachdienst kommt zur Liste dazu. Der Verlaufseintrag ist vom Typ
     * „Ausbildung“ und nennt den Grund.
     *
     * @param int|string $value Stufen-ID (RD, FW) oder Fachdienst-Nummer (FD)
     */
    public function grant(int $personnelId, string $area, int|string $value, string $reason, string $editor): bool
    {
        $row = Capsule::table('intra_mitarbeiter')->where('id', $personnelId)->first(['qualird', 'qualifw2', 'fachdienste']);
        if ($row === null) {
            throw new InvalidArgumentException('Mitarbeiter ' . $personnelId . ' gibt es nicht.');
        }

        if ($area === self::AREA_DEPARTMENT) {
            $number  = (string) $value;
            $current = json_decode((string) ($row->fachdienste ?? ''), true);
            $current = is_array($current) ? array_values(array_map('strval', $current)) : [];
            if (in_array($number, $current, true)) {
                return false;
            }
            $label = (string) (Capsule::table('intra_mitarbeiter_fdquali')->where('sgnr', $number)->value('sgname') ?? $number);
            Capsule::table('intra_mitarbeiter')->where('id', $personnelId)->update(['fachdienste' => json_encode([...$current, $number])]);
        } else {
            $column = $this->column($area);
            $target = $this->skill($area, (int) $value);
            if ($target === null) {
                throw new InvalidArgumentException('Die Qualifikation ' . $value . ' gibt es nicht.');
            }
            $current = $this->skill($area, (int) $row->{$column});
            if ($current !== null && $current->priority >= $target->priority) {
                return false;
            }
            $label = $target->name;
            Capsule::table('intra_mitarbeiter')->where('id', $personnelId)->update([$column => $target->id]);
        }

        $this->log->addEntry(
            $personnelId,
            PersonalLogManager::TYPE_TRAINING,
            sprintf('Qualifikation (%s) <strong>%s</strong> erworben: %s', htmlspecialchars($area), htmlspecialchars($label), htmlspecialchars($reason)),
            $editor,
            ['change_type' => 'training', 'qualification_type' => $area, 'new_value' => $label, 'reason' => $reason],
        );
        app(EventDispatcher::class)->fire(new PersonnelSaved($personnelId));

        return true;
    }

    private function column(string $area): string
    {
        return match ($area) {
            self::AREA_RD => 'qualird',
            self::AREA_FW => 'qualifw2',
            default       => throw new InvalidArgumentException('Unbekannter Qualifikationsbereich: ' . $area),
        };
    }

    private function skill(string $area, int $id): AmbSkill|FdSkill|null
    {
        return $area === self::AREA_RD ? AmbSkill::find($id) : FdSkill::find($id);
    }

    private function name(string $area, int $id): string
    {
        return $this->skill($area, $id)->name ?? '?';
    }
}
