<?php

declare(strict_types=1);

namespace App\Cron\JobHandler;

use App\Cron\JobResult;

/**
 * Kontrakt für alle Cron-Handler-Typen (Console-Command, Queue-Dispatch,
 * Webhook). Der Scheduler bekommt pro Job einen Handler übergeben und
 * delegiert die eigentliche Ausführung daran.
 */
interface JobHandlerInterface
{
    /**
     * @param string              $handler  Handler-Identifier (z.B. Command-Name, Job-Klassen-FQCN, Webhook-URL)
     * @param array<string,mixed> $config   Handler-spezifische Konfiguration aus intra_cron_jobs.config
     * @param int                 $timeoutSeconds Harte Grenze für den einzelnen Job-Lauf
     */
    public function run(string $handler, array $config, int $timeoutSeconds): JobResult;
}
