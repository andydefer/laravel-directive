<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit;

use AndyDefer\Directive\Collections\ParameterVOCollection;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestConcreteDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\Directive\ValueObjects\ParameterVO;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\PhpServices\Enums\PrimitiveType;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class AbstractDirectiveTest extends UnitTestCase
{
    private DirectiveInteractionService&MockObject $interaction;

    private ?Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        $this->application = null; // Pas besoin de Laravel pour ces tests
    }

    private function createDirectiveContext(): DirectiveContext
    {
        return new DirectiveContext(
            blueprint: new DirectiveBlueprintRecord(TestConcreteDirective::class, 'test-concrete', 'Test directive'),
            aliases: new StringTypedCollection,
            laravelApplication: $this->application,
        );
    }

    private function createDirectiveWithContext(DirectiveContext $context): TestConcreteDirective
    {
        return new TestConcreteDirective($context, $this->interaction);
    }

    // ==================== Argument Management Tests ====================

    public function test_arguments_are_set_correctly_via_context(): void
    {
        $arguments = new ParameterVOCollection;
        $arguments->add(
            new ParameterVO(name: 'name', value: 'John Doe', type: PrimitiveType::STRING),
            new ParameterVO(name: 'email', value: 'john@example.com', type: PrimitiveType::STRING),
        );

        $context = $this->createDirectiveContext();
        $context->setArguments($arguments);

        $directive = $this->createDirectiveWithContext($context);

        $this->assertSame('John Doe', $directive->argument('name'));
        $this->assertSame('john@example.com', $directive->argument('email'));
    }

    public function test_argument_returns_null_for_unknown_key(): void
    {
        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);

        $this->assertNull($directive->argument('unknown'));
    }

    public function test_argument_returns_null_when_value_is_empty_string(): void
    {
        $arguments = new ParameterVOCollection;
        $arguments->add(new ParameterVO(name: 'comment', value: '', type: PrimitiveType::STRING));

        $context = $this->createDirectiveContext();
        $context->setArguments($arguments);

        $directive = $this->createDirectiveWithContext($context);

        $this->assertNull($directive->argument('comment'));
    }

    public function test_has_argument_returns_true_when_argument_exists(): void
    {
        $arguments = new ParameterVOCollection;
        $arguments->add(new ParameterVO(name: 'name', value: 'John Doe', type: PrimitiveType::STRING));

        $context = $this->createDirectiveContext();
        $context->setArguments($arguments);

        $directive = $this->createDirectiveWithContext($context);

        $this->assertTrue($directive->hasArgument('name'));
    }

    public function test_has_argument_returns_false_when_argument_does_not_exist(): void
    {
        $arguments = new ParameterVOCollection;
        $arguments->add(new ParameterVO(name: 'name', value: 'John Doe', type: PrimitiveType::STRING));

        $context = $this->createDirectiveContext();
        $context->setArguments($arguments);

        $directive = $this->createDirectiveWithContext($context);

        $this->assertFalse($directive->hasArgument('email'));
    }

    public function test_has_argument_returns_false_for_empty_string_value(): void
    {
        $arguments = new ParameterVOCollection;
        $arguments->add(new ParameterVO(name: 'comment', value: '', type: PrimitiveType::STRING));

        $context = $this->createDirectiveContext();
        $context->setArguments($arguments);

        $directive = $this->createDirectiveWithContext($context);

        $this->assertFalse($directive->hasArgument('comment'));
    }

    // ==================== Option Management Tests ====================

    public function test_options_are_set_correctly_via_context(): void
    {
        $options = new ParameterVOCollection;
        $options->add(
            new ParameterVO(name: 'role', value: 'admin', type: PrimitiveType::STRING),
            new ParameterVO(name: 'active', value: true, type: PrimitiveType::BOOL),
            new ParameterVO(name: 'count', value: 10, type: PrimitiveType::INT),
        );

        $context = $this->createDirectiveContext();
        $context->setOptions($options);

        $directive = $this->createDirectiveWithContext($context);

        $this->assertSame('admin', $directive->option('role'));
        $this->assertTrue($directive->option('active'));
        $this->assertSame(10, $directive->option('count'));
    }

    public function test_option_returns_null_for_unknown_key(): void
    {
        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);

        $this->assertNull($directive->option('unknown'));
    }

    public function test_option_returns_null_for_empty_string_value(): void
    {
        $options = new ParameterVOCollection;
        $options->add(new ParameterVO(name: 'role', value: '', type: PrimitiveType::STRING));

        $context = $this->createDirectiveContext();
        $context->setOptions($options);

        $directive = $this->createDirectiveWithContext($context);

        $this->assertNull($directive->option('role'));
    }

    public function test_has_option_returns_true_when_option_exists(): void
    {
        $options = new ParameterVOCollection;
        $options->add(new ParameterVO(name: 'force', value: true, type: PrimitiveType::BOOL));

        $context = $this->createDirectiveContext();
        $context->setOptions($options);

        $directive = $this->createDirectiveWithContext($context);

        $this->assertTrue($directive->hasOption('force'));
    }

    public function test_has_option_returns_false_when_option_does_not_exist(): void
    {
        $options = new ParameterVOCollection;
        $options->add(new ParameterVO(name: 'force', value: true, type: PrimitiveType::BOOL));

        $context = $this->createDirectiveContext();
        $context->setOptions($options);

        $directive = $this->createDirectiveWithContext($context);

        $this->assertFalse($directive->hasOption('unknown'));
    }

    public function test_has_option_returns_false_for_empty_string_value(): void
    {
        $options = new ParameterVOCollection;
        $options->add(new ParameterVO(name: 'role', value: '', type: PrimitiveType::STRING));

        $context = $this->createDirectiveContext();
        $context->setOptions($options);

        $directive = $this->createDirectiveWithContext($context);

        $this->assertFalse($directive->hasOption('role'));
    }

    // ==================== Display Method Delegation Tests ====================

    public function test_line_delegates_to_interaction(): void
    {
        $expectedMessage = 'Test message';
        $this->interaction->expects($this->once())->method('line')->with($expectedMessage);

        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);
        $directive->line($expectedMessage);
    }

    public function test_info_delegates_to_interaction(): void
    {
        $expectedMessage = 'Test message';
        $this->interaction->expects($this->once())->method('info')->with($expectedMessage);

        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);
        $directive->info($expectedMessage);
    }

    public function test_error_delegates_to_interaction(): void
    {
        $expectedMessage = 'Test message';
        $this->interaction->expects($this->once())->method('error')->with($expectedMessage);

        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);
        $directive->error($expectedMessage);
    }

    public function test_warn_delegates_to_interaction(): void
    {
        $expectedMessage = 'Test message';
        $this->interaction->expects($this->once())->method('warn')->with($expectedMessage);

        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);
        $directive->warn($expectedMessage);
    }

    // ==================== User Interaction Delegation Tests ====================

    public function test_ask_delegates_to_interaction(): void
    {
        $expectedQuestion = 'What is your name?';
        $expectedAnswer = 'John Doe';

        $this->interaction->expects($this->once())
            ->method('ask')
            ->with($expectedQuestion)
            ->willReturn($expectedAnswer);

        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);
        $result = $directive->ask($expectedQuestion);

        $this->assertSame($expectedAnswer, $result);
    }

    public function test_confirm_delegates_to_interaction(): void
    {
        $expectedQuestion = 'Continue?';
        $expectedAnswer = true;

        $this->interaction->expects($this->once())
            ->method('confirm')
            ->with($expectedQuestion)
            ->willReturn($expectedAnswer);

        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);
        $result = $directive->confirm($expectedQuestion);

        $this->assertTrue($result);
    }

    // ==================== Table Display Delegation Tests ====================

    public function test_table_delegates_to_interaction(): void
    {
        $headers = new StringTypedCollection;
        $headers->add('Name', 'Email');

        $rows = new RowCollection;
        $row = new RowCollection;
        $row->add('John', 'john@example.com');
        $rows->add($row);

        $this->interaction->expects($this->once())
            ->method('table')
            ->with($headers, $rows);

        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);
        $directive->table($headers, $rows);
    }

    // ==================== Default Values Tests ====================

    public function test_get_aliases_returns_empty_collection_by_default(): void
    {
        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);

        $aliases = $directive->getAliases();

        $this->assertInstanceOf(StringTypedCollection::class, $aliases);
        $this->assertTrue($aliases->isEmpty());
    }

    public function test_get_blueprint_returns_correct_blueprint(): void
    {
        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);

        $blueprint = $directive->getBlueprint();

        $this->assertSame(TestConcreteDirective::class, $blueprint->class);
        $this->assertSame('test-concrete', $blueprint->signature);
    }

    public function test_arguments_are_empty_by_default(): void
    {
        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);

        $this->assertNull($directive->argument('anything'));
        $this->assertFalse($directive->hasArgument('anything'));
    }

    public function test_options_are_empty_by_default(): void
    {
        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);

        $this->assertNull($directive->option('anything'));
        $this->assertFalse($directive->hasOption('anything'));
    }

    // ==================== Variadic Arguments Tests ====================

    public function test_variadic_arguments_are_set_correctly(): void
    {
        $variadicArguments = new StringTypedCollection;
        $variadicArguments->add('file1.txt', 'file2.txt', 'file3.txt');

        $context = $this->createDirectiveContext();
        $context->setVariadicArguments($variadicArguments);

        $directive = $this->createDirectiveWithContext($context);

        $this->assertTrue($directive->hasVariadicArguments());
        $this->assertEquals(3, $directive->getVariadicArguments()->count());
        $this->assertTrue($directive->getVariadicArguments()->contains('file1.txt'));
    }

    public function test_variadic_arguments_are_empty_by_default(): void
    {
        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);

        $this->assertFalse($directive->hasVariadicArguments());
        $this->assertTrue($directive->getVariadicArguments()->isEmpty());
    }

    // ==================== New Line and Separator Tests ====================

    public function test_new_line_delegates_to_interaction(): void
    {
        $this->interaction->expects($this->once())->method('newLine');

        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);
        $directive->newLine();
    }

    public function test_separator_delegates_to_interaction_with_default_parameters(): void
    {
        $this->interaction->expects($this->once())->method('separator')->with('-', 80);

        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);
        $directive->separator();
    }

    public function test_separator_with_custom_character(): void
    {
        $this->interaction->expects($this->once())->method('separator')->with('=', 80);

        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);
        $directive->separator('=');
    }

    public function test_separator_with_custom_length(): void
    {
        $this->interaction->expects($this->once())->method('separator')->with('-', 50);

        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);
        $directive->separator(length: 50);
    }

    public function test_separator_with_custom_character_and_length(): void
    {
        $this->interaction->expects($this->once())->method('separator')->with('*', 100);

        $context = $this->createDirectiveContext();
        $directive = $this->createDirectiveWithContext($context);
        $directive->separator('*', 100);
    }
}
