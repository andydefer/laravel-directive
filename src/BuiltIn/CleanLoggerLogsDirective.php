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
use Illuminate\Support\Facades\Config;

/**
 * Built-in directive that cleans old Laravel Logger log files.
 *
 * Removes log files that exceed the configured retention period.
 * Supports dry-run mode for previewing deletions and verbose output
 * for detailed information about which files would be affected.
 */
final class CleanLoggerLogsDirective extends AbstractDirective
{
    private const INDENTATION_LEVEL = 2;

    public function getSignature(): string
    {
        return 'clean:logger-logs 
                {days=0}#"Number of days to retain (NULL = delete all with confirmation)" 
                {--dry-run}#"Preview deletions without actually deleting" 
                {--verbose}#"Show detailed information about each file" 
                {--force}#"Skip confirmation when deleting all files"';
    }

    public function getDescription(): string
    {
        return 'Remove old Laravel Logger log files that exceed the retention period';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['logger-clean', 'lc']);
    }

    protected function execute(): ExitCode
    {
        $container = $this->getApplication();
        $logger = $container->make(ExecutionStatsLogger::class);
        $fileSystem = $container->make(FileSystemInterface::class);

        $daysRaw = $this->getArgument('days') ?? Config::get('logger.retention_days', 30);
        $dryRun = $this->getFlag('dry-run');
        $verbose = $this->getFlag('verbose');
        $force = $this->getFlag('force');

        // ✅ La SEULE différence : le path vient de la config du package logger
        $basePath = Config::get('logger.path', storage_path('logs/structured'));

        // Si days est null, on supprime TOUT
        $deleteAll = $daysRaw === null || $daysRaw === '';

        // Si days est fourni mais non numérique
        if ($daysRaw !== null && $daysRaw !== '' && ! is_numeric($daysRaw)) {
            $this->error('Days must be a valid number');

            return ExitCode::INVALID_ARGUMENT;
        }

        $days = $deleteAll ? 0 : (int) $daysRaw;

        if ($days < 0) {
            $this->error('Days cannot be negative');

            return ExitCode::INVALID_ARGUMENT;
        }

        // Vérifier si le dossier existe avant d'afficher les stats
        if (! $fileSystem->isDirectory($basePath)) {
            $this->info('No log files to delete.');

            return ExitCode::SUCCESS;
        }

        // ✅ Si days = 0, on supprime TOUT en utilisant une date future
        $cutoffDateTime = $days === 0
            ? Carbon::now()->addDay()->format('Y-m-d') // Supprime tout (date future)
            : Carbon::now()->subDays($days)->format('Y-m-d');

        $filesToDelete = $this->getFilesToDelete($fileSystem, $basePath, $cutoffDateTime);

        $count = count($filesToDelete);

        // Ne pas afficher les stats si aucun fichier à supprimer
        if ($verbose && $count > 0) {
            $this->displayStatistics($filesToDelete, $count, $days);
        }

        if ($count === 0) {
            $this->info('No log files to delete.');

            return ExitCode::SUCCESS;
        }

        // Si days = 0 (delete all) et pas force, demander confirmation
        if ($days === 0 && ! $force && ! $dryRun) {
            $this->newLine();
            $this->error('⚠️  WARNING: You are about to delete ALL log files!');
            $this->newLine();

            $confirmed = $this->confirm('Are you sure you want to delete all log files?');

            if (! $confirmed) {
                $this->info('Operation cancelled.');

                return ExitCode::SUCCESS;
            }

            $this->newLine();
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
            'Retention' => $days === 0 ? 'ALL (delete everything)' : $days.' days',
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
