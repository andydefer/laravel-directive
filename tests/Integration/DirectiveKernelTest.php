<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration;

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Tests\IntegrationTestCase;

final class DirectiveKernelTest extends IntegrationTestCase
{
    private DirectiveKernel $kernel;

    private DirectiveDiscoveryService $discovery;

    protected function setUp(): void
    {
        parent::setUp();

        // Capturer les sorties console
        ob_start();

        $this->kernel = DirectiveKernel::init($this->laravelContainer);

        // Ajouter le chemin des fixtures
        $this->kernel->addSource(getcwd().'/tests/Fixtures/Directives');
    }

    protected function tearDown(): void
    {
        // Nettoyer le buffer de sortie
        ob_end_clean();

        parent::tearDown();
    }

    public function test_run_without_arguments_returns_help(): void
    {
        $result = $this->kernel->run(['directive']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_help_alias_returns_help(): void
    {
        $result = $this->kernel->run(['directive', '--help']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_short_help_alias_returns_help(): void
    {
        $result = $this->kernel->run(['directive', '-h']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_list_alias_returns_list(): void
    {
        $result = $this->kernel->run(['directive', '--list']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_short_list_alias_returns_list(): void
    {
        $result = $this->kernel->run(['directive', '-l']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_version_alias_returns_version(): void
    {
        $result = $this->kernel->run(['directive', '--version']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_short_version_alias_returns_version(): void
    {
        $result = $this->kernel->run(['directive', '-v']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_valid_directive_returns_success(): void
    {
        $result = $this->kernel->run(['directive', 'test-directive John john@example.com']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_unknown_directive_returns_not_found(): void
    {
        $result = $this->kernel->run(['directive', 'unknown-command']);

        $this->assertSame(ExitCode::NOT_FOUND, $result);
    }

    public function test_run_with_directive_and_arguments_passes_query_correctly(): void
    {
        $result = $this->kernel->run(['directive', 'test-directive John^Doe john@example.com']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_alias_help_after_other_args(): void
    {
        $result = $this->kernel->run(['directive', '--help']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_directive_using_circular_call_returns_conflict(): void
    {
        $result = $this->kernel->run(['directive', 'test-circular']);

        $this->assertSame(ExitCode::CONFLICT, $result);
    }

    public function test_run_with_directive_using_calls_returns_success(): void
    {
        $result = $this->kernel->run(['directive', 'test-call']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_respects_before_and_after_hooks(): void
    {
        $result = $this->kernel->run(['directive', 'test-before-after']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_before_hook_failure_returns_runtime_error(): void
    {
        $result = $this->kernel->run(['directive', 'test-before-failing']);

        $this->assertSame(ExitCode::RUNTIME_ERROR, $result);
    }

    public function test_run_with_execute_hook_failure_returns_runtime_error(): void
    {
        $result = $this->kernel->run(['directive', 'test-after-failing']);

        $this->assertSame(ExitCode::RUNTIME_ERROR, $result);
    }

    public function test_run_with_nested_before_after_hooks_executes_correctly(): void
    {
        $result = $this->kernel->run(['directive', 'test-nested-before-after']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }
}
