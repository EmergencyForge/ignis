<?php

namespace App\Documents;

use App\Models\DocumentTemplateAsset;
use Illuminate\Database\Capsule\Manager as Capsule;

class TemplateAssetManager
{
    private string $storagePath;

    private const ALLOWED_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/svg+xml',
    ];

    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    public function __construct(string $storagePath = __DIR__ . '/../../storage/template-assets')
    {
        $this->storagePath = rtrim($storagePath, '/\\');
    }

    /**
     * Lädt ein Bild hoch und speichert es
     *
     * @param array $file $_FILES Array-Eintrag
     * @param int|null $templateId Template-ID (null für globale Assets)
     * @param string $assetType Asset-Typ (image, background, logo, signature)
     * @return array Asset-Daten mit id, url, width, height
     */
    public function upload(array $file, ?int $templateId = null, string $assetType = 'image'): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('Upload-Fehler: ' . $this->getUploadErrorMessage($file['error']));
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new \Exception('Datei ist zu groß (max. 10MB)');
        }

        $mimeType = mime_content_type($file['tmp_name']);
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new \Exception('Ungültiger Dateityp. Erlaubt: PNG, JPG, GIF, SVG');
        }

        // Generiere eindeutigen Dateinamen
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16)) . '.' . strtolower($extension);

        // Stelle sicher, dass das Verzeichnis existiert
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        $targetPath = $this->storagePath . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \Exception('Datei konnte nicht gespeichert werden');
        }

        // Bild-Dimensionen ermitteln
        $width = null;
        $height = null;
        if ($mimeType !== 'image/svg+xml') {
            $imageInfo = getimagesize($targetPath);
            if ($imageInfo) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
            }
        }

        // DB-Eintrag erstellen
        $asset = DocumentTemplateAsset::create([
            'template_id' => $templateId,
            'filename' => $filename,
            'original_name' => $file['name'],
            'mime_type' => $mimeType,
            'file_size' => $file['size'],
            'width_px' => $width,
            'height_px' => $height,
            'asset_type' => $assetType,
            'uploaded_by' => $_SESSION['user_id'] ?? null,
        ]);

        return [
            'id' => (int) $asset->id,
            'url' => '/storage/template-assets/' . $filename,
            'original_name' => $file['name'],
            'mime_type' => $mimeType,
            'width' => $width,
            'height' => $height,
            'asset_type' => $assetType,
        ];
    }

    /**
     * Löscht ein Asset
     */
    public function delete(int $assetId): bool
    {
        $asset = DocumentTemplateAsset::find($assetId);

        if (!$asset) {
            return false;
        }

        // Datei löschen
        $filePath = $this->storagePath . '/' . $asset->filename;
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // DB-Eintrag löschen
        $asset->delete();
        return true;
    }

    /**
     * Listet Assets für ein Template (+ globale Assets)
     */
    public function listAssets(?int $templateId = null): array
    {
        $query = Capsule::table('intra_dokument_template_assets');

        if ($templateId !== null) {
            $query->where(function ($q) use ($templateId) {
                $q->where('template_id', $templateId)->orWhereNull('template_id');
            });
        }

        $assets = $query
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        foreach ($assets as &$asset) {
            $asset['url'] = '/storage/template-assets/' . $asset['filename'];
        }

        return $assets;
    }

    /**
     * Gibt ein Asset als Base64-String zurück (für PDF-Rendering)
     */
    public function getAsBase64(int $assetId): ?string
    {
        $asset = DocumentTemplateAsset::find($assetId);

        if (!$asset) {
            return null;
        }

        $filePath = $this->storagePath . '/' . $asset->filename;
        if (!file_exists($filePath)) {
            return null;
        }

        $data = file_get_contents($filePath);
        return 'data:' . $asset->mime_type . ';base64,' . base64_encode($data);
    }

    /**
     * Gibt den Dateipfad eines Assets zurück
     */
    public function getAssetPath(int $assetId): ?string
    {
        $asset = DocumentTemplateAsset::find($assetId);

        if (!$asset) {
            return null;
        }

        return $this->storagePath . '/' . $asset->filename;
    }

    /**
     * Gibt ein einzelnes Asset zurück
     */
    public function getAsset(int $assetId): ?array
    {
        $asset = DocumentTemplateAsset::find($assetId);

        if (!$asset) {
            return null;
        }

        $data = $asset->getAttributes();
        $data['url'] = '/storage/template-assets/' . $data['filename'];

        return $data;
    }

    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'Datei überschreitet die maximale Upload-Größe',
            UPLOAD_ERR_FORM_SIZE => 'Datei überschreitet die maximale Formulargröße',
            UPLOAD_ERR_PARTIAL => 'Datei wurde nur teilweise hochgeladen',
            UPLOAD_ERR_NO_FILE => 'Keine Datei hochgeladen',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Verzeichnis fehlt',
            UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht geschrieben werden',
            UPLOAD_ERR_EXTENSION => 'Upload durch Erweiterung blockiert',
            default => 'Unbekannter Fehler',
        };
    }
}
