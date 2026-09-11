<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * `intra_dokument_kategorien.color` speichert seit dieser Migration einen
 * Farbschlüssel (`neutral|info|ok|warn|danger|dark`, siehe
 * App\Models\DocumentCategory::CHIP_CLASSES) statt des Chip-Klassennamens.
 * Die Vorlagen bilden den Schlüssel über das Model auf die Klasse ab und
 * verstehen alte Werte weiterhin; hier werden nur die Bestandsdaten
 * übersetzt. Additiv: kein Spaltenwechsel, der Rollback schreibt die
 * Klassennamen zurück.
 */
final class DocumentCategoryColorKeys extends AbstractMigration
{
    private const TO_KEY = [
        'ignis-chip--secondary' => 'neutral',
        'ignis-chip--primary'   => 'info',
        'ignis-chip--info'      => 'info',
        'ignis-chip--success'   => 'ok',
        'ignis-chip--warning'   => 'warn',
        'ignis-chip--danger'    => 'danger',
        'ignis-chip--dark'      => 'dark',
        // Falls die Vorgänger-Migration auf einer Installation nie lief.
        'text-bg-secondary'     => 'neutral',
        'text-bg-light'         => 'neutral',
        'text-bg-primary'       => 'info',
        'text-bg-info'          => 'info',
        'text-bg-success'       => 'ok',
        'text-bg-warning'       => 'warn',
        'text-bg-danger'        => 'danger',
        'text-bg-dark'          => 'dark',
    ];

    private const TO_CLASS = [
        'neutral' => 'ignis-chip--secondary',
        'info'    => 'ignis-chip--info',
        'ok'      => 'ignis-chip--success',
        'warn'    => 'ignis-chip--warning',
        'danger'  => 'ignis-chip--danger',
        'dark'    => 'ignis-chip--dark',
    ];

    public function up(): void
    {
        if (!$this->hasTable('intra_dokument_kategorien')) {
            return;
        }

        foreach (self::TO_KEY as $old => $key) {
            $this->execute(sprintf(
                'UPDATE intra_dokument_kategorien SET color = %s WHERE color = %s',
                $this->quoteSql($key),
                $this->quoteSql($old)
            ));
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('intra_dokument_kategorien')) {
            return;
        }

        foreach (self::TO_CLASS as $key => $class) {
            $this->execute(sprintf(
                'UPDATE intra_dokument_kategorien SET color = %s WHERE color = %s',
                $this->quoteSql($class),
                $this->quoteSql($key)
            ));
        }
    }

    private function quoteSql(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
