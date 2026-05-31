<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Enums\InputType;
use AndyDefer\Directive\Enums\MessageType;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\DisplayMessageRecord;
use AndyDefer\Directive\Records\DisplayTableRecord;
use AndyDefer\Directive\Records\QuestionRecord;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Records\UserChoiceRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Tasks\InputTask;
use AndyDefer\Directive\Tasks\RenderTask;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class DirectiveInteractionServiceTest extends UnitTestCase
{
    private RenderTask&MockObject $renderTask;

    private InputTask&MockObject $inputTask;

    private DirectiveInteractionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderTask = $this->createMock(RenderTask::class);
        $this->inputTask = $this->createMock(InputTask::class);

        $this->service = new DirectiveInteractionService(
            $this->renderTask,
            $this->inputTask,
        );
    }

    // ==================== Display Message Tests ====================

    public function test_line_renders_message_with_line_type(): void
    {
        $message = 'Test line message';

        $this->renderTask->expects($this->once())
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

        $this->service->line($message);
    }

    public function test_info_renders_message_with_info_type(): void
    {
        $message = 'Test info message';

        $this->renderTask->expects($this->once())
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

        $this->service->info($message);
    }

    public function test_error_renders_message_with_error_type(): void
    {
        $message = 'Test error message';

        $this->renderTask->expects($this->once())
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

        $this->service->error($message);
    }

    public function test_warn_renders_message_with_warning_type(): void
    {
        $message = 'Test warning message';

        $this->renderTask->expects($this->once())
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

        $this->service->warn($message);
    }

    // ==================== User Interaction Tests ====================

    public function test_ask_delegates_to_input_task_with_simple_question(): void
    {
        $question = 'What is your name?';
        $expectedAnswer = 'John Doe';

        $this->inputTask->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function ($record) use ($question) {
                    return $record instanceof QuestionRecord && $record->question === $question;
                }),
                InputType::SIMPLE_QUESTION
            )
            ->willReturn($expectedAnswer);

        $result = $this->service->ask($question);

        $this->assertSame($expectedAnswer, $result);
    }

    public function test_confirm_delegates_to_input_task_with_confirmation(): void
    {
        $question = 'Continue?';
        $expectedAnswer = true;

        $this->inputTask->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function ($record) use ($question) {
                    return $record instanceof QuestionRecord && $record->question === $question;
                }),
                InputType::CONFIRMATION
            )
            ->willReturn($expectedAnswer);

        $result = $this->service->confirm($question);

        $this->assertTrue($result);
    }

    public function test_ask_user_choice_delegates_to_input_task_with_user_choice(): void
    {
        $name = 'test-alias';
        $max = 5;
        $expectedChoice = 3;

        $this->inputTask->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function ($record) use ($max) {
                    return $record instanceof UserChoiceRecord && $record->max === $max;
                }),
                InputType::USER_CHOICE
            )
            ->willReturn($expectedChoice);

        $result = $this->service->askUserChoice($name, $max);

        $this->assertSame($expectedChoice, $result);
    }

    // ==================== Table Display Tests ====================

    public function test_table_renders_table(): void
    {
        $headers = new StringTypedCollection;
        $headers->add('Name', 'Email');

        $rows = new RowCollection;
        $row = new RowCollection;
        $row->add('John Doe', 'john@example.com');
        $rows->add($row);

        $this->renderTask->expects($this->once())
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

        $this->service->table($headers, $rows);
    }

    public function test_table_handles_empty_rows(): void
    {
        $headers = new StringTypedCollection;
        $headers->add('Name', 'Email');

        $rows = new RowCollection;

        $this->renderTask->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (RenderRecord $record) {
                    return $record->type === RenderType::TABLE
                        && $record->tableRecord instanceof DisplayTableRecord;
                }),
                RenderType::TABLE
            )
            ->willReturn('');

        $this->service->table($headers, $rows);
    }

    public function test_table_handles_empty_headers(): void
    {
        $headers = new StringTypedCollection;
        $rows = new RowCollection;
        $row = new RowCollection;
        $row->add('John Doe');
        $rows->add($row);

        $this->renderTask->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (RenderRecord $record) {
                    return $record->type === RenderType::TABLE
                        && $record->tableRecord instanceof DisplayTableRecord;
                }),
                RenderType::TABLE
            )
            ->willReturn('');

        $this->service->table($headers, $rows);
    }
}
