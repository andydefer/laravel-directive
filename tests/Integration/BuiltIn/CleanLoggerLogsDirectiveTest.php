<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration\BuiltIn;

use AndyDefer\Directive\BuiltIn\CleanLoggerLogsDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use AndyDefer\PhpServices\Services\FileSystemService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

final class CleanLoggerLogsDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    private string $tempDir;

    private FileSystemService $fileSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/clean_logger_logs_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->fileSystem = new FileSystemService;

        Config::set('logger.path', $this->tempDir);
        Config::set('logger.retention_days', 30);

        $this->service = new DirectiveTestingService($this->app);

        $kernel = $this->service->getKernel();
        $kernel->setLogBasePath($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->service->destroy();

        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach (array_diff($items, ['.', '..']) as $item) {
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    private function createLogFile(int $ageDays = 0, string $hour = '10', ?string $date = null): string
    {
        if ($date !== null) {
            $carbonDate = Carbon::createFromFormat('Y-m-d', $date);
        } else {
            $carbonDate = Carbon::now()->subDays($ageDays);
        }

        $formattedDate = $carbonDate->format('Y-m-d');
        $dir = $this->tempDir.'/'.$formattedDate;

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $filePath = $dir.'/'.$hour.'.jsonl';

        $timestamp = $carbonDate->format('Y-m-d').'T'.$hour.':00:00Z';

        $content = json_encode([
            'time' => $timestamp,
            'level' => 'info',
            'type' => 'test',
            'payload' => ['message' => 'test log'],
        ])."\n";

        file_put_contents($filePath, $content);

        $modificationTime = $carbonDate->timestamp;
        touch($filePath, $modificationTime);

        return $filePath;
    }

    private function getExpectedFilePath(int $ageDays = 0, string $hour = '10', ?string $date = null): string
    {
        if ($date !== null) {
            $formattedDate = $date;
        } else {
            $formattedDate = Carbon::now()->subDays($ageDays)->format('Y-m-d');
        }

        return $this->tempDir.'/'.$formattedDate.'/'.$hour.'.jsonl';
    }
    // ==================== SIGNATURE TESTS ====================

    public function test_get_signature(): void
    {
        $directive = new CleanLoggerLogsDirective($this->service->getKernel(), '');
        $signature = $directive->getSignature();

        $this->assertStringContainsString('clean:logger-logs', $signature);
        $this->assertStringContainsString('days=?', $signature);
        $this->assertStringContainsString('--dry-run', $signature);
        $this->assertStringContainsString('--verbose', $signature);
        $this->assertStringContainsString('--force', $signature);
    }

    public function test_get_description(): void
    {
        $directive = new CleanLoggerLogsDirective($this->service->getKernel(), '');
        $this->assertStringContainsString('Remove old Laravel Logger log files', $directive->getDescription());
    }

    public function test_get_aliases(): void
    {
        $directive = new CleanLoggerLogsDirective($this->service->getKernel(), '');
        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('lc'));
        $this->assertTrue($aliases->contains('logger-clean'));
    }

    // ==================== DRY RUN TESTS ====================

    public function test_dry_run_with_no_files(): void
    {
        $response = $this->service->run('clean:logger-logs --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No log files to delete', $this->stripAnsi($response->output));
    }

    public function test_dry_run_with_old_files(): void
    {
        $this->createLogFile(60);
        $oldFile = $this->getExpectedFilePath(60);
        $this->assertFileExists($oldFile);

        $response = $this->service->run('clean:logger-logs 30 --dry-run --verbose');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('DRY RUN MODE', $cleanedOutput);
        $this->assertStringContainsString('Would delete 1 file(s)', $cleanedOutput);
        $this->assertFileExists($oldFile);
    }

    public function test_dry_run_with_verbose_shows_file_details(): void
    {
        $this->createLogFile(60);

        $response = $this->service->run('clean:logger-logs 30 --dry-run --verbose');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('10.jsonl', $cleanedOutput);
        $this->assertStringContainsString('KB', $cleanedOutput);
    }

    public function test_dry_run_shows_statistics_with_verbose(): void
    {
        $this->createLogFile(60);
        $this->createLogFile(60, '11');

        $response = $this->service->run('clean:logger-logs 30 --dry-run --verbose');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Retention', $cleanedOutput);
        $this->assertStringContainsString('Files to delete', $cleanedOutput);
        $this->assertStringContainsString('Total size', $cleanedOutput);
        $this->assertStringContainsString('Date range', $cleanedOutput);
        $this->assertStringContainsString('Base path', $cleanedOutput);
    }

    // ==================== DAYS OPTION TESTS ====================

    public function test_default_days_from_config(): void
    {
        Config::set('logger.retention_days', 30);

        $this->createLogFile(31);
        $oldFile = $this->getExpectedFilePath(31);
        $this->assertFileExists($oldFile);

        $this->createLogFile(20);
        $recentFile = $this->getExpectedFilePath(20);
        $this->assertFileExists($recentFile);

        $response = $this->service->run('clean:logger-logs --dry-run');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would delete 1 file(s)', $cleanedOutput);
    }

    public function test_custom_days_overrides_config(): void
    {
        Config::set('logger.retention_days', 30);

        $this->createLogFile(31);
        $oldFile = $this->getExpectedFilePath(31);
        $this->assertFileExists($oldFile);

        $this->createLogFile(20);
        $recentFile = $this->getExpectedFilePath(20);
        $this->assertFileExists($recentFile);

        $response = $this->service->run('clean:logger-logs 15 --dry-run');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would delete 2 file(s)', $cleanedOutput);
    }

    public function test_null_days_uses_config_retention(): void
    {
        Config::set('logger.retention_days', 7);

        $this->createLogFile(10);
        $oldFile = $this->getExpectedFilePath(10);
        $this->assertFileExists($oldFile);

        $this->createLogFile(5);
        $recentFile = $this->getExpectedFilePath(5);
        $this->assertFileExists($recentFile);

        $response = $this->service->run('clean:logger-logs --dry-run');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would delete 1 file(s)', $cleanedOutput);
    }

    public function test_zero_days_means_delete_all(): void
    {
        // Créer un fichier de 60 jours
        $date60 = Carbon::now()->subDays(60)->format('Y-m-d');
        $this->createLogFile(0, '10', $date60);
        $oldFile1 = $this->getExpectedFilePath(0, '10', $date60);
        $this->assertFileExists($oldFile1);

        // Créer un fichier de 30 jours
        $date30 = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->createLogFile(0, '11', $date30);
        $oldFile2 = $this->getExpectedFilePath(0, '11', $date30);
        $this->assertFileExists($oldFile2);

        // Créer un fichier récent (ne sera PAS supprimé)
        $dateToday = Carbon::now()->format('Y-m-d');
        $this->createLogFile(0, '12', $dateToday);
        $recentFile = $this->getExpectedFilePath(0, '12', $dateToday);
        $this->assertFileExists($recentFile);

        $response = $this->service->run('clean:logger-logs 0 --dry-run --verbose');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would delete 2 file(s)', $cleanedOutput);
    }

    public function test_invalid_days_value_returns_error(): void
    {
        $response = $this->service->run('clean:logger-logs invalid --dry-run');

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString('Days must be a valid number', $this->stripAnsi($response->output));
    }

    public function test_negative_days_returns_error(): void
    {
        $response = $this->service->run('clean:logger-logs -5 --dry-run');

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString('Days cannot be negative', $this->stripAnsi($response->output));
    }

    // ==================== ACTUAL DELETION TESTS ====================

    public function test_actual_deletion(): void
    {
        $this->createLogFile(60);
        $oldFile = $this->getExpectedFilePath(60);
        $this->assertFileExists($oldFile);

        $response = $this->service->run('clean:logger-logs 30');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Successfully deleted', $cleanedOutput);
        $this->assertFileDoesNotExist($oldFile);
    }

    public function test_deletion_with_verbose(): void
    {
        $this->createLogFile(60);
        $this->createLogFile(60, '11');

        $response = $this->service->run('clean:logger-logs 30 --verbose');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleting log files', $cleanedOutput);
        $this->assertStringContainsString('✓ Deleted:', $cleanedOutput);
        $this->assertStringContainsString('Successfully deleted 2 file(s)', $cleanedOutput);
    }

    public function test_deletion_removes_empty_directories(): void
    {
        $this->createLogFile(60);
        $oldFile = $this->getExpectedFilePath(60);
        $this->assertFileExists($oldFile);

        $date = Carbon::now()->subDays(60)->format('Y-m-d');
        $dir = $this->tempDir.'/'.$date;
        $this->assertDirectoryExists($dir);

        $response = $this->service->run('clean:logger-logs 30');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertDirectoryDoesNotExist($dir);
    }

    public function test_deletion_handles_multiple_files(): void
    {
        $this->createLogFile(60);
        $this->createLogFile(60, '11');
        $this->createLogFile(60, '12');

        $date = Carbon::now()->subDays(60)->format('Y-m-d');
        $files = [
            $this->tempDir.'/'.$date.'/10.jsonl',
            $this->tempDir.'/'.$date.'/11.jsonl',
            $this->tempDir.'/'.$date.'/12.jsonl',
        ];

        foreach ($files as $file) {
            $this->assertFileExists($file);
        }

        $response = $this->service->run('clean:logger-logs 30 --verbose');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Successfully deleted 3 file(s)', $cleanedOutput);

        foreach ($files as $file) {
            $this->assertFileDoesNotExist($file);
        }
    }

    public function test_deletion_keeps_recent_files(): void
    {
        $recentFile = $this->createLogFile(0);
        $this->assertFileExists($recentFile);

        $oldFile = $this->createLogFile(60);
        $this->assertFileExists($oldFile);

        $response = $this->service->run('clean:logger-logs 30');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->assertFileExists($recentFile);
        $this->assertFileDoesNotExist($oldFile);
    }

    // ==================== DELETE ALL TESTS ====================

    public function test_delete_all_with_force_skips_confirmation(): void
    {
        $this->createLogFile(60);
        $oldFile = $this->getExpectedFilePath(60);
        $this->assertFileExists($oldFile);

        $response = $this->service->run('clean:logger-logs 0 --force');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringNotContainsString('WARNING', $cleanedOutput);
        $this->assertStringContainsString('Successfully deleted', $cleanedOutput);
        $this->assertFileDoesNotExist($oldFile);
    }

    public function test_delete_all_dry_run_shows_files(): void
    {
        $this->createLogFile(60);
        $this->createLogFile(60, '11');
        $oldFile1 = $this->getExpectedFilePath(60);
        $oldFile2 = $this->getExpectedFilePath(60, '11');
        $this->assertFileExists($oldFile1);
        $this->assertFileExists($oldFile2);

        $response = $this->service->run('clean:logger-logs 0 --dry-run --verbose');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('DRY RUN MODE', $cleanedOutput);
        $this->assertStringContainsString('Would delete 2 file(s)', $cleanedOutput);
        $this->assertFileExists($oldFile1);
        $this->assertFileExists($oldFile2);
    }

    // ==================== EDGE CASES ====================

    public function test_handles_missing_log_directory(): void
    {
        Config::set('logger.path', '/nonexistent/path/'.uniqid());

        $service = new DirectiveTestingService($this->app);
        $response = $service->run('clean:logger-logs --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No log files to delete', $this->stripAnsi($response->output));

        $service->destroy();
    }

    public function test_handles_corrupted_files_gracefully(): void
    {
        $date = Carbon::now()->format('Y-m-d');
        $dir = $this->tempDir.'/'.$date;
        mkdir($dir, 0777, true);
        $corruptedFile = $dir.'/10.jsonl';
        file_put_contents($corruptedFile, 'invalid json{');
        $this->assertFileExists($corruptedFile);

        $response = $this->service->run('clean:logger-logs 30');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertFileExists($corruptedFile);

        @unlink($corruptedFile);
    }

    public function test_handles_non_jsonl_files(): void
    {
        $date = Carbon::now()->format('Y-m-d');
        $dir = $this->tempDir.'/'.$date;
        mkdir($dir, 0777, true);
        $txtFile = $dir.'/test.txt';
        file_put_contents($txtFile, 'This is not a jsonl file');

        $this->createLogFile(60);
        $dateOld = Carbon::now()->subDays(60)->format('Y-m-d');
        $jsonlFile = $this->tempDir.'/'.$dateOld.'/10.jsonl';
        $this->assertFileExists($jsonlFile);

        $response = $this->service->run('clean:logger-logs 30');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->assertFileExists($txtFile);
        $this->assertFileDoesNotExist($jsonlFile);

        @unlink($txtFile);
    }

    public function test_config_path_used_correctly(): void
    {
        Config::set('logger.path', $this->tempDir);

        $this->createLogFile(60);
        $oldFile = $this->getExpectedFilePath(60);
        $this->assertFileExists($oldFile);

        $response = $this->service->run('clean:logger-logs 30');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertFileDoesNotExist($oldFile);
    }
}
