<?php

declare(strict_types=1);

namespace Plugin\Training\Completion;

use Closure;
use EmergencyForge\Training\Completion\Completion;
use EmergencyForge\Training\Completion\CompletionHandler;
use EmergencyForge\Training\Completion\StepResult;

/** Ein Schritt aus {@see Steps} als Handler für den Runner des Pakets. */
final class Step implements CompletionHandler
{
    /** @param Closure(Completion): StepResult $run */
    public function __construct(private readonly Closure $run)
    {
    }

    public function run(Completion $completion): StepResult
    {
        return ($this->run)($completion);
    }
}
