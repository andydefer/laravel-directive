<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration\Services;

use AndyDefer\Directive\Bootstrap\Paths;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestCalculatorDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestConcreteDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestEchoDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestGreetingDirective;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use AndyDefer\DomainStructures\Utils\ListCollection;

final class DirectiveTestingServiceTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DirectiveTestingService(
            $this->app,
            [Paths::projectRoot().'/tests/Fixtures/Directives'],
        );

    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    // ==================== run() TESTS ====================

    public function test_run_returns_success_for_concrete_directive(): void
    {

        $response = $this->service->run('test-concrete John john@example.com');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    }

    public function test_run_returns_success_for_echo_directive(): void
    {

        $response = $this->service->run('test-echo Hello^World');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello World', $response->output);
    }

    public function test_run_returns_success_for_echo_directive_with_default_message(): void
    {

        $response = $this->service->run('test-echo');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello World', $response->output);
    }

    public function test_run_returns_success_for_variadic_directive(): void
    {

        $response = $this->service->run('test-variadic John [file1.txt, file2.txt] --verbose');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Name: John', $response->output);
        $this->assertStringContainsString('file1.txt', $response->output);
        $this->assertStringContainsString('file2.txt', $response->output);
        $this->assertStringContainsString('Verbose mode enabled', $response->output);
    }

    public function test_run_returns_success_for_greeting_directive(): void
    {

        $response = $this->service->run('greeting Alice');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, Alice!', $response->output);
    }

    public function test_run_returns_success_for_greeting_directive_with_default_name(): void
    {

        $response = $this->service->run('greeting');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, World!', $response->output);
    }

    public function test_run_returns_success_for_calculator_add_operation(): void
    {

        $response = $this->service->run('calculator add 10 5');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('15', $response->output);
    }

    public function test_run_returns_success_for_calculator_sub_operation(): void
    {

        $response = $this->service->run('calculator sub 10 5');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('5', $response->output);
    }

    public function test_run_returns_success_for_calculator_mul_operation(): void
    {

        $response = $this->service->run('calculator mul 10 5');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('50', $response->output);
    }

    public function test_run_returns_success_for_calculator_div_operation(): void
    {

        $response = $this->service->run('calculator div 10 2');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('5', $response->output);
    }

    public function test_run_returns_failure_for_calculator_div_by_zero(): void
    {

        $response = $this->service->run('calculator div 10 0');

        $this->assertSame(ExitCode::RUNTIME_ERROR, $response->exit_code);
        $this->assertStringContainsString('Division by zero', $response->output);
    }

    public function test_run_returns_invalid_argument_for_calculator_invalid_operation(): void
    {

        $response = $this->service->run('calculator invalid_op 10 5');

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString('Unknown operation', $response->output);
    }

    public function test_run_returns_not_found_for_nonexistent_directive(): void
    {

        $response = $this->service->run('unknown-command');

        $this->assertSame(ExitCode::NOT_FOUND, $response->exit_code);
    }

    // ==================== runSignature() TESTS ====================

    public function test_run_signature_returns_success_for_greeting(): void
    {
        $response = $this->service->runSignature('greeting Alice');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, Alice!', $response->output);
    }

    public function test_run_signature_returns_success_for_calculator(): void
    {
        $response = $this->service->runSignature('calculator add 10 5');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('15', $response->output);
    }

    public function test_run_signature_returns_success_for_echo_with_message(): void
    {
        $response = $this->service->runSignature('test-echo Hello^World');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello World', $response->output);
    }

    public function test_run_signature_returns_success_for_variadic_with_flags(): void
    {
        $response = $this->service->runSignature('test-variadic John [file1.txt, file2.txt] --verbose');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Name: John', $response->output);
        $this->assertStringContainsString('file1.txt', $response->output);
        $this->assertStringContainsString('file2.txt', $response->output);
        $this->assertStringContainsString('Verbose mode enabled', $response->output);
    }

    public function test_run_signature_returns_not_found_for_nonexistent(): void
    {
        $response = $this->service->runSignature('unknown-command');

        $this->assertSame(ExitCode::NOT_FOUND, $response->exit_code);
    }

    // ==================== runDirective() TESTS ====================

    public function test_run_directive_with_fqcn_returns_success(): void
    {
        $response = $this->service->runDirective(TestGreetingDirective::class, ['Alice']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, Alice!', $response->output);
    }

    public function test_run_directive_with_fqcn_and_default_arguments(): void
    {
        $response = $this->service->runDirective(TestGreetingDirective::class);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, World!', $response->output);
    }

    public function test_run_directive_with_fqcn_and_multiple_arguments(): void
    {
        $response = $this->service->runDirective(
            TestConcreteDirective::class,
            ['John', 'john@example.com', 'json', 'file1.txt', 'file2.txt', '--force', '--verbose']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    }

    public function test_run_directive_with_fqcn_and_flags(): void
    {
        $response = $this->service->runDirective(
            TestEchoDirective::class,
            ['Hello^World']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello World', $response->output);
    }

    public function test_run_directive_with_fqcn_calculator(): void
    {
        $response = $this->service->runDirective(
            TestCalculatorDirective::class,
            ['add', '10', '5']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('15', $response->output);
    }

    public function test_run_directive_with_fqcn_calculator_error(): void
    {
        $response = $this->service->runDirective(
            TestCalculatorDirective::class,
            ['div', '10', '0']
        );

        $this->assertSame(ExitCode::RUNTIME_ERROR, $response->exit_code);
        $this->assertStringContainsString('Division by zero', $response->output);
    }

    public function test_run_directive_with_fqcn_unknown_directive(): void
    {
        // Une classe qui n'existe pas
        $response = $this->service->runDirective('NonExistentDirective');

        $this->assertSame(ExitCode::RUNTIME_ERROR, $response->exit_code);
    }

    // ==================== ENVIRONMENT TESTS ====================

    public function test_temp_directory_is_created(): void
    {

        $reflection = new \ReflectionClass($this->service);
        $tempDirProperty = $reflection->getProperty('tempDir');
        $tempDir = $tempDirProperty->getValue($this->service);

        $this->assertNotNull($tempDir);
        $this->assertDirectoryExists($tempDir);
    }

    public function test_destroy_cleans_up_temp_directory(): void
    {

        $reflection = new \ReflectionClass($this->service);
        $tempDirProperty = $reflection->getProperty('tempDir');
        $tempDir = $tempDirProperty->getValue($this->service);

        $this->assertDirectoryExists($tempDir);

        $this->service->destroy();

        $this->assertDirectoryDoesNotExist($tempDir);
    }

    public function test_working_directory_changes_to_temp_directory(): void
    {

        $reflection = new \ReflectionClass($this->service);
        $tempDirProperty = $reflection->getProperty('tempDir');
        $tempDir = $tempDirProperty->getValue($this->service);

        $currentDir = getcwd();

        $this->assertSame($tempDir, $currentDir);
    }

    public function test_working_directory_is_restored_after_destroy(): void
    {

        $originalCwd = getcwd();

        $newService = new DirectiveTestingService(
            $this->app,
            [Paths::projectRoot().'/tests/Fixtures/Directives'],
        );

        $reflection = new \ReflectionClass($newService);
        $tempDirProperty = $reflection->getProperty('tempDir');
        $tempDir = $tempDirProperty->getValue($newService);

        $this->assertSame($tempDir, getcwd());

        $newService->destroy();

        $this->assertSame($originalCwd, getcwd());
    }

    public function test_multiple_services_have_different_temp_directories(): void
    {

        $service1 = new DirectiveTestingService(
            $this->app,
            [Paths::projectRoot().'/tests/Fixtures/Directives'],
        );
        $service2 = new DirectiveTestingService(
            $this->app,
            [Paths::projectRoot().'/tests/Fixtures/Directives'],
        );

        $reflection = new \ReflectionClass(DirectiveTestingService::class);
        $tempDirProperty = $reflection->getProperty('tempDir');

        $tempDir1 = $tempDirProperty->getValue($service1);
        $tempDir2 = $tempDirProperty->getValue($service2);

        $this->assertNotSame($tempDir1, $tempDir2);
        $this->assertDirectoryExists($tempDir1);
        $this->assertDirectoryExists($tempDir2);

        $service1->destroy();
        $service2->destroy();
    }

    // ==================== PROBLEMS TESTS ====================

    public function test_run_returns_problems_when_error_occurs(): void
    {
        $response = $this->service->runDirective('NonExistentDirective');

        $this->assertSame(ExitCode::RUNTIME_ERROR, $response->exit_code);
        $this->assertFalse($response->problems->isEmpty());
    }

    public function test_run_problems_sequential_contains_problem_records(): void
    {
        $response = $this->service->runDirective('NonExistentDirective');

        $problems = $response->problems;
        $this->assertFalse($problems->isEmpty());

        $firstProblem = $problems->first();
        $this->assertIsArray($firstProblem);
        $this->assertArrayHasKey('key', $firstProblem);
        $this->assertArrayHasKey('context', $firstProblem);
        $this->assertArrayHasKey('message', $firstProblem);
        $this->assertArrayHasKey('context_data', $firstProblem);
        $this->assertArrayHasKey('timestamp', $firstProblem);
    }

    public function test_run_problems_contain_fqcn_context(): void
    {
        $response = $this->service->runDirective('NonExistentDirective');

        $problems = $response->problems;
        $this->assertFalse($problems->isEmpty());

        $found = false;
        foreach ($problems as $problem) {
            if ($problem['key'] === 'run_directive') {
                $found = true;
                $this->assertArrayHasKey('fqcn', $problem['context_data']);
                $this->assertStringContainsString('NonExistentDirective', $problem['context_data']['fqcn']);
                break;
            }
        }
        $this->assertTrue($found, 'Problem with run_directive key not found');
    }

    public function test_run_signature_returns_problems_on_error(): void
    {
        $response = $this->service->runSignature('non-existent-command');

        $this->assertSame(ExitCode::NOT_FOUND, $response->exit_code);
    }

    public function test_run_problems_are_persisted_across_multiple_calls(): void
    {
        $response1 = $this->service->runDirective('NonExistentDirective1');
        $this->assertSame(ExitCode::RUNTIME_ERROR, $response1->exit_code);
        $count1 = $response1->problems->count();

        $response2 = $this->service->runDirective('NonExistentDirective2');
        $this->assertSame(ExitCode::RUNTIME_ERROR, $response2->exit_code);
        $count2 = $response2->problems->count();

        $this->assertGreaterThanOrEqual($count1, $count2);
    }

    public function test_run_successful_directive_returns_empty_problems(): void
    {
        $response = $this->service->run('greeting Alice');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertTrue($response->problems->isEmpty());
    }

    public function test_run_directive_problems_contain_timestamp(): void
    {
        $response = $this->service->runDirective('NonExistentDirective');

        $problems = $response->problems;
        $this->assertFalse($problems->isEmpty());

        $firstProblem = $problems->first();
        $this->assertArrayHasKey('timestamp', $firstProblem);
        $this->assertIsString($firstProblem['timestamp']);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $firstProblem['timestamp']);
    }

    public function test_run_directive_problems_contain_human_readable_context(): void
    {
        $response = $this->service->runDirective('NonExistentDirective');

        $problems = $response->problems;
        $this->assertFalse($problems->isEmpty());

        foreach ($problems as $problem) {
            $this->assertArrayHasKey('context', $problem);
            $this->assertIsString($problem['context']);
            $this->assertNotEmpty($problem['context']);
            $this->assertGreaterThan(10, strlen($problem['context']));
        }
    }

    public function test_run_multiple_errors_accumulate_problems(): void
    {
        $this->service->runDirective('NonExistentDirective1');
        $this->service->runDirective('NonExistentDirective2');
        $this->service->runDirective('NonExistentDirective3');

        $response = $this->service->runDirective('NonExistentDirective4');

        $problems = $response->problems;
        $this->assertGreaterThanOrEqual(4, $problems->count());
    }

    public function test_run_kernel_problems_are_accessible_via_getter(): void
    {
        $kernel = $this->service->getKernel();

        $problems = $kernel->getProblems();
        $this->assertInstanceOf(ListCollection::class, $problems);
    }
}
