<?php

declare(strict_types=1);

namespace Plugin\Training\Controllers;

use App\Documents\Editor\TemplateImageSource;
use App\Exceptions\UploadException;
use App\Helpers\Flash;
use App\Plugins\PluginLoader;
use DomainException;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use EmergencyForge\Training\UnitKind;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Training\FileStore;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\CourseType;
use Plugin\Training\Models\Module;
use Plugin\Training\Models\TrainingFile;
use Plugin\Training\Models\Unit;
use Plugin\Training\Policies\TrainingPolicy;

/**
 * Module und Einheiten. Ein Modul gehört einer Lehrgangsart (bearbeiten
 * nur Verwalter) oder als Zusatz einem Lehrgang (bearbeitet dessen
 * Leitung).
 */
final class CurriculumController extends BaseController
{
    /** Was die Lehrgangsart-Seite anlegen lässt. Lernkontrollen kommen mit den Prüfungen. */
    private const KINDS = [UnitKind::File, UnitKind::Text, UnitKind::Link, UnitKind::KnowledgeArticle, UnitKind::Session];

    public function __construct(private readonly FileStore $files = new FileStore())
    {
    }

    /** POST /training/types/{id}/modules */
    public function addTypeModule(Request $request, string $id): Response
    {
        $type = CourseType::query()->find((int) $id);
        if ($type === null) {
            return self::notFound();
        }

        return $this->addModule('course_type_id', $type->id, (string) ($request->post['title'] ?? ''));
    }

    /** POST /training/manage/{id}/modules */
    public function addCourseModule(Request $request, string $id): Response
    {
        $course = self::findCourse($id);
        if ($course === null || !TrainingPolicy::lead($course)) {
            return self::notFound();
        }

        return $this->addModule('course_id', $course->id, (string) ($request->post['title'] ?? ''));
    }

    /** POST /training/modules/{id} */
    public function renameModule(Request $request, string $id): Response
    {
        $module = $this->module($id);
        if ($module === null) {
            return self::notFound();
        }
        $title = trim((string) ($request->post['title'] ?? ''));
        if ($title === '') {
            return self::back(self::ownerPath($module), 'Bitte einen Titel angeben.');
        }
        $module->title = mb_substr($title, 0, 150);
        $module->save();

        return self::back(self::ownerPath($module), null, 'Modul umbenannt.');
    }

    /** POST /training/modules/{id}/delete */
    public function deleteModule(Request $request, string $id): Response
    {
        $module = $this->module($id);
        if ($module === null) {
            return self::notFound();
        }
        $fileIds = Unit::query()->where('module_id', $module->id)->whereNotNull('file_id')->pluck('file_id')->all();
        $module->delete();
        $this->dropOrphans($fileIds);

        return self::back(self::ownerPath($module), null, 'Modul „' . $module->title . '“ gelöscht.');
    }

    /** GET /training/modules/{id}/units/create */
    public function createUnit(Request $request, string $id): Response
    {
        $module = $this->module($id);
        if ($module === null) {
            return self::notFound();
        }
        $unit = new Unit();
        $unit->module_id      = $module->id;
        $unit->kind           = (UnitKind::tryFrom($request->queryString('kind')) ?? UnitKind::File)->value;
        $unit->title          = '';
        $unit->required       = true;
        $unit->allow_download = true;

        return $this->unitForm($module, $unit);
    }

    /** POST /training/modules/{id}/units */
    public function storeUnit(Request $request, string $id): Response
    {
        $module = $this->module($id);
        if ($module === null) {
            return self::notFound();
        }
        $unit = new Unit();
        $unit->module_id = $module->id;
        $unit->position  = 1 + (int) Unit::query()->where('module_id', $module->id)->max('position');

        return $this->saveUnit($module, $unit, $request);
    }

    /** GET /training/units/{id}/edit */
    public function editUnit(Request $request, string $id): Response
    {
        [$module, $unit] = $this->unit($id);

        return $unit === null ? self::notFound() : $this->unitForm($module, $unit);
    }

    /** POST /training/units/{id} */
    public function updateUnit(Request $request, string $id): Response
    {
        [$module, $unit] = $this->unit($id);

        return $unit === null ? self::notFound() : $this->saveUnit($module, $unit, $request);
    }

    /** POST /training/units/{id}/delete */
    public function deleteUnit(Request $request, string $id): Response
    {
        [$module, $unit] = $this->unit($id);
        if ($unit === null) {
            return self::notFound();
        }
        $fileId = $unit->file_id;
        $unit->delete();
        $this->dropOrphans($fileId !== null ? [$fileId] : []);

        return self::back(self::ownerPath($module), null, 'Einheit „' . $unit->title . '“ gelöscht.');
    }

    /** POST /training/units/{id}/required */
    public function required(Request $request, string $id): Response
    {
        [, $unit] = $this->unit($id);
        if ($unit === null) {
            return self::json(['error' => 'Nicht gefunden.'], 404);
        }
        $unit->required = ($request->post['required'] ?? '') === '1';
        $unit->save();

        return self::json(['required' => $unit->required]);
    }

