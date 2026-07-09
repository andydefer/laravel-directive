<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Records\ExecutionStatsRecord;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelJsonl\Contexts\JsonlContext;
use AndyDefer\LaravelJsonl\JsonlService;
use AndyDefer\LaravelJsonl\Records\LogJsonlRecord;
use AndyDefer\LaravelJsonl\Strategies\TemporalPathStrategy;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

/**
 * Execution statistics logger using JSONL format.
 *
 * Logs execution statistics to a JSONL file for later analysis.
 * Each execution is written as a single JSON line using the TemporalPathStrategy.
 */
final class ExecutionStatsLogger
{
    private JsonlService $jsonlService;

    public function __construct(
        private readonly DirectiveConfigInterface $config,
        private readonly FileSystemInterface $fileSystem,
        JsonlService $jsonlService,
        private readonly Console $console,
    ) {
        $this->jsonlService = $jsonlService;
    }

    /**
     * Log execution statistics to the JSONL file.
     */
    public function log(ExecutionStatsRecord $record, ?MapCollection $context = null): void
    {
        try {
            $logRecord = $this->buildLogRecord($record, $context);
            $this->jsonlService->write($logRecord);
        } catch (\Throwable $e) {
            $this->console->alertWarning('⚠️ Unable to write execution log: '.$e->getMessage());
        }
    }

    /**
     * Build the LogJsonlRecord from execution stats.
     */
    private function buildLogRecord(ExecutionStatsRecord $record, ?MapCollection $context = null): LogJsonlRecord
    {
        $payload = [
            'command' => $record->command,
            'directive_class' => $record->directiveClass,
            'signature' => $record->signature,
            'exit_code' => $record->exitCode->value,
            'exit_code_label' => $record->exitCode->getLabel(),
            'success' => $record->exitCode->isSuccess(),
            'duration_seconds' => $record->duration,
            'memory_bytes' => $record->memoryUsage,
            'memory_human' => $this->formatMemory($record->memoryUsage),
            'peak_memory_bytes' => $record->peakMemoryUsage,
            'peak_memory_human' => $this->formatMemory($record->peakMemoryUsage),
            'calls_count' => $record->callsCount,
        ];

        if ($record->error !== null) {
            $payload['error'] = $record->error;
        }

        if ($context !== null && ! $context->isEmpty()) {
            $payload['context'] = $context->toArray();
        }

        return new LogJsonlRecord(
            time: new DateTimeVO,
            level: $record->exitCode->isSuccess() ? 'info' : 'error',
            type: 'directive_execution',
            payload: new StrictDataObject($payload),
        );
    }

    /**
     * Set a custom base path for logs.
     */
    public function setBasePath(string $path): self
    {
        $strategy = new TemporalPathStrategy($path);
        $this->jsonlService = new JsonlService(
            $strategy,
            $this->fileSystem,
            new JsonlContext
        );

        return $this;
    }

    /**
     * Get the current base path.
     */
    public function getBasePath(): string
    {
        return $this->jsonlService->getBaseDirectory();
    }

    /**
     * Get the JSONL service instance.
     */
    public function getJsonlService(): JsonlService
    {
        return $this->jsonlService;
    }

    /**
     * Get statistics summary from logs.
     */
    public function getSummary(): array
    {
        $logs = $this->readAll();

        if (empty($logs)) {
            return [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'success_rate' => 0,
                'avg_duration' => 0.0,
                'avg_memory' => 0.0,
                'total_calls' => 0,
                'avg_calls' => 0.0,
            ];
        }

        $total = count($logs);
        $success = 0;
        $failed = 0;
        $totalDuration = 0;
        $totalMemory = 0;
        $totalCalls = 0;

        foreach ($logs as $log) {
            if ($log['payload']['success'] ?? false) {
                $success++;
            } else {
                $failed++;
            }
            $totalDuration += $log['payload']['duration_seconds'] ?? 0;
            $totalMemory += $log['payload']['memory_bytes'] ?? 0;
            $totalCalls += $log['payload']['calls_count'] ?? 0;
        }

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
            'avg_duration' => $total > 0 ? $totalDuration / $total : 0.0,
            'avg_memory' => $total > 0 ? $totalMemory / $total : 0.0,
            'total_calls' => $totalCalls,
            'avg_calls' => $total > 0 ? round($totalCalls / $total, 2) : 0.0,
        ];
    }

    /**
     * Read all logs from the current date.
     */
    private function readAll(): array
    {
        $today = date('Y-m-d');
        $filePath = $this->getBasePath().'/'.$today.'/'.date('H').'.jsonl';

        if (! $this->jsonlService->fileExists($filePath)) {
            return [];
        }

        return $this->jsonlService->readAll($filePath);
    }

    /**
     * Format memory in a human-readable format.
     */
    private function formatMemory(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return number_format($bytes / 1048576, 2).' MB';
    }
}
