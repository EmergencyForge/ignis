<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Settings;

use App\Http\Controllers\Settings\DocumentController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(DocumentController::class);
        $this->assertInstanceOf(DocumentController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(DocumentController::class);
        foreach (['categories', 'templates', 'visualEditor'] as $method) {
            $this->assertTrue($reflection->hasMethod($method), "DocumentController::$method() fehlt");
        }
    }
}
