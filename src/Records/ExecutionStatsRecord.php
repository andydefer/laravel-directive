<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Execution statistics record.
 *
 * Contains all metrics collected during a directive execution.
 */
final class ExecutionStatsRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $command,
        public readonly string $directiveClass,
        public readonly string $signature,
        public readonly ExitCode $exitCode,
        public readonly float $duration,
        public readonly int $memoryUsage,
        public readonly int $peakMemoryUsage,
        public readonly int $callsCount,
        public readonly ?string $error = null,
    ) {}
}
