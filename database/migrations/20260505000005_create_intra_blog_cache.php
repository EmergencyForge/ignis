<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * intra_blog_cache + intra_blog_meta — lokaler Spiegel der Hub-Blog-API.
 *
 * Schwester-Tabelle zu `intra_changelog_cache` (Migration 20260505000001).
 * Reichere Item-Felder als der Changelog: Cover-Image, Author (name +
 * avatar), Category + Label, Tags, Reading-Minutes, Pinned-Flag.
 *
 * Meta-Tabelle (intra_blog_meta) speichert ETag + Last-Modified vom Hub,
 * damit der naechste Refresh `If-None-Match`/`If-Modified-Since` mitsenden
 * kann und der Hub mit 304 antworten darf.
 */
class CreateIntraBlogCache extends AbstractMigration
{
    public function change(): void
    {
        if (!$this->hasTable('intra_blog_cache')) {
            $this->table('intra_blog_cache', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'string', ['limit' => 80])
                ->addColumn('slug', 'string', ['limit' => 160])
                ->addColumn('title', 'string', ['limit' => 255])
                ->addColumn('subtitle', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('preview', 'text', ['null' => true])
                ->addColumn('cover_image', 'string', ['limit' => 512, 'null' => true])
                ->addColumn('author_name', 'string', ['limit' => 120])
                ->addColumn('author_avatar', 'string', ['limit' => 512, 'null' => true])
                ->addColumn('category', 'string', ['limit' => 64])
                ->addColumn('category_label', 'string', ['limit' => 120])
                ->addColumn('tags', 'text', ['null' => true]) // JSON-Array
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
                ->addColumn('key_name', 'string', ['limit' => 64])
                ->addColumn('value', 'text', ['null' => true])
                ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->create();
        }
    }
}