    /**
     * POST /training/modules/order: {modules: [id, ...], units: {modulId: [id, ...]}}
     * Einheiten dürfen zwischen Modulen desselben Lehrplans wandern.
     */
    public function order(Request $request): Response
    {
        $body    = $request->json() ?? [];
        $modules = array_map('intval', is_array($body['modules'] ?? null) ? $body['modules'] : []);
        $units   = is_array($body['units'] ?? null) ? $body['units'] : [];
        /** @var \Illuminate\Support\Collection<int, Module> $rows */
        $rows    = Module::query()->whereIn('id', $modules ?: [0])->get()->keyBy('id');
        if ($modules === [] || $rows->count() !== count(array_unique($modules))) {
            return self::json(['error' => 'Unbekanntes Modul.'], 422);
        }
        /** @var Module $first */
        $first = $rows->first();
        foreach ($rows as $row) {
            if ($row->course_type_id !== $first->course_type_id || $row->course_id !== $first->course_id) {
                return self::json(['error' => 'Module aus verschiedenen Lehrplänen.'], 422);
            }
        }
        if (!$this->mayEdit($first)) {
            return self::json(['error' => 'Keine Berechtigung.'], 403);
        }

        $unitIds = [];
        foreach ($units as $moduleId => $ids) {
            if (!$rows->has((int) $moduleId) || !is_array($ids)) {
                return self::json(['error' => 'Unbekanntes Modul.'], 422);
            }
            $unitIds = [...$unitIds, ...array_map('intval', $ids)];
        }
        if ($unitIds !== [] && Unit::query()->whereIn('id', $unitIds)->whereNotIn('module_id', $modules)->exists()) {
            return self::json(['error' => 'Einheit aus einem anderen Lehrplan.'], 422);
        }

        Capsule::connection()->transaction(static function () use ($modules, $units): void {
            foreach ($modules as $position => $moduleId) {
                Module::query()->whereKey($moduleId)->update(['position' => $position]);
            }
            foreach ($units as $moduleId => $ids) {
                foreach (array_values($ids) as $position => $unitId) {
                    Unit::query()->whereKey((int) $unitId)->update(['module_id' => (int) $moduleId, 'position' => $position]);
                }
            }
        });

        return self::json(['ok' => true]);
    }

    /** GET /training/files/{id}: Vorschau für die Bearbeiter des Lehrplans. */
    public function file(Request $request, string $id): Response
    {
        $file = TrainingFile::query()->find((int) $id);
        if ($file === null) {
            return self::notFound();
        }
        /** @var list<Module> $modules */
        $modules = Module::query()->whereIn('id', Unit::query()->where('file_id', $file->id)->select('module_id'))->get()->all();
        $module  = null;
        foreach ($modules as $candidate) {
            if ($this->mayEdit($candidate)) {
                $module = $candidate;
                break;
            }
        }

        return $module === null ? self::notFound() : self::deliver($file, $request->queryString('download') === '1');
    }

    /**
     * POST /api/training/modules/{id}/images: Bild für einen Artikel. Liegt
     * bei den Vorlagenbildern, weil der Renderer nur Pfade derselben Seite
     * mit Bildendung durchlässt und deren Route ohne Anmeldung ausliefert.
     */
    public function uploadImage(Request $request, string $id): Response
    {
        if ($this->module($id) === null) {
            return self::json(['error' => 'Keine Berechtigung für diesen Lehrplan.'], 403);
        }
        $upload = $request->files['image'] ?? null;
        if (!is_array($upload)) {
            return self::json(['error' => 'Es wurde keine Datei ausgewählt.'], 422);
        }
        try {
            $name = TemplateImageSource::store($upload);
        } catch (UploadException $e) {
            return self::json(['error' => $e->getMessage()], $e->status);
        }

        return self::json(['src' => self::base() . TemplateImageSource::ROUTE . $name, 'alt' => '']);
    }

    private function addModule(string $column, int $ownerId, string $title): Response
    {
        $module           = new Module();
        $module->{$column} = $ownerId;
        $module->title    = mb_substr(trim($title), 0, 150) ?: 'Neues Modul';
        $module->position = 1 + (int) Module::query()->where($column, $ownerId)->max('position');
        $module->save();

        return self::back(self::ownerPath($module), null, 'Modul „' . $module->title . '“ angelegt.');
    }

    private function unitForm(Module $module, Unit $unit, int $status = 200): Response
    {
        $kb = app(PluginLoader::class)->isActive('knowledge-base')
            ? Capsule::table('intra_kb_entries')->where('is_archived', 0)->orderBy('title')->pluck('title', 'id')->all()
            : [];

        return $this->page('types/unit-form', [
            'module'  => $module,
            'unit'    => $unit,
            'kinds'   => self::KINDS,
            'kb'      => $kb,
            'back'    => self::ownerPath($module),
            'doc'     => is_array($unit->content['doc'] ?? null) ? $unit->content['doc'] : ['type' => 'doc', 'content' => []],
        ], $status);
    }

