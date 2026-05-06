<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * `intra_dokument_kategorien.color`-Werte von Bootstrap-Style
 * (`text-bg-secondary` etc.) auf das hauseigene `ignis-chip--X`-Pattern
 * umstellen. Templates rendern den gespeicherten Wert direkt als
 * Folge-Klasse hinter `ignis-chip` — durch die Umbenennung greift
 * automatisch das ignis-Soft-Variant-Styling, ohne dass die Templates
 * angefasst werden müssen.
 */
final class MigrateCategoryColorToIgnisChip extends AbstractMigration
{
    private const COLOR_MAP = [
        'text-bg-primary'   => 'ignis-chip--primary',
        'text-bg-success'   => 'ignis-chip--success',
        'text-bg-warning'   => 'ignis-chip--warning',
        'text-bg-danger'    => 'ignis-chip--danger',
        'text-bg-info'      => 'ignis-chip--info',
        'text-bg-secondary' => 'ignis-chip--secondary',
        'text-bg-dark'      => 'ignis-chip--dark',
        'text-bg-light'     => 'ignis-chip--secondary',
    ];

    public function up(): void
    {
        if (!$this->hasTable('intra_dokument_kategorien')) {
            return;
        }

        foreach (self::COLOR_MAP as $old => $new) {
            $this->execute(sprintf(
                "UPDATE intra_dokument_kategorien SET color = %s WHERE color = %s",
                $this->quoteSql($new),
                $this->quoteSql($old)
            ));
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('intra_dokument_kategorien')) {
            return;
        }

        // Reverse-Map (mehrere bs-Varianten mappen auf dieselbe ignis-Variante,
        // beim Rollback verlieren wir das — text-bg-light geht z.B. dauerhaft
        // zu text-bg-secondary verloren. Acceptable; der Rollback ist nur für
        // Notfälle gedacht und keine Round-Trip-Garantie).
        $reverse = [
            'ignis-chip--primary'   => 'text-bg-primary',
            'ignis-chip--success'   => 'text-bg-success',
            'ignis-chip--warning'   => 'text-bg-warning',
            'ignis-chip--danger'    => 'text-bg-danger',
            'ignis-chip--info'      => 'text-bg-info',
            'ignis-chip--secondary' => 'text-bg-secondary',
            'ignis-chip--dark'      => 'text-bg-dark',
        ];
        foreach ($reverse as $new => $old) {
            $this->execute(sprintf(
                "UPDATE intra_dokument_kategorien SET color = %s WHERE color = %s",
                $this->quoteSql($old),
                $this->quoteSql($new)
            ));
        }
    }

    private function quoteSql(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
