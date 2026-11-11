<?php

declare(strict_types=1);

namespace Plugin\Training\Controllers;

use App\Personnel\AccountLink;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use EmergencyForge\Training\UnitKind;
use Plugin\Training\Curriculum;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\Participation;
use Plugin\Training\Presentations;

final class ApiController extends BaseController
{
    /**
     * POST /api/training/units/{unit}/pages: {course, pages, total} aus dem
     * PDF-Betrachter. Ausbilder in der Vorschau melden nichts.
     */
    public function pages(Request $request, string $unit): Response
    {
        $body   = $request->json() ?? [];
        $course = Course::query()->with('type')->find((int) ($body['course'] ?? 0));
        $pid    = AccountLink::currentId();
        if ($course === null || $pid === null || !$course->state()->materialsOpen()) {
            return self::json(['error' => 'Nicht gefunden.'], 404);
        }
        $participation = Participation::query()->where('course_id', $course->id)->where('personnel_id', $pid)->first();
        $unitModel     = Curriculum::for($course)->unit((int) $unit);
        if ($participation === null || !$participation->canLearn() || $unitModel === null || $unitModel->file?->viewsAsPdf() !== true) {
            return self::json(['error' => 'Nicht gefunden.'], 404);
        }
        // Folien einer Präsentation sind nur offen, wenn die Regel der Einheit es sagt.
        if ($unitModel->type() === UnitKind::Presentation && !Presentations::slidesOpen($unitModel, new \DateTimeImmutable())) {
            return self::json(['error' => 'Nicht gefunden.'], 404);
        }

        $total = max(0, min(5000, (int) ($body['total'] ?? 0)));
        $pages = array_values(array_unique(array_filter(
            array_map('intval', is_array($body['pages'] ?? null) ? $body['pages'] : []),
            static fn (int $p): bool => $p >= 1 && ($total === 0 || $p <= $total),
        )));

        return self::json(Curriculum::recordPages($participation, $unitModel, array_slice($pages, 0, 5000), $total));
    }
}
