<?php

declare(strict_types=1);

/**
 * Ausbildung: Web-Routen.
 *
 * Die Middleware prüft nur das Recht. Ob jemand an einem bestimmten
 * Lehrgang lernen, ihn leiten oder betreuen darf, entscheiden die
 * Controller über TrainingPolicy; ein fremder Lehrgang ist dort ein 404.
 *
 * @var \EmergencyForge\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\PolicyMiddleware;
use Plugin\Training\Controllers\AiDraftController;
use Plugin\Training\Controllers\AttemptController;
use Plugin\Training\Controllers\CurriculumController;
use Plugin\Training\Controllers\ExamController;
use Plugin\Training\Controllers\ManageController;
use Plugin\Training\Controllers\PresentationController;
use Plugin\Training\Controllers\QuestionController;
use Plugin\Training\Controllers\TrainingController;
use Plugin\Training\Controllers\TypeController;

$view   = [new AuthMiddleware(), new PolicyMiddleware('training.view')];
$teach  = [new AuthMiddleware(), new PermissionMiddleware(['admin', 'training.teach', 'training.manage'])];
$manage = [new AuthMiddleware(), new PermissionMiddleware(['admin', 'training.manage'])];
$exams  = [new AuthMiddleware(), new PermissionMiddleware(['admin', 'training.exams'])];
$typeView = [new AuthMiddleware(), new PermissionMiddleware(['admin', 'training.manage', 'training.exams'])];

$router->get('/training',                                             [TrainingController::class, 'index'],    $view);
$router->get('/training/courses/{id:\d+}',                            [TrainingController::class, 'course'],   $view);
$router->post('/training/courses/{id:\d+}/enroll',                    [TrainingController::class, 'enroll'],   $view);
$router->post('/training/courses/{id:\d+}/withdraw',                  [TrainingController::class, 'withdraw'], $view);
$router->get('/training/courses/{course:\d+}/units/{unit:\d+}',       [TrainingController::class, 'unit'],     $view);
$router->post('/training/courses/{course:\d+}/units/{unit:\d+}/done', [TrainingController::class, 'done'],     $view);
$router->post('/training/courses/{course:\d+}/units/{unit:\d+}/open', [TrainingController::class, 'open'],     $view);
$router->get('/training/courses/{course:\d+}/files/{file:\d+}',       [TrainingController::class, 'file'],     $view);
$router->get('/training/courses/{course:\d+}/sessions/{session:\d+}/slides', [PresentationController::class, 'slides'], $view);

// Lehrgänge leiten und betreuen
$router->get('/training/manage',                                            [ManageController::class, 'index'],         $teach);
$router->get('/training/manage/create',                                     [ManageController::class, 'create'],        $manage);
$router->post('/training/manage',                                           [ManageController::class, 'store'],         $manage);
$router->get('/training/manage/{id:\d+}',                                   [ManageController::class, 'show'],          $teach);
$router->get('/training/manage/{id:\d+}/edit',                              [ManageController::class, 'edit'],          $teach);
$router->post('/training/manage/{id:\d+}',                                  [ManageController::class, 'update'],        $teach);
$router->post('/training/manage/{id:\d+}/status',                           [ManageController::class, 'status'],        $teach);
$router->post('/training/manage/{id:\d+}/participants',                     [ManageController::class, 'enroll'],        $teach);
$router->post('/training/manage/{id:\d+}/participants/{pid:\d+}/decide',    [ManageController::class, 'decide'],        $teach);
$router->post('/training/manage/{id:\d+}/participants/{pid:\d+}/abort',     [ManageController::class, 'abort'],         $teach);
$router->post('/training/manage/{id:\d+}/progress',                         [ManageController::class, 'progress'],      $teach);
$router->post('/training/manage/{id:\d+}/sessions',                         [ManageController::class, 'storeSession'],  $teach);
$router->post('/training/manage/{id:\d+}/sessions/{sid:\d+}/delete',        [ManageController::class, 'deleteSession'], $teach);
$router->post('/training/manage/{id:\d+}/sessions/{sid:\d+}/attendance',    [ManageController::class, 'attendance'],    $teach);
$router->post('/training/manage/{id:\d+}/sessions/{sid:\d+}/slides',        [ManageController::class, 'slides'],        $teach);
$router->post('/training/manage/{id:\d+}/modules',                          [CurriculumController::class, 'addCourseModule'], $teach);
$router->post('/training/manage/{id:\d+}/exams/{exam:\d+}/release',         [ExamController::class, 'release'],         $teach);
$router->post('/training/manage/{id:\d+}/exams/{exam:\d+}/revoke',          [ExamController::class, 'revoke'],          $teach);
$router->post('/training/manage/{id:\d+}/exams/{exam:\d+}/extra',           [ExamController::class, 'extra'],           $teach);
$router->post('/training/manage/{id:\d+}/exams/{exam:\d+}/sheet',           [ExamController::class, 'sheet'],           $teach);
$router->post('/training/manage/{id:\d+}/completions/{pid:\d+}/retry',      [ManageController::class, 'retry'],         $teach);
// Folgeaufgaben gehören oft Rollen ohne Ausbildungsrecht; wem sie gehört, prüft der Controller.
$router->post('/training/follow-ups/{id:\d+}/done',                         [ManageController::class, 'followUpDone'],  [new AuthMiddleware()]);

// Prüfung ablegen und bewerten: ob die Person das darf, prüft der Controller.
$router->get('/training/courses/{course:\d+}/exams/{exam:\d+}',             [AttemptController::class, 'start'],  $view);
$router->post('/training/courses/{course:\d+}/exams/{exam:\d+}/start',      [AttemptController::class, 'begin'],  $view);
$router->get('/training/attempts/{id:\d+}',                                 [AttemptController::class, 'show'],   $view);
$router->post('/training/attempts/{id:\d+}/submit',                         [AttemptController::class, 'submit'], $view);
$router->get('/training/attempts/{id:\d+}/images/{position:\d+}',           [AttemptController::class, 'image'],  $view);
$router->get('/training/attempts/{id:\d+}/review',                          [AttemptController::class, 'review'], $teach);
$router->post('/training/attempts/{id:\d+}/review',                         [AttemptController::class, 'grade'],  $teach);
$router->get('/training/attempts/{id:\d+}/sheet',                           [AttemptController::class, 'sheet'],  $teach);
$router->post('/training/attempts/{id:\d+}/sheet',                          [AttemptController::class, 'rate'],   $teach);
$router->post('/training/attempts/{id:\d+}/cosign',                         [AttemptController::class, 'cosign'], $teach);

// Lehrgangsarten
$router->get('/training/types',                        [TypeController::class, 'index'],      $typeView);
$router->get('/training/types/create',                 [TypeController::class, 'create'],     $manage);
$router->post('/training/types',                       [TypeController::class, 'store'],      $manage);
$router->post('/training/types/import',                [QuestionController::class, 'importType'], $exams);
$router->get('/training/types/{id:\d+}',               [TypeController::class, 'show'],       $typeView);
$router->get('/training/types/{id:\d+}/export',        [QuestionController::class, 'exportType'], $exams);
$router->post('/training/types/{id:\d+}',              [TypeController::class, 'update'],     $manage);
$router->post('/training/types/{id:\d+}/sequential',   [TypeController::class, 'sequential'], $manage);
$router->post('/training/types/{id:\d+}/completion',   [TypeController::class, 'completion'], $manage);
$router->post('/training/types/{id:\d+}/copy',         [TypeController::class, 'copy'],       $manage);
$router->post('/training/types/{id:\d+}/delete',       [TypeController::class, 'destroy'],    $manage);
$router->post('/training/types/{id:\d+}/modules',      [CurriculumController::class, 'addTypeModule'], $manage);

// Fragenpool und Prüfungsordnung (`training.exams`)
$router->post('/training/types/{id:\d+}/topics',             [QuestionController::class, 'storeTopic'],  $exams);
$router->post('/training/topics/{id:\d+}',                   [QuestionController::class, 'updateTopic'], $exams);
$router->post('/training/topics/{id:\d+}/delete',            [QuestionController::class, 'deleteTopic'], $exams);
$router->get('/training/types/{id:\d+}/questions/create',    [QuestionController::class, 'create'],      $exams);
$router->post('/training/types/{id:\d+}/questions',          [QuestionController::class, 'store'],       $exams);
$router->get('/training/types/{id:\d+}/questions/export',    [QuestionController::class, 'export'],      $exams);
$router->post('/training/types/{id:\d+}/questions/import',   [QuestionController::class, 'import'],      $exams);
$router->get('/training/types/{id:\d+}/questions/ai',        [AiDraftController::class, 'form'],         $exams);
$router->post('/training/types/{id:\d+}/questions/ai',       [AiDraftController::class, 'generate'],     $exams);
$router->get('/training/questions/{id:\d+}/edit',            [QuestionController::class, 'edit'],        $exams);
$router->post('/training/questions/{id:\d+}',                [QuestionController::class, 'update'],      $exams);
$router->post('/training/questions/{id:\d+}/delete',         [QuestionController::class, 'destroy'],     $exams);
$router->post('/training/questions/{id:\d+}/active',         [QuestionController::class, 'active'],      $exams);
$router->get('/training/questions/{id:\d+}/image',           [QuestionController::class, 'image'],       $exams);
$router->post('/training/types/{id:\d+}/exams',              [ExamController::class, 'store'],           $exams);
$router->post('/training/exams/{id:\d+}',                    [ExamController::class, 'update'],          $exams);
$router->post('/training/exams/{id:\d+}/delete',             [ExamController::class, 'destroy'],         $exams);
$router->post('/training/exams/{id:\d+}/move',               [ExamController::class, 'move'],            $exams);

// Lehrplan: Modul und Einheit prüft der Controller gegen ihren Besitzer.
$router->post('/training/modules/order',                   [CurriculumController::class, 'order'],        $teach);
$router->post('/training/modules/{id:\d+}',                [CurriculumController::class, 'renameModule'], $teach);
$router->post('/training/modules/{id:\d+}/delete',         [CurriculumController::class, 'deleteModule'], $teach);
$router->get('/training/modules/{id:\d+}/units/create',    [CurriculumController::class, 'createUnit'],   $teach);
$router->post('/training/modules/{id:\d+}/units',          [CurriculumController::class, 'storeUnit'],    $teach);
$router->get('/training/units/{id:\d+}/edit',              [CurriculumController::class, 'editUnit'],     $teach);
$router->post('/training/units/{id:\d+}',                  [CurriculumController::class, 'updateUnit'],   $teach);
$router->post('/training/units/{id:\d+}/delete',           [CurriculumController::class, 'deleteUnit'],   $teach);
$router->post('/training/units/{id:\d+}/required',         [CurriculumController::class, 'required'],     $teach);
$router->get('/training/files/{id:\d+}',                   [CurriculumController::class, 'file'],         $teach);
