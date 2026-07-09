<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit;

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestAfterFailingDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestBeforeAfterDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestBeforeFailingDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestNestedBeforeAfterDirective;
use AndyDefer\Directive\Tests\IntegrationTestCase;

final class DirectiveIntegrationTest extends IntegrationTestCase
{
    private DirectiveKernel $kernel;

    private DirectiveDiscoveryService $discovery;

    protected function setUp(): void
    {
        parent::setUp();

        TestBeforeAfterDirective::resetLog();
        TestBeforeFailingDirective::resetLog();
        TestAfterFailingDirective::resetLog();
        TestNestedBeforeAfterDirective::resetLog();

        $this->kernel = DirectiveKernel::init($this->laravelContainer);

        $this->kernel->addSource(getcwd().'/tests/Fixtures/Directives');

        ob_start();
    }

    protected function tearDown(): void
    {
        ob_end_clean();
        parent::tearDown();
    }

    public function test_executes_directive(): void
    {
        $result = $this->kernel->run(['directive', 'test-directive John john@example.com']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_returns_not_found_for_unknown_directive(): void
    {
        $result = $this->kernel->run(['directive', 'unknown-command']);

        $this->assertSame(ExitCode::NOT_FOUND, $result);
    }

    public function test_executes_help_directive(): void
    {
        $result = $this->kernel->run(['directive', '--help']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_executes_list_directive(): void
    {
        $result = $this->kernel->run(['directive', '--list']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_executes_version_directive(): void
    {
        $result = $this->kernel->run(['directive', '--version']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_executes_directive_with_alias(): void
    {
        $result = $this->kernel->run(['directive', '-h']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_detects_circular_calls(): void
    {
        $result = $this->kernel->run(['directive', 'test-circular']);

        $this->assertSame(ExitCode::CONFLICT, $result);
    }

    public function test_executes_calls_recursively(): void
    {
        $result = $this->kernel->run(['directive', 'test-call']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_before_hook_executes_before_execute(): void
    {
        $result = $this->kernel->run(['directive', 'test-before-after']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $directive = $this->app->make(TestBeforeAfterDirective::class, ['query' => 'test-before-after']);
        $this->assertStringStartsWith('before-', $directive->getLog());
        $this->assertStringContainsString('execute-', $directive->getLog());
        $this->assertStringEndsWith('after-0', $directive->getLog());
    }

    public function test_before_hook_failure_returns_runtime_error(): void
    {
        $result = $this->kernel->run(['directive', 'test-before-failing']);

        $this->assertSame(ExitCode::RUNTIME_ERROR, $result);

        $directive = $this->app->make(TestBeforeFailingDirective::class, ['query' => 'test-before-failing']);
        $this->assertStringStartsWith('before-', $directive->getLog());
        $this->assertStringNotContainsString('execute-', $directive->getLog());
        $this->assertStringNotContainsString('after-', $directive->getLog());
    }

    public function test_execute_hook_failure_returns_runtime_error_and_after_is_called(): void
    {
        $result = $this->kernel->run(['directive', 'test-after-failing']);

        $this->assertSame(ExitCode::RUNTIME_ERROR, $result);

        $directive = $this->app->make(TestAfterFailingDirective::class, ['query' => 'test-after-failing']);
        $this->assertStringStartsWith('before-', $directive->getLog());
        $this->assertStringContainsString('execute-', $directive->getLog());
        $this->assertStringContainsString('after-', $directive->getLog());
    }

    public function test_nested_before_after_hooks_execute_in_correct_order(): void
    {
        TestBeforeAfterDirective::resetLog();
        TestNestedBeforeAfterDirective::resetLog();

        $result = $this->kernel->run(['directive', 'test-nested-before-after']);

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
