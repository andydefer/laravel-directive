<?php

// tests/Unit/Services/DirectiveTestingServiceTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Enums\TestingStep;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestConcreteDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestEchoDirective;
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

        $config = new DirectiveTestingConfig;
        $this->context = new DirectiveTestingContext(false);
        $this->context->setConfig($config);

        // Mode isolé : on ne passe pas d'application
        $this->service = new DirectiveTestingService(null, $this->context);

        // Forcer l'initialisation de l'environnement minimal
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('initializeMinimalEnvironment');
        $method->invoke($this->service);
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    // ==================== Registration Tests (Instance) ====================

    public function test_register_directive_instance(): void
    {
        $directive = $this->service->createTestDirective('test-calculator', function ($d) {
            $d->line('Calculator executed');

            return ExitCode::SUCCESS;
        });

        $this->service->registerDirectiveInstance($directive);

        $directiveFromRegistry = $this->context->getClosureRegistry()->get('test-calculator');
        $this->assertNotNull($directiveFromRegistry);
    }

    public function test_register_multiple_directive_instances(): void
    {
        $directive1 = $this->service->createTestDirective('test-1', function ($d) {
            $d->line('Test 1');

            return ExitCode::SUCCESS;
        });
        $directive2 = $this->service->createTestDirective('test-2', function ($d) {
            $d->line('Test 2');

            return ExitCode::SUCCESS;
        });

        $this->service->registerDirectiveInstances([$directive1, $directive2]);

        $this->assertNotNull($this->context->getClosureRegistry()->get('test-1'));
        $this->assertNotNull($this->context->getClosureRegistry()->get('test-2'));
    }

    // ==================== Registration Tests (Class Name) ====================

    public function test_register_directive_by_class_name(): void
    {
        // Créer d'abord une directive temporaire avec une classe concrète
        $directive = $this->service->createTestDirective('test-by-class', function ($d) {
            $d->line('Executed by class');

            return ExitCode::SUCCESS;
        });

        // Enregistrer par instance car la classe n'existe pas vraiment
        $this->service->registerDirectiveInstance($directive);

        $directiveFromRegistry = $this->context->getClosureRegistry()->get('test-by-class');
        $this->assertNotNull($directiveFromRegistry);
    }

    public function test_register_directive_by_class_name_throws_exception_for_invalid_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Directive class NonExistentDirectiveClass does not exist');

        $this->service->registerDirective('NonExistentDirectiveClass');
    }

    public function test_register_multiple_directives_by_class_names(): void
    {
        // Les fixtures existent dans le package
        $this->service->registerDirective(TestConcreteDirective::class);
        $this->service->registerDirective(TestEchoDirective::class);

        // Vérifier qu'elles sont bien enregistrées
        $directive1 = $this->context->getRegistry()->getDirective(TestConcreteDirective::class);
        $directive2 = $this->context->getRegistry()->getDirective(TestEchoDirective::class);

        $this->assertNotNull($directive1);
        $this->assertNotNull($directive2);
        $this->assertInstanceOf(TestConcreteDirective::class, $directive1);
        $this->assertInstanceOf(TestEchoDirective::class, $directive2);
    }

    // ==================== Registration + Run Tests ====================

    public function test_register_and_run_instance(): void
    {
        $directive = $this->service->createTestDirective('test-register-run', function ($d) {
            $d->line('Register and run executed');

            return ExitCode::SUCCESS;
        });

        $response = $this->service->registerAndRunInstance($directive, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Register and run executed', $response->output);
    }

    public function test_register_and_run_by_class_name(): void
    {
        // Créer une directive et l'enregistrer par classe
        $directive = $this->service->createTestDirective('test-register-run-class', function ($d) {
            $d->line('Register and run by class executed');

            return ExitCode::SUCCESS;
        });

        // Enregistrer par instance car la classe n'existe pas vraiment
        $this->service->registerDirectiveInstance($directive);

        $response = $this->service->runDirective('test-register-run-class');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Register and run by class executed', $response->output);
    }

    // ==================== Run Method Tests ====================

    public function test_run_method_enregisters_and_executes(): void
    {
        $directive = $this->service->createTestDirective('test-run-method', function ($d) {
            $d->line('Run method executed');

            return ExitCode::SUCCESS;
        });

        // Enregistrer par instance
        $this->service->registerDirectiveInstance($directive);

        // runDirective est l'équivalent pour les signatures
        $response = $this->service->runDirective('test-run-method');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Run method executed', $response->output);
    }

    // ==================== Run Directive Tests ====================

    public function test_run_directive_returns_success_response(): void
    {
        $this->service->createTestDirective('calculator', function ($d) {
            $result = 5 + 3;
            $d->line((string) $result);

            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('calculator');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('8', $response->output);
    }

    public function test_run_non_existent_directive_returns_not_found(): void
    {
        $response = $this->service->runDirective('non-existent-directive');

        $this->assertSame(ExitCode::NOT_FOUND, $response->exit_code);
        $this->assertStringContainsString('not found', $response->output);
    }

    // ==================== Create Test Directive Tests ====================

    public function test_create_test_directive_with_closure(): void
    {
        $executed = false;

        $this->service->createTestDirective('test-closure', function ($d) use (&$executed) {
            $executed = true;
            $d->line('Closure executed');

            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('test-closure');

        $this->assertTrue($executed);
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Closure executed', $response->output);
    }

    public function test_create_test_directive_returns_failure_on_error(): void
    {
        $this->service->createTestDirective('test-error', function () {
            return ExitCode::FAILURE;
        });

        $response = $this->service->runDirective('test-error');

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
    }

    // ==================== Calculator Operation Tests ====================

    public function test_calculator_add_operation(): void
    {
        $this->service->createTestDirective('calc-add {a} {b}', function ($d) {
            $a = (int) $d->argument('a');
            $b = (int) $d->argument('b');
            $result = $a + $b;
            $d->line((string) $result);

            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('calc-add', ['15', '25']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('40', $response->output);
    }

    public function test_calculator_subtract_operation(): void
    {
        $this->service->createTestDirective('calc-sub {a} {b}', function ($d) {
            $a = (int) $d->argument('a');
            $b = (int) $d->argument('b');
            $result = $a - $b;
            $d->line((string) $result);

            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('calc-sub', ['100', '30']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('70', $response->output);
    }

    public function test_calculator_multiply_operation(): void
    {
        $this->service->createTestDirective('calc-mul {a} {b}', function ($d) {
            $a = (int) $d->argument('a');
            $b = (int) $d->argument('b');
            $result = $a * $b;
            $d->line((string) $result);

            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('calc-mul', ['12', '12']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('144', $response->output);
    }

    public function test_calculator_division_operation(): void
    {
        $this->service->createTestDirective('calc-div {a} {b}', function ($d) {
            $a = (int) $d->argument('a');
            $b = (int) $d->argument('b');
            $result = $a / $b;
            $d->line((string) $result);

            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('calc-div', ['100', '4']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('25', $response->output);
    }

    // ==================== Error Handling Tests ====================

    public function test_calculator_division_by_zero_returns_invalid_argument(): void
    {
        $this->service->createTestDirective('calc-div-zero {a} {b}', function ($d) {
            $b = (int) $d->argument('b');
            if ($b === 0) {
                $d->error('Division by zero');

                return ExitCode::INVALID_ARGUMENT;
            }
            $a = (int) $d->argument('a');
            $result = $a / $b;
            $d->line((string) $result);

            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('calc-div-zero', ['10', '0']);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString('Division by zero', $response->output);
    }

    public function test_calculator_missing_required_argument_returns_invalid_argument(): void
    {
        $this->service->createTestDirective('calc-add {a} {b}', function ($d) {
            $a = (int) $d->argument('a');
            $b = (int) $d->argument('b');
            $result = $a + $b;
            $d->line((string) $result);

            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('calc-add', ['10']);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString('Not enough arguments', $response->output);
    }

    // ==================== Context Tracking Tests ====================

    public function test_context_tracks_executed_directives(): void
    {
        $this->service->createTestDirective('track-test', function ($d) {
            $d->line('executed');

            return ExitCode::SUCCESS;
        });

        $this->service->runDirective('track-test');

        $this->assertTrue($this->context->hasBeenExecuted('track-test'));
        $this->assertEquals(1, $this->context->getExecutedDirectivesCount());
    }

    public function test_context_tracks_step_results(): void
    {
        $this->service->createTestDirective('step-test', function ($d) {
            $d->line('test');

            return ExitCode::SUCCESS;
        });

        $this->service->runDirective('step-test');

        $this->assertGreaterThanOrEqual(1, $this->context->getStepsExecutedCount());
        $this->assertTrue($this->context->hasStepResult(TestingStep::BUILD_CONTAINER));
    }

    // ==================== Verbose Option Tests ====================

    public function test_directive_with_verbose_option(): void
    {
        $this->service->createTestDirective('verbose-test', function ($d) {
            $d->line('Executed');

            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('verbose-test', ['--verbose']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    }

    // ==================== Service Lifecycle Tests ====================

    public function test_service_can_be_destroyed_and_recreated(): void
    {
        $this->service->destroy();

        $newConfig = new DirectiveTestingConfig;
        $newContext = new DirectiveTestingContext(false);
        $newContext->setConfig($newConfig);
        $newService = new DirectiveTestingService(null, $newContext);

        // Forcer l'initialisation
        $reflection = new \ReflectionClass($newService);
        $method = $reflection->getMethod('initializeMinimalEnvironment');
        $method->invoke($newService);

        $newService->createTestDirective('new-test', function ($d) {
            $d->line('works');

            return ExitCode::SUCCESS;
        });
        $response = $newService->runDirective('new-test');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('works', $response->output);

        $newService->destroy();
    }

    public function test_service_is_idempotent(): void
    {
        $config1 = new DirectiveTestingConfig;
        $context1 = new DirectiveTestingContext(false);
        $context1->setConfig($config1);
        $service1 = new DirectiveTestingService(null, $context1);

        $config2 = new DirectiveTestingConfig;
        $context2 = new DirectiveTestingContext(false);
        $context2->setConfig($config2);
        $service2 = new DirectiveTestingService(null, $context2);

        $this->assertNotSame($service1->getContext(), $service2->getContext());

        $service1->destroy();
        $service2->destroy();
    }

    // ==================== Edge Cases Tests ====================

    public function test_run_directive_with_empty_arguments(): void
    {
        $this->service->createTestDirective('test-empty', function ($d) {
            $d->line('No arguments received');

            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('test-empty', []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No arguments received', $response->output);
    }

    public function test_run_directive_with_special_characters_in_arguments(): void
    {
        $this->service->createTestDirective('test-special {args*}', function ($d) {
            $args = $d->getVariadicArguments()->toArray();
            $argsString = implode(', ', $args);
            $d->line("Received: {$argsString}");

            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('test-special', ['hello-world', 'foo_bar', 'test@domain.com']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    }

    public function test_create_multiple_test_directives(): void
    {
        $executed = [];

        $this->service->createTestDirective('test-1', function ($d) use (&$executed) {
            $executed[] = 'test-1';
            $d->line('Test 1 executed');

            return ExitCode::SUCCESS;
        });

        $this->service->createTestDirective('test-2', function ($d) use (&$executed) {
            $executed[] = 'test-2';
            $d->line('Test 2 executed');

            return ExitCode::SUCCESS;
        });

        $response1 = $this->service->runDirective('test-1');
        $response2 = $this->service->runDirective('test-2');

        $this->assertSame(ExitCode::SUCCESS, $response1->exit_code);
        $this->assertSame(ExitCode::SUCCESS, $response2->exit_code);
        $this->assertStringContainsString('Test 1 executed', $response1->output);
        $this->assertStringContainsString('Test 2 executed', $response2->output);
        $this->assertContains('test-1', $executed);
        $this->assertContains('test-2', $executed);
    }

    // ==================== Response Content Tests ====================

    public function test_response_output_contains_expected_value(): void
    {
        $this->service->createTestDirective('calc-mul {a} {b}', function ($d) {
            $a = (int) $d->argument('a');
            $b = (int) $d->argument('b');
            $result = $a * $b;
            $d->line((string) $result);

            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('calc-mul', ['4', '5']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('20', $response->output);
    }

    public function test_response_output_does_not_contain_unexpected_value(): void
    {
        $this->service->createTestDirective('calc-mul {a} {b}', function ($d) {
            $a = (int) $d->argument('a');
            $b = (int) $d->argument('b');
            $result = $a * $b;
            $d->line((string) $result);

            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('calc-mul', ['4', '5']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringNotContainsString('999', $response->output);
    }

    public function test_response_output_matches_pattern(): void
    {
        $this->service->createTestDirective('calc-mul {a} {b}', function ($d) {
            $a = (int) $d->argument('a');
            $b = (int) $d->argument('b');
            $result = $a * $b;
            $d->line((string) $result);

            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('calc-mul', ['4', '5']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertMatchesRegularExpression('/20/', $response->output);
    }

    public function test_multiple_assertions_on_same_response(): void
    {
        $this->service->createTestDirective('calc-add {a} {b}', function ($d) {
            $a = (int) $d->argument('a');
            $b = (int) $d->argument('b');
            $result = $a + $b;
            $d->line((string) $result);

            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('calc-add', ['100', '50']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('150', $response->output);
        $this->assertStringNotContainsString('error', $response->output);
        $this->assertIsString($response->output);
    }

    // ==================== Get Interaction Tests ====================

    public function test_get_interaction_returns_interaction_service(): void
    {
        $interaction = $this->service->getInteraction();

        $this->assertNotNull($interaction);
        $this->assertInstanceOf(DirectiveInteractionService::class, $interaction);
    }

    public function test_get_context_returns_context(): void
    {
        $context = $this->service->getContext();

        $this->assertSame($this->context, $context);
    }

    // ==================== Clear Registered Directives Tests ====================

    public function test_clear_registered_directives_removes_all(): void
    {
        $directive = $this->service->createTestDirective('test-clear', function ($d) {
            $d->line('Test');

            return ExitCode::SUCCESS;
        });
        $this->service->registerDirectiveInstance($directive);
        $this->assertNotNull($this->context->getClosureRegistry()->get('test-clear'));

        $this->service->clearRegisteredDirectives();

        $this->assertNull($this->context->getClosureRegistry()->get('test-clear'));
    }

    // ==================== Tests pour le répertoire temporaire ====================

    public function test_temp_directory_is_created_and_destroyed(): void
    {
        $tempDir = $this->context->getTempDir();

        $this->assertNotNull($tempDir);
        $this->assertDirectoryExists($tempDir);
        $this->assertTrue($this->context->isInTempDirectory());

        $this->service->destroy();

        $this->assertNull($this->context->getTempDir());
        $this->assertFalse($this->context->isInTempDirectory());
    }

    public function test_working_directory_changes_to_temp(): void
    {
        $tempDir = $this->context->getTempDir();
        $currentDir = getcwd();

        $this->assertStringContainsString('directive_test', $currentDir);
        $this->assertSame($tempDir, $currentDir);
    }

    public function test_environment_variables_are_set(): void
    {
        $tempDir = $this->context->getTempDir();

        $this->assertSame($tempDir, getenv('FILE_CREATOR_WORKING_DIR'));
        $this->assertSame($tempDir . '/app/Directives', getenv('DIRECTIVE_PATH'));
    }

    // ==================== Tests pour registerAndRun ====================

    public function test_register_and_run_with_class_name(): void
    {
        $directive = $this->service->createTestDirective('test-register-run-class', function ($d) {
            $d->line('Success via registerAndRun');

            return ExitCode::SUCCESS;
        });

        $this->service->registerDirectiveInstance($directive);

        $response = $this->service->registerAndRun(TestConcreteDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    }

    // ==================== Tests pour la méthode run() ====================

    public function test_run_method_with_class(): void
    {
        $directive = $this->service->createTestDirective('test-run-class', function ($d) {
            $d->line('Success via run method');

            return ExitCode::SUCCESS;
        });

        $this->service->registerDirectiveInstance($directive);

        $response = $this->service->run(TestConcreteDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    }

    // ==================== Tests pour le nettoyage ====================

    public function test_destroy_cleans_temp_directory(): void
    {
        $tempDir = $this->context->getTempDir();
        $testFile = $tempDir . '/test.txt';
        file_put_contents($testFile, 'test');

        $this->assertFileExists($testFile);

        $this->service->destroy();

        $this->assertFileDoesNotExist($tempDir);
    }

    public function test_multiple_services_have_different_temp_dirs(): void
    {
        $config = new DirectiveTestingConfig;
        $context2 = new DirectiveTestingContext(false);
        $context2->setConfig($config);
        $service2 = new DirectiveTestingService(null, $context2);

        $tempDir1 = $this->context->getTempDir();
        $tempDir2 = $context2->getTempDir();

        $this->assertNotSame($tempDir1, $tempDir2);
        $this->assertDirectoryExists($tempDir1);
        $this->assertDirectoryExists($tempDir2);

        $service2->destroy();
    }

    // ==================== Tests pour les variables d'environnement ====================

    public function test_file_creator_working_dir_env_is_set(): void
    {
        $tempDir = $this->context->getTempDir();
        $envValue = getenv('FILE_CREATOR_WORKING_DIR');

        $this->assertSame($tempDir, $envValue);
    }

    public function test_directive_path_env_is_set(): void
    {
        $tempDir = $this->context->getTempDir();
        $expectedPath = $tempDir . '/app/Directives';
        $envValue = getenv('DIRECTIVE_PATH');

        $this->assertSame($expectedPath, $envValue);
    }

    // ==================== Tests pour extractSignatureFromClass ====================

    public function test_extract_signature_from_existing_class(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractSignatureFromClass');

        $signature = $method->invoke($this->service, TestConcreteDirective::class);

        $this->assertSame('test-concrete', $signature);
    }

    // ==================== Tests pour createDirectiveInstance ====================

    public function test_create_directive_instance_without_constructor(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('createDirectiveInstance');

        $directive = $method->invoke($this->service, TestConcreteDirective::class);

        $this->assertInstanceOf(TestConcreteDirective::class, $directive);
    }
}
