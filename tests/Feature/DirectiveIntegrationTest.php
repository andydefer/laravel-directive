<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Directive\Feature;

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

        // Use a dummy path that doesn't exist (we'll rely on registrar)
        $config = DirectiveConfig::default()->withDirectivesPath('/tmp/nonexistent_directives_' . uniqid());

        // Bind the config to the container BEFORE registering the provider
        $this->app->instance(DirectiveConfig::class, $config);

        // Register the service provider
        $this->app->register(DirectiveServiceProvider::class);

        // Get the registrar and register test directives directly
        $registrar = $this->app->make(DirectiveRegistrar::class);

        $classes = new StringTypedCollection();
        $classes->add(TestEchoDirective::class);
        $registrar->register($classes);

        $this->kernel = $this->app->make(DirectiveKernel::class);

        // Enable debug to see what's happening
        if (method_exists($this->kernel, 'enableDebug')) {
            $this->kernel->enableDebug(true);
        }
    }

    // ==================== Tests des options globales ====================

    public function test_kernel_shows_help_when_no_arguments(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertStringContainsString('Directive System', $output);
        $this->assertStringContainsString('USAGE:', $output);
    }

    public function test_kernel_returns_success_for_help_command(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', '--help']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertStringContainsString('Directive System', $output);
    }

    public function test_kernel_returns_success_for_list_command(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', '--list']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertStringContainsString('Available Directives', $output);
    }

    // ==================== Tests des directives ====================

    public function test_kernel_returns_success_for_existing_directive(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', 'test:echo']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertStringContainsString('Hello World', $output);
    }

    public function test_kernel_handles_directive_with_arguments(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', 'test:echo', 'Custom Message']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertStringContainsString('Custom Message', $output);
    }

    public function test_kernel_returns_not_found_for_unknown_command(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', 'unknown:command']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::NOT_FOUND, $result);
        $this->assertStringContainsString('not found', $output);
    }

    public function test_kernel_handles_directive_with_options(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', 'test:echo', '--verbose']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Tests des alias ====================

    public function test_kernel_respects_directive_aliases(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', 'echo']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertStringContainsString('Hello World', $output);
    }

    // ==================== Tests des cas limites ====================

    public function test_kernel_handles_empty_string_as_argument(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', 'test:echo', '']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_kernel_handles_special_characters_in_arguments(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', 'test:echo', 'Hello @#$% World!']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertStringContainsString('Hello @#$% World!', $output);
    }

    // ==================== Tests supplémentaires ====================

    public function test_kernel_handles_multiple_arguments(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', 'test:echo', 'arg1', 'arg2', 'arg3']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertStringContainsString('arg1', $output);
    }

    public function test_kernel_handles_help_with_short_option(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', '-h']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertStringContainsString('Directive System', $output);
    }

    public function test_kernel_handles_list_with_short_option(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', '-l']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertStringContainsString('Available Directives', $output);
    }

    public function test_kernel_handles_mixed_short_and_long_options(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', 'test:echo', '-v', '--verbose', '--force']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_kernel_handles_option_with_value(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', 'test:echo', '--message=HelloWorld']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_kernel_returns_not_found_for_invalid_alias(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', 'invalid-alias']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::NOT_FOUND, $result);
        $this->assertStringContainsString('not found', $output);
    }

    public function test_kernel_handles_very_long_argument(): void
    {
        $longArgument = str_repeat('a', 1000);

        ob_start();
        $result = $this->kernel->run(['directive', 'test:echo', $longArgument]);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertStringContainsString($longArgument, $output);
    }

    public function test_kernel_handles_unicode_characters_in_argument(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', 'test:echo', '你好世界']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertStringContainsString('你好世界', $output);
    }

    public function test_kernel_list_displays_registered_directives(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', '--list']);
        $output = ob_get_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertStringContainsString('test:echo', $output);
    }
}
