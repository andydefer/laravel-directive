<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestCalculatorDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestConcreteDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestEchoDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestGreetingDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestVariadicDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
final class DirectiveTestingServiceTest extends UnitTestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DirectiveTestingService();
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    // ==================== run() Method Tests ====================

    public function test_run_returns_success_for_concrete_directive(): void
    {
        $response = $this->service->run(TestConcreteDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    }

    public function test_run_returns_success_for_echo_directive(): void
    {
        $response = $this->service->run(TestEchoDirective::class, ['Hello World']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello World', $response->output);
    }

    public function test_run_returns_success_for_echo_directive_with_default_message(): void
    {
        $response = $this->service->run(TestEchoDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello World', $response->output);
    }

    public function test_run_returns_success_for_variadic_directive(): void
    {
        $response = $this->service->run(TestVariadicDirective::class, ['John', 'file1.txt', 'file2.txt', '--verbose']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Name: John', $response->output);
        $this->assertStringContainsString('file1.txt', $response->output);
        $this->assertStringContainsString('file2.txt', $response->output);
        $this->assertStringContainsString('Verbose mode enabled', $response->output);
    }

    public function test_run_returns_success_for_greeting_directive(): void
    {
        $response = $this->service->run(TestGreetingDirective::class, ['Alice']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, Alice!', $response->output);
    }

    public function test_run_returns_success_for_greeting_directive_with_default_name(): void
    {
        $response = $this->service->run(TestGreetingDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, World!', $response->output);
    }

    public function test_run_returns_success_for_calculator_add_operation(): void
    {
        $response = $this->service->run(TestCalculatorDirective::class, ['add', '10', '5']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('15', $response->output);
    }

    public function test_run_returns_success_for_calculator_sub_operation(): void
    {
        $response = $this->service->run(TestCalculatorDirective::class, ['sub', '10', '5']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('5', $response->output);
    }

    public function test_run_returns_success_for_calculator_mul_operation(): void
    {
        $response = $this->service->run(TestCalculatorDirective::class, ['mul', '10', '5']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('50', $response->output);
    }

    public function test_run_returns_success_for_calculator_div_operation(): void
    {
        $response = $this->service->run(TestCalculatorDirective::class, ['div', '10', '2']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('5', $response->output);
    }

    public function test_run_returns_failure_for_calculator_div_by_zero(): void
    {
        $response = $this->service->run(TestCalculatorDirective::class, ['div', '10', '0']);

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
        $this->assertStringContainsString('Division by zero', $response->output);
    }

    public function test_run_returns_invalid_argument_for_calculator_invalid_operation(): void
    {
        $response = $this->service->run(TestCalculatorDirective::class, ['invalid_op', '10', '5']);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString('Unknown operation: invalid_op', $response->output);
    }

    public function test_run_throws_exception_for_nonexistent_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Directive class NonExistentDirectiveClass does not exist');

        $this->service->run('NonExistentDirectiveClass');
    }

    // ==================== destroy() Method Tests ====================

    public function test_destroy_cleans_up_temp_directory(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $tempDirProperty = $reflection->getProperty('tempDir');
        $tempDir = $tempDirProperty->getValue($this->service);

        $this->assertDirectoryExists($tempDir);

        $this->service->destroy();

        $this->assertDirectoryDoesNotExist($tempDir);
    }

    public function test_destroy_called_multiple_times_does_not_throw_exception(): void
    {
        $this->service->destroy();
        $this->service->destroy();
        $this->service->destroy();

        $this->addToAssertionCount(1);
    }

    // ==================== getInteraction() Method Tests ====================

    public function test_get_interaction_returns_interaction_service(): void
    {
        $interaction = $this->service->getInteraction();

        $this->assertInstanceOf(DirectiveInteractionService::class, $interaction);
    }

    public function test_get_interaction_returns_same_instance_on_multiple_calls(): void
    {
        $interaction1 = $this->service->getInteraction();
        $interaction2 = $this->service->getInteraction();

        $this->assertSame($interaction1, $interaction2);
    }

    // ==================== Temp Directory Tests ====================

    public function test_temp_directory_is_created(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $tempDirProperty = $reflection->getProperty('tempDir');
        $tempDir = $tempDirProperty->getValue($this->service);

        $this->assertNotNull($tempDir);
        $this->assertDirectoryExists($tempDir);
    }

    public function test_working_directory_changes_to_temp_directory(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $tempDirProperty = $reflection->getProperty('tempDir');
        $tempDir = $tempDirProperty->getValue($this->service);

        $currentDir = getcwd();
        $this->assertSame($tempDir, $currentDir);
    }

    // ==================== Multiple Services Tests ====================

    public function test_multiple_services_have_different_temp_directories(): void
    {
        $service1 = new DirectiveTestingService();
        $service2 = new DirectiveTestingService();

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

    public function test_services_are_independent(): void
    {
        $service1 = new DirectiveTestingService();
        $service2 = new DirectiveTestingService();

        $response1 = $service1->run(TestGreetingDirective::class, ['Service1']);
        $response2 = $service2->run(TestGreetingDirective::class, ['Service2']);

        $this->assertSame(ExitCode::SUCCESS, $response1->exit_code);
        $this->assertSame(ExitCode::SUCCESS, $response2->exit_code);
        $this->assertStringContainsString('Service1', $response1->output);
        $this->assertStringContainsString('Service2', $response2->output);

        $service1->destroy();
        $service2->destroy();
    }

    // ==================== Response Content Tests ====================

    public function test_response_output_contains_expected_value(): void
    {
        $response = $this->service->run(TestEchoDirective::class, ['Expected Output']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Expected Output', $response->output);
    }

    public function test_response_output_does_not_contain_unexpected_value(): void
    {
        $response = $this->service->run(TestEchoDirective::class, ['Hello']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringNotContainsString('Unexpected', $response->output);
    }

    public function test_response_output_matches_pattern(): void
    {
        $response = $this->service->run(TestEchoDirective::class, ['Pattern']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertMatchesRegularExpression('/Pattern/', $response->output);
    }

    public function test_multiple_assertions_on_same_response(): void
    {
        $response = $this->service->run(TestEchoDirective::class, ['Test Message']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Test Message', $response->output);
        $this->assertStringNotContainsString('error', $response->output);
        $this->assertIsString($response->output);
    }

    // ==================== Edge Cases Tests ====================

    public function test_run_with_empty_arguments_array_returns_success(): void
    {
        $response = $this->service->run(TestGreetingDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('World', $response->output);
    }

    public function test_run_with_boolean_arguments(): void
    {
        $response = $this->service->run(TestEchoDirective::class, ['true']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('true', $response->output);
    }

    public function test_run_with_numeric_arguments(): void
    {
        $response = $this->service->run(TestEchoDirective::class, ['123']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('123', $response->output);
    }

    public function test_run_with_special_characters_in_arguments(): void
    {
        $response = $this->service->run(TestEchoDirective::class, ['hello-world']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('hello-world', $response->output);
    }

    // ==================== Variadic Arguments Tests ====================

    public function test_variadic_directive_with_multiple_files(): void
    {
        $arguments = ['John', 'file1.txt', 'file2.txt', 'file3.txt'];
        $response = $this->service->run(TestVariadicDirective::class, $arguments);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('file1.txt', $response->output);
        $this->assertStringContainsString('file2.txt', $response->output);
        $this->assertStringContainsString('file3.txt', $response->output);
    }

    public function test_variadic_directive_with_no_files(): void
    {
        $response = $this->service->run(TestVariadicDirective::class, ['John']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Name: John', $response->output);
    }

    // ==================== Calculator Edge Cases Tests ====================

    public function test_calculator_add_operation_without_optional_b(): void
    {
        $response = $this->service->run(TestCalculatorDirective::class, ['add', '10']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('10', $response->output);
    }

    public function test_calculator_mod_operation(): void
    {
        $response = $this->service->run(TestCalculatorDirective::class, ['mod', '10', '3']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('1', $response->output);
    }

    public function test_calculator_pow_operation(): void
    {
        $response = $this->service->run(TestCalculatorDirective::class, ['pow', '2', '3']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('8', $response->output);
    }

    // ==================== Service Recreation Tests ====================

    public function test_service_can_be_recreated_after_destroy(): void
    {
        $this->service->destroy();

        $newService = new DirectiveTestingService();
        $response = $newService->run(TestGreetingDirective::class, ['recreated']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('recreated', $response->output);

        $newService->destroy();
    }

    // ==================== Performance Tests ====================

    public function test_multiple_executions_with_same_service(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $response = $this->service->run(TestEchoDirective::class, ["Execution {$i}"]);
            $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
            $this->assertStringContainsString("Execution {$i}", $response->output);
        }
    }

    // ==================== Alias Tests ====================

    public function test_echo_directive_has_alias(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $createDirectiveMethod = $reflection->getMethod('createDirective');

        $directive = $createDirectiveMethod->invoke($this->service, TestEchoDirective::class);
        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('echo'));
    }

    public function test_calculator_directive_has_aliases(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $createDirectiveMethod = $reflection->getMethod('createDirective');

        $directive = $createDirectiveMethod->invoke($this->service, TestCalculatorDirective::class);
        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('calc'));
        $this->assertTrue($aliases->contains('math'));
    }

    // ==================== Context Creation Tests ====================

    public function test_directive_context_is_created_correctly(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $createContextMethod = $reflection->getMethod('createDirectiveContext');

        $context = $createContextMethod->invoke($this->service, TestConcreteDirective::class);

        $this->assertInstanceOf(\AndyDefer\Directive\Contexts\DirectiveContext::class, $context);
        $this->assertNotNull($context->getBlueprint());
        $this->assertNotNull($context->getAliases());
    }

    // ==================== Parse Arguments Tests ====================

    public function test_parse_arguments_handles_key_value_pairs(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $parseMethod = $reflection->getMethod('parseArguments');

        $result = $parseMethod->invoke($this->service, 'test {name} {email}', ['John', 'john@example.com']);

        $this->assertArrayHasKey('arguments', $result);
        $this->assertArrayHasKey('options', $result);
        $this->assertArrayHasKey('variadic', $result);
    }

    // ==================== Temp Directory Cleanup Tests ====================

    public function test_temp_directory_is_removed_after_destroy(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $tempDirProperty = $reflection->getProperty('tempDir');
        $tempDir = $tempDirProperty->getValue($this->service);

        $this->assertDirectoryExists($tempDir);

        $this->service->destroy();

        $this->assertDirectoryDoesNotExist($tempDir);
    }

    // ==================== Working Directory Restoration Tests ====================

    public function test_working_directory_is_restored_after_destroy(): void
    {
        $originalCwd = getcwd();

        $newService = new DirectiveTestingService();

        $reflection = new \ReflectionClass($newService);
        $tempDirProperty = $reflection->getProperty('tempDir');
        $tempDir = $tempDirProperty->getValue($newService);

        $this->assertSame($tempDir, getcwd());

        $newService->destroy();

        $this->assertSame($originalCwd, getcwd());
    }
}
