<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * intra_dashboard_tiles.url: deutsch -> englisch.
 *
 * Phase C der URL-Migration. Tiles, die per Admin-UI mit deutschen URLs
 * eingetragen wurden (`/kalender`, `/manv`, `/mitarbeiter/list`, …), werden
 * auf die kanonischen englischen Pfade umgeschrieben. Damit kostet ein
 * Tile-Klick keinen 301-Hop mehr.
 *
 * Idempotent: REPLACE auf bereits-englischen Werten ist no-op. Custom-URLs
 * mit Sub-Pfaden werden ebenfalls erfasst (z.B. `/manv/board?id=1` →
 * `/mci/board?id=1`).
 *
 * Sicherheitsnetz fuer den Rest: der UrlMap-basierte Redirector in
 * public/index.php redirected jede uebrig gebliebene deutsche URL via 301.
 */
class TranslateDashboardTileUrls extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('intra_dashboard_tiles')) {
            return;
        }

        $pdo = $this->getAdapter()->getConnection();

        // Top-Level-Map (gleiche Tabelle wie UrlMap::LEGACY_TO_CANONICAL,
        // bewusst inline gehalten — Migration soll nicht von App-Code abhaengen
        // und auch laufen, wenn UrlMap.php spaeter umstrukturiert wird).
        $topMap = [
            '/benutzer'            => '/users',
            '/antrag'              => '/forms',
            '/antraege'            => '/forms',
            '/benachrichtigungen'  => '/notifications',
            '/fahrtenbuch'         => '/logbook',
            '/kalender'            => '/calendar',
            '/mitarbeiter'         => '/personnel',
            '/manv'                => '/mci',
            '/einsatz'             => '/firetab',
            '/wissensdb'           => '/lexicon',
            '/dokumente'           => '/documents',
        ];

        $settingsMap = [
            'fahrzeuge'    => 'vehicles',
            'dienstgrade'  => 'ranks',
            'qualifw'      => 'fdskills',
            'qualird'      => 'ambskills',
            'qualifd'      => 'specialties',
            'beladelisten' => 'vehload',
            'defekte'      => 'defects',
            'medikamente'  => 'medications',
            'antrag'       => 'forms',
            'personal'     => 'personnel',
        ];

        $stmt = $pdo->prepare('UPDATE intra_dashboard_tiles SET url = REPLACE(url, :alt, :neu) WHERE url LIKE :like_alt');

        foreach ($topMap as $alt => $neu) {
            $stmt->execute([
                ':alt'      => $alt,
                ':neu'      => $neu,
                ':like_alt' => '%' . $alt . '%',
            ]);
        }
        foreach ($settingsMap as $alt => $neu) {
            $stmt->execute([
                ':alt'      => '/' . $alt . '/',
                ':neu'      => '/' . $neu . '/',
                ':like_alt' => '%/settings/' . $alt . '/%',
            ]);
        }
    }

    public function down(): void
    {
        // Bewusst kein Down-Path — die Translation ist eine Einbahnstrasse.
        // Wer downgraden muss, kann manuelle SQL-Statements gegen die alten
        // Pfade ausfuehren.
    }
}
