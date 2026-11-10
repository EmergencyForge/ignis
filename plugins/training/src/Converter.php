<?php

declare(strict_types=1);

namespace Plugin\Training;

use Plugin\Training\Models\TrainingFile;
use RuntimeException;

/**
 * Wandelt PowerPoint per LibreOffice in PDF, damit sie im Betrachter läuft.
 * Eingeschaltet über `TRAINING_CONVERTER` mit dem Programm, etwa `soffice`
 * oder `/usr/bin/soffice`. Das Docker-Image bringt LibreOffice nicht mit.
 */
final class Converter
{
    public const PPTX = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';

    public const PENDING = 'pending';
    public const DONE    = 'done';
    public const FAILED  = 'failed';

    public function __construct(
        private readonly string $binary,
        private readonly FileStore $store = new FileStore(),
    ) {
    }

    /** Null, wenn kein Umwandler eingetragen ist. */
    public static function configured(): ?self
    {
        $binary = trim((string) env_value('TRAINING_CONVERTER'));

        return $binary !== '' ? new self($binary) : null;
    }

    /** Ob das Programm sich aufrufen lässt; `exec` kann auf Webspaces gesperrt sein. */
    public function available(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        exec(escapeshellarg($this->binary) . ' --version 2>&1', $out, $code);

        return $code === 0;
    }

    /** @return array{done: int, failed: int} */
    public function runPending(int $limit = 5): array
    {
        $result = ['done' => 0, 'failed' => 0];
        /** @var list<TrainingFile> $files */
        $files  = TrainingFile::query()->where('convert_status', self::PENDING)->where('mime', self::PPTX)->orderBy('id')->limit($limit)->get()->all();
        foreach ($files as $file) {
            try {
                $this->convert($file);
                $result['done']++;
            } catch (RuntimeException $e) {
                \App\Logging\Logger::warning('Unterlage ' . $file->id . ' nicht umgewandelt: ' . $e->getMessage());
                $file->convert_status = self::FAILED;
                $file->save();
                $result['failed']++;
            }
        }

        return $result;
    }

    private function convert(TrainingFile $file): void
    {
        $source = $this->store->path($file);
        if ($source === null) {
            throw new RuntimeException('Datei fehlt.');
        }
        $work = sys_get_temp_dir() . '/ignis-convert-' . bin2hex(random_bytes(6));
        if (!@mkdir($work, 0700)) {
            throw new RuntimeException('Arbeitsordner lässt sich nicht anlegen.');
        }
        try {
            // Eigenes Profil je Lauf: LibreOffice hängt sonst, wenn ein zweiter Lauf dasselbe Profil sperrt.
            $command = sprintf(
                '%s -env:UserInstallation=%s --headless --norestore --convert-to pdf --outdir %s %s 2>&1',
                escapeshellarg($this->binary),
                escapeshellarg('file://' . $work . '/profile'),
                escapeshellarg($work),
                escapeshellarg($source),
            );
            exec($command, $out, $code);
            $pdf = $work . '/' . pathinfo($source, PATHINFO_FILENAME) . '.pdf';
            if ($code !== 0 || !is_file($pdf)) {
                throw new RuntimeException('LibreOffice meldet ' . $code . ': ' . implode(' ', array_slice($out, -3)));
            }
            $this->store->attachPdf($file, $pdf);
        } finally {
            self::remove($work);
        }
        $file->convert_status = self::DONE;
        $file->save();
    }

    private static function remove(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
