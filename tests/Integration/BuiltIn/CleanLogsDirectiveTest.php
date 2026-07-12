<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration\BuiltIn;

use AndyDefer\Directive\BuiltIn\CleanLogsDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Services\ExecutionStatsLogger;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use AndyDefer\PhpServices\Services\FileSystemService;
use Carbon\Carbon;

final class CleanLogsDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    private string $tempDir;

    private ExecutionStatsLogger $logger;

    private FileSystemService $fileSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/clean_logs_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->fileSystem = new FileSystemService;

        $this->app['config']->set('directive.log_base_path', $this->tempDir);

        $this->service = new DirectiveTestingService($this->app);

        $this->logger = $this->app->make(ExecutionStatsLogger::class)->setBasePath($this->tempDir);
    }

    protected function tearDown(): void
    {

        $this->service->destroy();

        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        } else {
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

    /**
     * Create a log file with the specified age.
     *
     * @param  int  $ageDays  Number of days ago the file was created (default: 0)
     * @param  string  $hour  Hour of the log file (default: '10')
     * @param  string|null  $date  Optional specific date (overrides age)
     * @return string Path to the created file
     */
    private function createLogFile(int $ageDays = 0, string $hour = '10', ?string $date = null): string
    {

        // ✅ Utiliser Carbon pour la date
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

        // ✅ Utiliser Carbon pour la modification
        $modificationTime = $carbonDate->timestamp;
        touch($filePath, $modificationTime);

        return $filePath;
    }

    /**
     * Get the expected file path for a log file.
     */
    private function getExpectedFilePath(int $ageDays = 0, string $hour = '10'): string
    {
        $date = Carbon::now()->subDays($ageDays)->format('Y-m-d');

        return $this->tempDir.'/'.$date.'/'.$hour.'.jsonl';
    }

    private function dumpDirectoryContents(string $path): void
    {
        if (is_dir($path)) {
            $items = scandir($path);
            foreach ($items as $item) {
                if ($item !== '.' && $item !== '..') {
                    $fullPath = $path.'/'.$item;
                    if (is_dir($fullPath)) {
                        $this->dumpDirectoryContents($fullPath);
                    } else {
                        $size = filesize($fullPath);
                        $mtime = Carbon::createFromTimestamp(filemtime($fullPath))->format('Y-m-d H:i:s');
                    }
                }
            }
        } else {
        }
    }

    // ==================== SIGNATURE TESTS ====================

    public function test_get_signature(): void
    {

        $directive = new CleanLogsDirective($this->service->getKernel(), '');
        $signature = $directive->getSignature();

        $this->assertStringContainsString('clean:directive-logs', $signature);
        $this->assertStringContainsString('days=', $signature);
        $this->assertStringContainsString('--dry-run', $signature);
        $this->assertStringContainsString('--verbose', $signature);

    }

    public function test_get_description(): void
    {

        $directive = new CleanLogsDirective($this->service->getKernel(), '');
        $this->assertStringContainsString('Remove old execution log files', $directive->getDescription());

    }

    public function test_get_aliases(): void
    {

        $directive = new CleanLogsDirective($this->service->getKernel(), '');
        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('ldc'));
        $this->assertTrue($aliases->contains('log-directive-clean'));

    }

    // ==================== DRY RUN TESTS ====================

    public function test_dry_run_with_no_files(): void
    {

        $response = $this->service->run('clean:directive-logs --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No log files to delete', $this->stripAnsi($response->output));

    }

    public function test_dry_run_with_old_files(): void
    {

        // ✅ Créer un fichier de 60 jours
        $this->createLogFile(60);
        $oldFile = $this->getExpectedFilePath(60);
        $this->assertFileExists($oldFile);

        $this->dumpDirectoryContents($this->tempDir);

        $response = $this->service->run('clean:directive-logs --dry-run --verbose');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('DRY RUN MODE', $cleanedOutput);
        $this->assertStringContainsString('Would delete 1 file(s)', $cleanedOutput);

        // Le fichier doit toujours exister
        $this->assertFileExists($oldFile);

    }

    public function test_dry_run_with_verbose_shows_file_details(): void
    {

        $this->createLogFile(60);

        $response = $this->service->run('clean:directive-logs --dry-run --verbose');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('10.jsonl', $cleanedOutput);
        $this->assertStringContainsString('KB', $cleanedOutput);

    }

    public function test_dry_run_shows_statistics_with_verbose(): void
    {

        $this->createLogFile(60);
        $this->createLogFile(60, '11');

        $response = $this->service->run('clean:directive-logs --dry-run --verbose');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Retention', $cleanedOutput);
        $this->assertStringContainsString('Files to delete', $cleanedOutput);
        $this->assertStringContainsString('Total size', $cleanedOutput);
        $this->assertStringContainsString('Date range', $cleanedOutput);

    }

    // ==================== DAYS OPTION TESTS ====================

    public function test_default_days_30(): void
    {

        // Fichier de 31 jours (devrait être supprimé par défaut)
        $this->createLogFile(31);
        $oldFile = $this->getExpectedFilePath(31);
        $this->assertFileExists($oldFile);

        $this->dumpDirectoryContents($this->tempDir);

        $response = $this->service->run('clean:directive-logs --dry-run');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would delete 1 file(s)', $cleanedOutput);

    }

    public function test_custom_days_7(): void
    {

        // Fichier de 10 jours (devrait être supprimé avec days=7)
        $this->createLogFile(10);
        $oldFile = $this->getExpectedFilePath(10);
        $this->assertFileExists($oldFile);

        $this->dumpDirectoryContents($this->tempDir);

        $response = $this->service->run('clean:directive-logs 7 --dry-run');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would delete 1 file(s)', $cleanedOutput);

    }

    public function test_custom_days_15_keeps_recent_files(): void
    {

        // Créer un fichier de 10 jours (ne devrait PAS être supprimé avec days=15)
        $this->createLogFile(10);
        $recentFile = $this->getExpectedFilePath(10);
        $this->assertFileExists($recentFile);

        $this->dumpDirectoryContents($this->tempDir);

        $response = $this->service->run('clean:directive-logs 15 --dry-run');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        // Le fichier ne doit PAS être supprimé car il est plus récent que 15 jours
        $this->assertStringContainsString('No log files to delete', $cleanedOutput);

    }

    public function test_invalid_days_value_returns_error(): void
    {

        $this->createLogFile(31);

        $response = $this->service->run('clean:directive-logs invalid --dry-run');

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString('Days must be a valid number', $this->stripAnsi($response->output));

    }

    // ==================== ACTUAL DELETION TESTS ====================

    public function test_actual_deletion(): void
    {

        // Créer un fichier ancien
        $this->createLogFile(60);
        $oldFile = $this->getExpectedFilePath(60);
        $this->assertFileExists($oldFile);

        $this->dumpDirectoryContents($this->tempDir);

        $response = $this->service->run('clean:directive-logs 30');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Successfully deleted', $cleanedOutput);
        $this->assertFileDoesNotExist($oldFile);

        $this->dumpDirectoryContents($this->tempDir);

    }

    public function test_deletion_with_verbose(): void
    {

        $this->createLogFile(60);
        $this->createLogFile(60, '11');

        $response = $this->service->run('clean:directive-logs 30 --verbose');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleting log files', $cleanedOutput);
        $this->assertStringContainsString('✓ Deleted:', $cleanedOutput);
        $this->assertStringContainsString('Successfully deleted 2 file(s)', $cleanedOutput);

    }

    public function test_deletion_removes_empty_directories(): void
    {

        // Créer un fichier dans un sous-dossier
        $this->createLogFile(60);
        $oldFile = $this->getExpectedFilePath(60);
        $this->assertFileExists($oldFile);

        // Vérifier que le dossier existe
        $date = Carbon::now()->subDays(60)->format('Y-m-d');
        $dir = $this->tempDir.'/'.$date;
        $this->assertDirectoryExists($dir);

        $this->dumpDirectoryContents($this->tempDir);

        $response = $this->service->run('clean:directive-logs 30');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        // Le dossier devrait être supprimé car il est vide après la suppression du fichier
        $this->assertDirectoryDoesNotExist($dir);

        $this->dumpDirectoryContents($this->tempDir);

    }

    public function test_deletion_handles_multiple_files(): void
    {

        // Créer plusieurs fichiers anciens
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

        $this->dumpDirectoryContents($this->tempDir);

        $response = $this->service->run('clean:directive-logs 30 --verbose');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Successfully deleted 3 file(s)', $cleanedOutput);

        foreach ($files as $file) {
            $this->assertFileDoesNotExist($file);
        }

        $this->dumpDirectoryContents($this->tempDir);

    }

    public function test_deletion_keeps_recent_files(): void
    {

        // Créer les fichiers
        $recentFile = $this->createLogFile(0);
        $this->assertFileExists($recentFile);

        $oldFile = $this->createLogFile(60);
        $this->assertFileExists($oldFile);

        $this->dumpDirectoryContents($this->tempDir);

        // ✅ FORCER LE CHEMIN CORRECT
        $logger = $this->app->make(ExecutionStatsLogger::class);
        $logger->setBasePath($this->tempDir);

        // ✅ VÉRIFIER que le chemin est bon

        // ✅ Exécuter la commande
        $response = $this->service->run('clean:directive-logs 30');

        // ✅ Afficher la sortie pour debug

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        // Le fichier récent doit rester
        $this->assertFileExists($recentFile);
        // Le fichier ancien doit être supprimé
        $this->assertFileDoesNotExist($oldFile);

        $this->dumpDirectoryContents($this->tempDir);

    }

    // ==================== EDGE CASES ====================

    public function test_handles_missing_log_directory(): void
    {

        $this->app['config']->set('directive.log_base_path', '/nonexistent/path/'.uniqid());

        $service = new DirectiveTestingService($this->app);
        $response = $service->run('clean:directive-logs --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No log files to delete', $this->stripAnsi($response->output));

        $service->destroy();

    }

    public function test_handles_corrupted_files_gracefully(): void
    {

        // Créer un fichier corrompu
        $date = Carbon::now()->format('Y-m-d');
        $dir = $this->tempDir.'/'.$date;
        mkdir($dir, 0777, true);
        $corruptedFile = $dir.'/10.jsonl';
        file_put_contents($corruptedFile, 'invalid json{');
        $this->assertFileExists($corruptedFile);

        $response = $this->service->run('clean:directive-logs 30');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        // Le fichier corrompu existe toujours car il n'est pas détecté comme jsonl valide
        $this->assertFileExists($corruptedFile);

        // Nettoyer le fichier corrompu pour les tests suivants
        @unlink($corruptedFile);

    }

    public function test_handles_non_jsonl_files(): void
    {

        // Créer un fichier non-jsonl
        $date = Carbon::now()->format('Y-m-d');
        $dir = $this->tempDir.'/'.$date;
        mkdir($dir, 0777, true);
        $txtFile = $dir.'/test.txt';
        file_put_contents($txtFile, 'This is not a jsonl file');

        // Créer un fichier jsonl ancien
        $this->createLogFile(60);
        $dateOld = Carbon::now()->subDays(60)->format('Y-m-d');
        $jsonlFile = $this->tempDir.'/'.$dateOld.'/10.jsonl';
        $this->assertFileExists($jsonlFile);

        $this->dumpDirectoryContents($this->tempDir);

        $response = $this->service->run('clean:directive-logs 30');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        // Le fichier txt doit rester (non-jsonl)
        $this->assertFileExists($txtFile);
        // Le fichier jsonl doit être supprimé
        $this->assertFileDoesNotExist($jsonlFile);

        // Nettoyer le fichier txt
        @unlink($txtFile);

        $this->dumpDirectoryContents($this->tempDir);

    }
}
