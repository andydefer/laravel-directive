<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Feature;

use AndyDefer\Directive\Tests\TestCase;
use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveRegistrar;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestEchoDirective;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class DirectiveIntegrationTest extends TestCase
{
    private DirectiveKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a dummy path that doesn't exist
        $config = DirectiveConfig::default()->withDirectivesPath('/tmp/nonexistent_directives_' . uniqid());

        $this->app->instance(DirectiveConfig::class, $config);

        $this->app->register(DirectiveServiceProvider::class);

        $registrar = $this->app->make(DirectiveRegistrar::class);

        $classes = new StringTypedCollection();
        $classes->add(TestEchoDirective::class);
        $registrar->register($classes);

        $this->kernel = $this->app->make(DirectiveKernel::class);
    }

    private function runAndCaptureOutput(array $argv): array
    {
        ob_start();
        $result = $this->kernel->run($argv);
        $output = ob_get_clean();

        return [
            'result' => $result,
            'output' => $output,
        ];
    }

    // ==================== Tests des options globales ====================

    public function test_kernel_shows_help_when_no_arguments(): void
    {
        $response = $this->runAndCaptureOutput(['directive']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Directive System', $response['output']);
        $this->assertStringContainsString('USAGE:', $response['output']);
    }

    public function test_kernel_returns_success_for_help_command(): void
    {
        $response = $this->runAndCaptureOutput(['directive', '--help']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Directive System', $response['output']);
    }

    public function test_kernel_returns_success_for_list_command(): void
    {
        $response = $this->runAndCaptureOutput(['directive', '--list']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Available Directives', $response['output']);
    }

    // ==================== Tests des directives ====================

    public function test_kernel_returns_success_for_existing_directive(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Hello World', $response['output']);
    }

    public function test_kernel_handles_directive_with_arguments(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', 'Custom Message']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Custom Message', $response['output']);
    }

    public function test_kernel_returns_not_found_for_unknown_command(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'unknown-command']);

        $this->assertSame(ExitCode::NOT_FOUND, $response['result']);
        $this->assertStringContainsString('not found', $response['output']);
    }

    public function test_kernel_handles_directive_with_options(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', '--verbose']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Hello World', $response['output']);
    }

    // ==================== Tests des alias ====================

    public function test_kernel_respects_directive_aliases(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'echo']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Hello World', $response['output']);
    }

    // ==================== Tests des cas limites ====================

    public function test_kernel_handles_empty_string_as_argument(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', '']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
    }

    public function test_kernel_handles_special_characters_in_arguments(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', 'Hello @#$% World!']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Hello @#$% World!', $response['output']);
    }

    // ==================== Tests supplémentaires ====================

    public function test_kernel_handles_multiple_arguments(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', 'arg1', 'arg2', 'arg3']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('arg1', $response['output']);
    }

    public function test_kernel_handles_help_with_short_option(): void
    {
        $response = $this->runAndCaptureOutput(['directive', '-h']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Directive System', $response['output']);
    }

    public function test_kernel_handles_list_with_short_option(): void
    {
        $response = $this->runAndCaptureOutput(['directive', '-l']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Available Directives', $response['output']);
    }

    public function test_kernel_handles_mixed_short_and_long_options(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', '-v', '--verbose', '--force']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Hello World', $response['output']);
    }

    public function test_kernel_handles_option_with_value(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', '--message=HelloWorld']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Hello World', $response['output']);
    }

    public function test_kernel_returns_not_found_for_invalid_alias(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'invalid-alias']);

        $this->assertSame(ExitCode::NOT_FOUND, $response['result']);
        $this->assertStringContainsString('not found', $response['output']);
    }

    public function test_kernel_handles_very_long_argument(): void
    {
        $longArgument = str_repeat('a', 1000);
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', $longArgument]);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString($longArgument, $response['output']);
    }

    public function test_kernel_handles_unicode_characters_in_argument(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', '你好世界']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('你好世界', $response['output']);
    }

    public function test_kernel_list_displays_registered_directives(): void
    {
        $response = $this->runAndCaptureOutput(['directive', '--list']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('test-echo', $response['output']);
    }
}
