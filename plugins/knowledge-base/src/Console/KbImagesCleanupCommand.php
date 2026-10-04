<?php

declare(strict_types=1);

namespace Plugin\KnowledgeBase\Console;

use App\Logging\Logger;
use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Entfernt Bilder aus storage/kb-images, die in keinem Eintrag mehr
 * vorkommen, nächtlich über intra_cron_jobs (`kb.images-cleanup`).
 *
 * Ein Bild landet beim Hochladen sofort auf der Platte, im Artikel steht
 * es erst nach dem Speichern. Wer es wieder herausnimmt oder nie speichert,
 * hinterlässt eine Datei, die niemand mehr anzeigt.
 *
 * Gelöscht wird nur, was älter als MAX_AGE_SECONDS ist (die Bilder eines
 * gerade offenen Editors bleiben) und in keiner Spalte irgendeines
 * Eintrags steht. Archivierte Einträge zählen mit. Verglichen wird der
 * Dateiname, nicht die URL, damit ein geänderter BASE_PATH nichts kostet.
 *
 *   php cli/intra.php kb:images:cleanup
 */
#[AsCommand(
    name: 'kb:images:cleanup',
    description: 'Entfernt hochgeladene Artikelbilder, die kein Eintrag mehr verwendet',
)]
final class KbImagesCleanupCommand extends Command
{
    private const MAX_AGE_SECONDS = 24 * 3600;

    /** Name, wie ihn FileUpload::store() für uploadImage() vergibt. */
    private const FILE_NAME = '[0-9a-f]{32}\.(?:jpg|png|webp|gif)';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $removed = $this->cleanup(dirname(__DIR__, 4) . '/storage/kb-images', time());

        Logger::info('KB-Bilder aufgeräumt', ['entfernt' => $removed]);
        $output->writeln("<info>Verwaiste Artikelbilder entfernt:</info> $removed");

        return Command::SUCCESS;
    }

    /**
     * Löscht in $dir die alten Bilder ohne Verweis und gibt die Anzahl
     * zurück. Scheitert die Abfrage, fliegt die Exception, bevor auch nur
     * eine Datei angefasst wurde.
     */
    public function cleanup(string $dir, int $now): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        // Alle Spalten, nicht nur die Editorfelder: ein Feld, das später
        // dazukommt, schützt seine Bilder dann ohne Änderung hier.
        $referenced = [];
        foreach (Capsule::table('intra_kb_entries')->orderBy('id')->cursor() as $row) {
            $referenced += self::referencedFiles((array) $row);
        }

        $count = 0;
        foreach (scandir($dir) ?: [] as $name) {
            if (!preg_match('/^' . self::FILE_NAME . '$/D', $name) || isset($referenced[$name])) {
                continue;
            }
            $path = $dir . '/' . $name;
            if (is_link($path) || !is_file($path)) {
                continue;
            }
            $mtime = @filemtime($path);
            if ($mtime === false || ($now - $mtime) < self::MAX_AGE_SECONDS) {
                continue;
            }
            if (@unlink($path)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Dateinamen aller Bilder aus storage/kb-images, die in den Werten
     * vorkommen, als Set (Name => true).
     *
     * @param iterable<mixed> $values
     * @return array<string, true>
     */
    public static function referencedFiles(iterable $values): array
    {
        $names = [];
        foreach ($values as $value) {
            if (!is_string($value) || !str_contains($value, 'kb-images/')) {
                continue;
            }
            preg_match_all('#kb-images/(' . self::FILE_NAME . ')#', $value, $m);
            foreach ($m[1] as $name) {
                $names[$name] = true;
            }
        }
        return $names;
    }
}
