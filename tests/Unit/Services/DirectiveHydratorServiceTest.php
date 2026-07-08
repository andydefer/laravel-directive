<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestDirective;
use AndyDefer\Directive\Tests\IntegrationTestCase;

final class DirectiveHydratorServiceTest extends IntegrationTestCase
{
    private DirectiveHydratorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DirectiveHydratorService($this->app);
    }

    public function test_hydrate_creates_directive_with_query(): void
    {
        $directive = $this->service->hydrate(TestDirective::class, 'test-directive John john@example.com');

        $this->assertSame('John', $directive->argument('name'));
        $this->assertSame('john@example.com', $directive->argument('email'));
    }

    public function test_hydrate_creates_directive_with_default_values(): void
    {
        $directive = $this->service->hydrate(TestDirective::class, 'test-directive John john@example.com');

        $this->assertSame('zip', $directive->argument('format'));
    }

    public function test_hydrate_creates_directive_with_options(): void
    {
        $directive = $this->service->hydrate(TestDirective::class, 'test-directive John john@example.com --force');

        $this->assertTrue($directive->option('force'));
        $this->assertFalse($directive->option('verbose'));
    }

    public function test_hydrate_creates_directive_with_variadic_arguments(): void
    {
        $directive = $this->service->hydrate(
            TestDirective::class,
            'test-directive John john@example.com zip [file1.txt, file2.txt, file3.txt]'
        );

        $variadic = $directive->getVariadicArguments();
        $this->assertCount(3, $variadic);
        $this->assertTrue($variadic->contains('file1.txt'));
        $this->assertTrue($variadic->contains('file2.txt'));
        $this->assertTrue($variadic->contains('file3.txt'));
    }
}
