<?php

declare(strict_types=1);

/**
 * Wissensdatenbank — Web-Routen.
 *
 * Auth: AuthMiddleware mit `KB_PUBLIC_ACCESS`-Flag-Inversion. Wenn das
 * Flag true ist, ist das Lexikon public lesbar; sonst Login-Pflicht.
 * Edit-/Manage-Permissions werden im Controller via Permissions::check()
 * pro Aktion geprüft.
 *
 * @var \App\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use Plugin\KnowledgeBase\Controllers\LexiconController;

$lexiconAuth = [new AuthMiddleware('KB_PUBLIC_ACCESS', invert: true)];

$router->get( '/lexicon',                 [LexiconController::class, 'index'],          $lexiconAuth);
$router->get( '/lexicon/',                [LexiconController::class, 'index'],          $lexiconAuth);
$router->get( '/lexicon/index',           [LexiconController::class, 'index'],          $lexiconAuth);
$router->get( '/lexicon/view',            [LexiconController::class, 'view'],           $lexiconAuth);
$router->match(['GET', 'POST'], '/lexicon/create', [LexiconController::class, 'create'], $lexiconAuth);
$router->match(['GET', 'POST'], '/lexicon/edit',   [LexiconController::class, 'edit'],   $lexiconAuth);
$router->post('/lexicon/archive',         [LexiconController::class, 'archive'],        $lexiconAuth);
$router->post('/lexicon/pin',             [LexiconController::class, 'pin'],            $lexiconAuth);
$router->post('/lexicon/toggle-editor',   [LexiconController::class, 'toggleEditor'],   $lexiconAuth);
$router->get( '/lexicon/manage-taxonomy', [LexiconController::class, 'manageTaxonomy'], $lexiconAuth);
