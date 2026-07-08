<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestAfterFailingDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestBeforeAfterDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestBeforeFailingDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestNestedBeforeAfterDirective;
use AndyDefer\Directive\Tests\IntegrationTestCase;

final class DirectiveExecutionServiceTest extends IntegrationTestCase
{
    private DirectiveExecutionService $service;

    private DirectiveDiscoveryService $discovery;

    // Dans le setUp du test
    protected function setUp(): void
    {
        parent::setUp();

        // Reset des logs statiques
        TestBeforeAfterDirective::resetLog();
        TestBeforeFailingDirective::resetLog();
        TestAfterFailingDirective::resetLog();
        TestNestedBeforeAfterDirective::resetLog();

        $this->service = $this->app->make(DirectiveExecutionService::class);
        $this->discovery = $this->app->make(DirectiveDiscoveryService::class);
        $this->discovery->addSource(getcwd().'/tests/Fixtures/Directives');

        ob_start();
    }

    protected function tearDown(): void
    {
        // Nettoyer et ignorer les sorties
        ob_end_clean();

        parent::tearDown();
    }

    // ==================== EXECUTION TESTS ====================

    public function test_executes_directive(): void
    {
        $result = $this->service->execute('test-directive John john@example.com');

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_returns_not_found_for_unknown_directive(): void
    {
        $result = $this->service->execute('unknown-command');

        $this->assertSame(ExitCode::NOT_FOUND, $result);
    }

    public function test_executes_help_directive(): void
    {
        $result = $this->service->execute('--help');

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_executes_list_directive(): void
    {
        $result = $this->service->execute('--list');

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_executes_version_directive(): void
    {
        $result = $this->service->execute('--version');

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_executes_directive_with_alias(): void
    {
        $result = $this->service->execute('-h');

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_detects_circular_calls(): void
    {
        $result = $this->service->execute('test-circular');

        $this->assertSame(ExitCode::CONFLICT, $result);
    }

    public function test_executes_calls_recursively(): void
    {
        $result = $this->service->execute('test-call');

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== BEFORE / AFTER HOOKS TESTS ====================

    public function test_before_hook_executes_before_execute(): void
    {
        $result = $this->service->execute('test-before-after');

        $this->assertSame(ExitCode::SUCCESS, $result);

        $directive = $this->app->make(TestBeforeAfterDirective::class, ['query' => 'test-before-after']);
        $this->assertStringStartsWith('before-', $directive->getLog());
        $this->assertStringContainsString('execute-', $directive->getLog());
        $this->assertStringEndsWith('after-0', $directive->getLog());
    }

    public function test_before_hook_failure_returns_runtime_error(): void
    {
        $result = $this->service->execute('test-before-failing');

        $this->assertSame(ExitCode::RUNTIME_ERROR, $result);

        $directive = $this->app->make(TestBeforeFailingDirective::class, ['query' => 'test-before-failing']);
        $this->assertStringStartsWith('before-', $directive->getLog());
        $this->assertStringNotContainsString('execute-', $directive->getLog());
        $this->assertStringNotContainsString('after-', $directive->getLog());
    }

    public function test_execute_hook_failure_returns_runtime_error_and_after_is_called(): void
    {
        $result = $this->service->execute('test-after-failing');

        $this->assertSame(ExitCode::RUNTIME_ERROR, $result);

        $directive = $this->app->make(TestAfterFailingDirective::class, ['query' => 'test-after-failing']);
        $this->assertStringStartsWith('before-', $directive->getLog());
        $this->assertStringContainsString('execute-', $directive->getLog());
        $this->assertStringContainsString('after-', $directive->getLog());
    }

    public function test_nested_before_after_hooks_execute_in_correct_order(): void
    {
        // Reset des logs
        TestBeforeAfterDirective::resetLog();
        TestNestedBeforeAfterDirective::resetLog();

        $result = $this->service->execute('test-nested-before-after');

        $this->assertSame(ExitCode::SUCCESS, $result);

        $log = TestNestedBeforeAfterDirective::getLog();
        $childLog = TestBeforeAfterDirective::getLog();

        $this->assertStringContainsString('parent-before-', $log);
        $this->assertStringContainsString('parent-execute-', $log);
        $this->assertStringContainsString('parent-after-', $log);
        $this->assertStringContainsString('before-', $childLog);
        $this->assertStringContainsString('execute-', $childLog);
        $this->assertStringContainsString('after-0', $childLog);
    }
}
