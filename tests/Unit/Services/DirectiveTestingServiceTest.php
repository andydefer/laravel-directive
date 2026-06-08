<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveResponseRecord;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Tests\Fixtures\Directives\AnotherTestDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestCalculatorDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestEchoDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
final class DirectiveTestingServiceTest extends UnitTestCase
{
    private DirectiveTestingService $testingService;
    private DirectiveConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange
        $this->config = DirectiveConfig::default();
        $this->testingService = new DirectiveTestingService($this->config);
        $this->testingService->initialize();
    }

    protected function tearDown(): void
    {
        // Act
        $this->testingService->destroy();
        parent::tearDown();
    }

    public function test_initialize_creates_testing_environment(): void
    {
        // Arrange - Nothing to arrange, service already initialized in setUp

        // Act - Get the temp directory and check initialization
        $isInitialized = $this->testingService->isInitialized();
        $tempDir = $this->testingService->getTempDirectory();

        // Assert
        $this->assertTrue($isInitialized);
        $this->assertNotNull($tempDir);
        $this->assertDirectoryExists($tempDir);
    }

    public function test_initialize_is_idempotent(): void
    {
        // Arrange
        $firstTempDir = $this->testingService->getTempDirectory();

        // Act
        $this->testingService->initialize();
        $secondTempDir = $this->testingService->getTempDirectory();

        // Assert
        $this->assertSame($firstTempDir, $secondTempDir);
    }

    public function test_destroy_cleans_up_testing_environment(): void
    {
        // Arrange
        $tempDir = $this->testingService->getTempDirectory();

        // Act
        $this->testingService->destroy();

        // Assert
        $this->assertFalse($this->testingService->isInitialized());
        $this->assertDirectoryDoesNotExist($tempDir);
    }

    public function test_register_directive(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);

        // Act
        $this->testingService->registerDirective($directive);
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['add', '5', '3']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('8', $response->output);
    }

    public function test_register_multiple_directives(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive1 = new TestCalculatorDirective($realInteraction);
        $directive2 = new AnotherTestDirective($realInteraction);

        // Act
        $this->testingService->registerDirectives([$directive1, $directive2]);
        $response1 = $this->testingService->runDirective(TestCalculatorDirective::class, ['add', '5', '3']);
        $response2 = $this->testingService->runDirective(AnotherTestDirective::class);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response1->exitCode);
        $this->assertStringContainsString('8', $response1->output);
        $this->assertSame(ExitCode::SUCCESS, $response2->exitCode);
    }

    public function test_clear_registered_directives(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        $responseBefore = $this->testingService->runDirective(TestCalculatorDirective::class, ['add', '5', '3']);
        $this->assertSame(ExitCode::SUCCESS, $responseBefore->exitCode);

        // Act
        $this->testingService->clearRegisteredDirectives();
        $responseAfter = $this->testingService->runDirective(TestCalculatorDirective::class, ['add', '5', '3']);

        // Assert
        $this->assertSame(ExitCode::NOT_FOUND, $responseAfter->exitCode);
    }

    public function test_create_test_directive_with_closure(): void
    {
        // Arrange
        $executed = false;
        $expectedOutput = 'Hello World';

        // Act
        $directive = $this->testingService->createTestDirective(
            'test:greet {name}',
            function ($d) use (&$executed, $expectedOutput) {
                $executed = true;
                $d->line($expectedOutput);
                return ExitCode::SUCCESS;
            }
        );

        $response = $this->testingService->runDirective(get_class($directive), ['John']);

        // Assert
        $this->assertTrue($executed);
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString($expectedOutput, $response->output);
    }

    public function test_run_directive_by_class_name_with_registered_instance(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['add', '10', '5']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('15', $response->output);
    }

    public function test_run_directive_returns_response_record(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['add', '5', '3']);

        // Assert
        $this->assertInstanceOf(DirectiveResponseRecord::class, $response);
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertIsString($response->output);
    }

    public function test_run_directive_with_invalid_class_name_returns_not_found(): void
    {
        // Arrange - No directive registered

        // Act
        $response = $this->testingService->runDirective('NonExistentDirective');

        // Assert
        $this->assertSame(ExitCode::NOT_FOUND, $response->exitCode);
        $this->assertStringContainsString('not found', $response->output);
    }

    public function test_get_buffer_level(): void
    {
        // Arrange
        $level = $this->testingService->getBufferLevel();

        // Act
        $this->testingService->runDirective('NonExistentDirective');
        $levelAfter = $this->testingService->getBufferLevel();

        // Assert
        $this->assertIsInt($level);
        $this->assertIsInt($levelAfter);
    }

    public function test_initialize_with_laravel_boot_creates_laravel_structure(): void
    {
        // Arrange
        $this->testingService->destroy();

        // Act
        $this->testingService->initialize(bootLaravel: true);
        $tempDir = $this->testingService->getTempDirectory();

        // Assert
        $this->assertFileExists($tempDir . '/bootstrap/app.php');
        $this->assertFileExists($tempDir . '/config/app.php');
        $this->assertDirectoryExists($tempDir . '/storage');
        $this->assertDirectoryExists($tempDir . '/storage/framework');
        $this->assertDirectoryExists($tempDir . '/storage/framework/views');
        $this->assertDirectoryExists($tempDir . '/storage/framework/cache');
        $this->assertDirectoryExists($tempDir . '/app');
        $this->assertDirectoryExists($tempDir . '/app/Http');
        $this->assertDirectoryExists($tempDir . '/app/Models');
    }

    public function test_get_laravel_application_returns_instance_when_booted(): void
    {
        // Arrange
        $this->testingService->destroy();
        $this->testingService->initialize(bootLaravel: true);

        // Act
        $laravelApp = $this->testingService->getLaravelApplication();

        // Assert
        $this->assertNotNull($laravelApp);
        $this->assertInstanceOf(\Illuminate\Foundation\Application::class, $laravelApp);
    }

    public function test_get_laravel_application_returns_null_when_not_booted(): void
    {
        // Arrange
        $this->testingService->destroy();
        $this->testingService->initialize(bootLaravel: false);

        // Act
        $laravelApp = $this->testingService->getLaravelApplication();

        // Assert
        $this->assertNull($laravelApp);
    }

    public function test_calculator_add_operation(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['add', '15', '25']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('40', $response->output);
    }

    public function test_calculator_subtract_operation(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['sub', '100', '30']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('70', $response->output);
    }

    public function test_calculator_multiply_operation(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['mul', '12', '12']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('144', $response->output);
    }

    public function test_calculator_division_operation(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['div', '100', '4']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('25', $response->output);
    }

    public function test_calculator_power_operation(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['pow', '3', '4']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('81', $response->output);
    }

    public function test_calculator_modulo_operation(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['mod', '17', '5']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('2', $response->output);
    }

    public function test_calculator_division_by_zero_returns_failure(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['div', '10', '0']);

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Division by zero', $response->output);
    }

    public function test_calculator_invalid_operation_returns_invalid_argument(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['invalid_op', '10', '5']);

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Unknown operation', $response->output);
    }

    public function test_calculator_missing_required_argument_returns_invalid_argument(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['add']);

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Not enough arguments', $response->output);
    }

    public function test_directive_with_verbose_option(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['--verbose', 'add', '15', '27']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('42', $response->output);
        $this->assertStringContainsString('Operation: add', $response->output);
    }

    public function test_response_output_contains_expected_value(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['mul', '4', '5']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('20', $response->output);
    }

    public function test_response_output_does_not_contain_unexpected_value(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['mul', '4', '5']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringNotContainsString('999', $response->output);
    }

    public function test_response_output_matches_pattern(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['pow', '2', '8']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertMatchesRegularExpression('/256/', $response->output);
    }

    public function test_multiple_assertions_on_same_response(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestCalculatorDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestCalculatorDirective::class, ['add', '100', '50']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('150', $response->output);
        $this->assertStringNotContainsString('error', $response->output);
    }

    public function test_run_directive_with_echo_directive(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestEchoDirective($realInteraction);
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestEchoDirective::class, ['Hello World']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Hello World', $response->output);
    }

    public function test_run_directive_with_custom_exit_code(): void
    {
        // Arrange
        $realInteraction = $this->getRealInteraction();
        $directive = new TestDirective(
            $realInteraction,
            'test:custom-exit',
            'Test with custom exit code',
            ExitCode::INVALID_ARGUMENT
        );
        $this->testingService->registerDirective($directive);

        // Act
        $response = $this->testingService->runDirective(TestDirective::class);

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
    }

    public function test_ensure_initialized_throws_exception_when_not_initialized(): void
    {
        // Arrange
        $this->testingService->destroy();

        $reflection = new \ReflectionClass($this->testingService);
        $method = $reflection->getMethod('ensureInitialized');

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Directive testing environment not initialized. Call initialize() first.');

        // Act
        $method->invoke($this->testingService);
    }

    public function test_run_directive_with_closure_that_throws_exception(): void
    {
        // Arrange
        $directive = $this->testingService->createTestDirective(
            'test:error',
            function ($d) {
                throw new \Exception('Test exception');
            }
        );

        // Act
        $response = $this->testingService->runDirective(get_class($directive));

        // Assert
        $this->assertSame(ExitCode::FAILURE, $response->exitCode);
        $this->assertStringContainsString('Test exception', $response->output);
    }

    public function test_run_directive_with_closure_that_returns_custom_exit_code(): void
    {
        // Arrange
        $directive = $this->testingService->createTestDirective(
            'test:custom-exit',
            function ($d) {
                $d->line('Custom exit');
                return ExitCode::INVALID_ARGUMENT;
            }
        );

        // Act
        $response = $this->testingService->runDirective(get_class($directive));

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Custom exit', $response->output);
    }

    public function test_run_directive_with_arguments_and_options(): void
    {
        // Arrange
        $executed = false;
        $directive = $this->testingService->createTestDirective(
            'test:command {name} {--greeting=Hello}',
            function ($d) use (&$executed) {
                $executed = true;
                $name = $d->argument('name');
                $greeting = $d->option('greeting');
                $d->line("{$greeting}, {$name}!");
                return ExitCode::SUCCESS;
            }
        );

        // Act
        $response = $this->testingService->runDirective(
            get_class($directive),
            ['John', '--greeting=Bonjour']
        );

        // Assert
        $this->assertTrue($executed);
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Bonjour, John!', $response->output);
    }

    public function test_get_temp_directory_returns_string_when_initialized(): void
    {
        // Arrange - Service already initialized in setUp

        // Act
        $tempDir = $this->testingService->getTempDirectory();

        // Assert
        $this->assertIsString($tempDir);
        $this->assertNotEmpty($tempDir);
    }

    public function test_get_temp_directory_returns_null_when_not_initialized(): void
    {
        // Arrange
        $this->testingService->destroy();

        // Act
        $tempDirAfter = $this->testingService->getTempDirectory();

        // Assert
        $this->assertNull($tempDirAfter);
    }

    /**
     * Get a real DirectiveInteractionService from the testing service
     */
    private function getRealInteraction(): \AndyDefer\Directive\Services\DirectiveInteractionService
    {
        $reflection = new \ReflectionClass($this->testingService);
        $interactionProperty = $reflection->getProperty('interaction');
        return $interactionProperty->getValue($this->testingService);
    }
}
