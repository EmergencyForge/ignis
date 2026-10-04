<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\ErrorHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Die Fehlerseite für Nutzer wird erst ausgegeben, wenn sie ganz gerendert
 * ist. Wirft die Vorlage selbst, bleibt nichts Halbes stehen und der
 * ErrorHandler nimmt den nächsten Rückfall.
 */
final class ErrorHandlerTemplateTest extends TestCase
{
    private string $template = '';

    protected function tearDown(): void
    {
        if ($this->template !== '' && is_file($this->template)) {
            unlink($this->template);
        }

        parent::tearDown();
    }

    #[Test]
    public function eine_werfende_vorlage_gibt_nichts_aus_und_meldet_den_rueckfall(): void
    {
        $this->template = tempnam(sys_get_temp_dir(), 'ignis-err') . '.php';
        file_put_contents($this->template, '<p>halb</p><?php throw new \RuntimeException("kaputt");');

        $level = ob_get_level();
        ob_start();
        $rendered = $this->render($this->template, []);
        $output = (string) ob_get_clean();

        $this->assertFalse($rendered);
        $this->assertSame('', $output);
        $this->assertSame($level, ob_get_level());
    }

    #[Test]
    public function eine_vorlage_bekommt_ihre_variablen(): void
    {
        $this->template = tempnam(sys_get_temp_dir(), 'ignis-err') . '.php';
        file_put_contents($this->template, '<?= $errorId ?>');

        ob_start();
        $rendered = $this->render($this->template, ['errorId' => 'B6A924A5']);
        $output = (string) ob_get_clean();

        $this->assertTrue($rendered);
        $this->assertSame('B6A924A5', $output);
    }

    /** @param array<string, mixed> $vars */
    private function render(string $template, array $vars): bool
    {
        $method = new ReflectionMethod(ErrorHandler::class, 'renderTemplate');

        return (bool) $method->invoke(null, $template, $vars);
    }
}
