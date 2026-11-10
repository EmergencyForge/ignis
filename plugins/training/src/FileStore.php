<?php

declare(strict_types=1);

namespace Plugin\Training;

use DomainException;
use EmergencyForge\Training\MaterialType;
use Plugin\Training\Models\TrainingFile;
use RuntimeException;

/**
 * Unterlagen liegen außerhalb des Docroots unter zufälligen Namen. Den Typ
 * bestimmt der Inhalt, nicht Name oder Browser. Ausgeliefert wird nur über
 * die Routen mit Rechteprüfung.
 */
final class FileStore
{
    private readonly string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? dirname(__DIR__, 3) . '/storage/private/training';
    }

    /**
     * Neue Datei ablegen oder `$replace` durch eine neue Fassung ersetzen.
     * Die Einheiten zeigen weiter auf denselben Datensatz.
     *
     * @param array<string, mixed> $upload Eintrag aus $_FILES
     */
    public function store(array $upload, ?int $userId, ?TrainingFile $replace = null): TrainingFile
    {
        $error = is_int($upload['error'] ?? null) ? $upload['error'] : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new DomainException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Die Datei ist zu groß (höchstens 25 MB).',
                UPLOAD_ERR_NO_FILE                        => 'Es wurde keine Datei ausgewählt.',
                default                                   => 'Der Upload ist fehlgeschlagen.',
            });
        }
        $tmp = is_string($upload['tmp_name'] ?? null) ? $upload['tmp_name'] : '';
        if ($tmp === '' || !is_file($tmp)) {
            throw new DomainException('Es wurde keine Datei hochgeladen.');
        }
        $size = (int) filesize($tmp);
        if ($size > MaterialType::DEFAULT_MAX_BYTES) {
            throw new DomainException('Die Datei ist zu groß (höchstens 25 MB).');
        }
        $type = MaterialType::detect($tmp);
        if ($type === null) {
            throw new DomainException('Dieser Dateityp ist nicht erlaubt. Erlaubt sind PDF, Bilder, Word, Excel und PowerPoint.');
        }

        if (!is_dir($this->dir) && !@mkdir($this->dir, 0750, true) && !is_dir($this->dir)) {
            throw new RuntimeException('Ablage für Unterlagen fehlt: ' . $this->dir);
        }
        $name  = bin2hex(random_bytes(16)) . '.' . $type->extension;
        $moved = PHP_SAPI === 'cli'
            ? @rename($tmp, $this->dir . '/' . $name)
            : (is_uploaded_file($tmp) && move_uploaded_file($tmp, $this->dir . '/' . $name));
        if (!$moved) {
            throw new RuntimeException('Unterlage konnte nicht gespeichert werden.');
        }

        $file = $replace ?? new TrainingFile();
        if ($replace !== null) {
            $this->unlink($replace->storage_name);
            if ($replace->pdf_storage_name !== null) {
                $this->unlink($replace->pdf_storage_name);
            }
            $file->version = $replace->version + 1;
        }
        $file->pdf_storage_name = null;
        $file->convert_status   = $type->mime === Converter::PPTX ? Converter::PENDING : null;
        $file->storage_name  = $name;
        $file->original_name = self::cleanName(is_string($upload['name'] ?? null) ? $upload['name'] : '', $type->extension);
        $file->mime          = $type->mime;
        $file->size          = $size;
        $file->uploaded_by   = $userId;
        $file->save();

        return $file;
    }

    /** Pfad auf der Platte, oder null wenn die Datei fehlt. */
    public function path(TrainingFile $file): ?string
    {
        return $this->resolve($file->storage_name);
    }

    /** Die umgewandelte PDF einer Office-Datei, sonst null. */
    public function pdfPath(TrainingFile $file): ?string
    {
        return $file->pdf_storage_name !== null ? $this->resolve($file->pdf_storage_name) : null;
    }

    /** Legt die umgewandelte PDF neben die Datei und vermerkt sie. */
    public function attachPdf(TrainingFile $file, string $pdf): void
    {
        $name = bin2hex(random_bytes(16)) . '.pdf';
        if (!@rename($pdf, $this->dir . '/' . $name) && !(@copy($pdf, $this->dir . '/' . $name))) {
            throw new RuntimeException('PDF konnte nicht abgelegt werden.');
        }
        if ($file->pdf_storage_name !== null) {
            $this->unlink($file->pdf_storage_name);
        }
        $file->pdf_storage_name = $name;
    }

    private function resolve(string $storageName): ?string
    {
        $base = realpath($this->dir);
        $path = realpath($this->dir . '/' . basename($storageName));
        if ($base === false || $path === false || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $path;
    }

    public function delete(TrainingFile $file): void
    {
        $this->unlink($file->storage_name);
        if ($file->pdf_storage_name !== null) {
            $this->unlink($file->pdf_storage_name);
        }
        $file->delete();
    }

    private function unlink(string $storageName): void
    {
        $path = $this->dir . '/' . basename($storageName);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Der Name kommt vom Browser und landet im Download-Header. */
    private static function cleanName(string $name, string $extension): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/u', '', basename(str_replace('\\', '/', $name))));
        if ($name === '') {
            $name = 'Unterlage.' . $extension;
        }

        return mb_substr($name, 0, 200);
    }
}
