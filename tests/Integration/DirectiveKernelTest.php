<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration;

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Helpers\Paths;
use AndyDefer\Directive\Records\ExecutionStatsRecord;
use AndyDefer\Directive\Services\ExecutionStatsLogger;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use AndyDefer\DomainStructures\Utils\ListCollection;
use Carbon\Carbon;

final class DirectiveKernelTest extends IntegrationTestCase
{
    private DirectiveKernel $kernel;

    private string $logBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        ob_start();

        $this->kernel = DirectiveKernel::init($this->app);

        $this->kernel->addSource(Paths::projectRoot().'/tests/Fixtures/Directives');

        $this->logBasePath = sys_get_temp_dir().'/directive_kernel_logs_'.uniqid();
        $this->kernel->setLogBasePath($this->logBasePath);
    }

    protected function tearDown(): void
    {
        ob_end_clean();

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
        $result = $this->kernel->run(['directive', 'test:directive', 'John', 'john@example.com']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_aliases(): void
    {
        $result = $this->kernel->run(['directive', 'test:echo', 'Hello']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_options(): void
    {
        $result = $this->kernel->run(['directive', 'test:directive', 'John', 'john@example.com', '--force']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_verbose(): void
    {
        $result = $this->kernel->run(['directive', 'test:directive', 'John', 'john@example.com', '--verbose']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_files(): void
    {
        $result = $this->kernel->run(['directive', 'test:directive', 'John', 'john@example.com', 'file1.txt', 'file2.txt']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_format(): void
    {
        $result = $this->kernel->run(['directive', 'test:directive', 'John', 'john@example.com', 'json']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_calls(): void
    {
        $result = $this->kernel->run(['directive', 'test:call']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_before_after(): void
    {
        $result = $this->kernel->run(['directive', 'test:before-after']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_before_failing(): void
    {
        $result = $this->kernel->run(['directive', 'test:before-failing']);

        $this->assertSame(ExitCode::RUNTIME_ERROR, $result);
    }

    public function test_run_directive_with_after_failing(): void
    {
        $result = $this->kernel->run(['directive', 'test:after-failing']);

        $this->assertSame(ExitCode::RUNTIME_ERROR, $result);
    }

    public function test_run_directive_with_nested_before_after(): void
    {
        $result = $this->kernel->run(['directive', 'test:nested-before-after']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_directive_with_circular_dependency(): void
    {
        $result = $this->kernel->run(['directive', 'test:circular']);

        $this->assertSame(ExitCode::CONFLICT, $result);
    }

    public function test_run_directive_with_signature(): void
    {
        $result = $this->kernel->runSignature('test:directive John john@example.com');

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
        $result = $this->kernel->run(['directive', 'context:set', 'John']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $context = $this->kernel->getContext();
        $this->assertTrue($context->hasKey('user_name'));
        $this->assertSame('John', $context->get('user_name'));
        $this->assertSame(1, $context->get('counter'));

        $result = $this->kernel->run(['directive', 'context:get']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_context_increment_and_decrement(): void
    {
        $this->kernel->resetContext();

        $result = $this->kernel->run(['directive', 'context:increment']);

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertSame(1, $this->kernel->getContext()->get('counter'));

        $result = $this->kernel->run(['directive', 'context:increment', '5']);

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertSame(6, $this->kernel->getContext()->get('counter'));

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

        $context = $this->kernel->getContext()
            ->put('name', 'John')
            ->put('age', 30)
            ->put('city', 'Paris');
        $this->kernel->setContext($context);

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

        $context = $this->kernel->getContext()
            ->put('name', 'John')
            ->put('age', 30)
            ->put('city', 'Paris');
        $this->kernel->setContext($context);

        $this->assertFalse($this->kernel->getContext()->isEmpty());

        $result = $this->kernel->run(['directive', 'context:clear']);

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertTrue($this->kernel->getContext()->isEmpty());
    }

    public function test_context_snapshot_and_restore(): void
    {
        $this->kernel->resetContext();

        $context = $this->kernel->getContext()
            ->put('name', 'Alice')
            ->put('age', 30);
        $this->kernel->setContext($context);

        $snapshot = $this->kernel->getContext();

        $context = $this->kernel->getContext()
            ->put('name', 'Bob')
            ->put('city', 'Paris');
        $this->kernel->setContext($context);

        $this->assertSame('Bob', $this->kernel->getContext()->get('name'));
        $this->assertTrue($this->kernel->getContext()->hasKey('city'));

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

        $this->kernel->run(['directive', 'context:set', 'John']);
        $this->kernel->run(['directive', 'context:increment']);

        $result = $this->kernel->runSignature('context:all');

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_context_is_isolated_per_execution(): void
    {
        $this->kernel->resetContext();
        $this->kernel->run(['directive', 'context:set', 'John']);
        $this->assertSame('John', $this->kernel->getContext()->get('user_name'));

        $this->kernel->resetContext();
        $this->kernel->run(['directive', 'context:set', 'Jane']);
        $this->assertSame('Jane', $this->kernel->getContext()->get('user_name'));
        $this->assertNotSame('John', $this->kernel->getContext()->get('user_name'));
    }

    public function test_context_with_directive_calls(): void
    {
        $this->kernel->resetContext();

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

        $result = $this->kernel->run(['directive', 'test:directive', 'John', 'john@example.com']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $logFile = $this->getLogFilePath();
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $this->assertNotEmpty($content);

        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('payload', $data);

        $payload = $data['payload'];
        $this->assertSame('test:directive', $payload['command']);
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

        $result = $this->kernel->run(['directive', 'test:after-failing']);

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

        $this->kernel->run(['directive', 'context:set', 'John']);
        $result = $this->kernel->run(['directive', 'test:directive', 'Jane', 'jane@example.com']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $logFile = $this->getLogFilePath();
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $lines = explode("\n", trim($content));

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

        $result = $this->kernel->run(['directive', 'test:directive', 'John', 'john@example.com']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $lastStats = $this->kernel->getLastStats();

        $this->assertInstanceOf(ExecutionStatsRecord::class, $lastStats);
        $this->assertSame('test:directive', $lastStats->command);
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

        $result = $this->kernel->run(['directive', 'test:directive', 'John', 'john@example.com']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $now = Carbon::now();
        $today = $now->format('Y-m-d');
        $hour = $now->format('H');
        $filePath = $newBasePath.'/'.$today.'/'.$hour.'.jsonl';

        $this->assertFileExists($filePath);

        if (is_dir($newBasePath)) {
            $this->removeDirectory($newBasePath);
        }
    }

    public function test_kernel_logs_multiple_executions(): void
    {
        $this->kernel->resetContext();

        $this->kernel->run(['directive', 'test:directive', 'John', 'john@example.com']);
        $this->kernel->run(['directive', 'test:echo', 'Hello']);
        $this->kernel->run(['directive', 'test:directive', 'Jane', 'jane@example.com']);

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

        $this->assertContains('test:directive', $commands);
        $this->assertContains('test:echo', $commands);
    }

    public function test_kernel_logs_performance_metrics(): void
    {
        $this->kernel->resetContext();

        $result = $this->kernel->run(['directive', 'test:directive', 'John', 'john@example.com']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $lastStats = $this->kernel->getLastStats();

        $this->assertGreaterThan(0, $lastStats->duration);
        $this->assertGreaterThanOrEqual(0, $lastStats->memoryUsage);
        $this->assertGreaterThanOrEqual(0, $lastStats->peakMemoryUsage);
    }

    // ==================== PROBLEMS TESTS ====================

    public function test_kernel_problems_are_empty_initially(): void
    {
        $problems = $this->kernel->getProblems();
        $this->assertInstanceOf(ListCollection::class, $problems);
        $this->assertTrue($problems->isEmpty());
    }

    public function test_kernel_records_problem_when_execution_fails(): void
    {
        $this->kernel->runDirective('NonExistentDirectiveClass');

        $problems = $this->kernel->getProblems();
        $this->assertInstanceOf(ListCollection::class, $problems);
        $this->assertFalse($problems->isEmpty());
    }

    public function test_kernel_clear_problems_empties_collection(): void
    {
        $this->kernel->runDirective('NonExistentDirectiveClass');

        $this->assertFalse($this->kernel->getProblems()->isEmpty());

        $this->kernel->clearProblems();
        $this->assertTrue($this->kernel->getProblems()->isEmpty());
    }

    public function test_kernel_problem_contains_command_context(): void
    {
        $this->kernel->runDirective('NonExistentDirectiveClass');

        $problems = $this->kernel->getProblems();
        $this->assertFalse($problems->isEmpty());

        $found = false;
        foreach ($problems as $problem) {
            if ($problem->get('key') === 'run_directive' || $problem->get('key') === 'instantiate_and_run') {
                $found = true;
                $contextData = $problem->get('context_data');

                if (isset($contextData['fqcn'])) {
                    $this->assertStringContainsString('NonExistentDirectiveClass', $contextData['fqcn']);
                } elseif (isset($contextData['class'])) {
                    $this->assertStringContainsString('NonExistentDirectiveClass', $contextData['class']);
                } else {
                    $this->fail('No class or fqcn key found in context_data');
                }
                break;
            }
        }
        $this->assertTrue($found, 'Problem with context not found');
    }

    public function test_kernel_problem_when_directive_instantiation_fails(): void
    {
        $result = $this->kernel->runDirective('NonExistentDirectiveClass');

        $this->assertSame(ExitCode::RUNTIME_ERROR, $result);

        $problems = $this->kernel->getProblems();
        $this->assertFalse($problems->isEmpty());

        $found = false;
        foreach ($problems as $problem) {
            $key = $problem->get('key');
            if ($key === 'run_directive' || $key === 'instantiate_and_run') {
                $found = true;
                $contextData = $problem->get('context_data');

                if (isset($contextData['fqcn'])) {
                    $this->assertStringContainsString('NonExistentDirectiveClass', $contextData['fqcn']);
                } elseif (isset($contextData['class'])) {
                    $this->assertStringContainsString('NonExistentDirectiveClass', $contextData['class']);
                } else {
                    $this->fail('No class or fqcn key found in context_data');
                }
                break;
            }
        }
        $this->assertTrue($found, 'Problem for failed instantiation not found');
    }

    public function test_kernel_problem_timestamp_is_recorded(): void
    {
        $this->kernel->runDirective('NonExistentDirectiveClass');

        $problems = $this->kernel->getProblems();
        $this->assertFalse($problems->isEmpty());

        $problem = $problems->first();
        $this->assertTrue($problem->has('timestamp'));

        $timestamp = $problem->get('timestamp');
        $this->assertIsString($timestamp);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $timestamp);
    }

    public function test_kernel_problems_persist_across_executions(): void
    {
        $this->kernel->runDirective('NonExistentDirectiveClass1');
        $firstCount = $this->kernel->getProblems()->count();

        $this->kernel->runDirective('NonExistentDirectiveClass2');
        $secondCount = $this->kernel->getProblems()->count();

        $this->assertGreaterThanOrEqual($firstCount, $secondCount);
        $this->assertGreaterThan(0, $firstCount);
    }

    public function test_kernel_problem_keys_are_unique_identifiers(): void
    {
        $this->kernel->runDirective('NonExistentDirectiveClass');

        $problems = $this->kernel->getProblems();
        $this->assertFalse($problems->isEmpty());

        $keys = [];
        foreach ($problems as $problem) {
            $key = $problem->get('key');
            $this->assertIsString($key);
            $this->assertNotEmpty($key);
            $keys[] = $key;
        }
        $this->assertNotContains('', $keys);
    }

    public function test_kernel_problem_context_is_human_readable(): void
    {
        $this->kernel->runDirective('NonExistentDirectiveClass');

        $problems = $this->kernel->getProblems();
        $this->assertFalse($problems->isEmpty());

        foreach ($problems as $problem) {
            $context = $problem->get('context');
            $this->assertIsString($context);
            $this->assertNotEmpty($context);
            $this->assertGreaterThan(10, strlen($context));
        }
    }

    // ==================== VERBOSE MODE TESTS ====================

    public function test_verbose_mode_disabled_by_default(): void
    {
        $kernel = DirectiveKernel::init($this->app);

        $this->assertFalse($kernel->isVerbose());
    }

    public function test_verbose_mode_can_be_enabled(): void
    {
        $kernel = DirectiveKernel::init($this->app);
        $kernel->verbose(true);

        $this->assertTrue($kernel->isVerbose());
    }

    public function test_verbose_mode_can_be_disabled(): void
    {
        $kernel = DirectiveKernel::init($this->app);
        $kernel->verbose(true);
        $kernel->verbose(false);

        $this->assertFalse($kernel->isVerbose());
    }

    public function test_verbose_mode_with_output_disables_verbose(): void
    {
        $kernel = DirectiveKernel::init($this->app);
        $kernel->verbose(true);
        $kernel->withOutput();

        $this->assertFalse($kernel->isVerbose());
    }

    public function test_verbose_mode_without_output_enables_verbose(): void
    {
        $kernel = DirectiveKernel::init($this->app);
        $kernel->verbose(false);
        $kernel->withoutOutput();

        $this->assertTrue($kernel->isVerbose());
    }

    public function test_verbose_mode_does_not_display_problems_when_none_exist(): void
    {
        $kernel = DirectiveKernel::init($this->app);
        $kernel->addSource(Paths::projectRoot().'/tests/Fixtures/Directives');
        $kernel->verbose(true);

        ob_start();
        $kernel->run(['directive', 'help']);
        $output = ob_get_clean();

        $this->assertStringNotContainsString('Problem(s) Encountered', $output);
    }

    public function test_verbose_mode_displays_problems_when_they_exist(): void
    {
        $kernel = DirectiveKernel::init($this->app);
        $kernel->verbose(true);

        ob_start();
        $kernel->run(['directive', 'non-existent-command']);
        $output = ob_get_clean();

        $this->assertStringContainsString('Problem(s) Encountered', $output);
    }

    public function test_verbose_mode_displays_problems_in_log_format(): void
    {
        $kernel = DirectiveKernel::init($this->app);
        $kernel->verbose(true);

        ob_start();
        $kernel->run(['directive', 'non-existent-command']);
        $output = ob_get_clean();

        // Vérifier que le message d'erreur est présent
        $this->assertStringContainsString('Directive not found: non-existent-command', $output);

        // ✅ CORRECTION : assertStringContainsString au lieu de assertMatchesRegularExpression
        $this->assertStringContainsString('"command"', $output);
        $this->assertStringContainsString('"non-existent-command"', $output);
        $this->assertStringContainsString('"query"', $output);
        $this->assertStringContainsString('"non-existent-command"', $output);

        // Vérifier les entêtes et pieds de page
        $this->assertStringContainsString('Problem(s) Encountered', $output);
        $this->assertStringContainsString('End of Problems', $output);
    }

    public function test_verbose_mode_displays_problem_key_context_message_and_timestamp(): void
    {
        $kernel = DirectiveKernel::init($this->app);
        $kernel->verbose(true);

        ob_start();
        $kernel->run(['directive', 'non-existent-command']);
        $output = ob_get_clean();

        // ✅ Le key est présent
        $this->assertStringContainsString('directive_not_found', $output);
        // ✅ Le context est présent
        $this->assertStringContainsString('Directive not found: non-existent-command', $output);
        // ✅ La date est présente
        $this->assertStringContainsString('2024-01-01', $output);
    }

    public function test_verbose_mode_displays_problems_with_context_data(): void
    {
        $kernel = DirectiveKernel::init($this->app);
        $kernel->verbose(true);

        ob_start();
        $kernel->run(['directive', 'non-existent-command']);
        $output = ob_get_clean();

        // ✅ Le contexte data contient "command" et "query"
        $this->assertStringContainsString('"command"', $output);
        $this->assertStringContainsString('"query"', $output);
        $this->assertStringContainsString('non-existent-command', $output);
    }

    public function test_verbose_mode_displays_end_of_problems_marker(): void
    {
        $kernel = DirectiveKernel::init($this->app);
        $kernel->verbose(true);

        ob_start();
        $kernel->run(['directive', 'non-existent-command']);
        $output = ob_get_clean();

        $this->assertStringContainsString('End of Problems', $output);
    }

    public function test_verbose_mode_problems_count_is_displayed(): void
    {
        $kernel = DirectiveKernel::init($this->app);
        $kernel->verbose(true);

        ob_start();
        $kernel->run(['directive', 'non-existent-command']);
        $output = ob_get_clean();

        $this->assertMatchesRegularExpression('/=== \d+ Problem\(s\) Encountered ===/', $output);
    }

    public function test_verbose_mode_works_with_run_directive(): void
    {
        $kernel = DirectiveKernel::init($this->app);
        $kernel->verbose(true);

        ob_start();
        $kernel->runDirective('NonExistentDirective');
        $output = ob_get_clean();

        $this->assertStringContainsString('Problem(s) Encountered', $output);
        $this->assertStringContainsString('run_directive', $output);
    }

    public function test_verbose_mode_preserves_problems_in_response_record(): void
    {
        $kernel = DirectiveKernel::init($this->app);
        $kernel->verbose(true);

        $kernel->run(['directive', 'non-existent-command']);

        $problems = $kernel->getProblems();
        $this->assertFalse($problems->isEmpty());
    }

    public function test_verbose_mode_logs_use_error_level(): void
    {
        $kernel = DirectiveKernel::init($this->app);
        $kernel->verbose(true);

        ob_start();
        $kernel->run(['directive', 'non-existent-command']);
        $output = ob_get_clean();

        $this->assertStringContainsString('ERROR', $output);
    }
}
