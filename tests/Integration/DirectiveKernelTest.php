<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration;

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\ExecutionStatsRecord;
use AndyDefer\Directive\Services\ExecutionStatsLogger;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use Carbon\Carbon;

final class DirectiveKernelTest extends IntegrationTestCase
{
    private DirectiveKernel $kernel;

    private string $logBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        ob_start();

        $this->kernel = DirectiveKernel::init($this->laravelContainer);

        // Ajouter le chemin des fixtures
        $this->kernel->addSource(getcwd().'/tests/Fixtures/Directives');

        // Répertoire temporaire pour les logs
        $this->logBasePath = sys_get_temp_dir().'/directive_kernel_logs_'.uniqid();
        $this->kernel->setLogBasePath($this->logBasePath);
    }

    protected function tearDown(): void
    {
        // Nettoyer le buffer de sortie
        ob_end_clean();

        // Nettoyer les logs
        if (is_dir($this->logBasePath)) {
            $this->removeDirectory($this->logBasePath);
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

    private function getLogFilePath(): string
    {
        $now = Carbon::now();
        $today = $now->format('Y-m-d');
        $hour = $now->format('H');

        return $this->logBasePath.'/'.$today.'/'.$hour.'.jsonl';
    }

    // ==================== EXISTING TESTS ====================

    public function test_run_without_arguments_returns_help(): void
    {
        $result = $this->kernel->run(['directive']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_help_flag_returns_help(): void
    {
        $result = $this->kernel->run(['directive', '--help']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_version_flag_returns_version(): void
    {
        $result = $this->kernel->run(['directive', '--version']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_list_flag_returns_list(): void
    {
        $result = $this->kernel->run(['directive', '--list']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_unknown_directive_returns_not_found(): void
    {
        $result = $this->kernel->run(['directive', 'unknown-command']);

        $this->assertSame(ExitCode::NOT_FOUND, $result);
    }

    public function test_run_with_valid_directive_returns_success(): void
    {
        $result = $this->kernel->run(['directive', 'test-directive', 'John', 'john@example.com']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_aliases(): void
    {
        $result = $this->kernel->run(['directive', 'test-echo', 'Hello']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_options(): void
    {
        $result = $this->kernel->run(['directive', 'test-directive', 'John', 'john@example.com', '--force']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_verbose(): void
    {
        $result = $this->kernel->run(['directive', 'test-directive', 'John', 'john@example.com', '--verbose']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_files(): void
    {
        $result = $this->kernel->run(['directive', 'test-directive', 'John', 'john@example.com', 'file1.txt', 'file2.txt']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_format(): void
    {
        $result = $this->kernel->run(['directive', 'test-directive', 'John', 'john@example.com', 'json']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_calls(): void
    {
        $result = $this->kernel->run(['directive', 'test-call']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_before_after(): void
    {
        $result = $this->kernel->run(['directive', 'test-before-after']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_before_failing(): void
    {
        $result = $this->kernel->run(['directive', 'test-before-failing']);

        $this->assertSame(ExitCode::RUNTIME_ERROR, $result);
    }

    public function test_run_directive_with_after_failing(): void
    {
        $result = $this->kernel->run(['directive', 'test-after-failing']);

        $this->assertSame(ExitCode::RUNTIME_ERROR, $result);
    }

    public function test_run_directive_with_nested_before_after(): void
    {
        $result = $this->kernel->run(['directive', 'test-nested-before-after']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_circular_dependency(): void
    {
        $result = $this->kernel->run(['directive', 'test-circular']);

        $this->assertSame(ExitCode::CONFLICT, $result);
    }

    public function test_run_directive_with_signature(): void
    {
        $result = $this->kernel->runSignature('test-directive John john@example.com');

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_fqcn(): void
    {
        $result = $this->kernel->runDirective(
            'AndyDefer\Directive\Tests\Fixtures\Directives\TestDirective',
            ['John', 'john@example.com']
        );

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== CONTEXT TESTS ====================

    public function test_context_is_initialized_empty(): void
    {
        $context = $this->kernel->getContext();

        $this->assertTrue($context->isEmpty());
        $this->assertEquals(0, $context->count());
    }

    public function test_context_shared_between_directives(): void
    {
        // Execute directive that sets context
        $result = $this->kernel->run(['directive', 'context:set', 'John']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        // Check context was set
        $context = $this->kernel->getContext();
        $this->assertTrue($context->hasKey('user_name'));
        $this->assertSame('John', $context->get('user_name'));
        $this->assertSame(1, $context->get('counter'));

        // Execute directive that uses context
        $result = $this->kernel->run(['directive', 'context:get']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_context_increment_and_decrement(): void
    {
        // Start with counter = 0
        $this->kernel->resetContext();

        // Increment
        $result = $this->kernel->run(['directive', 'context:increment']);

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertSame(1, $this->kernel->getContext()->get('counter'));

        // Increment by 5
        $result = $this->kernel->run(['directive', 'context:increment', '5']);

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertSame(6, $this->kernel->getContext()->get('counter'));

        // Decrement by 2
        $result = $this->kernel->run(['directive', 'context:decrement', '2']);

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertSame(4, $this->kernel->getContext()->get('counter'));
    }

    public function test_context_merge(): void
    {
        $this->kernel->resetContext();

        $result = $this->kernel->run(['directive', 'context:merge']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $context = $this->kernel->getContext();
        $this->assertSame('John', $context->get('name'));
        $this->assertSame(30, $context->get('age'));
        $this->assertSame('Paris', $context->get('city'));
    }

    public function test_context_remove(): void
    {
        $this->kernel->resetContext();

        // Set some values
        $context = $this->kernel->getContext()
            ->put('name', 'John')
            ->put('age', 30)
            ->put('city', 'Paris');
        $this->kernel->setContext($context);

        // Remove one
        $result = $this->kernel->run(['directive', 'context:remove', 'age']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $context = $this->kernel->getContext();
        $this->assertTrue($context->hasKey('name'));
        $this->assertFalse($context->hasKey('age'));
        $this->assertTrue($context->hasKey('city'));
    }

    public function test_context_clear(): void
    {
        $this->kernel->resetContext();

        // Set some values
        $context = $this->kernel->getContext()
            ->put('name', 'John')
            ->put('age', 30)
            ->put('city', 'Paris');
        $this->kernel->setContext($context);

        $this->assertFalse($this->kernel->getContext()->isEmpty());

        // Clear
        $result = $this->kernel->run(['directive', 'context:clear']);

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertTrue($this->kernel->getContext()->isEmpty());
    }

    public function test_context_snapshot_and_restore(): void
    {
        $this->kernel->resetContext();

        // Set initial values
        $context = $this->kernel->getContext()
            ->put('name', 'Alice')
            ->put('age', 30);
        $this->kernel->setContext($context);

        // Take snapshot
        $snapshot = $this->kernel->getContext();

        // Modify context
        $context = $this->kernel->getContext()
            ->put('name', 'Bob')
            ->put('city', 'Paris');
        $this->kernel->setContext($context);

        $this->assertSame('Bob', $this->kernel->getContext()->get('name'));
        $this->assertTrue($this->kernel->getContext()->hasKey('city'));

        // Restore snapshot
        $this->kernel->setContext($snapshot);

        $this->assertSame('Alice', $this->kernel->getContext()->get('name'));
        $this->assertFalse($this->kernel->getContext()->hasKey('city'));
        $this->assertSame(30, $this->kernel->getContext()->get('age'));
    }

    public function test_run_returns_context(): void
    {
        $this->kernel->resetContext();

        $result = $this->kernel->run(['directive', 'context:set', 'John']);

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertSame('John', $this->kernel->getContext()->get('user_name'));
        $this->assertSame(1, $this->kernel->getContext()->get('counter'));
    }

    public function test_context_all_returns_complete_context(): void
    {
        $this->kernel->resetContext();

        // Set multiple values via directives
        $this->kernel->run(['directive', 'context:set', 'John']);
        $this->kernel->run(['directive', 'context:increment']);

        // Get complete context
        $result = $this->kernel->runSignature('context:all');

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_context_is_isolated_per_execution(): void
    {
        // First execution
        $this->kernel->resetContext();
        $this->kernel->run(['directive', 'context:set', 'John']);
        $this->assertSame('John', $this->kernel->getContext()->get('user_name'));

        // Reset and new execution
        $this->kernel->resetContext();
        $this->kernel->run(['directive', 'context:set', 'Jane']);
        $this->assertSame('Jane', $this->kernel->getContext()->get('user_name'));
        $this->assertNotSame('John', $this->kernel->getContext()->get('user_name'));
    }

    public function test_context_with_directive_calls(): void
    {
        $this->kernel->resetContext();

        // Parent directive that calls children
        $result = $this->kernel->run(['directive', 'context:orchestrate']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $context = $this->kernel->getContext();
        $this->assertSame('John', $context->get('name'));
        $this->assertTrue($context->hasKey('step1_done'));
        $this->assertTrue($context->hasKey('step2_done'));
        $this->assertSame(2, $context->get('steps_completed'));
    }

    // ==================== LOGGER TESTS ====================

    public function test_kernel_logs_execution_to_jsonl(): void
    {
        $this->kernel->resetContext();

        $result = $this->kernel->run(['directive', 'test-directive', 'John', 'john@example.com']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $logFile = $this->getLogFilePath();
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $this->assertNotEmpty($content);

        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('payload', $data);

        $payload = $data['payload'];
        $this->assertSame('test-directive', $payload['command']);
        $this->assertStringContainsString('TestDirective', $payload['directive_class']);
        $this->assertSame(0, $payload['exit_code']);
        $this->assertTrue($payload['success']);
        $this->assertArrayHasKey('duration_seconds', $payload);
        $this->assertArrayHasKey('memory_bytes', $payload);
        $this->assertArrayHasKey('calls_count', $payload);
    }

    public function test_kernel_logs_error_when_directive_fails(): void
    {
        $this->kernel->resetContext();

        $result = $this->kernel->run(['directive', 'test-after-failing']);

        $this->assertSame(ExitCode::RUNTIME_ERROR, $result);

        $logFile = $this->getLogFilePath();
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $this->assertNotEmpty($content);

        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('payload', $data);

        $payload = $data['payload'];
        $this->assertFalse($payload['success']);
        $this->assertSame(5, $payload['exit_code']); // RUNTIME_ERROR = 5
    }

    public function test_kernel_logs_context_with_execution(): void
    {
        $this->kernel->resetContext();

        // Set context then execute
        $this->kernel->run(['directive', 'context:set', 'John']);
        $result = $this->kernel->run(['directive', 'test-directive', 'Jane', 'jane@example.com']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $logFile = $this->getLogFilePath();
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $lines = explode("\n", trim($content));

        // Trouver la ligne du log (dernière ligne)
        $lastLine = end($lines);
        $data = json_decode($lastLine, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('payload', $data);

        $payload = $data['payload'];
        $this->assertArrayHasKey('context', $payload);
        $this->assertArrayHasKey('user_name', $payload['context']);
        $this->assertSame('John', $payload['context']['user_name']);
    }

    public function test_kernel_gets_last_stats(): void
    {
        $this->kernel->resetContext();

        $result = $this->kernel->run(['directive', 'test-directive', 'John', 'john@example.com']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $lastStats = $this->kernel->getLastStats();

        $this->assertInstanceOf(ExecutionStatsRecord::class, $lastStats);
        $this->assertSame('test-directive', $lastStats->command);
        $this->assertStringContainsString('TestDirective', $lastStats->directiveClass);
        $this->assertSame(ExitCode::SUCCESS, $lastStats->exitCode);
        $this->assertGreaterThan(0, $lastStats->duration);
        $this->assertGreaterThanOrEqual(0, $lastStats->memoryUsage);
        $this->assertSame(0, $lastStats->callsCount);
    }

    public function test_kernel_get_logger_returns_logger_instance(): void
    {
        $logger = $this->kernel->getLogger();

        $this->assertInstanceOf(ExecutionStatsLogger::class, $logger);
    }

    public function test_kernel_set_log_base_path_changes_location(): void
    {
        $newBasePath = sys_get_temp_dir().'/directive_kernel_new_logs_'.uniqid();

        $this->kernel->setLogBasePath($newBasePath);

        $result = $this->kernel->run(['directive', 'test-directive', 'John', 'john@example.com']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $now = Carbon::now();
        $today = $now->format('Y-m-d');
        $hour = $now->format('H');
        $filePath = $newBasePath.'/'.$today.'/'.$hour.'.jsonl';

        $this->assertFileExists($filePath);

        // Nettoyer
        if (is_dir($newBasePath)) {
            $this->removeDirectory($newBasePath);
        }
    }

    public function test_kernel_logs_multiple_executions(): void
    {
        $this->kernel->resetContext();

        $this->kernel->run(['directive', 'test-directive', 'John', 'john@example.com']);
        $this->kernel->run(['directive', 'test-echo', 'Hello']);
        $this->kernel->run(['directive', 'test-directive', 'Jane', 'jane@example.com']);

        $logFile = $this->getLogFilePath();
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $lines = array_filter(explode("\n", trim($content)));

        $this->assertCount(3, $lines);

        $commands = [];
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            $commands[] = $data['payload']['command'];
        }

        $this->assertContains('test-directive', $commands);
        $this->assertContains('test-echo', $commands);
    }

    public function test_kernel_logs_performance_metrics(): void
    {
        $this->kernel->resetContext();

        $result = $this->kernel->run(['directive', 'test-directive', 'John', 'john@example.com']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $lastStats = $this->kernel->getLastStats();

        // Les métriques devraient être positives
        $this->assertGreaterThan(0, $lastStats->duration);
        $this->assertGreaterThanOrEqual(0, $lastStats->memoryUsage);
        $this->assertGreaterThanOrEqual(0, $lastStats->peakMemoryUsage);
    }
}
