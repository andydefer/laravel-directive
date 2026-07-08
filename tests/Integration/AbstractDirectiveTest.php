<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestConcreteDirective;
use AndyDefer\Directive\Tests\IntegrationTestCase;

final class AbstractDirectiveTest extends IntegrationTestCase
{
    private function createDirective(string $query): AbstractDirective
    {
        return new TestConcreteDirective($this->app, $query);
    }

    public function test_argument_returns_value(): void
    {
        $directive = $this->createDirective('test-concrete John^Doe john@example.com');

        $this->assertSame('John Doe', $directive->argument('name'));
        $this->assertSame('john@example.com', $directive->argument('email'));
    }

    public function test_argument_returns_null_for_unknown_key(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com');

        $this->assertNull($directive->argument('unknown'));
    }

    public function test_has_argument_returns_true_when_exists(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com');

        $this->assertTrue($directive->hasArgument('name'));
    }

    public function test_has_argument_returns_false_when_not_exists(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com');

        $this->assertFalse($directive->hasArgument('unknown'));
    }

    public function test_argument_returns_default_value(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com');

        $this->assertSame('zip', $directive->argument('format'));
    }

    public function test_argument_overrides_default_value(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com tar^gz');

        $this->assertSame('tar gz', $directive->argument('format'));
    }

    public function test_option_returns_value(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com --force');

        $this->assertTrue($directive->isFlagActive('force'));
        $this->assertFalse($directive->isFlagActive('verbose'));
    }

    public function test_option_returns_false_for_unknown_key(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com --force');

        $this->assertFalse($directive->hasFlag('unknown'));
    }

    public function test_has_option_returns_true_when_exists(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com --force');

        $this->assertTrue($directive->hasFlag('force'));
    }

    public function test_has_option_returns_false_when_not_exists(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com --force');

        $this->assertFalse($directive->hasFlag('unknown'));
    }

    public function test_has_option_returns_false_for_inactive_option(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com --force');

        $this->assertFalse($directive->isFlagActive('verbose'));
    }

    public function test_variadic_arguments_returns_values(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com zip [file1.txt, file2.txt, file3.txt]');

        $variadic = $directive->getVariadicArguments();
        $this->assertCount(3, $variadic);
        $this->assertTrue($variadic->contains('file1.txt'));
        $this->assertTrue($variadic->contains('file2.txt'));
        $this->assertTrue($variadic->contains('file3.txt'));
    }

    public function test_has_variadic_arguments_returns_true_when_exists(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com zip [file1.txt, file2.txt]');

        $this->assertTrue($directive->hasVariadicArguments());
    }

    public function test_has_variadic_arguments_returns_false_when_empty(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com');

        $this->assertFalse($directive->hasVariadicArguments());
    }

    public function test_line_outputs_message(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com');
        $directive->line('Hello World');
        $this->expectOutputRegex('/Hello World\s*/');
    }

    public function test_info_outputs_formatted_message(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com');
        $directive->info('Hello World');
        $this->expectOutputRegex('/INFO.*Hello World/');
    }

    public function test_error_outputs_formatted_message(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com');
        $directive->error('Hello World');
        $this->expectOutputRegex('/ERROR.*Hello World/');
    }

    public function test_new_line_outputs_empty_line(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com');
        $directive->newLine();
        $this->expectOutputRegex('/\n/');
    }

    public function test_separator_outputs_line(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com');
        $directive->separator();
        $this->expectOutputRegex('/-{80}/');
    }

    public function test_get_laravel_returns_application(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com');

        $this->assertSame($this->app, $directive->getLaravel());
    }

    public function test_run_returns_success_exit_code(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com');

        $result = $directive->run();

        $this->assertSame(0, $result->value);
    }

    public function test_get_calls_returns_empty_array_by_default(): void
    {
        $directive = $this->createDirective('test-concrete John john@example.com');

        $this->assertEmpty($directive->getCalls());
    }
}
