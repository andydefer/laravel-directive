<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Testing;

use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Testing\InteractsWithDirectives;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestCalculatorDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
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

    private function createCalculatorDirective(): TestCalculatorDirective
    {
        $context = new DirectiveContext(
            laravelBootstrapper: $this->laravelBootstrapperContext ?? new LaravelBootstrapperContext,
            blueprint: new DirectiveBlueprintRecord(TestCalculatorDirective::class, 'calculator {operation} {a} {b} {--verbose}', 'Test calculator directive'),
            aliases: new StringTypedCollection,
            shouldBootLaravel: false,
        );
        return new TestCalculatorDirective($context, $this->interaction);
    }

    public function test_register_directive(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();

        // Act
        $this->registerDirective($directive);

        // Assert
        $this->assertInstanceOf(TestCalculatorDirective::class, $directive);
    }

    public function test_run_directive_returns_response_object(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective('calculator', ['add', '5', '3']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('8', $response->output);
    }

    public function test_create_test_directive_with_closure(): void
    {
        // Arrange
        $executed = false;

        // Act
        $this->createTestDirective('test-closure', function ($d) use (&$executed) {
            $executed = true;
            $d->line('Closure executed');
            return ExitCode::SUCCESS;
        });

        $response = $this->runDirective('test-closure');

        // Assert
        $this->assertTrue($executed, 'The closure was not executed');
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Closure executed', $response->output);
    }

    public function test_run_directive_by_class_name(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective(TestCalculatorDirective::class, ['add', '10', '5']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('15', $response->output);
    }

    public function test_response_output_contains_expected_value(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective('calculator', ['mul', '4', '5']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('20', $response->output);
    }

    public function test_response_output_does_not_contain_unexpected_value(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective('calculator', ['mul', '4', '5']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringNotContainsString('999', $response->output);
    }

    public function test_response_output_matches_pattern(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective('calculator', ['pow', '2', '8']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertMatchesRegularExpression('/256/', $response->output);
    }

    public function test_directive_not_found_returns_not_found(): void
    {
        // Arrange - no directive registered

        // Act
        $response = $this->runDirective('non-existent-directive');

        // Assert
        $this->assertSame(ExitCode::NOT_FOUND, $response->exitCode);
        $this->assertStringContainsString('not found', $response->output);
    }

    public function test_multiple_assertions_on_same_response(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective('calculator', ['add', '100', '50']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('150', $response->output);
    }

    public function test_clear_registered_directives(): void
    {
        // Arrange
        $uniqueName = 'temp-directive-' . uniqid();

        $this->createTestDirective($uniqueName, function ($d) {
            $d->line('Temp directive executed');
            return ExitCode::SUCCESS;
        });

        // Assert directive exists before clearing
        $responseBefore = $this->runDirective($uniqueName);
        $this->assertSame(ExitCode::SUCCESS, $responseBefore->exitCode);

        // Act
        $this->clearRegisteredDirectives();

        // Assert directive no longer exists
        $responseAfter = $this->runDirective($uniqueName);
        $this->assertSame(ExitCode::NOT_FOUND, $responseAfter->exitCode);
    }

    public function test_calculator_add_operation(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective('calculator', ['add', '15', '25']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('40', $response->output);
    }

    public function test_calculator_subtract_operation(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective('calculator', ['sub', '100', '30']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('70', $response->output);
    }

    public function test_calculator_multiply_operation(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective('calculator', ['mul', '12', '12']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('144', $response->output);
    }

    public function test_calculator_division_operation(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective('calculator', ['div', '100', '4']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('25', $response->output);
    }

    public function test_calculator_power_operation(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective('calculator', ['pow', '3', '4']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('81', $response->output);
    }

    public function test_calculator_modulo_operation(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective('calculator', ['mod', '17', '5']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('2', $response->output);
    }

    public function test_calculator_division_by_zero_returns_failure(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective('calculator', ['div', '10', '0']);

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Division by zero', $response->output);
    }

    public function test_calculator_invalid_operation_returns_invalid_argument(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective('calculator', ['invalid_op', '10', '5']);

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Unknown operation', $response->output);
    }

    public function test_calculator_missing_required_argument(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective('calculator', ['add']);

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Not enough arguments', $response->output);
    }

    public function test_directive_with_verbose_option(): void
    {
        // Arrange
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective('calculator', ['--verbose', 'add', '15', '27']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('42', $response->output);
        $this->assertStringContainsString('Operation: add', $response->output);
    }

    public function test_init_directive_testing_with_boot_laravel(): void
    {
        // Arrange
        $this->destroyDirectiveTesting();

        // Act
        $this->initDirectiveTesting(bootLaravel: true);

        // Assert
        $this->assertFileExists($this->directiveTempDir . '/bootstrap/app.php');
        $this->assertFileExists($this->directiveTempDir . '/config/app.php');
        $this->assertDirectoryExists($this->directiveTempDir . '/storage');

        // Act & Assert - verify directive works
        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);
        $this->assertInstanceOf(TestCalculatorDirective::class, $directive);
    }

    public function test_run_directive_with_boot_laravel_enabled(): void
    {
        // Arrange
        $this->destroyDirectiveTesting();
        $this->initDirectiveTesting(bootLaravel: true);

        $directive = $this->createCalculatorDirective();
        $this->registerDirective($directive);

        // Act
        $response = $this->runDirective('calculator', ['add', '5', '3']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('8', $response->output);
    }

    public function test_multiple_directive_testing_initializations(): void
    {
        // Arrange
        $this->initDirectiveTesting();
        $firstTempDir = $this->directiveTempDir;

        // Act
        $this->initDirectiveTesting();

        // Assert
        $this->assertSame($firstTempDir, $this->directiveTempDir);
    }
}
