<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Bootstrap\Paths;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestConcreteDirective;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use AndyDefer\DomainStructures\Utils\MapCollection;

final class AbstractDirectiveTest extends IntegrationTestCase
{
    private DirectiveKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();

        // Capturer les sorties console

        $this->kernel = DirectiveKernel::init($this->laravelContainer);
        $this->kernel->addSource(Paths::projectRoot().'/tests/Fixtures/Directives');
        $this->kernel->resetContext();
    }

    private function createDirective(string $query): AbstractDirective
    {
        return new TestConcreteDirective($this->kernel, $query);
    }

    public function test_argument_returns_value(): void
    {
        $directive = $this->createDirective('test:concrete John^Doe john@example.com');

        $this->assertSame('John Doe', $directive->getArgument('name'));
        $this->assertSame('john@example.com', $directive->getArgument('email'));
    }

    public function test_argument_returns_null_for_unknown_key(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertNull($directive->getArgument('unknown'));
    }

    public function test_has_argument_returns_true_when_exists(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertTrue($directive->hasArgument('name'));
    }

    public function test_has_argument_returns_false_when_not_exists(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertFalse($directive->hasArgument('unknown'));
    }

    public function test_argument_returns_default_value(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertSame('zip', $directive->getArgument('format'));
    }

    public function test_argument_overrides_default_value(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com tar^gz');

        $this->assertSame('tar gz', $directive->getArgument('format'));
    }

    public function test_option_returns_value(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com --force');

        $this->assertTrue($directive->isFlagActive('force'));
        $this->assertFalse($directive->isFlagActive('verbose'));
    }

    public function test_option_returns_false_for_unknown_key(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com --force');

        $this->assertFalse($directive->hasFlag('unknown'));
    }

    public function test_has_option_returns_true_when_exists(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com --force');

        $this->assertTrue($directive->hasFlag('force'));
    }

    public function test_has_option_returns_false_when_not_exists(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com --force');

        $this->assertFalse($directive->hasFlag('unknown'));
    }

    public function test_has_option_returns_false_for_inactive_option(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com --force');

        $this->assertFalse($directive->isFlagActive('verbose'));
    }

    public function test_variadic_arguments_returns_values(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip [file1.txt, file2.txt, file3.txt]');

        $variadic = $directive->getVariadicArguments();
        $this->assertCount(3, $variadic);
        $this->assertTrue($variadic->contains('file1.txt'));
        $this->assertTrue($variadic->contains('file2.txt'));
        $this->assertTrue($variadic->contains('file3.txt'));
    }

    public function test_has_variadic_arguments_returns_true_when_exists(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip [file1.txt, file2.txt]');

        $this->assertTrue($directive->hasVariadicArguments());
    }

    public function test_has_variadic_arguments_returns_false_when_empty(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertFalse($directive->hasVariadicArguments());
    }

    public function test_line_outputs_message(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');
        $directive->line('Hello World');
        $this->expectOutputRegex('/Hello World\s*/');
    }

    public function test_info_outputs_formatted_message(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');
        $directive->info('Hello World');
        $this->expectOutputRegex('/INFO.*Hello World/');
    }

    public function test_error_outputs_formatted_message(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');
        $directive->error('Hello World');
        $this->expectOutputRegex('/ERROR.*Hello World/');
    }

    public function test_new_line_outputs_empty_line(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');
        $directive->newLine();
        $this->expectOutputRegex('/\n/');
    }

    public function test_separator_outputs_line(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');
        $directive->separator();
        $this->expectOutputRegex('/-{80}/');
    }

    public function test_get_container_returns_container(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertSame($this->laravelContainer, $directive->getContainer());
    }

    public function test_run_returns_success_exit_code(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $result = $directive->run();

        $this->assertSame(0, $result->value);
    }

    public function test_get_calls_returns_empty_array_by_default(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertEmpty($directive->getCalls());
    }

    // ==================== CONTEXT TESTS ====================

    public function test_context_set_and_get(): void
    {
        // Execute directive that sets context
        $result = $this->kernel->run(['directive', 'context:set', 'John']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        // Check context was set
        $context = $this->kernel->getContext();
        $this->assertTrue($context->hasKey('user_name'));
        $this->assertSame('John', $context->get('user_name'));
        $this->assertSame(1, $context->get('counter'));

        // Execute directive that gets context
        $result = $this->kernel->run(['directive', 'context:get']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_context_increment_and_decrement(): void
    {
        $this->kernel->resetContext();

        // Set initial counter
        $this->kernel->run(['directive', 'context:set', 'John']);

        // Increment by 1
        $result = $this->kernel->run(['directive', 'context:increment']);
        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertSame(2, $this->kernel->getContext()->get('counter'));

        // Increment by 5
        $result = $this->kernel->run(['directive', 'context:increment', '5']);
        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertSame(7, $this->kernel->getContext()->get('counter'));

        // Decrement by 2
        $result = $this->kernel->run(['directive', 'context:decrement', '2']);
        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertSame(5, $this->kernel->getContext()->get('counter'));
    }

    public function test_context_merge(): void
    {
        $this->kernel->resetContext();

        // Set initial value
        $this->kernel->run(['directive', 'context:set', 'John']);

        // Merge additional data
        $result = $this->kernel->run(['directive', 'context:merge']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $context = $this->kernel->getContext();
        $this->assertSame('John', $context->get('user_name')); // Existing
        $this->assertSame('John', $context->get('name'));     // Merged
        $this->assertSame(30, $context->get('age'));          // Merged
        $this->assertSame('Paris', $context->get('city'));    // Merged
    }

    public function test_context_remove(): void
    {
        $this->kernel->resetContext();

        // Set values via context:set
        $this->kernel->run(['directive', 'context:set', 'John']);

        // Add age and city via context:merge
        $this->kernel->run(['directive', 'context:merge']);

        // Vérifier que age existe avant la suppression
        $context = $this->kernel->getContext();
        $this->assertTrue($context->hasKey('user_name'));
        $this->assertTrue($context->hasKey('age'));
        $this->assertTrue($context->hasKey('city'));

        // Remove age
        $result = $this->kernel->run(['directive', 'context:remove', 'age']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $context = $this->kernel->getContext();
        $this->assertTrue($context->hasKey('user_name'));
        $this->assertFalse($context->hasKey('age'));
        $this->assertTrue($context->hasKey('city'));
    }

    public function test_context_clear(): void
    {
        $this->kernel->resetContext();

        // Set values
        $this->kernel->run(['directive', 'context:set', 'John']);
        $this->assertFalse($this->kernel->getContext()->isEmpty());

        // Clear
        $result = $this->kernel->run(['directive', 'context:clear']);

        $this->assertSame(ExitCode::SUCCESS, $result);
        $this->assertTrue($this->kernel->getContext()->isEmpty());
    }

    public function test_context_has(): void
    {
        $this->kernel->resetContext();

        // Context should not have user_name yet
        $this->assertFalse($this->kernel->getContext()->hasKey('user_name'));

        // Set value
        $this->kernel->run(['directive', 'context:set', 'John']);

        // Now it should exist
        $this->assertTrue($this->kernel->getContext()->hasKey('user_name'));
        $this->assertSame('John', $this->kernel->getContext()->get('user_name'));
    }

    public function test_context_pipeline(): void
    {
        $this->kernel->resetContext();

        $result = $this->kernel->run(['directive', 'context:pipeline', 'Alice']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $context = $this->kernel->getContext();
        $this->assertSame('Alice', $context->get('name'));
        $this->assertTrue($context->get('validated'));
        $this->assertTrue($context->get('enriched'));
        $this->assertNotNull($context->get('timestamp'));
        $this->assertEquals(3, $context->get('counter'));
    }

    public function test_context_orchestration(): void
    {
        $this->kernel->resetContext();

        $result = $this->kernel->run(['directive', 'context:orchestrate']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $context = $this->kernel->getContext();
        $this->assertSame('John', $context->get('name'));
        $this->assertSame('JOHN', $context->get('processed_user'));
        $this->assertTrue($context->get('step1_done'));
        $this->assertTrue($context->get('step2_done'));
        $this->assertEquals(2, $context->get('steps_completed'));
    }

    public function test_context_all(): void
    {
        $this->kernel->resetContext();

        // Set multiple values
        $this->kernel->run(['directive', 'context:set', 'John']);
        $this->kernel->run(['directive', 'context:increment']);

        // Get all context
        $result = $this->kernel->run(['directive', 'context:all']);

        $this->assertSame(ExitCode::SUCCESS, $result);

        $context = $this->kernel->getContext();
        $this->assertInstanceOf(MapCollection::class, $context);
        $this->assertTrue($context->hasKey('user_name'));
        $this->assertTrue($context->hasKey('counter'));
        $this->assertSame('John', $context->get('user_name'));
        $this->assertEquals(2, $context->get('counter'));
    }

    public function test_context_snapshot_and_restore(): void
    {
        $this->kernel->resetContext();

        // Set initial values
        $this->kernel->run(['directive', 'context:set', 'Alice']);
        $this->assertSame('Alice', $this->kernel->getContext()->get('user_name'));

        // Take snapshot
        $snapshot = $this->kernel->getContext();

        // Modify context
        $this->kernel->run(['directive', 'context:set', 'Bob']);
        $this->assertSame('Bob', $this->kernel->getContext()->get('user_name'));

        // Restore snapshot
        $this->kernel->setContext($snapshot);

        $this->assertSame('Alice', $this->kernel->getContext()->get('user_name'));
        $this->assertEquals(1, $this->kernel->getContext()->get('counter'));
    }
}
