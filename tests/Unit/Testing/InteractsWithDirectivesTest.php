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
        $this->initDirectiveTesting();
    }

    protected function tearDown(): void
    {
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }

    public function test_register_directive(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $this->assertInstanceOf(TestCalculatorDirective::class, $directive);
    }

    public function test_run_directive_returns_response_object(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        $response = $this->runDirective('calculator', ['add', '5', '3']);

        $this->assertInstanceOf(DirectiveResponse::class, $response);
        $this->assertSame(ExitCode::SUCCESS, $response->getExitCode());
        $this->assertStringContainsString('8', $response->getOutput());
    }

    public function test_create_test_directive_with_closure(): void
    {
        $executed = false;

        $this->createTestDirective('test-closure', function ($d) use (&$executed) {
            $executed = true;
            $d->line('Closure executed');

            return ExitCode::SUCCESS;
        });

        $response = $this->runDirective('test-closure');

        $this->assertTrue($executed, 'La closure n\'a pas été exécutée');
        $this->assertSame(ExitCode::SUCCESS, $response->getExitCode());
        $this->assertStringContainsString('Closure executed', $response->getOutput());
    }

    public function test_run_and_assert_helper(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $response = $this->runAndAssert(TestCalculatorDirective::class, ['add', '10', '5']);
        $this->assertStringContainsString('15', $response->getOutput());
    }

    public function test_response_assert_output_contains(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $this->runDirective('calculator', ['mul', '4', '5'])
            ->assertSuccess()
            ->assertOutputContains('20');
    }

    public function test_response_assert_output_not_contains(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $this->runDirective('calculator', ['mul', '4', '5'])
            ->assertSuccess()
            ->assertOutputNotContains('999');
    }

    public function test_response_assert_output_matches(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $this->runDirective('calculator', ['pow', '2', '8'])
            ->assertSuccess()
            ->assertOutputMatches('/256/');
    }

    public function test_directive_not_found_returns_not_found(): void
    {
        $response = $this->runDirective('non-existent-directive');
        $this->assertSame(ExitCode::NOT_FOUND, $response->getExitCode());
        $this->assertStringContainsString('not found', $response->getOutput());
    }

    public function test_chained_assertions(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $this->runDirective('calculator', ['add', '100', '50'])
            ->assertSuccess()
            ->assertOutputContains('150');
    }

    public function test_clear_registered_directives(): void
    {
        $uniqueName = 'temp-directive-' . uniqid();

        $this->createTestDirective($uniqueName, function ($d) {
            $d->line('Temp directive executed');

            return ExitCode::SUCCESS;
        });

        $response = $this->runDirective($uniqueName);
        $this->assertSame(ExitCode::SUCCESS, $response->getExitCode(), 'La directive temporaire n\'a pas fonctionné');
        $this->assertStringContainsString('Temp directive executed', $response->getOutput());

        $this->clearRegisteredDirectives();

        $response = $this->runDirective($uniqueName);
        $this->assertSame(ExitCode::NOT_FOUND, $response->getExitCode(), 'La directive devrait être introuvable après nettoyage');
        $this->assertStringContainsString('not found', $response->getOutput());

        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
    }

    public function test_calculator_add_operation(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $this->runDirective('calculator', ['add', '15', '25'])
            ->assertSuccess()
            ->assertOutputContains('40');
    }

    public function test_calculator_subtract_operation(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $this->runDirective('calculator', ['sub', '100', '30'])
            ->assertSuccess()
            ->assertOutputContains('70');
    }

    public function test_calculator_multiply_operation(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $this->runDirective('calculator', ['mul', '12', '12'])
            ->assertSuccess()
            ->assertOutputContains('144');
    }

    public function test_calculator_division_operation(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $this->runDirective('calculator', ['div', '100', '4'])
            ->assertSuccess()
            ->assertOutputContains('25');
    }

    public function test_calculator_power_operation(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $this->runDirective('calculator', ['pow', '3', '4'])
            ->assertSuccess()
            ->assertOutputContains('81');
    }

    public function test_calculator_modulo_operation(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $this->runDirective('calculator', ['mod', '17', '5'])
            ->assertSuccess()
            ->assertOutputContains('2');
    }

    public function test_calculator_division_by_zero_returns_failure(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $response = $this->runDirective('calculator', ['div', '10', '0']);

        // Selon ce que retourne réellement la directive
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->getExitCode());
        $this->assertStringContainsString('Division by zero', $response->getOutput());
    }

    public function test_calculator_invalid_operation_returns_invalid_argument(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $response = $this->runDirective('calculator', ['invalid_op', '10', '5']);
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->getExitCode());
        $this->assertStringContainsString('Unknown operation', $response->getOutput());
    }

    public function test_calculator_missing_required_argument(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $response = $this->runDirective('calculator', ['add']);
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->getExitCode());
        $this->assertStringContainsString('Not enough arguments', $response->getOutput());
    }

    public function test_directive_with_verbose_option(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $this->runDirective('calculator', ['--verbose', 'add', '15', '27'])
            ->assertSuccess()
            ->assertOutputContains('42')
            ->assertOutputContains('Operation: add');
    }

    public function test_init_directive_testing_with_boot_laravel(): void
    {
        $this->destroyDirectiveTesting();
        $this->initDirectiveTesting(bootLaravel: true);

        $this->assertFileExists($this->directiveTempDir . '/bootstrap/app.php');
        $this->assertFileExists($this->directiveTempDir . '/config/app.php');
        $this->assertDirectoryExists($this->directiveTempDir . '/storage');

        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);
        $this->assertInstanceOf(TestCalculatorDirective::class, $directive);
    }

    public function test_run_directive_with_boot_laravel_enabled(): void
    {
        $this->destroyDirectiveTesting();
        $this->initDirectiveTesting(bootLaravel: true);

        $directive = new TestCalculatorDirective($this->interaction);
        $this->registerDirective($directive);

        $response = $this->runDirective('calculator', ['add', '5', '3']);

        $this->assertSame(ExitCode::SUCCESS, $response->getExitCode());
        $this->assertStringContainsString('8', $response->getOutput());
    }

    public function test_multiple_directive_testing_initializations(): void
    {
        $this->initDirectiveTesting();
        $firstTempDir = $this->directiveTempDir;

        $this->initDirectiveTesting();

        $this->assertSame($firstTempDir, $this->directiveTempDir);
    }
}
