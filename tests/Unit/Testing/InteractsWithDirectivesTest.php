<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Testing;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Testing\DirectiveResponse;
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

        // Arrange: Initialize the testing environment
        $this->initDirectiveTesting();
    }

    protected function tearDown(): void
    {
        // Clean up: Destroy the testing environment
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }

    public function test_register_directive(): void
    {
        // Arrange: Create a directive instance
        $directive = new TestCalculatorDirective($this->interaction);

        // Act: Register the directive
        $this->registerDirective($directive);

        // Assert: Verify the directive was registered successfully
        $this->assertInstanceOf(TestCalculatorDirective::class, $directive);
    }

    public function test_run_directive_returns_response_object(): void
    {
        // Arrange: Create and register a directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Run the directive with arguments
        $response = $this->runDirective('calculator', ['add', '5', '3']);

        // Assert: Verify the response is correct
        $this->assertInstanceOf(DirectiveResponse::class, $response);
        $this->assertSame(ExitCode::SUCCESS, $response->getExitCode());
        $this->assertStringContainsString('8', $response->getOutput());
    }

    public function test_create_test_directive_with_closure(): void
    {
        // Arrange: Create a flag to track closure execution
        $executed = false;

        // Act: Create a test directive with a closure
        $this->createTestDirective('test-closure', function ($d) use (&$executed) {
            $executed = true;
            $d->line('Closure executed');

            return ExitCode::SUCCESS;
        });

        $response = $this->runDirective('test-closure');

        // Assert: Verify the closure was executed
        $this->assertTrue($executed, 'The closure was not executed');
        $this->assertSame(ExitCode::SUCCESS, $response->getExitCode());
        $this->assertStringContainsString('Closure executed', $response->getOutput());
    }

    public function test_run_and_assert_helper(): void
    {
        // Arrange: Create and register a directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Run and assert the directive
        $response = $this->runAndAssert(TestCalculatorDirective::class, ['add', '10', '5']);

        // Assert: Verify the output contains the expected result
        $this->assertStringContainsString('15', $response->getOutput());
    }

    public function test_response_assert_output_contains(): void
    {
        // Arrange: Create and register a directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act & Assert: Run directive and assert output contains expected value
        $this->runDirective('calculator', ['mul', '4', '5'])
            ->assertSuccess()
            ->assertOutputContains('20');
    }

    public function test_response_assert_output_not_contains(): void
    {
        // Arrange: Create and register a directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act & Assert: Run directive and assert output does not contain unexpected value
        $this->runDirective('calculator', ['mul', '4', '5'])
            ->assertSuccess()
            ->assertOutputNotContains('999');
    }

    public function test_response_assert_output_matches(): void
    {
        // Arrange: Create and register a directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act & Assert: Run directive and assert output matches regex pattern
        $this->runDirective('calculator', ['pow', '2', '8'])
            ->assertSuccess()
            ->assertOutputMatches('/256/');
    }

    public function test_directive_not_found_returns_not_found(): void
    {
        // Act: Run a non-existent directive
        $response = $this->runDirective('non-existent-directive');

        // Assert: Verify not found exit code and message
        $this->assertSame(ExitCode::NOT_FOUND, $response->getExitCode());
        $this->assertStringContainsString('not found', $response->getOutput());
    }

    public function test_chained_assertions(): void
    {
        // Arrange: Create and register a directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act & Assert: Chain multiple assertions
        $this->runDirective('calculator', ['add', '100', '50'])
            ->assertSuccess()
            ->assertOutputContains('150');
    }

    public function test_clear_registered_directives(): void
    {
        // Arrange: Create a temporary directive
        $uniqueName = 'temp-directive-' . uniqid();

        $this->createTestDirective($uniqueName, function ($d) {
            $d->line('Temp directive executed');
            return ExitCode::SUCCESS;
        });

        // Assert: Verify directive exists before clearing
        $response = $this->runDirective($uniqueName);
        $this->assertSame(ExitCode::SUCCESS, $response->getExitCode(), 'Temporary directive did not work');
        $this->assertStringContainsString('Temp directive executed', $response->getOutput());

        // Act: Clear all registered directives
        $this->clearRegisteredDirectives();

        // Assert: Verify directive no longer exists
        $response = $this->runDirective($uniqueName);
        $this->assertSame(ExitCode::NOT_FOUND, $response->getExitCode(), 'Directive should not be found after clearing');
        $this->assertStringContainsString('not found', $response->getOutput());

        // Re-register for subsequent tests
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
    }

    public function test_calculator_add_operation(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act & Assert: Test addition operation
        $this->runDirective('calculator', ['add', '15', '25'])
            ->assertSuccess()
            ->assertOutputContains('40');
    }

    public function test_calculator_subtract_operation(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act & Assert: Test subtraction operation
        $this->runDirective('calculator', ['sub', '100', '30'])
            ->assertSuccess()
            ->assertOutputContains('70');
    }

    public function test_calculator_multiply_operation(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act & Assert: Test multiplication operation
        $this->runDirective('calculator', ['mul', '12', '12'])
            ->assertSuccess()
            ->assertOutputContains('144');
    }

    public function test_calculator_division_operation(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act & Assert: Test division operation
        $this->runDirective('calculator', ['div', '100', '4'])
            ->assertSuccess()
            ->assertOutputContains('25');
    }

    public function test_calculator_power_operation(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act & Assert: Test power operation
        $this->runDirective('calculator', ['pow', '3', '4'])
            ->assertSuccess()
            ->assertOutputContains('81');
    }

    public function test_calculator_modulo_operation(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act & Assert: Test modulo operation
        $this->runDirective('calculator', ['mod', '17', '5'])
            ->assertSuccess()
            ->assertOutputContains('2');
    }

    public function test_calculator_division_by_zero_returns_failure(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Run division by zero operation
        $response = $this->runDirective('calculator', ['div', '10', '0']);

        // Assert: Verify invalid argument exit code and error message
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->getExitCode());
        $this->assertStringContainsString('Division by zero', $response->getOutput());
    }

    public function test_calculator_invalid_operation_returns_invalid_argument(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Run invalid operation
        $response = $this->runDirective('calculator', ['invalid_op', '10', '5']);

        // Assert: Verify invalid argument exit code and error message
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->getExitCode());
        $this->assertStringContainsString('Unknown operation', $response->getOutput());
    }

    public function test_calculator_missing_required_argument(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act: Run directive with missing required argument
        $response = $this->runDirective('calculator', ['add']);

        // Assert: Verify invalid argument exit code and error message
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->getExitCode());
        $this->assertStringContainsString('Not enough arguments', $response->getOutput());
    }

    public function test_directive_with_verbose_option(): void
    {
        // Arrange: Create and register calculator directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        // Act & Assert: Test directive with verbose option
        $this->runDirective('calculator', ['--verbose', 'add', '15', '27'])
            ->assertSuccess()
            ->assertOutputContains('42')
            ->assertOutputContains('Operation: add');
    }

    public function test_init_directive_testing_with_boot_laravel(): void
    {
        // Arrange: Destroy existing environment and recreate with Laravel
        $this->destroyDirectiveTesting();
        $this->initDirectiveTesting(bootLaravel: true);

        // Assert: Verify Laravel structure was created
        $this->assertFileExists($this->directiveTempDir . '/bootstrap/app.php');
        $this->assertFileExists($this->directiveTempDir . '/config/app.php');
        $this->assertDirectoryExists($this->directiveTempDir . '/storage');

        // Act: Register a directive in this environment
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

        // Act: Create, register, and run a directive
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        $response = $this->runDirective('calculator', ['add', '5', '3']);

        // Assert: Verify successful execution
        $this->assertSame(ExitCode::SUCCESS, $response->getExitCode());
        $this->assertStringContainsString('8', $response->getOutput());
    }

    public function test_multiple_directive_testing_initializations(): void
    {
        // Arrange: Initialize once
        $this->initDirectiveTesting();
        $firstTempDir = $this->directiveTempDir;

        // Act: Initialize again (should be idempotent)
        $this->initDirectiveTesting();

        // Assert: Verify the same temporary directory is used
        $this->assertSame($firstTempDir, $this->directiveTempDir);
    }
}
