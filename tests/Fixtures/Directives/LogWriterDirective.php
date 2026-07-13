<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\ExecutionStatsRecord;
use AndyDefer\Directive\Services\ExecutionStatsLogger;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Fixture directive that writes logs for testing the clean logs functionality.
 *
 * This directive creates log entries in the execution stats logger to simulate
 * real log files that can be cleaned up by the CleanLogsDirective.
 */
final class LogWriterDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'log:write {message=Test^log^message} {count=1}';
    }

    public function getDescription(): string
    {
        return 'Write test log entries for testing the clean logs functionality';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['lw']);
    }

    protected function execute(): ExitCode
    {
        $application = $this->getApplication();
        /** @var ExecutionStatsLogger $logger */
        $logger = $application->make(ExecutionStatsLogger::class);

        $message = $this->getArgument('message') ?? 'Test log message';
        $count = (int) ($this->getArgument('count') ?? 1);

        $this->info("Writing {$count} log entry(s) with message: {$message}");

        for ($i = 0; $i < $count; $i++) {
            $logger->log(ExecutionStatsRecord::from([
                'command' => 'test:command',
                'directiveClass' => 'App\\Directives\\TestDirective',
                'signature' => 'test:command {name}',
                'exitCode' => ExitCode::CONFLICT,
                'duration' => 0.123,
                'memoryUsage' => 1024,
                'peakMemoryUsage' => 2048,
                'callsCount' => 2,
                'error' => 'Une erreur de test',
            ]));
        }

        $this->getConsole()->success("✅ {$count} log entry(s) written successfully");

        return ExitCode::SUCCESS;
    }
}
