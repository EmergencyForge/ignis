<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Carbon;

/**
 * Hochgeladene Unterlage, privat unter storage/private/training/.
 *
 * @property int         $id
 * @property string      $storage_name
 * @property string      $original_name
 * @property string      $mime
 * @property int         $size
 * @property int         $version
 * @property int|null    $uploaded_by
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class TrainingFile extends EloquentModel
{
    protected $table = 'intra_training_files';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = ['id' => 'integer', 'size' => 'integer', 'version' => 'integer', 'uploaded_by' => 'integer'];

    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function label(): string
    {
        return match (true) {
            $this->isPdf()                     => 'PDF',
            $this->isImage()                   => 'Bild',
            str_contains($this->mime, 'presentation') => 'PowerPoint',
            str_contains($this->mime, 'wordprocessing') => 'Word',
            str_contains($this->mime, 'spreadsheet')    => 'Excel',
            default                            => 'Datei',
        };
    }

    public function icon(): string
    {
        return match ($this->label()) {
            'PDF'        => 'fa-solid fa-file-pdf',
            'Bild'       => 'fa-solid fa-file-image',
            'PowerPoint' => 'fa-solid fa-file-powerpoint',
            'Word'       => 'fa-solid fa-file-word',
            'Excel'      => 'fa-solid fa-file-excel',
            default      => 'fa-solid fa-file',
        };
    }

    /** „4,2 MB“ */
    public function sizeLabel(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1, ',', '.') . ' MB'
            : max(1, (int) round($this->size / 1024)) . ' KB';
    }
}
