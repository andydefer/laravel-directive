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
}
