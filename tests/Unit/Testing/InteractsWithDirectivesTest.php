<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Testing;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Testing\InteractsWithDirectives;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestCalculatorDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
final class InteractsWithDirectivesTest extends UnitTestCase
{
    use InteractsWithDirectives;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initDirectiveTesting();
    }

    protected function tearDown(): void
    {
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }

    public function test_register_directive(): void
    {
        // Arrange: Create a directive instance
        $directive = new TestCalculatorDirective($this->interaction);

        // Act: Register the directive
        $this->registerDirective($directive);

        // Assert: Verify the directive was registered
        $this->assertInstanceOf(TestCalculatorDirective::class, $directive);
    }

    public function test_run_directive_returns_response_object(): void
    {
        // Arrange: Create and register a directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Run the directive
        $response = $this->runDirective('calculator', ['add', '5', '3']);

        // Assert: Verify the response
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('8', $response->output);
    }

    public function test_create_test_directive_with_closure(): void
    {
        // Arrange: Create a flag to track closure execution
        $executed = false;

        // Act: Create a test directive with closure
        $this->createTestDirective('test-closure', function ($d) use (&$executed) {
            $executed = true;
            $d->line('Closure executed');
            return ExitCode::SUCCESS;
        });

        $response = $this->runDirective('test-closure');

        // Assert: Verify closure was executed
        $this->assertTrue($executed, 'The closure was not executed');
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Closure executed', $response->output);
    }

    public function test_run_directive_by_class_name(): void
    {
        // Arrange: Create and register a directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Run the directive by signature
        $response = $this->runDirective('calculator', ['add', '10', '5']);

        // Assert: Verify the result
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('15', $response->output);
    }

    public function test_response_output_contains_expected_value(): void
    {
        // Arrange: Create and register a directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Run the directive
        $response = $this->runDirective('calculator', ['mul', '4', '5']);

        // Assert: Verify output contains expected result
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('20', $response->output);
    }

    public function test_response_output_does_not_contain_unexpected_value(): void
    {
        // Arrange: Create and register a directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Run the directive
        $response = $this->runDirective('calculator', ['mul', '4', '5']);

        // Assert: Verify output does not contain unexpected value
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringNotContainsString('999', $response->output);
    }

    public function test_response_output_matches_pattern(): void
    {
        // Arrange: Create and register a directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Run the directive
        $response = $this->runDirective('calculator', ['pow', '2', '8']);

        // Assert: Verify output matches pattern
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertMatchesRegularExpression('/256/', $response->output);
    }

    public function test_directive_not_found_returns_not_found(): void
    {
        // Arrange: No directive registered

        // Act: Run a non-existent directive
        $response = $this->runDirective('non-existent-directive');

        // Assert: Verify not found response
        $this->assertSame(ExitCode::NOT_FOUND, $response->exitCode);
        $this->assertStringContainsString('not found', $response->output);
    }

    public function test_multiple_assertions_on_same_response(): void
    {
        // Arrange: Create and register a directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Run the directive
        $response = $this->runDirective('calculator', ['add', '100', '50']);

        // Assert: Verify multiple conditions
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('150', $response->output);
    }

    public function test_clear_registered_directives(): void
    {
        // Arrange: Create and register a temporary directive
        $uniqueName = 'temp-directive-' . uniqid();

        $this->createTestDirective($uniqueName, function ($d) {
            $d->line('Temp directive executed');
            return ExitCode::SUCCESS;
        });

        // Assert: Verify directive exists before clearing
        $response = $this->runDirective($uniqueName);
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Temp directive executed', $response->output);

        // Act: Clear all registered directives
        $this->clearRegisteredDirectives();

        // Assert: Verify directive no longer exists
        $response = $this->runDirective($uniqueName);
        $this->assertSame(ExitCode::NOT_FOUND, $response->exitCode);
        $this->assertStringContainsString('not found', $response->output);

        // Re-register for subsequent tests
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
    }

    public function test_calculator_add_operation(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Perform addition
        $response = $this->runDirective('calculator', ['add', '15', '25']);

        // Assert: Verify result
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('40', $response->output);
    }

    public function test_calculator_subtract_operation(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Perform subtraction
        $response = $this->runDirective('calculator', ['sub', '100', '30']);

        // Assert: Verify result
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('70', $response->output);
    }

    public function test_calculator_multiply_operation(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Perform multiplication
        $response = $this->runDirective('calculator', ['mul', '12', '12']);

        // Assert: Verify result
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('144', $response->output);
    }

    public function test_calculator_division_operation(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Perform division
        $response = $this->runDirective('calculator', ['div', '100', '4']);

        // Assert: Verify result
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('25', $response->output);
    }

    public function test_calculator_power_operation(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Perform power operation
        $response = $this->runDirective('calculator', ['pow', '3', '4']);

        // Assert: Verify result
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('81', $response->output);
    }

    public function test_calculator_modulo_operation(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Perform modulo operation
        $response = $this->runDirective('calculator', ['mod', '17', '5']);

        // Assert: Verify result
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('2', $response->output);
    }

    public function test_calculator_division_by_zero_returns_failure(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Attempt division by zero
        $response = $this->runDirective('calculator', ['div', '10', '0']);

        // Assert: Verify error response
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Division by zero', $response->output);
    }

    public function test_calculator_invalid_operation_returns_invalid_argument(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Attempt invalid operation
        $response = $this->runDirective('calculator', ['invalid_op', '10', '5']);

        // Assert: Verify error response
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Unknown operation', $response->output);
    }

    public function test_calculator_missing_required_argument(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Run directive with missing argument
        $response = $this->runDirective('calculator', ['add']);

        // Assert: Verify error response
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Not enough arguments', $response->output);
    }

    public function test_directive_with_verbose_option(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Run directive with verbose flag
        $response = $this->runDirective('calculator', ['--verbose', 'add', '15', '27']);

        // Assert: Verify output includes verbose information
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('42', $response->output);
        $this->assertStringContainsString('Operation: add', $response->output);
    }

    public function test_init_directive_testing_with_boot_laravel(): void
    {
        // Arrange: Destroy existing environment
        $this->destroyDirectiveTesting();

        // Act: Initialize with Laravel boot
        $this->initDirectiveTesting(bootLaravel: true);

        // Assert: Verify Laravel structure was created
        $this->assertFileExists($this->directiveTempDir . '/bootstrap/app.php');
        $this->assertFileExists($this->directiveTempDir . '/config/app.php');
        $this->assertDirectoryExists($this->directiveTempDir . '/storage');

        // Act: Register a directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Assert: Verify directive was registered
        $this->assertInstanceOf(TestCalculatorDirective::class, $directive);
    }

    public function test_run_directive_with_boot_laravel_enabled(): void
    {
        // Arrange: Destroy existing environment and recreate with Laravel
        $this->destroyDirectiveTesting();
        $this->initDirectiveTesting(bootLaravel: true);

        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Run directive
        $response = $this->runDirective('calculator', ['add', '5', '3']);

        // Assert: Verify successful execution
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('8', $response->output);
    }

    public function test_multiple_directive_testing_initializations(): void
    {
        // Arrange: First initialization
        $this->initDirectiveTesting();
        $firstTempDir = $this->directiveTempDir;

        // Act: Second initialization (should be idempotent)
        $this->initDirectiveTesting();

        // Assert: Same temporary directory is used
        $this->assertSame($firstTempDir, $this->directiveTempDir);
    }
}
