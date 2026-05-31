<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestConcreteDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class AbstractDirectiveTest extends UnitTestCase
{
    private DirectiveInteractionService&MockObject $interaction;

    private TestConcreteDirective $directive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        $this->directive = new TestConcreteDirective($this->interaction);
    }

    public function test_set_arguments_sets_arguments(): void
    {
        $arguments = new ParameterCollection;
        $arguments->add(
            new ParameterRecord(name: 'name', value: 'John Doe'),
            new ParameterRecord(name: 'email', value: 'john@example.com'),
        );

        $result = $this->directive->setArguments($arguments);

        $this->assertSame($this->directive, $result);
        $this->assertSame('John Doe', $this->directive->argument('name'));
        $this->assertSame('john@example.com', $this->directive->argument('email'));
    }

    public function test_argument_returns_null_for_unknown_key(): void
    {
        $arguments = new ParameterCollection;
        $this->directive->setArguments($arguments);

        $result = $this->directive->argument('unknown');

        $this->assertNull($result);
    }

    public function test_argument_returns_null_when_value_is_boolean(): void
    {
        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'active', value: true));
        $this->directive->setArguments($arguments);

        $result = $this->directive->argument('active');

        $this->assertNull($result);
    }

    public function test_argument_returns_null_when_value_is_empty_string(): void
    {
        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'comment', value: ''));
        $this->directive->setArguments($arguments);

        $result = $this->directive->argument('comment');

        $this->assertNull($result);
    }

    public function test_has_argument_returns_true_when_argument_exists_with_non_empty_value(): void
    {
        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'name', value: 'John Doe'));
        $this->directive->setArguments($arguments);

        $result = $this->directive->hasArgument('name');

        $this->assertTrue($result);
    }

    public function test_has_argument_returns_false_when_argument_does_not_exist(): void
    {
        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'name', value: 'John Doe'));
        $this->directive->setArguments($arguments);

        $result = $this->directive->hasArgument('email');

        $this->assertFalse($result);
    }

    public function test_has_argument_returns_false_for_argument_with_empty_string_value(): void
    {
        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'comment', value: ''));
        $this->directive->setArguments($arguments);

        $result = $this->directive->hasArgument('comment');

        $this->assertFalse($result);
    }

    public function test_has_argument_returns_false_for_argument_with_null_value(): void
    {
        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'optional', value: null));
        $this->directive->setArguments($arguments);

        $result = $this->directive->hasArgument('optional');

        $this->assertFalse($result);
    }

    public function test_set_arguments_returns_self_for_chaining(): void
    {
        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'name', value: 'John'));

        $result = $this->directive->setArguments($arguments);

        $this->assertSame($this->directive, $result);
    }

    public function test_set_options_sets_options(): void
    {
        $options = new ParameterCollection;
        $options->add(
            new ParameterRecord(name: 'role', value: 'admin'),
            new ParameterRecord(name: 'active', value: true),
            new ParameterRecord(name: 'count', value: '10'),
        );

        $result = $this->directive->setOptions($options);

        $this->assertSame($this->directive, $result);
        $this->assertSame('admin', $this->directive->option('role'));
        $this->assertTrue($this->directive->option('active'));
        $this->assertSame('10', $this->directive->option('count'));
    }

    public function test_option_returns_null_for_unknown_key(): void
    {
        $options = new ParameterCollection;
        $this->directive->setOptions($options);

        $result = $this->directive->option('unknown');

        $this->assertNull($result);
    }

    public function test_option_returns_null_for_empty_string_value(): void
    {
        $options = new ParameterCollection;
        $options->add(new ParameterRecord(name: 'role', value: ''));
        $this->directive->setOptions($options);

        $result = $this->directive->option('role');

        $this->assertNull($result);
    }

    public function test_has_option_returns_true_when_option_exists_with_non_empty_value(): void
    {
        $options = new ParameterCollection;
        $options->add(new ParameterRecord(name: 'force', value: true));
        $this->directive->setOptions($options);

        $result = $this->directive->hasOption('force');

        $this->assertTrue($result);
    }

    public function test_has_option_returns_false_when_option_does_not_exist(): void
    {
        $options = new ParameterCollection;
        $options->add(new ParameterRecord(name: 'force', value: true));
        $this->directive->setOptions($options);

        $result = $this->directive->hasOption('unknown');

        $this->assertFalse($result);
    }

    public function test_has_option_returns_false_for_option_with_empty_string_value(): void
    {
        $options = new ParameterCollection;
        $options->add(new ParameterRecord(name: 'role', value: ''));
        $this->directive->setOptions($options);

        $result = $this->directive->hasOption('role');

        $this->assertFalse($result);
    }

    public function test_set_options_returns_self_for_chaining(): void
    {
        $options = new ParameterCollection;
        $options->add(new ParameterRecord(name: 'force', value: true));

        $result = $this->directive->setOptions($options);

        $this->assertSame($this->directive, $result);
    }

    public function test_line_delegates_to_interaction(): void
    {
        $expectedMessage = 'Test message';

        $this->interaction->expects($this->once())
            ->method('line')
            ->with($expectedMessage);

        $this->directive->line($expectedMessage);
    }

    public function test_info_delegates_to_interaction(): void
    {
        $expectedMessage = 'Test message';

        $this->interaction->expects($this->once())
            ->method('info')
            ->with($expectedMessage);

        $this->directive->info($expectedMessage);
    }

    public function test_error_delegates_to_interaction(): void
    {
        $expectedMessage = 'Test message';

        $this->interaction->expects($this->once())
            ->method('error')
            ->with($expectedMessage);

        $this->directive->error($expectedMessage);
    }

    public function test_warn_delegates_to_interaction(): void
    {
        $expectedMessage = 'Test message';

        $this->interaction->expects($this->once())
            ->method('warn')
            ->with($expectedMessage);

        $this->directive->warn($expectedMessage);
    }

    public function test_ask_delegates_to_interaction(): void
    {
        $expectedQuestion = 'What is your name?';
        $expectedAnswer = 'John Doe';

        $this->interaction->expects($this->once())
            ->method('ask')
            ->with($expectedQuestion)
            ->willReturn($expectedAnswer);

        $result = $this->directive->ask($expectedQuestion);

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

        $result = $this->directive->confirm($expectedQuestion);

        $this->assertTrue($result);
    }

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

        $this->directive->table($headers, $rows);
    }

    public function test_get_aliases_returns_empty_string_typed_collection_by_default(): void
    {
        $aliases = $this->directive->getAliases();

        $this->assertInstanceOf(StringTypedCollection::class, $aliases);
        $this->assertTrue($aliases->isEmpty());
    }

    public function test_get_blueprint_returns_directive_blueprint_record(): void
    {
        $blueprint = $this->directive->getBlueprint();

        $this->assertSame(TestConcreteDirective::class, $blueprint->class);
        $this->assertSame('test-concrete', $blueprint->signature);
        $this->assertSame('Test concrete directive for AbstractDirective tests', $blueprint->description);
    }

    public function test_arguments_are_empty_by_default(): void
    {
        $result = $this->directive->argument('anything');
        $this->assertNull($result);
        $this->assertFalse($this->directive->hasArgument('anything'));
    }

    public function test_options_are_empty_by_default(): void
    {
        $optionResult = $this->directive->option('anything');
        $hasOptionResult = $this->directive->hasOption('anything');

        $this->assertNull($optionResult);
        $this->assertFalse($hasOptionResult);
    }
}
