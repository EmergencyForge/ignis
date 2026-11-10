<?php

declare(strict_types=1);

namespace Plugin\Training\Controllers;

use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Plugin\Training\Models\Session;
use Plugin\Training\Policies\TrainingPolicy;

/**
 * Vortrag an einem Termin unter /training/courses/{id}/sessions/{sid}.
 */
final class PresentationController extends BaseController
{
    /** GET /training/courses/{course}/sessions/{session}/slides */
    public function slides(Request $request, string $course, string $session): Response
    {
        $courseModel = self::findCourse($course);
        $file        = $courseModel !== null
            ? Session::query()->where('course_id', $courseModel->id)->with('slides')->find((int) $session)?->slides
            : null;
        // Die Route lässt jeden mit Ausbildungssicht herein; Folien sind Lehrgangsmaterial.
        if ($courseModel === null || !TrainingPolicy::follow($courseModel, self::ownParticipation($courseModel)) || $file === null || !$file->viewsAsPdf()) {
            return self::notFound();
        }

        return self::deliver($file, false);
    }
}
