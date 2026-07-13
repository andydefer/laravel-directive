<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Helpers\Paths;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Tests\IntegrationTestCase;

final class DirectiveCallIntegrationTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DirectiveTestingService(
            $this->app,
            [Paths::projectRoot().'/tests/Fixtures/Directives'],
        );
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    public function test_parent_directive_executes_child_directives(): void
    {
        $response = $this->service->run('test:parent');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Parent directive started', $response->output);
        $this->assertStringContainsString('15', $response->output);
        $this->assertStringContainsString('8', $response->output);
        $this->assertStringContainsString('Hello, John!', $response->output);
        $this->assertStringContainsString('Parent directive finished', $response->output);
    }

    public function test_parent_directive_with_no_calls(): void
    {
        $response = $this->service->run('greeting Alice');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, Alice!', $response->output);
    }

    public function test_child_output_appears_after_parent_execution(): void
    {
        $response = $this->service->run('test:parent');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = $response->output;

        $this->assertTrue(
            strpos($output, 'Parent directive started') < strpos($output, 'Parent directive finished'),
            'Parent should start before finishing'
        );

        $this->assertTrue(
            strpos($output, 'Parent directive finished') < strpos($output, '15'),
            'Children should execute after parent finishes'
        );

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
        $response1 = $this->service->run('test:parent');
        $this->assertSame(ExitCode::SUCCESS, $response1->exit_code);
        $this->assertStringContainsString('15', $response1->output);
        $this->assertStringContainsString('8', $response1->output);
        $this->assertStringContainsString('Hello, John!', $response1->output);

        $response2 = $this->service->run('greeting Bob');
        $this->assertSame(ExitCode::SUCCESS, $response2->exit_code);
        $this->assertStringContainsString('Hello, Bob!', $response2->output);
        $this->assertStringNotContainsString('15', $response2->output);
        $this->assertStringNotContainsString('8', $response2->output);
    }

    public function test_calculator_directive_works_independently(): void
    {
        $response = $this->service->run('calculator add 10 5');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('15', $response->output);

        $response = $this->service->run('calculator pow 2 3');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('8', $response->output);
    }

    public function test_greeting_directive_works_independently(): void
    {
        $response = $this->service->run('greeting Alice');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, Alice!', $response->output);

        $response = $this->service->run('greeting');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, World!', $response->output);
    }

    public function test_nested_calls_execute_in_correct_order(): void
    {
        $response = $this->service->run('test:nested-before-after');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Parent before hook executed', $response->output);
        $this->assertStringContainsString('Parent execute hook executed', $response->output);
        $this->assertStringContainsString('Before hook executed', $response->output);
        $this->assertStringContainsString('Execute hook executed', $response->output);
        $this->assertStringContainsString('After hook executed', $response->output);
        $this->assertStringContainsString('Parent after hook executed', $response->output);
    }
}
