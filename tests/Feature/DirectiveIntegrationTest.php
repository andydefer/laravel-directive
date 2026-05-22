<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Directive\Feature;

use AndyDefer\Directive\Tests\TestCase;
use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Enums\ExitCode;

final class DirectiveIntegrationTest extends TestCase
{
    private DirectiveKernel $kernel;

    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturesPath = __DIR__ . '/../Fixtures/Directives';

        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesPath);

        $this->app->instance(DirectiveConfig::class, $config);
        $this->app->register(DirectiveServiceProvider::class);

        $this->kernel = $this->app->make(DirectiveKernel::class);
    }

    public function test_kernel_returns_success_for_list_command(): void
    {
        // Capturer la sortie pour éviter l'affichage en console
        ob_start();
        $result = $this->kernel->run(['directive', '--list']);
        ob_end_clean();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_kernel_returns_not_found_for_unknown_command(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', 'unknown:command']);
        ob_end_clean();

        $this->assertSame(ExitCode::NOT_FOUND, $result);
    }
}
