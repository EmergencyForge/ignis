<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Der Blog-Abruf vom Hub entfällt: Ankündigungen kommen jetzt aus dem Forum
 * (siehe 20260926000001). Weg sind der Cron-Eintrag `blog.refresh` und die
 * Cache-Tabellen intra_blog_cache/intra_blog_meta.
 *
 * down() legt Tabellen und Cron-Eintrag in der Form von 20260505000005 und
 * 20260505000006 wieder an (Inhalt nicht, reiner Cache). Den Befehl
 * blog:refresh gibt es danach aber nicht mehr, der Job würde also scheitern;
 * wer zurückrollt, holt auch den Code zurück.
 */
final class RemoveBlogCache extends AbstractMigration
{
    public function up(): void
    {
        if ($this->hasTable('intra_cron_jobs')) {
            $this->execute("DELETE FROM intra_cron_jobs WHERE identifier = 'blog.refresh'");
        }
        foreach (['intra_blog_cache', 'intra_blog_meta'] as $table) {
            if ($this->hasTable($table)) {
                $this->table($table)->drop()->save();
            }
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('intra_blog_cache')) {
            $this->table('intra_blog_cache', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'string', ['limit' => 80, 'null' => false])
                ->addColumn('slug', 'string', ['limit' => 160])
                ->addColumn('title', 'string', ['limit' => 255])
                ->addColumn('subtitle', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('preview', 'text', ['null' => true])
                ->addColumn('cover_image', 'string', ['limit' => 512, 'null' => true])
                ->addColumn('author_name', 'string', ['limit' => 120])
                ->addColumn('author_avatar', 'string', ['limit' => 512, 'null' => true])
                ->addColumn('category', 'string', ['limit' => 64])
                ->addColumn('category_label', 'string', ['limit' => 120])
                ->addColumn('tags', 'text', ['null' => true])
                ->addColumn('reading_minutes', 'integer', ['null' => true])
                ->addColumn('pinned', 'boolean', ['default' => false])
                ->addColumn('url', 'string', ['limit' => 512])
                ->addColumn('published_at', 'datetime')
                ->addColumn('fetched_at', 'datetime')
                ->addIndex(['pinned', 'published_at'], ['name' => 'idx_blog_pinned_published'])
                ->addIndex(['category'], ['name' => 'idx_blog_category'])
                ->create();
        }

        if (!$this->hasTable('intra_blog_meta')) {
            $this->table('intra_blog_meta', ['id' => false, 'primary_key' => ['key_name']])
                ->addColumn('key_name', 'string', ['limit' => 64, 'null' => false])
                ->addColumn('value', 'text', ['null' => true])
                ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->create();
        }

        if ($this->hasTable('intra_cron_jobs')) {
            $this->getAdapter()->getConnection()->prepare(
                'INSERT IGNORE INTO intra_cron_jobs
                    (identifier, name, description, handler_type, handler, schedule, config,
                     active, is_builtin, next_run_at)
                 VALUES
                    (:identifier, :name, :description, :handler_type, :handler, :schedule, :config,
                     :active, :is_builtin, NOW())'
            )->execute([
                'identifier'   => 'blog.refresh',
                'name'         => 'Blog-Posts aktualisieren',
                'description'  => 'Holt alle 30 Minuten neue Blog-Posts vom Hub.',
                'handler_type' => 'console',
                'handler'      => 'blog:refresh',
                'schedule'     => '*/30 * * * *',
                'config'       => json_encode(['timeout' => 15]),
                'active'       => 1,
                'is_builtin'   => 1,
            ]);
        }
    }
}
