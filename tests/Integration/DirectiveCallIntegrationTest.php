<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestCalculatorDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestGreetingDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestParentDirective;
use AndyDefer\Directive\Tests\IntegrationTestCase;

final class DirectiveCallIntegrationTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DirectiveTestingService($this->app);

        $this->service
            ->registerDirective(TestCalculatorDirective::class)
            ->registerDirective(TestGreetingDirective::class);
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    public function test_parent_directive_executes_child_directives(): void
    {
        $response = $this->service->run(TestParentDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Parent directive started', $response->output);
        $this->assertStringContainsString('15', $response->output);
        $this->assertStringContainsString('8', $response->output);
        $this->assertStringContainsString('Hello, John!', $response->output);
        $this->assertStringContainsString('Parent directive finished', $response->output);
    }

    public function test_parent_directive_with_no_calls(): void
    {
        $response = $this->service->run(TestGreetingDirective::class, ['Alice']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, Alice!', $response->output);
    }

    public function test_child_output_appears_after_parent_execution(): void
    {
        $response = $this->service->run(TestParentDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = $response->output;

        // Le parent s'exécute complètement avant les enfants
        $this->assertTrue(
            strpos($output, 'Parent directive started') < strpos($output, 'Parent directive finished'),
            'Parent should start before finishing'
        );

        // Les enfants s'exécutent après que le parent ait fini
        $this->assertTrue(
            strpos($output, 'Parent directive finished') < strpos($output, '15'),
            'Children should execute after parent finishes'
        );

        // L'ordre des enfants est respecté
        $this->assertTrue(
            strpos($output, '15') < strpos($output, '8'),
            'Calculator add should execute before pow'
        );
        $this->assertTrue(
            strpos($output, '8') < strpos($output, 'Hello, John!'),
            'Calculator pow should execute before greeting'
        );
    }

    public function test_multiple_executions_are_independent(): void
    {
        $response1 = $this->service->run(TestParentDirective::class, []);
        $this->assertSame(ExitCode::SUCCESS, $response1->exit_code);
        $this->assertStringContainsString('15', $response1->output);
        $this->assertStringContainsString('8', $response1->output);
        $this->assertStringContainsString('Hello, John!', $response1->output);

        $response2 = $this->service->run(TestGreetingDirective::class, ['Bob']);
        $this->assertSame(ExitCode::SUCCESS, $response2->exit_code);
        $this->assertStringContainsString('Hello, Bob!', $response2->output);
        $this->assertStringNotContainsString('15', $response2->output);
        $this->assertStringNotContainsString('8', $response2->output);
    }

    public function test_calculator_directive_works_independently(): void
    {
        $response = $this->service->run(TestCalculatorDirective::class, ['add', '10', '5']);
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('15', $response->output);

        $response = $this->service->run(TestCalculatorDirective::class, ['pow', '2', '3']);
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('8', $response->output);
    }

    public function test_greeting_directive_works_independently(): void
    {
        $response = $this->service->run(TestGreetingDirective::class, ['Alice']);
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, Alice!', $response->output);

        $response = $this->service->run(TestGreetingDirective::class, []);
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, World!', $response->output);
    }

    public function test_register_directive_twice_does_not_duplicate(): void
    {
        $service = new DirectiveTestingService($this->app);
        $service->registerDirective(TestCalculatorDirective::class);
        $service->registerDirective(TestCalculatorDirective::class);
        $service->registerDirective(TestGreetingDirective::class);

        $response = $service->run(TestParentDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('15', $response->output);
        $this->assertStringContainsString('8', $response->output);
        $this->assertStringContainsString('Hello, John!', $response->output);

        $service->destroy();
    }
}
