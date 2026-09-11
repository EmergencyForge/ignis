<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Dynamische Dokumenten-Kategorien: eigene Tabelle statt des bisherigen
 * ENUM-Felds (urkunde, zertifikat, schreiben, sonstiges). Legt die vier
 * Standard-Kategorien an, hängt category_id an die Templates und mappt
 * bestehende ENUM-Werte auf die neuen Kategorie-IDs.
 */
class CreateIntraDokumentKategorien08032026 extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('intra_dokument_kategorien')) {
            $this->table('intra_dokument_kategorien', [
                'signed'    => true,
                'engine'    => 'InnoDB',
                'encoding'  => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
            ])
                ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
                ->addColumn('color', 'string', ['limit' => 30, 'default' => 'text-bg-secondary', 'null' => false])
                ->addColumn('icon', 'string', ['limit' => 50, 'null' => true, 'default' => null])
                ->addColumn('sort_order', 'integer', ['default' => 0, 'null' => false])
                ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
                ->create();
        }

        // Standard-Kategorien nur in eine leere Tabelle einfügen
        $count = $this->fetchRow('SELECT COUNT(*) AS c FROM intra_dokument_kategorien');
        if ((int) ($count['c'] ?? $count[0] ?? 0) === 0) {
            $this->table('intra_dokument_kategorien')->insert([
                ['name' => 'Urkunde',    'color' => 'text-bg-secondary', 'icon' => 'fa-solid fa-scroll',      'sort_order' => 1],
                ['name' => 'Zertifikat', 'color' => 'text-bg-dark',      'icon' => 'fa-solid fa-certificate', 'sort_order' => 2],
                ['name' => 'Schreiben',  'color' => 'text-bg-warning',   'icon' => 'fa-solid fa-envelope',    'sort_order' => 3],
                ['name' => 'Sonstiges',  'color' => 'text-bg-info',      'icon' => 'fa-solid fa-file',        'sort_order' => 4],
            ])->saveData();
        }

        $templates = $this->table('intra_dokument_templates');
        if (!$templates->hasColumn('category_id')) {
            $templates->addColumn('category_id', 'integer', [
                'null'    => true,
                'default' => null,
                'after'   => 'category',
            ])->update();
        }

        // Bestehende ENUM-Werte auf die neuen Kategorie-IDs mappen
        $pdo = $this->getAdapter()->getConnection();
        $mapping = [
            'urkunde'    => 'Urkunde',
            'zertifikat' => 'Zertifikat',
            'schreiben'  => 'Schreiben',
            'sonstiges'  => 'Sonstiges',
        ];

        $lookup = $pdo->prepare('SELECT id FROM intra_dokument_kategorien WHERE name = :name LIMIT 1');
        $assign = $pdo->prepare(
            'UPDATE intra_dokument_templates SET category_id = :cat_id
             WHERE category = :cat_enum AND (category_id IS NULL OR category_id = 0)'
        );

        foreach ($mapping as $enumVal => $katName) {
            $lookup->execute(['name' => $katName]);
            $katId = $lookup->fetchColumn();
            if ($katId) {
                $assign->execute(['cat_id' => $katId, 'cat_enum' => $enumVal]);
            }
        }
    }

    public function down(): void
    {
        // Das alte ENUM-Feld `category` blieb unangetastet — Spalte und
        // Kategorien-Tabelle entfernen stellt den vorherigen Zustand her.
        $templates = $this->table('intra_dokument_templates');
        if ($templates->hasColumn('category_id')) {
            $templates->removeColumn('category_id')->update();
        }
        if ($this->hasTable('intra_dokument_kategorien')) {
            $this->table('intra_dokument_kategorien')->drop()->save();
        }
    }
}
