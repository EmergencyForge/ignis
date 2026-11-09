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
use Plugin\Training\Controllers\CurriculumController;
use Plugin\Training\Controllers\ManageController;
use Plugin\Training\Controllers\TrainingController;
use Plugin\Training\Controllers\TypeController;

$view   = [new AuthMiddleware(), new PolicyMiddleware('training.view')];
$teach  = [new AuthMiddleware(), new PermissionMiddleware(['admin', 'training.teach', 'training.manage'])];
$manage = [new AuthMiddleware(), new PermissionMiddleware(['admin', 'training.manage'])];

$router->get('/training',                                             [TrainingController::class, 'index'],    $view);
$router->get('/training/courses/{id:\d+}',                            [TrainingController::class, 'course'],   $view);
$router->post('/training/courses/{id:\d+}/enroll',                    [TrainingController::class, 'enroll'],   $view);
$router->post('/training/courses/{id:\d+}/withdraw',                  [TrainingController::class, 'withdraw'], $view);
$router->get('/training/courses/{course:\d+}/units/{unit:\d+}',       [TrainingController::class, 'unit'],     $view);
$router->post('/training/courses/{course:\d+}/units/{unit:\d+}/done', [TrainingController::class, 'done'],     $view);
$router->post('/training/courses/{course:\d+}/units/{unit:\d+}/open', [TrainingController::class, 'open'],     $view);
$router->get('/training/courses/{course:\d+}/files/{file:\d+}',       [TrainingController::class, 'file'],     $view);
$router->get('/training/images/{id:\d+}',                             [TrainingController::class, 'image'],    $view);

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
$router->post('/training/manage/{id:\d+}/modules',                          [CurriculumController::class, 'addCourseModule'], $teach);

// Lehrgangsarten
$router->get('/training/types',                        [TypeController::class, 'index'],      $manage);
$router->get('/training/types/create',                 [TypeController::class, 'create'],     $manage);
$router->post('/training/types',                       [TypeController::class, 'store'],      $manage);
$router->get('/training/types/{id:\d+}',               [TypeController::class, 'show'],       $manage);
$router->post('/training/types/{id:\d+}',              [TypeController::class, 'update'],     $manage);
$router->post('/training/types/{id:\d+}/sequential',   [TypeController::class, 'sequential'], $manage);
$router->post('/training/types/{id:\d+}/copy',         [TypeController::class, 'copy'],       $manage);
$router->post('/training/types/{id:\d+}/delete',       [TypeController::class, 'destroy'],    $manage);
$router->post('/training/types/{id:\d+}/modules',      [CurriculumController::class, 'addTypeModule'], $manage);

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
