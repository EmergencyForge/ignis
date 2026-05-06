<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\LogReader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogReaderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/intrarp-logreader-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    private function writeLog(string $name, string $content): void
    {
        file_put_contents($this->tmpDir . '/' . $name, $content);
    }

    #[Test]
    public function list_files_returns_only_log_files_sorted_by_mtime(): void
    {
        $this->writeLog('error-2026-04-01.log', "[2026-04-01 12:00:00] intrarp.ERROR: a {}\n");
        $this->writeLog('app-2026-04-02.log', "[2026-04-02 12:00:00] intrarp.INFO: b {}\n");
        $this->writeLog('readme.txt', "ignored\n");

        // mtime auf unterschiedliche Werte setzen
        touch($this->tmpDir . '/error-2026-04-01.log', 1700000000);
        touch($this->tmpDir . '/app-2026-04-02.log', 1700001000);

        $reader = new LogReader($this->tmpDir);
        $files = $reader->listFiles();

        $this->assertCount(2, $files);
        $this->assertSame('app-2026-04-02.log', $files[0]['name']); // neueste zuerst
        $this->assertSame('error-2026-04-01.log', $files[1]['name']);
        $this->assertSame('error', $files[1]['type']);
        $this->assertSame('app', $files[0]['type']);
    }

    #[Test]
    public function find_by_error_id_extracts_full_context(): void
    {
        $line = '[2026-04-04 06:18:00] intrarp.CRITICAL: Uncaught Exception [0B29305D]: '
            . 'SQLSTATE[22007]: Invalid datetime format '
            . '{"error_id":"0B29305D","exception":"PDOException","file":"F:\\\\GitKraken\\\\fahrtenbuch\\\\actions.php","line":102,"code":"22007","trace":"#0 stack-trace-content"}'
            . "\n";
        $this->writeLog('error-2026-04-04.log', $line);

        $reader = new LogReader($this->tmpDir);
        $entry = $reader->findByErrorId('0B29305D');

        $this->assertNotNull($entry);
        $this->assertSame('0B29305D', $entry['error_id']);
        $this->assertSame('CRITICAL', $entry['level']);
        $this->assertSame('PDOException', $entry['exception']);
        $this->assertSame(102, $entry['line']);
        $this->assertSame('actions.php', $entry['file']);
        $this->assertStringContainsString('stack-trace-content', $entry['trace']);
        $this->assertSame('error-2026-04-04.log', $entry['source_file']);
    }

    #[Test]
    public function find_by_error_id_returns_null_for_unknown(): void
    {
        $this->writeLog('error-2026-04-04.log', "[2026-04-04 06:18:00] intrarp.ERROR: nothing here\n");
        $reader = new LogReader($this->tmpDir);
        $this->assertNull($reader->findByErrorId('DEADBEEF'));
    }

    #[Test]
    public function find_by_error_id_validates_format(): void
    {
        $this->writeLog('error-2026-04-04.log', "ignored\n");
        $reader = new LogReader($this->tmpDir);
        $this->assertNull($reader->findByErrorId('NOT-HEX'));
        $this->assertNull($reader->findByErrorId('SHORT'));
        $this->assertNull($reader->findByErrorId(''));
    }

    #[Test]
    public function search_filters_by_query_and_level(): void
    {
        $content = "[2026-04-01 10:00:00] intrarp.ERROR: First error message {}\n"
                 . "[2026-04-01 10:01:00] intrarp.WARNING: A warning {}\n"
                 . "[2026-04-01 10:02:00] intrarp.ERROR: Second error with keyword {}\n";
        $this->writeLog('error-2026-04-01.log', $content);

        $reader = new LogReader($this->tmpDir);

        $byKeyword = $reader->search('keyword', 50);
        $this->assertCount(1, $byKeyword);
        $this->assertStringContainsString('Second error', $byKeyword[0]['message']);

        $byLevel = $reader->search('', 50, ['level' => 'WARNING']);
        $this->assertCount(1, $byLevel);
        $this->assertSame('WARNING', $byLevel[0]['level']);
    }

    #[Test]
    public function tail_returns_last_n_entries_newest_first(): void
    {
        $content = '';
        for ($i = 1; $i <= 10; $i++) {
            $minute = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $content .= "[2026-04-01 10:{$minute}:00] intrarp.INFO: msg {$i} {}\n";
        }
        $this->writeLog('app-2026-04-01.log', $content);

        $reader = new LogReader($this->tmpDir);
        $entries = $reader->tail('app-2026-04-01.log', 3);

        $this->assertCount(3, $entries);
        $this->assertStringContainsString('msg 10', $entries[0]['message']);
        $this->assertStringContainsString('msg 9', $entries[1]['message']);
        $this->assertStringContainsString('msg 8', $entries[2]['message']);
    }

    #[Test]
    public function multiline_trace_is_attached_to_entry(): void
    {
        $line = "[2026-04-04 06:18:00] intrarp.CRITICAL: Boom [AAAA1111]: msg "
            . '{"error_id":"AAAA1111","exception":"Error","file":"x.php","line":1,"trace":"#0 line1"}' . "\n"
            . "#1 line2 (continuation)\n"
            . "#2 line3 (continuation)\n"
            . "[2026-04-04 06:19:00] intrarp.INFO: next entry {}\n";
        $this->writeLog('error-2026-04-04.log', $line);

        $reader = new LogReader($this->tmpDir);
        $entry = $reader->findByErrorId('AAAA1111');

        $this->assertNotNull($entry);
        $this->assertStringContainsString('line2 (continuation)', $entry['raw']);
        $this->assertStringContainsString('line3 (continuation)', $entry['raw']);
    }
}
