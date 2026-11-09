<?php

declare(strict_types=1);

namespace App\Models;

use EmergencyForge\Editor\Letterhead as PackageLetterhead;
use EmergencyForge\Editor\Package;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Briefbogen: Layout und Kopf-/Fußzeilen, die mehrere Vorlagen teilen.
 * `content` ist ein Dokumentpaket ohne Textkörper. Vorlagen tragen die
 * Bänder aufgelöst in sich; ändert sich der Briefbogen, bekommt jede
 * Vorlage, die ihn nutzt, eine neue Fassung (siehe
 * LetterheadController::update()).
 *
 * @property int $id
 * @property string $name
 * @property array<string,mixed> $content
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Letterhead extends Model
{
    use SoftDeletes;

    protected $table = 'intra_letterheads';

    public $timestamps = true;

    /** @var array<string,string> */
    protected $casts = [
        'id'         => 'integer',
        'content'    => 'array',
        'created_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Form, die createDocumentEditor() unter `letterheads` erwartet.
     *
     * @return array{id: int, name: string, layout: mixed, header: mixed, footer: mixed}
     */
    public function forEditor(): array
    {
        $content = Package::normalize($this->content);

        return [
            'id'     => $this->id,
            'name'   => $this->name,
            'layout' => $content['layout'],
            'header' => $content['header'],
            'footer' => $content['footer'],
        ];
    }

    /**
     * Setzt Layout und Bänder dieses Briefbogens in ein Vorlagenpaket.
     *
     * @param array<string,mixed> $package
     * @return array<string,mixed>
     */
    public function applyTo(array $package): array
    {
        return PackageLetterhead::apply($package, $this->content, $this->id);
    }
}
