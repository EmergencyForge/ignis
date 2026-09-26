<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Leerzustände gibt es nur noch über templates/partials/empty.php. Die alten
 * Varianten sahen jede anders aus und hießen jedes Mal anders.
 */
final class EmptyStateUsageTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';
    private const OLD = ['twplus-empty', 'empty-state', 'logs-empty', 'ignis-table-empty'];

    public function testNoTemplateUsesAnOldEmptyStateClass(): void
    {
        $hits = [];
        foreach (['templates', 'assets/components', 'assets/js', 'plugins', 'index.php', 'login.php'] as $path) {
            $files = is_file(self::ROOT . '/' . $path) ? [self::ROOT . '/' . $path] : $this->sourceFiles(self::ROOT . '/' . $path);
            foreach ($files as $file) {
                if (str_contains($file, 'enotf')) {
                    continue;
                }
                $code = (string) file_get_contents($file);
                foreach (self::OLD as $class) {
                    if (preg_match('/[\s"\']' . preg_quote($class, '/') . '(?![\w-])/', $code)) {
                        $hits[] = str_replace(self::ROOT . '/', '', $file) . ': ' . $class;
                    }
                }
            }
        }
        self::assertSame([], $hits);
    }

    /**
     * Leerzustände, die ein Live-Filter ein- und ausblendet, sitzen in einer
     * dauerhaften Live-Region; sonst hört ein Screenreader nichts davon.
     */
    public function testLiveFilterEmptyStatesAreAnnounced(): void
    {
        $toggled = [
            'templates/logbook/index.php' => ['id="fbNoResults"'],
            'templates/settings/vehicles/defects/index.php' => ['id="defectNoResults"'],
            'templates/settings/vehicles/vehload/index.php' => ['id="no-results-message"', 'data-beladung-empty'],
        ];
        foreach ($toggled as $file => $markers) {
            $code = (string) file_get_contents(self::ROOT . '/' . $file);
            foreach ($markers as $marker) {
                self::assertMatchesRegularExpression('/<div aria-live="polite">\s*<div ' . preg_quote($marker, '/') . '/', $code, $file . ': ' . $marker);
            }
        }

        $logs = (string) file_get_contents(self::ROOT . '/templates/settings/system/logs.php');
        $js = (string) file_get_contents(self::ROOT . '/assets/js/modules/logs-app.js');
        foreach (['inboxStatus', 'failedJobsStatus'] as $id) {
            self::assertStringContainsString('<p id="' . $id . '" class="sr-only" role="status"></p>', $logs);
            self::assertStringContainsString("getElementById('" . $id . "')", $js);
        }
    }

    /** @return list<string> */
    private function sourceFiles(string $dir): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (in_array($file->getExtension(), ['php', 'js'], true)) {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }
        return $files;
    }
}
