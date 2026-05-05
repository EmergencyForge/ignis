<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * intra_changelog_cache — lokaler Spiegel der Hub-Changelog-API.
 *
 * Hub liefert paginierte JSON-Items, der Cache haelt sie als Rows. Damit
 * laeuft das Dashboard-Widget immer gegen die DB (schnell, offline-safe),
 * und der Hub-Fetch passiert ausschliesslich im Background-Refresh
 * (Console-Command, Cron). Wenn der Hub down ist, bleibt einfach die alte
 * Liste stehen — kein Render-Fehler im Admin-Panel.
 *
 * Zusaetzlich Meta-Tabelle fuer ETag/Last-Modified, damit der naechste
 * Fetch ein If-None-Match senden kann und der Hub mit 304 antworten darf
 * (kein DB-Hit auf seiner Seite, kein Body-Transfer auf unserer).
 */
class CreateIntraChangelogCache extends AbstractMigration
{
    public function change(): void
    {
        if (!$this->hasTable('intra_changelog_cache')) {
            $this->table('intra_changelog_cache', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'string', ['limit' => 80])
                ->addColumn('version', 'string', ['limit' => 32, 'null' => true])
                ->addColumn('product', 'string', ['limit' => 32, 'null' => true])
                ->addColumn('title', 'string', ['limit' => 255])
                ->addColumn('preview', 'text', ['null' => true])
                ->addColumn('url', 'string', ['limit' => 512])
                ->addColumn('tags', 'text', ['null' => true]) // JSON-Array
                ->addColumn('published_at', 'datetime')
                ->addColumn('fetched_at', 'datetime')
                ->addIndex(['published_at'], ['name' => 'idx_changelog_published'])
                ->create();
        }

        if (!$this->hasTable('intra_changelog_meta')) {
            $this->table('intra_changelog_meta', ['id' => false, 'primary_key' => ['key_name']])
                ->addColumn('key_name', 'string', ['limit' => 64])
                ->addColumn('value', 'text', ['null' => true])
                ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->create();
        }
    }
}
