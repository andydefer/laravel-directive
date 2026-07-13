<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Helpers\Paths;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestConcreteDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestEchoDirective;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use AndyDefer\DomainStructures\Utils\MapCollection;

final class AbstractDirectiveTest extends IntegrationTestCase
{
    private DirectiveKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kernel = DirectiveKernel::init($this->app);
        $this->kernel->addSource(Paths::projectRoot().'/tests/Fixtures/Directives');
        $this->kernel->resetContext();
    }

    private function createDirective(string $query): AbstractDirective
    {
        return new TestConcreteDirective($this->kernel, $query);
    }

    // ==================== ARGUMENT TESTS ====================

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

    public function test_argument_returns_enum_value(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip high [file1.txt]');

        $this->assertSame('high', $directive->getArgument('level'));
    }

    public function test_argument_returns_variadic_value(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip high [file1.txt, file2.txt]');

        $this->assertSame(['file1.txt', 'file2.txt'], $directive->getArgument('files'));
    }

    public function test_argument_returns_flag_value(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com --force');

        $this->assertTrue($directive->getArgument('force'));
    }

    public function test_argument_search_order_priority(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        // Required argument takes priority
        $this->assertSame('John', $directive->getArgument('name'));

        // Default argument
        $this->assertSame('zip', $directive->getArgument('format'));

        // Enum argument
        $this->assertNull($directive->getArgument('status'));

        // Variadic argument
        $this->assertSame([], $directive->getArgument('files'));

        // Flag argument
        $this->assertFalse($directive->getArgument('force'));
    }

    // ==================== HAS ARGUMENT TESTS ====================

    public function test_has_argument_returns_true_when_exists(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertTrue($directive->hasArgument('name'));
        $this->assertTrue($directive->hasArgument('email'));
        $this->assertTrue($directive->hasArgument('format'));
    }

    public function test_has_argument_returns_false_when_not_exists(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertFalse($directive->hasArgument('unknown'));
    }

    public function test_has_argument_returns_true_for_enum(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip high [file1.txt]');

        $this->assertTrue($directive->hasArgument('level'));
    }

    public function test_has_argument_returns_true_for_variadic(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip high [file1.txt]');

        $this->assertTrue($directive->hasArgument('files'));
    }

    public function test_has_argument_returns_true_for_flag(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com --force');

        $this->assertTrue($directive->hasArgument('force'));
    }

    // ==================== REQUIRED ARGUMENT TESTS ====================

    public function test_get_required_returns_value(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertSame('John', $directive->getRequired('name'));
        $this->assertSame('john@example.com', $directive->getRequired('email'));
    }

    public function test_get_required_returns_null_for_unknown(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertNull($directive->getRequired('unknown'));
    }

    public function test_get_requireds_returns_all(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $requireds = $directive->getRequireds();
        $this->assertArrayHasKey('name', $requireds);
        $this->assertArrayHasKey('email', $requireds);
        $this->assertSame('John', $requireds['name']);
        $this->assertSame('john@example.com', $requireds['email']);
    }

    // ==================== DEFAULT ARGUMENT TESTS ====================

    public function test_get_default_returns_value(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertSame('zip', $directive->getDefault('format'));
    }

    public function test_get_default_returns_null_for_unknown(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertNull($directive->getDefault('unknown'));
    }

    public function test_get_defaults_returns_all(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $defaults = $directive->getDefaults();
        $this->assertArrayHasKey('format', $defaults);
        $this->assertSame('zip', $defaults['format']);
    }

    public function test_get_default_overrides_value(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com tar^gz');

        $this->assertSame('tar gz', $directive->getDefault('format'));
    }

    // ==================== ENUM TESTS ====================

    public function test_get_enum_returns_value(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip high [file1.txt]');

        $this->assertSame('high', $directive->getEnum('level'));
    }

    public function test_get_enum_returns_null_for_unknown(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertNull($directive->getEnum('status'));
    }

    public function test_get_enums_returns_all(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip high [file1.txt]');

        $enums = $directive->getEnums();
        $this->assertArrayHasKey('level', $enums);
        $this->assertSame('high', $enums['level']);
    }

    public function test_get_enum_allowed_values_returns_allowed(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip high [file1.txt]');

        $allowed = $directive->getEnumAllowedValues('level');
        $this->assertIsArray($allowed);
        $this->assertContains('low', $allowed);
        $this->assertContains('medium', $allowed);
        $this->assertContains('high', $allowed);
    }

    public function test_get_enum_allowed_values_returns_null_for_unknown(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertNull($directive->getEnumAllowedValues('unknown'));
    }

    public function test_is_enum_required(): void
    {
        // Créer une directive avec un enum requis
        $directive = $this->createDirective('test:concrete John john@example.com zip high [file1.txt]');

        // Par défaut, l'énumération n'est pas requise
        $this->assertFalse($directive->isEnumRequired('level'));
    }

    public function test_is_enum_optional(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip high [file1.txt]');

        $this->assertFalse($directive->isEnumOptional('level'));
    }

    public function test_is_enum_value_allowed(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip high [file1.txt]');

        $this->assertTrue($directive->isEnumValueAllowed('level', 'high'));
        $this->assertTrue($directive->isEnumValueAllowed('level', 'medium'));
        $this->assertTrue($directive->isEnumValueAllowed('level', 'low'));
        $this->assertFalse($directive->isEnumValueAllowed('level', 'invalid'));
    }

    // ==================== VARIADIC TESTS ====================

    public function test_get_variadic_returns_values(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip high [file1.txt, file2.txt]');

        $values = $directive->getVariadic('files');
        $this->assertIsArray($values);
        $this->assertCount(2, $values);
        $this->assertContains('file1.txt', $values);
        $this->assertContains('file2.txt', $values);
    }

    public function test_get_variadic_returns_empty_array_for_unknown(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertSame([], $directive->getVariadic('unknown'));
    }

    public function test_get_variadics_returns_all(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip high [file1.txt, file2.txt]');

        $variadics = $directive->getVariadics();
        $this->assertArrayHasKey('files', $variadics);
        $this->assertCount(2, $variadics['files']);
    }

    public function test_has_variadic_returns_true_when_exists(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip high [file1.txt]');

        $this->assertTrue($directive->hasVariadic('files'));
    }

    public function test_has_variadic_returns_false_when_not_exists(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertEmpty($directive->getVariadic('files'));
    }

    public function test_get_variadic_arguments_returns_flat_collection(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip high [file1.txt, file2.txt, file3.txt]');

        $collection = $directive->getVariadicArguments();
        $this->assertCount(3, $collection);
        $this->assertTrue($collection->contains('file1.txt'));
        $this->assertTrue($collection->contains('file2.txt'));
        $this->assertTrue($collection->contains('file3.txt'));
    }

    public function test_has_variadic_arguments_returns_true_when_exists(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip high [file1.txt, file2.txt]');

        $this->assertTrue($directive->hasVariadicArguments());
    }

    public function test_has_variadic_arguments_returns_false_when_empty(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertFalse($directive->hasVariadicArguments());
    }

    // ==================== FLAG TESTS ====================

    public function test_get_flag_returns_value(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com --force');

        $this->assertTrue($directive->getFlag('force'));
        $this->assertFalse($directive->getFlag('verbose'));
    }

    public function test_get_flag_returns_false_for_unknown(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com --force');

        $this->assertFalse($directive->getFlag('unknown'));
    }

    public function test_get_flags_returns_all(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com --force --verbose');

        $flags = $directive->getFlags();
        $this->assertArrayHasKey('force', $flags);
        $this->assertArrayHasKey('verbose', $flags);
        $this->assertTrue($flags['force']);
        $this->assertTrue($flags['verbose']);
    }

    public function test_has_flag_returns_true_when_exists(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com --force');

        $this->assertTrue($directive->hasFlag('force'));
    }

    public function test_has_flag_returns_false_when_not_exists(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com --force');

        $this->assertFalse($directive->hasFlag('unknown'));
    }

    public function test_is_flag_active_returns_true_when_active(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com --force');

        $this->assertTrue($directive->isFlagActive('force'));
    }

    public function test_is_flag_active_returns_false_when_inactive(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com --force');

        $this->assertFalse($directive->isFlagActive('verbose'));
    }

    public function test_get_active_flags_returns_active_only(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com --force');

        $active = $directive->getActiveFlags();
        $this->assertContains('force', $active);
        $this->assertNotContains('verbose', $active);
    }

    // ==================== HAS METHODS TESTS ====================

    public function test_has_requireds_returns_true_when_requireds_exist(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertTrue($directive->hasRequireds());
    }

    public function test_has_defaults_returns_true_when_defaults_exist(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertTrue($directive->hasDefaults());
    }

    public function test_has_enums_returns_true_when_enums_exist(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com zip high [file1.txt]');

        $this->assertTrue($directive->hasEnums());
    }

    public function test_has_enums_returns_false_when_no_enums(): void
    {
        $directive = new TestEchoDirective($this->kernel, '');

        $this->assertFalse($directive->hasEnums());
    }

    public function test_has_flags_returns_true_when_flags_exist(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com --force');

        $this->assertTrue($directive->hasFlags());
    }

    public function test_has_flags_returns_false_when_no_flags(): void
    {
        $directive = new TestEchoDirective($this->kernel, '');

        $this->assertFalse($directive->hasFlags());
    }

    // ==================== OUTPUT METHODS TESTS ====================

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

    // ==================== ACCESSOR TESTS ====================

    public function test_get_container_returns_container(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertSame($this->app, $directive->getApplication());
    }

    public function test_get_kernel_returns_kernel(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertSame($this->kernel, $directive->getKernel());
    }

    public function test_get_console_returns_console(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertNotNull($directive->getConsole());
    }

    public function test_get_parsed_returns_parsed_record(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $parsed = $directive->getParsed();
        $this->assertSame('test:concrete', $parsed->source);
    }

    public function test_get_structure_returns_structure(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $structure = $directive->getStructure();
        $this->assertSame('test:concrete', $structure->getSource());
    }

    // ==================== EXECUTION TESTS ====================

    public function test_run_returns_success_exit_code(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $result = $directive->run();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_before_hook(): void
    {
        $directive = $this->createDirective('test:before-after');

        $result = $directive->run();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_after_hook(): void
    {
        $directive = $this->createDirective('test:before-after');

        $result = $directive->run();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== CALL TESTS ====================

    public function test_get_calls_returns_empty_array_by_default(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $this->assertEmpty($directive->getCalls());
    }

    public function test_call_queues_internal_call(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        // Use reflection to access protected call method
        $reflection = new \ReflectionClass($directive);
        $method = $reflection->getMethod('call');
        $method->invoke($directive, 'test:echo Hello');

        $calls = $directive->getCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('test:echo Hello', $calls[0]->query);
    }

    // ==================== CONTEXT TESTS ====================

    public function test_context_set_and_get(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $reflection = new \ReflectionClass($directive);
        $method = $reflection->getMethod('contextSet');
        $method->invoke($directive, 'test_key', 'test_value');

        $getMethod = $reflection->getMethod('contextGet');
        $value = $getMethod->invoke($directive, 'test_key');

        $this->assertSame('test_value', $value);
    }

    public function test_context_has(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $reflection = new \ReflectionClass($directive);
        $method = $reflection->getMethod('contextSet');
        $method->invoke($directive, 'test_key', 'test_value');

        $hasMethod = $reflection->getMethod('contextHas');
        $this->assertTrue($hasMethod->invoke($directive, 'test_key'));
        $this->assertFalse($hasMethod->invoke($directive, 'unknown_key'));
    }

    public function test_context_all(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $reflection = new \ReflectionClass($directive);
        $method = $reflection->getMethod('contextAll');
        $context = $method->invoke($directive);

        $this->assertInstanceOf(MapCollection::class, $context);
    }

    public function test_context_merge(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $reflection = new \ReflectionClass($directive);
        $mergeMethod = $reflection->getMethod('contextMerge');
        $mergeMethod->invoke($directive, ['key1' => 'value1', 'key2' => 'value2']);

        $getMethod = $reflection->getMethod('contextGet');
        $this->assertSame('value1', $getMethod->invoke($directive, 'key1'));
        $this->assertSame('value2', $getMethod->invoke($directive, 'key2'));
    }

    public function test_context_remove(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $reflection = new \ReflectionClass($directive);
        $setMethod = $reflection->getMethod('contextSet');
        $setMethod->invoke($directive, 'test_key', 'test_value');

        $removeMethod = $reflection->getMethod('contextRemove');
        $removeMethod->invoke($directive, 'test_key');

        $hasMethod = $reflection->getMethod('contextHas');
        $this->assertFalse($hasMethod->invoke($directive, 'test_key'));
    }

    public function test_context_clear(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $reflection = new \ReflectionClass($directive);
        $setMethod = $reflection->getMethod('contextSet');
        $setMethod->invoke($directive, 'test_key', 'test_value');

        $clearMethod = $reflection->getMethod('contextClear');
        $clearMethod->invoke($directive);

        $allMethod = $reflection->getMethod('contextAll');
        $context = $allMethod->invoke($directive);
        $this->assertTrue($context->isEmpty());
    }

    public function test_context_increment(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $reflection = new \ReflectionClass($directive);
        $incrementMethod = $reflection->getMethod('contextIncrement');

        $result = $incrementMethod->invoke($directive, 'counter');
        $this->assertSame(1, $result);

        $result = $incrementMethod->invoke($directive, 'counter', 5);
        $this->assertSame(6, $result);

        $getMethod = $reflection->getMethod('contextGet');
        $this->assertSame(6, $getMethod->invoke($directive, 'counter'));
    }

    public function test_context_decrement(): void
    {
        $directive = $this->createDirective('test:concrete John john@example.com');

        $reflection = new \ReflectionClass($directive);
        $setMethod = $reflection->getMethod('contextSet');
        $setMethod->invoke($directive, 'counter', 10);

        $decrementMethod = $reflection->getMethod('contextDecrement');
        $result = $decrementMethod->invoke($directive, 'counter', 3);
        $this->assertSame(7, $result);

        $getMethod = $reflection->getMethod('contextGet');
        $this->assertSame(7, $getMethod->invoke($directive, 'counter'));
    }
}
