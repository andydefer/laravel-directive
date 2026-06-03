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

        // Arrange: Create mock interaction service and directive instance
        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        $this->directive = new TestConcreteDirective($this->interaction);
    }

    // ==================== Argument Management Tests ====================

    public function test_set_arguments_sets_arguments_correctly(): void
    {
        // Arrange: Create argument collection
        $arguments = new ParameterCollection();
        $arguments->add(
            new ParameterRecord(name: 'name', value: 'John Doe'),
            new ParameterRecord(name: 'email', value: 'john@example.com'),
        );

        // Act: Set arguments on directive
        $result = $this->directive->setArguments($arguments);

        // Assert: Verify arguments were set correctly
        $this->assertSame($this->directive, $result);
        $this->assertSame('John Doe', $this->directive->argument('name'));
        $this->assertSame('john@example.com', $this->directive->argument('email'));
    }

    public function test_argument_returns_null_for_unknown_key(): void
    {
        // Arrange: Set empty arguments
        $arguments = new ParameterCollection();
        $this->directive->setArguments($arguments);

        // Act: Get unknown argument
        $result = $this->directive->argument('unknown');

        // Assert: Verify null is returned
        $this->assertNull($result);
    }

    public function test_argument_returns_null_when_value_is_boolean(): void
    {
        // Arrange: Add boolean argument
        $arguments = new ParameterCollection();
        $arguments->add(new ParameterRecord(name: 'active', value: true));
        $this->directive->setArguments($arguments);

        // Act: Get boolean argument
        $result = $this->directive->argument('active');

        // Assert: Verify null is returned (boolean values are not valid arguments)
        $this->assertNull($result);
    }

    public function test_argument_returns_null_when_value_is_empty_string(): void
    {
        // Arrange: Add empty string argument
        $arguments = new ParameterCollection();
        $arguments->add(new ParameterRecord(name: 'comment', value: ''));
        $this->directive->setArguments($arguments);

        // Act: Get empty string argument
        $result = $this->directive->argument('comment');

        // Assert: Verify null is returned
        $this->assertNull($result);
    }

    public function test_has_argument_returns_true_when_argument_exists_with_non_empty_value(): void
    {
        // Arrange: Add valid argument
        $arguments = new ParameterCollection();
        $arguments->add(new ParameterRecord(name: 'name', value: 'John Doe'));
        $this->directive->setArguments($arguments);

        // Act & Assert: Verify argument exists
        $this->assertTrue($this->directive->hasArgument('name'));
    }

    public function test_has_argument_returns_false_when_argument_does_not_exist(): void
    {
        // Arrange: Add argument but not the one we're checking
        $arguments = new ParameterCollection();
        $arguments->add(new ParameterRecord(name: 'name', value: 'John Doe'));
        $this->directive->setArguments($arguments);

        // Act & Assert: Verify argument does not exist
        $this->assertFalse($this->directive->hasArgument('email'));
    }

    public function test_has_argument_returns_false_for_argument_with_empty_string_value(): void
    {
        // Arrange: Add empty string argument
        $arguments = new ParameterCollection();
        $arguments->add(new ParameterRecord(name: 'comment', value: ''));
        $this->directive->setArguments($arguments);

        // Act & Assert: Verify empty string is not considered present
        $this->assertFalse($this->directive->hasArgument('comment'));
    }

    public function test_has_argument_returns_false_for_argument_with_null_value(): void
    {
        // Arrange: Add null argument
        $arguments = new ParameterCollection();
        $arguments->add(new ParameterRecord(name: 'optional', value: null));
        $this->directive->setArguments($arguments);

        // Act & Assert: Verify null is not considered present
        $this->assertFalse($this->directive->hasArgument('optional'));
    }

    public function test_set_arguments_returns_self_for_chaining(): void
    {
        // Arrange: Create argument collection
        $arguments = new ParameterCollection();
        $arguments->add(new ParameterRecord(name: 'name', value: 'John'));

        // Act: Set arguments
        $result = $this->directive->setArguments($arguments);

        // Assert: Verify method returns self for chaining
        $this->assertSame($this->directive, $result);
    }

    // ==================== Option Management Tests ====================

    public function test_set_options_sets_options_correctly(): void
    {
        // Arrange: Create options collection with mixed types
        $options = new ParameterCollection();
        $options->add(
            new ParameterRecord(name: 'role', value: 'admin'),
            new ParameterRecord(name: 'active', value: true),
            new ParameterRecord(name: 'count', value: '10'),
        );

        // Act: Set options on directive
        $result = $this->directive->setOptions($options);

        // Assert: Verify options were set correctly
        $this->assertSame($this->directive, $result);
        $this->assertSame('admin', $this->directive->option('role'));
        $this->assertTrue($this->directive->option('active'));
        $this->assertSame('10', $this->directive->option('count'));
    }

    public function test_option_returns_null_for_unknown_key(): void
    {
        // Arrange: Set empty options
        $options = new ParameterCollection();
        $this->directive->setOptions($options);

        // Act: Get unknown option
        $result = $this->directive->option('unknown');

        // Assert: Verify null is returned
        $this->assertNull($result);
    }

    public function test_option_returns_null_for_empty_string_value(): void
    {
        // Arrange: Add empty string option
        $options = new ParameterCollection();
        $options->add(new ParameterRecord(name: 'role', value: ''));
        $this->directive->setOptions($options);

        // Act: Get empty option
        $result = $this->directive->option('role');

        // Assert: Verify null is returned
        $this->assertNull($result);
    }

    public function test_has_option_returns_true_when_option_exists_with_non_empty_value(): void
    {
        // Arrange: Add boolean flag option
        $options = new ParameterCollection();
        $options->add(new ParameterRecord(name: 'force', value: true));
        $this->directive->setOptions($options);

        // Act & Assert: Verify option exists
        $this->assertTrue($this->directive->hasOption('force'));
    }

    public function test_has_option_returns_false_when_option_does_not_exist(): void
    {
        // Arrange: Add option but not the one we're checking
        $options = new ParameterCollection();
        $options->add(new ParameterRecord(name: 'force', value: true));
        $this->directive->setOptions($options);

        // Act & Assert: Verify option does not exist
        $this->assertFalse($this->directive->hasOption('unknown'));
    }

    public function test_has_option_returns_false_for_option_with_empty_string_value(): void
    {
        // Arrange: Add empty string option
        $options = new ParameterCollection();
        $options->add(new ParameterRecord(name: 'role', value: ''));
        $this->directive->setOptions($options);

        // Act & Assert: Verify empty string is not considered present
        $this->assertFalse($this->directive->hasOption('role'));
    }

    public function test_set_options_returns_self_for_chaining(): void
    {
        // Arrange: Create options collection
        $options = new ParameterCollection();
        $options->add(new ParameterRecord(name: 'force', value: true));

        // Act: Set options
        $result = $this->directive->setOptions($options);

        // Assert: Verify method returns self for chaining
        $this->assertSame($this->directive, $result);
    }

    // ==================== Display Method Delegation Tests ====================

    public function test_line_delegates_to_interaction(): void
    {
        // Arrange: Set expected message
        $expectedMessage = 'Test message';

        // Act: Expect interaction line method to be called
        $this->interaction->expects($this->once())
            ->method('line')
            ->with($expectedMessage);

        // Act: Call directive line method
        $this->directive->line($expectedMessage);
    }

    public function test_info_delegates_to_interaction(): void
    {
        // Arrange: Set expected message
        $expectedMessage = 'Test message';

        // Act: Expect interaction info method to be called
        $this->interaction->expects($this->once())
            ->method('info')
            ->with($expectedMessage);

        // Act: Call directive info method
        $this->directive->info($expectedMessage);
    }

    public function test_error_delegates_to_interaction(): void
    {
        // Arrange: Set expected message
        $expectedMessage = 'Test message';

        // Act: Expect interaction error method to be called
        $this->interaction->expects($this->once())
            ->method('error')
            ->with($expectedMessage);

        // Act: Call directive error method
        $this->directive->error($expectedMessage);
    }

    public function test_warn_delegates_to_interaction(): void
    {
        // Arrange: Set expected message
        $expectedMessage = 'Test message';

        // Act: Expect interaction warn method to be called
        $this->interaction->expects($this->once())
            ->method('warn')
            ->with($expectedMessage);

        // Act: Call directive warn method
        $this->directive->warn($expectedMessage);
    }

    // ==================== User Interaction Delegation Tests ====================

    public function test_ask_delegates_to_interaction(): void
    {
        // Arrange: Set expected question and answer
        $expectedQuestion = 'What is your name?';
        $expectedAnswer = 'John Doe';

        // Act: Expect interaction ask method to be called
        $this->interaction->expects($this->once())
            ->method('ask')
            ->with($expectedQuestion)
            ->willReturn($expectedAnswer);

        // Act: Call directive ask method
        $result = $this->directive->ask($expectedQuestion);

        // Assert: Verify answer is returned correctly
        $this->assertSame($expectedAnswer, $result);
    }

    public function test_confirm_delegates_to_interaction(): void
    {
        // Arrange: Set expected question and answer
        $expectedQuestion = 'Continue?';
        $expectedAnswer = true;

        // Act: Expect interaction confirm method to be called
        $this->interaction->expects($this->once())
            ->method('confirm')
            ->with($expectedQuestion)
            ->willReturn($expectedAnswer);

        // Act: Call directive confirm method
        $result = $this->directive->confirm($expectedQuestion);

        // Assert: Verify confirmation is returned correctly
        $this->assertTrue($result);
    }

    // ==================== Table Display Delegation Tests ====================

    public function test_table_delegates_to_interaction(): void
    {
        // Arrange: Create headers and rows
        $headers = new StringTypedCollection();
        $headers->add('Name', 'Email');

        $rows = new RowCollection();
        $row = new RowCollection();
        $row->add('John', 'john@example.com');
        $rows->add($row);

        // Act: Expect interaction table method to be called
        $this->interaction->expects($this->once())
            ->method('table')
            ->with($headers, $rows);

        // Act: Call directive table method
        $this->directive->table($headers, $rows);
    }

    // ==================== Default Values Tests ====================

    public function test_get_aliases_returns_empty_string_typed_collection_by_default(): void
    {
        // Act: Get aliases
        $aliases = $this->directive->getAliases();

        // Assert: Verify empty collection is returned
        $this->assertInstanceOf(StringTypedCollection::class, $aliases);
        $this->assertTrue($aliases->isEmpty());
    }

    public function test_get_blueprint_returns_directive_blueprint_record(): void
    {
        // Act: Get blueprint
        $blueprint = $this->directive->getBlueprint();

        // Assert: Verify blueprint contains correct data
        $this->assertSame(TestConcreteDirective::class, $blueprint->class);
        $this->assertSame('test-concrete', $blueprint->signature);
        $this->assertSame('Test concrete directive for AbstractDirective tests', $blueprint->description);
    }

    public function test_arguments_are_empty_by_default(): void
    {
        // Act: Get argument values from fresh directive
        $argumentResult = $this->directive->argument('anything');
        $hasArgumentResult = $this->directive->hasArgument('anything');

        // Assert: Verify no arguments are present
        $this->assertNull($argumentResult);
        $this->assertFalse($hasArgumentResult);
    }

    public function test_options_are_empty_by_default(): void
    {
        // Act: Get option values from fresh directive
        $optionResult = $this->directive->option('anything');
        $hasOptionResult = $this->directive->hasOption('anything');

        // Assert: Verify no options are present
        $this->assertNull($optionResult);
        $this->assertFalse($hasOptionResult);
    }

    // ==================== New Line and Separator Tests ====================

    public function test_new_line_delegates_to_interaction(): void
    {
        // Act: Expect interaction newLine method to be called
        $this->interaction->expects($this->once())
            ->method('newLine');

        // Act: Call directive newLine method
        $this->directive->newLine();
    }

    public function test_separator_delegates_to_interaction_with_default_parameters(): void
    {
        // Act: Expect interaction separator method to be called with default parameters
        $this->interaction->expects($this->once())
            ->method('separator')
            ->with('-', 80);

        // Act: Call directive separator method with no parameters
        $this->directive->separator();
    }

    public function test_separator_delegates_to_interaction_with_custom_character(): void
    {
        // Arrange: Custom separator character
        $character = '=';

        // Act: Expect interaction separator method to be called with custom character
        $this->interaction->expects($this->once())
            ->method('separator')
            ->with('=', 80);

        // Act: Call directive separator method with custom character
        $this->directive->separator($character);
    }

    public function test_separator_delegates_to_interaction_with_custom_length(): void
    {
        // Arrange: Custom separator length
        $length = 50;

        // Act: Expect interaction separator method to be called with custom length
        $this->interaction->expects($this->once())
            ->method('separator')
            ->with('-', 50);

        // Act: Call directive separator method with custom length
        $this->directive->separator(length: $length);
    }

    public function test_separator_delegates_to_interaction_with_custom_character_and_length(): void
    {
        // Arrange: Custom separator character and length
        $character = '*';
        $length = 100;

        // Act: Expect interaction separator method to be called with custom parameters
        $this->interaction->expects($this->once())
            ->method('separator')
            ->with('*', 100);

        // Act: Call directive separator method with custom parameters
        $this->directive->separator($character, $length);
    }

    public function test_multiple_new_lines_and_separators_work_together(): void
    {
        // Arrange: Set up expectations in sequence
        $this->interaction->expects($this->exactly(2))
            ->method('newLine');

        $this->interaction->expects($this->once())
            ->method('separator')
            ->with('-', 80);

        // Act: Chain multiple display methods
        $this->directive->newLine();
        $this->directive->separator();
        $this->directive->newLine();
    }
}
