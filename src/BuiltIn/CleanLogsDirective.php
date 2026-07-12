<?php

declare(strict_types=1);

namespace AndyDefer\Directive\BuiltIn;

use AndyDefer\ConsoleWriter\Console\Components\KeyValue;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\ExecutionStatsLogger;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use Carbon\Carbon;

/**
 * Built-in directive that cleans old execution log files.
 *
 * Removes log files that exceed the configured retention period.
 * Supports dry-run mode for previewing deletions and verbose output
 * for detailed information about which files would be affected.
 */
final class CleanLogsDirective extends AbstractDirective
{
    private const INDENTATION_LEVEL = 2;

    public function getSignature(): string
    {
        return 'clean:directive-logs {days=30} {--dry-run} {--verbose}';
    }

    public function getDescription(): string
    {
        return 'Remove old execution log files that exceed the retention period ';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['log-directive-clean', 'ldc']);
    }

    protected function execute(): ExitCode
    {
        $container = $this->getContainer();
        $logger = $container->make(ExecutionStatsLogger::class);
        $fileSystem = $container->make(FileSystemInterface::class);

        $daysRaw = $this->getArgument('days');

        if ($daysRaw !== null && ! is_numeric($daysRaw)) {
            $this->error('Days must be a valid number');

            return ExitCode::INVALID_ARGUMENT;
        }

        $days = (int) ($daysRaw ?? 30);
        $dryRun = $this->getFlag('dry-run');
        $verbose = $this->getFlag('verbose');

        if ($days < 1) {
            $this->error('Days must be at least 1');

            return ExitCode::INVALID_ARGUMENT;
        }

        $basePath = $logger->getBasePath();
        $cutoffDateTime = Carbon::now()->subDays($days)->format('Y-m-d');

        $filesToDelete = $this->getFilesToDelete($fileSystem, $basePath, $cutoffDateTime);

        $count = count($filesToDelete);

        if ($verbose) {
            $this->displayStatistics($filesToDelete, $count, $days);
        }

        if ($count === 0) {
            $this->info('No log files to delete.');

            return ExitCode::SUCCESS;
        }

        if ($dryRun) {
            return $this->handleDryRun($filesToDelete, $count);
        }

        return $this->handleDeletion($fileSystem, $filesToDelete, $count);
    }

    /**
     * Get files to delete based on cutoff date.
     *
     * @return array<int, string>
     */
    private function getFilesToDelete(FileSystemInterface $fileSystem, string $basePath, string $cutoffDateTime): array
    {
        $filesToDelete = [];

        if (! $fileSystem->isDirectory($basePath)) {
            return $filesToDelete;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile() || $file->getExtension() !== 'jsonl') {
                continue;
            }

            $fileModifiedTime = Carbon::createFromTimestamp($file->getMTime())->format('Y-m-d');

            if ($fileModifiedTime < $cutoffDateTime) {
                $filesToDelete[] = $file->getPathname();
            }
        }

        return $filesToDelete;
    }

    /**
     * Display statistics about the files to delete using KeyValue.
     *
     * @param  array<int, string>  $filesToDelete
     */
    private function displayStatistics(array $filesToDelete, int $count, int $days): void
    {
        $totalSize = 0;
        $dates = [];

        foreach ($filesToDelete as $file) {
            $size = filesize($file);
            $totalSize += $size;
            $dirName = dirname($file);
            $date = basename($dirName);
            $dates[] = $date;
        }

        $totalSizeMb = round($totalSize / 1024 / 1024, 2);
        $oldestDate = ! empty($dates) ? min($dates) : 'N/A';
        $newestDate = ! empty($dates) ? max($dates) : 'N/A';

        $data = MapCollection::from([
            'Retention' => $days.' days',
            'Files to delete' => $count,
            'Total size' => $totalSizeMb.' MB',
            'Date range' => $oldestDate.' to '.$newestDate,
            'Base path' => dirname($filesToDelete[0] ?? ''),
        ]);

        $this->newLine();
        $this->info('📊 Log Statistics:');
        $this->getConsole()->raw(KeyValue::render($data, self::INDENTATION_LEVEL));
        $this->newLine();
    }

    /**
     * Handle dry-run mode.
     *
     * @param  array<int, string>  $filesToDelete
     */
    private function handleDryRun(array $filesToDelete, int $count): ExitCode
    {
        $this->getConsole()->alertWarning('🔍 DRY RUN MODE - No files will be deleted');
        $this->newLine();

        if ($this->getFlag('verbose')) {
            $this->line('Files that would be deleted:');
            foreach ($filesToDelete as $file) {
                $sizeKb = round(filesize($file) / 1024, 2);
                $fileName = basename($file);
                $date = basename(dirname($file));
                $this->line("  - {$date}/{$fileName} ({$sizeKb} KB)");
            }
            $this->newLine();
        }

        $this->info("ℹ️  Would delete {$count} file(s)");

        return ExitCode::SUCCESS;
    }

    /**
     * Handle actual deletion.
     *
     * @param  array<int, string>  $filesToDelete
     */
    private function handleDeletion(FileSystemInterface $fileSystem, array $filesToDelete, int $count): ExitCode
    {
        $deleted = 0;
        $errors = 0;

        $this->info('Deleting log files...');

        foreach ($filesToDelete as $file) {
            try {
                if ($fileSystem->delete($file)) {
                    $deleted++;
                    if ($this->getFlag('verbose')) {
                        $this->line('  ✓ Deleted: '.basename($file));
                    }
                } else {
                    $errors++;
                }
            } catch (\Throwable $e) {
                $errors++;
                if ($this->getFlag('verbose')) {
                    $this->error('  ✗ Failed to delete: '.basename($file).' - '.$e->getMessage());
                }
            }
        }

        // Clean empty directories
        $basePath = dirname($filesToDelete[0] ?? '');

        while ($basePath && $basePath !== dirname($basePath)) {
            if ($fileSystem->isDirectory($basePath) && count(scandir($basePath)) === 2) {
                $fileSystem->delete($basePath);
            } else {
                break;
            }
            $basePath = dirname($basePath);
        }

        $this->newLine();
        if ($errors === 0) {
            $this->info("✅ Successfully deleted {$deleted} file(s)");
        } else {
            $this->getConsole()->alertWarning("⚠️  Deleted {$deleted} file(s), {$errors} error(s)");
        }

        return $errors === 0 ? ExitCode::SUCCESS : ExitCode::FAILURE;
    }
}
