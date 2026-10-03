<?php

declare(strict_types=1);

namespace Plugin\Mail;

use finfo;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;
use Plugin\Mail\Models\Attachment;
use Plugin\Mail\Models\Message;
use RuntimeException;

/**
 * Anhänge des Mailmoduls, privat unter storage/private/mail-attachments/
 * (außerhalb von public/, ausgeliefert nur über MailController::
 * attachmentDownload() an Beteiligte).
 *
 * Der Dateityp kommt aus den Magic Bytes (finfo), nicht aus Endung oder
 * dem vom Browser behaupteten Typ. Erlaubt: Bilder, PDF, reiner Text,
 * kein SVG (kann Skript tragen), kein HTML.
 *
 * Grenzen: 5 MB je Datei, 10 MB je Mail. Die Summe wird unter einer
 * Zeilensperre auf den Entwurf geprüft und geschrieben, sonst kämen zwei
 * gleichzeitige Uploads gemeinsam über die Grenze.
 */
final class AttachmentStorage
{
    public const MAX_FILE_BYTES  = 5 * 1024 * 1024;
    public const MAX_TOTAL_BYTES = 10 * 1024 * 1024;

    private const RELATIVE_DIR = 'storage/private/mail-attachments';

    /** @var array<string,string> MIME aus den Magic Bytes → Dateiendung */
    private const ALLOWED = [
        'image/png'       => 'png',
        'image/jpeg'      => 'jpg',
        'image/webp'      => 'webp',
        'image/gif'       => 'gif',
        'application/pdf' => 'pdf',
        'text/plain'      => 'txt',
    ];

