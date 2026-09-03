<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Cron\CronScheduler;
use App\Cron\JobHandler\ConsoleHandler;
use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Models\CronJob;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Admin-UI für das hauseigene Cron-System.
 *
 * Listet alle registrierten Jobs, erlaubt Pause/Activate, manuelle
 * Ausführung (Run Now) und zeigt die History der letzten Läufe pro Job.
 * Built-in-Jobs (is_builtin=1) sind vor Löschung geschützt, können aber
 * pausiert/aktiviert werden.
 */
final class CronController extends Controller
{
    public function __construct(
        private readonly CronScheduler $scheduler,
        private readonly ConsoleHandler $consoleHandler,
    ) {}

    public function index(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        $jobs = Capsule::table('intra_cron_jobs')
            ->select([
                'id', 'identifier', 'name', 'description', 'handler_type', 'handler',
                'schedule', 'active', 'is_builtin', 'last_status', 'last_run_at',
                'last_duration_ms', 'next_run_at', 'fail_count',
            ])
            ->orderByDesc('is_builtin')
            ->orderBy('identifier')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        foreach ($jobs as &$job) {
            $job['handler_available'] = $job['handler_type'] !== 'console'
                || $this->consoleHandler->isAvailable((string) $job['handler']);
        }
        unset($job);

        $token = defined('CRON_ENDPOINT_TOKEN') ? (string) CRON_ENDPOINT_TOKEN : '';

        $this->renderView('settings/system/cron', [
            'jobs'              => $jobs,
            'cronEndpointToken' => $token,
        ]);
    }

    public function history(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        $jobId = (int) ($_GET['id'] ?? 0);
        if ($jobId <= 0) {
            $this->jsonError('Kein Job angegeben', 400);
        }

        $runs = Capsule::table('intra_cron_runs')
            ->select(['id', 'started_at', 'finished_at', 'status', 'duration_ms', 'output'])
            ->where('job_id', $jobId)
            ->orderByDesc('started_at')
            ->limit(25)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'runs' => $runs]);
        exit;
    }

    public function toggle(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        $jobId = (int) ($_POST['id'] ?? 0);
        if ($jobId <= 0) {
            Flash::set('error', 'no-job');
            $this->redirect('settings/system/cron');
        }

        CronJob::query()
            ->where('id', $jobId)
            ->update([
                'active'     => Capsule::raw('1 - active'),
                'fail_count' => 0,
            ]);

        Flash::set('success', 'saved');
        $this->redirect('settings/system/cron');
    }

    public function runNow(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        $jobId = (int) ($_POST['id'] ?? 0);
        if ($jobId <= 0) {
            $this->jsonError('Kein Job angegeben', 400);
        }

        $result = $this->scheduler->runJobById($jobId);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result);
        exit;
    }

    public function delete(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        $jobId = (int) ($_POST['id'] ?? 0);
        if ($jobId <= 0) {
            Flash::set('error', 'no-job');
            $this->redirect('settings/system/cron');
        }

        $deleted = CronJob::query()
            ->where('id', $jobId)
            ->where('is_builtin', 0)
            ->delete();

        if ($deleted === 1) {
            Flash::set('success', 'deleted');
        } else {
            Flash::set('error', 'builtin-protected');
        }
        $this->redirect('settings/system/cron');
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        $identifier  = trim((string) ($_POST['identifier'] ?? ''));
        $name        = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $handlerType = (string) ($_POST['handler_type'] ?? 'webhook');
        $handler     = trim((string) ($_POST['handler'] ?? ''));
        $schedule    = trim((string) ($_POST['schedule'] ?? ''));
        $configJson  = trim((string) ($_POST['config'] ?? ''));

        if ($identifier === '' || $name === '' || $handler === '' || $schedule === '') {
            Flash::set('error', 'fields-missing');
            $this->redirect('settings/system/cron');
        }
        if (!in_array($handlerType, ['console', 'webhook', 'job'], true)) {
            Flash::set('error', 'invalid-handler-type');
            $this->redirect('settings/system/cron');
        }

        try {
            new \Cron\CronExpression($schedule);
        } catch (\Throwable $e) {
            Flash::set('error', 'invalid-schedule');
            $this->redirect('settings/system/cron');
        }

        if ($configJson !== '') {
            $decoded = json_decode($configJson, true);
            if (!is_array($decoded)) {
                Flash::set('error', 'invalid-config-json');
                $this->redirect('settings/system/cron');
            }
        } else {
            $configJson = '{}';
        }

        try {
            CronJob::create([
                'identifier'   => $identifier,
                'name'         => $name,
                'description'  => $description,
                'handler_type' => $handlerType,
                'handler'      => $handler,
                'schedule'     => $schedule,
                'config'       => $configJson,
                'active'       => 1,
                'is_builtin'   => 0,
                'next_run_at'  => Capsule::raw('UTC_TIMESTAMP()'),
            ]);
            Flash::set('success', 'created');
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                Flash::set('error', 'duplicate-identifier');
            } else {
                Flash::set('error', 'db-error');
            }
        }

        $this->redirect('settings/system/cron');
    }

    private function ensureAdmin(): void
    {
        if (!\App\Auth\Gate::allows('system.admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }
    }

    private function jsonError(string $message, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message]);
        exit;
    }
}