    private function saveUnit(Module $module, Unit $unit, Request $request): Response
    {
        $post = $request->post;
        $kind = UnitKind::tryFrom((string) ($post['kind'] ?? ''));
        $unit->title          = mb_substr(trim((string) ($post['title'] ?? '')), 0, 200);
        $unit->required       = ($post['required'] ?? '') === '1';
        $unit->allow_download = ($post['allow_download'] ?? '') === '1';
        if ($kind !== null && in_array($kind, self::KINDS, true)) {
            $unit->kind = $kind->value;
        }

        $upload = $request->files['file'] ?? null;
        $hasUpload = is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $doc       = json_decode((string) ($post['doc'] ?? ''), true);

        $error = match (true) {
            $kind === null || !in_array($kind, self::KINDS, true)                       => 'Bitte eine Art wählen.',
            $unit->title === ''                                                          => 'Bitte einen Titel angeben.',
            $kind === UnitKind::File && $unit->file_id === null && !$hasUpload          => 'Bitte eine Datei hochladen.',
            $kind === UnitKind::Link && TrainingController::safeUrl($post['url'] ?? null) === null => 'Bitte einen Link mit http:// oder https:// angeben.',
            $kind === UnitKind::KnowledgeArticle && (int) ($post['kb_entry_id'] ?? 0) <= 0     => 'Bitte einen Wissensartikel wählen.',
            $kind === UnitKind::Text && (!is_array($doc) || ($doc['type'] ?? null) !== 'doc') => 'Der Text ließ sich nicht lesen.',
            default                                                                      => null,
        };
        if ($error === null && $kind === UnitKind::File && $hasUpload) {
            try {
                $unit->file_id = $this->storeFile($upload, $unit)->id;
            } catch (DomainException $e) {
                $error = $e->getMessage();
            }
        }
        if ($error !== null) {
            Flash::error($error);

            return $this->unitForm($module, $unit, 422);
        }

        $previousFile  = $unit->getOriginal('file_id');
        $unit->url         = $kind === UnitKind::Link ? TrainingController::safeUrl($post['url'] ?? null) : null;
        $unit->kb_entry_id = $kind === UnitKind::KnowledgeArticle ? (int) $post['kb_entry_id'] : null;
        $unit->content     = $kind === UnitKind::Text ? ['doc' => $doc] : null;
        if ($kind !== UnitKind::File) {
            $unit->file_id = null;
        }
        $unit->save();
        if ($previousFile !== null && (int) $previousFile !== $unit->file_id) {
            $this->dropOrphans([(int) $previousFile]);
        }

        return self::back(self::ownerPath($module), null, 'Einheit „' . $unit->title . '“ gespeichert.');
    }

    /**
     * Eine neue Fassung ersetzt die Datei in allen Lehrgängen, die diese
     * Einheit nutzen. Teilt eine kopierte Lehrgangsart die Datei, bekommt
     * nur diese Einheit die neue Fassung.
     *
     * @param array<string, mixed> $upload
     */
    private function storeFile(array $upload, Unit $unit): TrainingFile
    {
        $current = $unit->file_id !== null ? TrainingFile::query()->find($unit->file_id) : null;
        if ($current === null) {
            return $this->files->store($upload, self::userId() ?: null);
        }
        $shared = Unit::query()->where('file_id', $current->id)->where('id', '!=', $unit->id ?? 0)->exists();
        if (!$shared) {
            return $this->files->store($upload, self::userId() ?: null, $current);
        }
        $file = $this->files->store($upload, self::userId() ?: null);
        $file->version = $current->version + 1;
        $file->save();

        return $file;
    }

    /** @param list<int|string> $fileIds */
    private function dropOrphans(array $fileIds): void
    {
        /** @var list<TrainingFile> $files */
        $files = TrainingFile::query()->whereIn('id', $fileIds ?: [0])->get()->all();
        foreach ($files as $file) {
            if (!Unit::query()->where('file_id', $file->id)->exists()) {
                $this->files->delete($file);
            }
        }
    }

    private function module(string $id): ?Module
    {
        $module = Module::query()->find((int) $id);

        return $module !== null && $this->mayEdit($module) ? $module : null;
    }

    /** @return array{0: Module, 1: Unit}|array{0: null, 1: null} */
    private function unit(string $id): array
    {
        $unit   = Unit::query()->with('file')->find((int) $id);
        $module = $unit !== null ? $this->module((string) $unit->module_id) : null;

        return $module !== null ? [$module, $unit] : [null, null];
    }

    private function mayEdit(Module $module): bool
    {
        if ($module->course_type_id !== null) {
            return TrainingPolicy::manage();
        }
        $course = Course::query()->find($module->course_id);

        return $course !== null && TrainingPolicy::lead($course);
    }

    private static function ownerPath(Module $module): string
    {
        return $module->course_type_id !== null
            ? 'training/types/' . $module->course_type_id . '#lehrplan'
            : 'training/manage/' . $module->course_id . '#unterlagen';
    }
}