    /**
     * @param array<string,mixed> $upload Eintrag aus $_FILES
     * @throws InvalidArgumentException mit einer Meldung für den Verfasser
     */
    public function store(Message $draft, array $upload): Attachment
    {
        $error = is_int($upload['error'] ?? null) ? $upload['error'] : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Die Datei ist zu groß (höchstens 5 MB je Anhang).',
                UPLOAD_ERR_NO_FILE => 'Es wurde keine Datei ausgewählt.',
                default => 'Der Upload ist fehlgeschlagen.',
            });
        }

        $tmp = is_string($upload['tmp_name'] ?? null) ? $upload['tmp_name'] : '';
        if ($tmp === '' || !is_file($tmp)) {
            throw new InvalidArgumentException('Es wurde keine Datei hochgeladen.');
        }

        $size = filesize($tmp);
        if ($size === false || $size > self::MAX_FILE_BYTES) {
            throw new InvalidArgumentException('Die Datei ist zu groß (höchstens 5 MB je Anhang).');
        }

        $mime      = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $extension = self::ALLOWED[$mime] ?? null;
        if ($extension === null) {
            throw new InvalidArgumentException('Dieser Dateityp ist nicht erlaubt (nur Bilder, PDF oder Text).');
        }

        $name = self::nameFor(self::sanitizeName(is_string($upload['name'] ?? null) ? $upload['name'] : ''), $mime);
        $dir  = $this->ensureDir();

        return Capsule::connection()->transaction(function () use ($draft, $tmp, $size, $mime, $extension, $name, $dir): Attachment {
            // Dieselbe Zeilensperre wie beim Senden: was hier ankommt, ist
            // entweder noch ein Entwurf oder schon gesendet, nie dazwischen.
            $locked = Message::query()->whereKey($draft->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new InvalidArgumentException('Entwurf wurde nicht gefunden.');
            }
            if ($locked->status !== 'draft') {
                throw new InvalidArgumentException('Die Mail ist schon gesendet, Anhänge lassen sich nicht mehr ändern.');
            }

            $total = (int) Attachment::query()->where('message_id', $draft->id)->sum('size');
            if ($total + $size > self::MAX_TOTAL_BYTES) {
                throw new InvalidArgumentException('Die Anhänge dieser Mail wären zusammen zu groß (höchstens 10 MB je Mail).');
            }

            $file = sprintf('mail-%d-%s.%s', $draft->id, bin2hex(random_bytes(8)), $extension);
            $moved = PHP_SAPI === 'cli'
                ? @rename($tmp, $dir . '/' . $file)
                : (is_uploaded_file($tmp) && move_uploaded_file($tmp, $dir . '/' . $file));
            if (!$moved) {
                throw new RuntimeException('Anhang konnte nicht gespeichert werden.');
            }

            $attachment = new Attachment();
            $attachment->message_id    = $draft->id;
            $attachment->path          = self::RELATIVE_DIR . '/' . $file;
            $attachment->original_name = $name;
            $attachment->mime          = $mime;
            $attachment->size          = (int) $size;
            $attachment->save();

            return $attachment;
        });
    }

    /** Pfad zur Datei für die Auslieferung, oder null, wenn sie fehlt. */
    public function absolutePath(Attachment $attachment): ?string
    {
        $dir  = realpath($this->dir());
        $file = realpath($this->dir() . '/' . basename($attachment->path));
        if ($dir === false || $file === false || !str_starts_with($file, $dir . DIRECTORY_SEPARATOR) || !is_file($file)) {
            return null;
        }

        return $file;
    }

    /**
     * Kopiert die Anhänge einer Mail auf einen Entwurf (Weiterleiten), bis
     * zur 10-MB-Grenze des Ziels. Jede Kopie bekommt eine eigene Datei, damit
     * das Löschen der einen die andere nicht mitreißt.
     */
    public function copyAll(Message $from, Message $to): void
    {
        $total = (int) Attachment::query()->where('message_id', $to->id)->sum('size');
        $dir   = $this->ensureDir();

        foreach ($from->attachments as $attachment) {
            $source = $this->absolutePath($attachment);
            if ($source === null || $total + $attachment->size > self::MAX_TOTAL_BYTES) {
                continue;
            }

            $file = sprintf('mail-%d-%s.%s', $to->id, bin2hex(random_bytes(8)), pathinfo($attachment->path, PATHINFO_EXTENSION));
            if (!@copy($source, $dir . '/' . $file)) {
                continue;
            }

            $copy = new Attachment();
            $copy->message_id    = $to->id;
            $copy->path          = self::RELATIVE_DIR . '/' . $file;
            $copy->original_name = $attachment->original_name;
            $copy->mime          = $attachment->mime;
            $copy->size          = $attachment->size;
            $copy->save();

            $total += $attachment->size;
        }
    }

    /** Datei bestenfalls, Zeile immer: eine fehlende Datei blockiert das Aufräumen nicht. */
    public function delete(Attachment $attachment): void
    {
        $file = $this->absolutePath($attachment);
        if ($file !== null) {
            @unlink($file);
        }
        $attachment->delete();
    }

    /**
     * Die Endung folgt dem erkannten Typ, nicht dem Namen: ein als Text
     * erkanntes „Wachplan.bat“ geht als „Wachplan.txt“ raus, eine fehlende
     * Endung kommt dazu. Beim Speichern und noch einmal beim Ausliefern.
     */
    public static function nameFor(string $name, string $mime): string
    {
        $extension = self::ALLOWED[$mime] ?? null;
        if ($extension === null) {
            return $name;
        }

        $stem    = $name;
        $current = '';
        if (preg_match('/^(.*)\.([A-Za-z0-9]{1,10})$/s', $name, $m) === 1) {
            [, $stem, $current] = $m;
        }
        $current = strtolower($current);
        if ($current === $extension || ($extension === 'jpg' && $current === 'jpeg')) {
            return $name;
        }

        return ($stem !== '' ? $stem : 'anhang') . '.' . $extension;
    }

    /**
     * Steuerzeichen (auch CR/LF) raus, bevor der Name gespeichert wird; im
     * Content-Disposition-Header könnten sie sonst Header einschleusen.
     */
    private static function sanitizeName(string $name): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $name));
        if (!mb_check_encoding($name, 'UTF-8')) {
            $name = (string) mb_convert_encoding($name, 'UTF-8', 'UTF-8');
        }
        $name = mb_substr($name, 0, 200, 'UTF-8');

        return $name !== '' ? $name : 'anhang';
    }

    private function dir(): string
    {
        return dirname(__DIR__, 3) . '/' . self::RELATIVE_DIR;
    }

    private function ensureDir(): string
    {
        $dir = $this->dir();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Ablage für Anhänge konnte nicht angelegt werden.');
        }

        return $dir;
    }
}
