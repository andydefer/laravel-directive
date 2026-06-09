<?php
// tests/Unit/Services/DirectiveTestingServiceTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestCalculatorDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestGreetingDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
final class DirectiveTestingServiceTest extends UnitTestCase
{
    private DirectiveTestingService $service;
    private DirectiveTestingContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $config = new DirectiveTestingConfig();
        $this->context = new DirectiveTestingContext(false);
        $this->context->setConfig($config);
        $this->service = new DirectiveTestingService($this->context);
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    // ==================== Registration Tests ====================

    public function test_register_directive(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());

        // Act
        $this->service->registerDirective($directive);

        // Assert
        $directiveFromRegistry = $this->context->getRegistry()->getDirective(TestCalculatorDirective::class);
        $this->assertInstanceOf(TestCalculatorDirective::class, $directiveFromRegistry);
    }

    public function test_register_multiple_directives(): void
    {
        // Arrange
        $calculatorDirective = new TestCalculatorDirective($this->service->getInteraction());
        $greetingDirective = new TestGreetingDirective($this->service->getInteraction());

        // Act
        $this->service->registerDirectives([$calculatorDirective, $greetingDirective]);

        // Assert
        $allDirectives = $this->context->getRegistry()->getAllDirectives();
        $this->assertCount(2, $allDirectives);
    }

    public function test_clear_registered_directives(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);
        $this->assertCount(1, $this->context->getRegistry()->getAllDirectives());

        // Act
        $this->service->clearRegisteredDirectives();

        // Assert
        $this->assertCount(0, $this->context->getRegistry()->getAllDirectives());
    }

    // ==================== Run Directive Tests ====================

    public function test_run_directive_returns_success_response(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $response = $this->service->runDirective('calculator', ['add', '5', '3']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('8', $response->output);
    }

    public function test_run_directive_by_class_name(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $response = $this->service->runDirective(TestCalculatorDirective::class, ['add', '10', '5']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('15', $response->output);
    }

    public function test_run_non_existent_directive_returns_not_found(): void
    {
        // Act
        $response = $this->service->runDirective('non-existent-directive');

        // Assert
        $this->assertSame(ExitCode::NOT_FOUND, $response->exitCode);
        $this->assertStringContainsString('not found', $response->output);
    }

    // ==================== Create Test Directive Tests ====================

    public function test_create_test_directive_with_closure(): void
    {
        // Arrange
        $executed = false;

        // Act
        $directive = $this->service->createTestDirective('test-closure', function ($d) use (&$executed) {
            $executed = true;
            $d->line('Closure executed');
            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('test-closure');

        // Assert
        $this->assertTrue($executed);
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Closure executed', $response->output);
    }

    public function test_create_test_directive_returns_failure_on_error(): void
    {
        // Arrange
        $this->service->createTestDirective('test-error', function () {
            return ExitCode::FAILURE;
        });

        // Act
        $response = $this->service->runDirective('test-error');

        // Assert
        $this->assertSame(ExitCode::FAILURE, $response->exitCode);
    }

    // ==================== Calculator Operation Tests ====================

    public function test_calculator_add_operation(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $response = $this->service->runDirective('calculator', ['add', '15', '25']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('40', $response->output);
    }

    public function test_calculator_subtract_operation(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $response = $this->service->runDirective('calculator', ['sub', '100', '30']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('70', $response->output);
    }

    public function test_calculator_multiply_operation(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $response = $this->service->runDirective('calculator', ['mul', '12', '12']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('144', $response->output);
    }

    public function test_calculator_division_operation(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $response = $this->service->runDirective('calculator', ['div', '100', '4']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('25', $response->output);
    }

    public function test_calculator_power_operation(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $response = $this->service->runDirective('calculator', ['pow', '3', '4']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('81', $response->output);
    }

    public function test_calculator_modulo_operation(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $response = $this->service->runDirective('calculator', ['mod', '17', '5']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('2', $response->output);
    }

    // ==================== Error Handling Tests ====================

    public function test_calculator_division_by_zero_returns_invalid_argument(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $response = $this->service->runDirective('calculator', ['div', '10', '0']);

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Division by zero', $response->output);
    }

    public function test_calculator_invalid_operation_returns_invalid_argument(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $response = $this->service->runDirective('calculator', ['invalid_op', '10', '5']);

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Unknown operation', $response->output);
    }

    public function test_calculator_missing_required_argument_returns_invalid_argument(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $response = $this->service->runDirective('calculator', ['add']);

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Not enough arguments', $response->output);
    }

    // ==================== Context Tracking Tests ====================

    public function test_context_tracks_executed_directives(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $this->service->runDirective('calculator', ['add', '1', '2']);

        // Assert
        $this->assertTrue($this->context->hasBeenExecuted('calculator'));
        $this->assertEquals(1, $this->context->getExecutedDirectivesCount());
    }

    public function test_context_tracks_created_temp_directory(): void
    {
        // Assert
        $this->assertTrue($this->context->hasTempDir());
        $this->assertNotNull($this->context->getTempDir());
        $this->assertDirectoryExists($this->context->getTempDir());
    }

    public function test_context_tracks_step_results(): void
    {
        // Act
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);
        $this->service->runDirective('calculator', ['add', '1', '2']);

        // Assert
        $this->assertGreaterThan(0, $this->context->getStepsExecutedCount());
        $this->assertTrue($this->context->hasStepResult('create_temp_directory'));
        $this->assertTrue($this->context->hasStepResult('build_container'));
    }

    // ==================== Verbose Option Tests ====================

    public function test_directive_with_verbose_option(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $response = $this->service->runDirective('calculator', ['--verbose', 'add', '15', '27']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('42', $response->output);
        $this->assertStringContainsString('Operation: add', $response->output);
    }

    // ==================== Service Lifecycle Tests ====================

    public function test_service_can_be_destroyed_and_recreated(): void
    {
        // Arrange
        $tempDir = $this->context->getTempDir();

        // Act
        $this->service->destroy();

        // Assert
        $this->assertNull($this->context->getTempDir());
        $this->assertFileDoesNotExist($tempDir);

        // Recreate
        $newConfig = new DirectiveTestingConfig();
        $newContext = new DirectiveTestingContext(false);
        $newContext->setConfig($newConfig);
        $newService = new DirectiveTestingService($newContext);

        // Assert new service works
        $directive = new TestCalculatorDirective($newService->getInteraction());
        $newService->registerDirective($directive);
        $response = $newService->runDirective('calculator', ['add', '1', '2']);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('3', $response->output);

        $newService->destroy();
    }

    public function test_service_is_idempotent(): void
    {
        // Arrange
        $firstTempDir = $this->context->getTempDir();

        // Act - create a second service
        $newConfig = new DirectiveTestingConfig();
        $newContext = new DirectiveTestingContext(false);
        $newContext->setConfig($newConfig);
        $newService = new DirectiveTestingService($newContext);
        $secondTempDir = $newContext->getTempDir();

        // Assert - different services have different temp directories
        $this->assertNotEquals($firstTempDir, $secondTempDir);

        $newService->destroy();
    }

    // ==================== Edge Cases Tests ====================

    public function test_run_directive_with_empty_arguments(): void
    {
        // Arrange
        $this->service->createTestDirective('test-empty', function ($d) {
            $d->line('No arguments received');
            return ExitCode::SUCCESS;
        });

        // Act
        $response = $this->service->runDirective('test-empty', []);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('No arguments received', $response->output);
    }

    public function test_run_directive_with_special_characters_in_arguments(): void
    {
        // Arrange
        $this->service->createTestDirective('test-special', function ($d) {
            $args = $d->argument('args') ?? '';
            $d->line("Received: {$args}");
            return ExitCode::SUCCESS;
        });

        // Act
        $response = $this->service->runDirective('test-special', ['hello-world', 'foo_bar', 'test@domain.com']);
        dump($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    }

    public function test_create_multiple_test_directives(): void
    {
        // Arrange
        $executed = [];

        // Act
        $this->service->createTestDirective('test-1', function ($d) use (&$executed) {
            $executed[] = 'test-1';
            $d->line('Test 1');
            return ExitCode::SUCCESS;
        });

        $this->service->createTestDirective('test-2', function ($d) use (&$executed) {
            $executed[] = 'test-2';
            $d->line('Test 2');
            return ExitCode::SUCCESS;
        });

        // Assert
        $response1 = $this->service->runDirective('test-1');
        $response2 = $this->service->runDirective('test-2');

        $this->assertSame(ExitCode::SUCCESS, $response1->exitCode);
        $this->assertSame(ExitCode::SUCCESS, $response2->exitCode);
        $this->assertContains('test-1', $executed);
        $this->assertContains('test-2', $executed);
    }

    // ==================== Response Content Tests ====================

    public function test_response_output_contains_expected_value(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $response = $this->service->runDirective('calculator', ['mul', '4', '5']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('20', $response->output);
    }

    public function test_response_output_does_not_contain_unexpected_value(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $response = $this->service->runDirective('calculator', ['mul', '4', '5']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringNotContainsString('999', $response->output);
    }

    public function test_response_output_matches_pattern(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $response = $this->service->runDirective('calculator', ['pow', '2', '8']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertMatchesRegularExpression('/256/', $response->output);
    }

    public function test_multiple_assertions_on_same_response(): void
    {
        // Arrange
        $directive = new TestCalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        // Act
        $response = $this->service->runDirective('calculator', ['add', '100', '50']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('150', $response->output);
        $this->assertStringNotContainsString('error', $response->output);
        $this->assertIsString($response->output);
    }

    // ==================== Get Interaction Tests ====================

    public function test_get_interaction_returns_interaction_service(): void
    {
        // Act
        $interaction = $this->service->getInteraction();

        // Assert
        $this->assertNotNull($interaction);
        $this->assertInstanceOf(\AndyDefer\Directive\Services\DirectiveInteractionService::class, $interaction);
    }

    public function test_get_context_returns_context(): void
    {
        // Act
        $context = $this->service->getContext();

        // Assert
        $this->assertSame($this->context, $context);
    }
}
