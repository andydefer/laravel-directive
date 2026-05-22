<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Directive\Unit;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\Directive\Tests\TestCase;
use AndyDefer\Directive\Enums\MessageType;
use AndyDefer\Directive\Records\AskQuestionRecord;
use AndyDefer\Directive\Records\ConfirmQuestionRecord;
use AndyDefer\Directive\Records\DisplayMessageRecord;
use AndyDefer\Directive\Records\DisplayTableRecord;
use AndyDefer\Directive\Tasks\AskQuestionTask;
use AndyDefer\Directive\Tasks\ConfirmQuestionTask;
use AndyDefer\Directive\Tasks\DisplayMessageTask;
use AndyDefer\Directive\Tasks\DisplayTableTask;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestConcreteDirective;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class AbstractDirectiveTest extends TestCase
{
    private DisplayMessageTask&MockObject $displayMessage;
    private AskQuestionTask&MockObject $askQuestion;
    private ConfirmQuestionTask&MockObject $confirmQuestion;
    private DisplayTableTask&MockObject $displayTable;
    private TestConcreteDirective $directive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->displayMessage = $this->createMock(DisplayMessageTask::class);
        $this->askQuestion = $this->createMock(AskQuestionTask::class);
        $this->confirmQuestion = $this->createMock(ConfirmQuestionTask::class);
        $this->displayTable = $this->createMock(DisplayTableTask::class);

        $this->directive = new TestConcreteDirective(
            $this->displayMessage,
            $this->askQuestion,
            $this->confirmQuestion,
            $this->displayTable,
        );
    }

    // ==================== Argument Tests ====================

    public function test_set_arguments_sets_arguments(): void
    {
        // Arrange
        $arguments = new ParameterCollection();
        $arguments->add(
            new ParameterRecord(name: 'name', value: 'John Doe'),
            new ParameterRecord(name: 'email', value: 'john@example.com'),
        );

        // Act
        $result = $this->directive->setArguments($arguments);

        // Assert
        $this->assertSame($this->directive, $result);
        $this->assertSame('John Doe', $this->directive->argument('name'));
        $this->assertSame('john@example.com', $this->directive->argument('email'));
    }

    public function test_argument_returns_null_for_unknown_key(): void
    {
        // Arrange
        $arguments = new ParameterCollection();
        $this->directive->setArguments($arguments);

        // Act
        $result = $this->directive->argument('unknown');

        // Assert
        $this->assertNull($result);
    }

    public function test_argument_returns_null_when_value_is_boolean(): void
    {
        // Arrange
        $arguments = new ParameterCollection();
        $arguments->add(new ParameterRecord(name: 'active', value: true));
        $this->directive->setArguments($arguments);

        // Act
        $result = $this->directive->argument('active');

        // Assert
        $this->assertNull($result);
    }

    public function test_set_arguments_returns_self_for_chaining(): void
    {
        // Arrange
        $arguments = new ParameterCollection();
        $arguments->add(new ParameterRecord(name: 'name', value: 'John'));

        // Act
        $result = $this->directive->setArguments($arguments);

        // Assert
        $this->assertSame($this->directive, $result);
    }

    // ==================== Option Tests ====================

    public function test_set_options_sets_options(): void
    {
        // Arrange
        $options = new ParameterCollection();
        $options->add(
            new ParameterRecord(name: 'role', value: 'admin'),
            new ParameterRecord(name: 'active', value: true),
            new ParameterRecord(name: 'count', value: '10'),
        );

        // Act
        $result = $this->directive->setOptions($options);

        // Assert
        $this->assertSame($this->directive, $result);
        $this->assertSame('admin', $this->directive->option('role'));
        $this->assertTrue($this->directive->option('active'));
        $this->assertSame('10', $this->directive->option('count'));
    }

    public function test_option_returns_null_for_unknown_key(): void
    {
        // Arrange
        $options = new ParameterCollection();
        $this->directive->setOptions($options);

        // Act
        $result = $this->directive->option('unknown');

        // Assert
        $this->assertNull($result);
    }

    public function test_has_option_returns_true_when_option_exists(): void
    {
        // Arrange
        $options = new ParameterCollection();
        $options->add(new ParameterRecord(name: 'force', value: true));
        $this->directive->setOptions($options);

        // Act
        $result = $this->directive->hasOption('force');

        // Assert
        $this->assertTrue($result);
    }

    public function test_has_option_returns_false_when_option_does_not_exist(): void
    {
        // Arrange
        $options = new ParameterCollection();
        $options->add(new ParameterRecord(name: 'force', value: true));
        $this->directive->setOptions($options);

        // Act
        $result = $this->directive->hasOption('unknown');

        // Assert
        $this->assertFalse($result);
    }

    public function test_set_options_returns_self_for_chaining(): void
    {
        // Arrange
        $options = new ParameterCollection();
        $options->add(new ParameterRecord(name: 'force', value: true));

        // Act
        $result = $this->directive->setOptions($options);

        // Assert
        $this->assertSame($this->directive, $result);
    }

    // ==================== Display Method Tests ====================

    public function test_line_delegates_to_display_message_task_with_line_type(): void
    {
        // Arrange
        $expectedMessage = 'Test message';
        $expectedType = MessageType::LINE;

        $this->displayMessage->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DisplayMessageRecord $record) use ($expectedMessage, $expectedType): bool {
                return $record->message === $expectedMessage && $record->type === $expectedType;
            }));

        // Act
        $this->directive->line($expectedMessage);

        // Assert - done via expectations
    }

    public function test_info_delegates_to_display_message_task_with_info_type(): void
    {
        // Arrange
        $expectedMessage = 'Test message';
        $expectedType = MessageType::INFO;

        $this->displayMessage->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DisplayMessageRecord $record) use ($expectedMessage, $expectedType): bool {
                return $record->message === $expectedMessage && $record->type === $expectedType;
            }));

        // Act
        $this->directive->info($expectedMessage);

        // Assert - done via expectations
    }

    public function test_error_delegates_to_display_message_task_with_error_type(): void
    {
        // Arrange
        $expectedMessage = 'Test message';
        $expectedType = MessageType::ERROR;

        $this->displayMessage->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DisplayMessageRecord $record) use ($expectedMessage, $expectedType): bool {
                return $record->message === $expectedMessage && $record->type === $expectedType;
            }));

        // Act
        $this->directive->error($expectedMessage);

        // Assert - done via expectations
    }

    public function test_warn_delegates_to_display_message_task_with_warning_type(): void
    {
        // Arrange
        $expectedMessage = 'Test message';
        $expectedType = MessageType::WARNING;

        $this->displayMessage->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DisplayMessageRecord $record) use ($expectedMessage, $expectedType): bool {
                return $record->message === $expectedMessage && $record->type === $expectedType;
            }));

        // Act
        $this->directive->warn($expectedMessage);

        // Assert - done via expectations
    }

    // ==================== User Interaction Tests ====================

    public function test_ask_delegates_to_ask_question_task(): void
    {
        // Arrange
        $expectedQuestion = 'What is your name?';
        $expectedAnswer = 'John Doe';

        $this->askQuestion->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (AskQuestionRecord $record) use ($expectedQuestion): bool {
                return $record->question === $expectedQuestion;
            }))
            ->willReturn($expectedAnswer);

        // Act
        $result = $this->directive->ask($expectedQuestion);

        // Assert
        $this->assertSame($expectedAnswer, $result);
    }

    public function test_confirm_delegates_to_confirm_question_task(): void
    {
        // Arrange
        $expectedQuestion = 'Continue?';
        $expectedAnswer = true;

        $this->confirmQuestion->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (AskQuestionRecord $record) use ($expectedQuestion): bool {
                return $record->question === $expectedQuestion;
            }))
            ->willReturn($expectedAnswer);

        // Act
        $result = $this->directive->confirm($expectedQuestion);

        // Assert
        $this->assertTrue($result);
    }

    // ==================== Table Method Tests ====================

    public function test_table_delegates_to_display_table_task(): void
    {
        // Arrange
        $headers = new StringTypedCollection();
        $headers->add('Name', 'Email');

        $rows = new RowCollection();
        $row = new RowCollection();
        $row->add('John', 'john@example.com');
        $rows->add($row);

        $this->displayTable->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DisplayTableRecord $record) use ($headers, $rows): bool {
                return $record->headers === $headers && $record->rows === $rows;
            }));

        // Act
        $this->directive->table($headers, $rows);

        // Assert - done via expectations
    }

    // ==================== Alias Tests ====================

    public function test_get_aliases_returns_empty_string_typed_collection_by_default(): void
    {
        // Arrange & Act
        $aliases = $this->directive->getAliases();

        // Assert
        $this->assertInstanceOf(StringTypedCollection::class, $aliases);
        $this->assertTrue($aliases->isEmpty());
    }

    // ==================== Blueprint Tests ====================

    public function test_get_blueprint_returns_directive_blueprint_record(): void
    {
        // Arrange & Act
        $blueprint = $this->directive->getBlueprint();

        // Assert
        $this->assertSame(TestConcreteDirective::class, $blueprint->class);
        $this->assertSame('test:concrete', $blueprint->signature);
        $this->assertSame('Test concrete directive for AbstractDirective tests', $blueprint->description);
    }

    // ==================== Default Value Tests ====================

    public function test_arguments_are_empty_by_default(): void
    {
        // Arrange - no arguments set

        // Act
        $result = $this->directive->argument('anything');

        // Assert
        $this->assertNull($result);
    }

    public function test_options_are_empty_by_default(): void
    {
        // Arrange - no options set

        // Act
        $optionResult = $this->directive->option('anything');
        $hasOptionResult = $this->directive->hasOption('anything');

        // Assert
        $this->assertNull($optionResult);
        $this->assertFalse($hasOptionResult);
    }
}
