<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Enums\InputType;
use AndyDefer\Directive\Enums\MessageType;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\DisplayMessageRecord;
use AndyDefer\Directive\Records\DisplayTableRecord;
use AndyDefer\Directive\Records\QuestionRecord;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Records\UserChoiceRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class DirectiveInteractionServiceTest extends UnitTestCase
{
    private RenderDispatcher&MockObject $renderDispatcher;

    private InputDispatcher&MockObject $inputDispatcher;

    private DirectiveInteractionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create mocked tasks
        $this->renderDispatcher = $this->createMock(RenderDispatcher::class);
        $this->inputDispatcher = $this->createMock(InputDispatcher::class);

        // Arrange: Create service instance with mocks
        $this->service = new DirectiveInteractionService(
            $this->renderDispatcher,
            $this->inputDispatcher,
        );
    }

    // ==================== Display Message Tests ====================

    public function test_line_renders_message_with_line_type(): void
    {
        // Arrange
        $message = 'Test line message';

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (RenderRecord $record) use ($message) {
                    return $record->type === RenderType::DISPLAY_MESSAGE
                        && $record->messageRecord instanceof DisplayMessageRecord
                        && $record->messageRecord->message === $message
                        && $record->messageRecord->type === MessageType::LINE;
                }),
                RenderType::DISPLAY_MESSAGE
            )
            ->willReturn('');

        // Act
        $this->service->line($message);
    }

    public function test_info_renders_message_with_info_type(): void
    {
        // Arrange
        $message = 'Test info message';

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (RenderRecord $record) use ($message) {
                    return $record->type === RenderType::DISPLAY_MESSAGE
                        && $record->messageRecord instanceof DisplayMessageRecord
                        && $record->messageRecord->message === $message
                        && $record->messageRecord->type === MessageType::INFO;
                }),
                RenderType::DISPLAY_MESSAGE
            )
            ->willReturn('');

        // Act
        $this->service->info($message);
    }

    public function test_error_renders_message_with_error_type(): void
    {
        // Arrange
        $message = 'Test error message';

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (RenderRecord $record) use ($message) {
                    return $record->type === RenderType::DISPLAY_MESSAGE
                        && $record->messageRecord instanceof DisplayMessageRecord
                        && $record->messageRecord->message === $message
                        && $record->messageRecord->type === MessageType::ERROR;
                }),
                RenderType::DISPLAY_MESSAGE
            )
            ->willReturn('');

        // Act
        $this->service->error($message);
    }

    public function test_warn_renders_message_with_warning_type(): void
    {
        // Arrange
        $message = 'Test warning message';

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (RenderRecord $record) use ($message) {
                    return $record->type === RenderType::DISPLAY_MESSAGE
                        && $record->messageRecord instanceof DisplayMessageRecord
                        && $record->messageRecord->message === $message
                        && $record->messageRecord->type === MessageType::WARNING;
                }),
                RenderType::DISPLAY_MESSAGE
            )
            ->willReturn('');

        // Act
        $this->service->warn($message);
    }

    // ==================== User Interaction Tests ====================

    public function test_ask_delegates_to_input_task_with_simple_question(): void
    {
        // Arrange
        $question = 'What is your name?';
        $expectedAnswer = 'John Doe';

        $this->inputDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function ($record) use ($question) {
                    return $record instanceof QuestionRecord && $record->question === $question;
                }),
                InputType::SIMPLE_QUESTION
            )
            ->willReturn($expectedAnswer);

        // Act
        $result = $this->service->ask($question);

        // Assert
        $this->assertSame($expectedAnswer, $result);
    }

    public function test_confirm_delegates_to_input_task_with_confirmation(): void
    {
        // Arrange
        $question = 'Continue?';
        $expectedAnswer = true;

        $this->inputDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function ($record) use ($question) {
                    return $record instanceof QuestionRecord && $record->question === $question;
                }),
                InputType::CONFIRMATION
            )
            ->willReturn($expectedAnswer);

        // Act
        $result = $this->service->confirm($question);

        // Assert
        $this->assertTrue($result);
    }

    public function test_ask_user_choice_delegates_to_input_task_with_user_choice(): void
    {
        // Arrange
        $name = 'test-alias';
        $max = 5;
        $expectedChoice = 3;

        $this->inputDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function ($record) use ($max) {
                    return $record instanceof UserChoiceRecord && $record->max === $max;
                }),
                InputType::USER_CHOICE
            )
            ->willReturn($expectedChoice);

        // Act
        $result = $this->service->askUserChoice($name, $max);

        // Assert
        $this->assertSame($expectedChoice, $result);
    }

    // ==================== Table Display Tests ====================

    public function test_table_renders_table(): void
    {
        // Arrange
        $headers = new StringTypedCollection;
        $headers->add('Name', 'Email');

        $rows = new RowCollection;
        $row = new RowCollection;
        $row->add('John Doe', 'john@example.com');
        $rows->add($row);

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (RenderRecord $record) use ($headers, $rows) {
                    return $record->type === RenderType::TABLE
                        && $record->tableRecord instanceof DisplayTableRecord
                        && $record->tableRecord->headers === $headers
                        && $record->tableRecord->rows === $rows;
                }),
                RenderType::TABLE
            )
            ->willReturn('');

        // Act
        $this->service->table($headers, $rows);
    }

    public function test_table_handles_empty_rows(): void
    {
        // Arrange
        $headers = new StringTypedCollection;
        $headers->add('Name', 'Email');

        $rows = new RowCollection;

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (RenderRecord $record) {
                    return $record->type === RenderType::TABLE
                        && $record->tableRecord instanceof DisplayTableRecord;
                }),
                RenderType::TABLE
            )
            ->willReturn('');

        // Act
        $this->service->table($headers, $rows);
    }

    public function test_table_handles_empty_headers(): void
    {
        // Arrange
        $headers = new StringTypedCollection;
        $rows = new RowCollection;
        $row = new RowCollection;
        $row->add('John Doe');
        $rows->add($row);

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (RenderRecord $record) {
                    return $record->type === RenderType::TABLE
                        && $record->tableRecord instanceof DisplayTableRecord;
                }),
                RenderType::TABLE
            )
            ->willReturn('');

        // Act
        $this->service->table($headers, $rows);
    }

    // ==================== New Line and Separator Tests ====================

    public function test_new_line_renders_empty_line(): void
    {
        // Arrange: Expect renderDispatcher to be called with empty line
        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (RenderRecord $record) {
                    return $record->type === RenderType::DISPLAY_MESSAGE
                        && $record->messageRecord instanceof DisplayMessageRecord
                        && $record->messageRecord->message === ''
                        && $record->messageRecord->type === MessageType::LINE;
                }),
                RenderType::DISPLAY_MESSAGE
            )
            ->willReturn('');

        // Act
        $this->service->newLine();
    }

    public function test_separator_renders_default_separator(): void
    {
        // Arrange: Default separator with 80 dashes
        $expectedSeparator = str_repeat('-', 80);

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (RenderRecord $record) use ($expectedSeparator) {
                    return $record->type === RenderType::DISPLAY_MESSAGE
                        && $record->messageRecord instanceof DisplayMessageRecord
                        && $record->messageRecord->message === $expectedSeparator
                        && $record->messageRecord->type === MessageType::LINE;
                }),
                RenderType::DISPLAY_MESSAGE
            )
            ->willReturn('');

        // Act
        $this->service->separator();
    }

    public function test_separator_renders_separator_with_custom_character(): void
    {
        // Arrange: Custom separator character
        $character = '=';
        $expectedSeparator = str_repeat('=', 80);

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (RenderRecord $record) use ($expectedSeparator) {
                    return $record->type === RenderType::DISPLAY_MESSAGE
                        && $record->messageRecord instanceof DisplayMessageRecord
                        && $record->messageRecord->message === $expectedSeparator
                        && $record->messageRecord->type === MessageType::LINE;
                }),
                RenderType::DISPLAY_MESSAGE
            )
            ->willReturn('');

        // Act
        $this->service->separator($character);
    }

    public function test_separator_renders_separator_with_custom_length(): void
    {
        // Arrange: Custom separator length
        $length = 50;
        $expectedSeparator = str_repeat('-', 50);

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (RenderRecord $record) use ($expectedSeparator) {
                    return $record->type === RenderType::DISPLAY_MESSAGE
                        && $record->messageRecord instanceof DisplayMessageRecord
                        && $record->messageRecord->message === $expectedSeparator
                        && $record->messageRecord->type === MessageType::LINE;
                }),
                RenderType::DISPLAY_MESSAGE
            )
            ->willReturn('');

        // Act
        $this->service->separator(length: $length);
    }

    public function test_separator_renders_separator_with_custom_character_and_length(): void
    {
        // Arrange: Custom separator character and length
        $character = '*';
        $length = 100;
        $expectedSeparator = str_repeat('*', 100);

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (RenderRecord $record) use ($expectedSeparator) {
                    return $record->type === RenderType::DISPLAY_MESSAGE
                        && $record->messageRecord instanceof DisplayMessageRecord
                        && $record->messageRecord->message === $expectedSeparator
                        && $record->messageRecord->type === MessageType::LINE;
                }),
                RenderType::DISPLAY_MESSAGE
            )
            ->willReturn('');

        // Act
        $this->service->separator($character, $length);
    }

    public function test_separator_with_different_characters(): void
    {
        $character = '#';
        $expectedSeparator = str_repeat($character, 80);

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (RenderRecord $record) use ($expectedSeparator) {
                    return $record->messageRecord->message === $expectedSeparator;
                }),
                RenderType::DISPLAY_MESSAGE
            )
            ->willReturn('');

        $this->service->separator($character);
    }

    public function test_separator_with_zero_length_renders_empty_string(): void
    {
        // Arrange: Length 0 should produce empty string
        $length = 0;
        $expectedSeparator = '';

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (RenderRecord $record) use ($expectedSeparator) {
                    return $record->type === RenderType::DISPLAY_MESSAGE
                        && $record->messageRecord instanceof DisplayMessageRecord
                        && $record->messageRecord->message === $expectedSeparator
                        && $record->messageRecord->type === MessageType::LINE;
                }),
                RenderType::DISPLAY_MESSAGE
            )
            ->willReturn('');

        // Act
        $this->service->separator(length: $length);
    }

    public function test_separator_with_negative_length_throws_exception(): void
    {
        $this->expectException(\ValueError::class);
        $this->service->separator(length: -5);
    }

    public function test_new_line_and_separator_work_together(): void
    {
        // Arrange: Set expectations in sequence
        $this->renderDispatcher->expects($this->atLeastOnce())
            ->method('execute')
            ->willReturn('');

        // Act: Chain multiple display methods (no assertions needed, just verify no errors)
        $this->service->newLine();
        $this->service->separator();
        $this->service->newLine();
        $this->service->separator('=', 40);
        $this->service->newLine();

        // Assert: If we reach here without exceptions, the test passes
        $this->addToAssertionCount(1);
    }
}
