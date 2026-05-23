<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Enums\InputType;
use AndyDefer\Directive\Enums\MessageType;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\DisplayMessageRecord;
use AndyDefer\Directive\Records\DisplayTableRecord;
use AndyDefer\Directive\Records\QuestionRecord;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Records\UserChoiceRecord;
use AndyDefer\Directive\Tasks\InputTask;
use AndyDefer\Directive\Tasks\RenderTask;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

/**
 * Service for directive user interaction (messages, questions, tables).
 */
class DirectiveInteractionService
{
    public function __construct(
        private readonly RenderTask $renderTask,
        private readonly InputTask $inputTask,
    ) {}

    // ==================== Display Methods ====================

    public function line(string $message): void
    {
        $this->renderMessage($message, MessageType::LINE);
    }

    public function info(string $message): void
    {
        $this->renderMessage($message, MessageType::INFO);
    }

    public function error(string $message): void
    {
        $this->renderMessage($message, MessageType::ERROR);
    }

    public function warn(string $message): void
    {
        $this->renderMessage($message, MessageType::WARNING);
    }

    private function renderMessage(string $message, MessageType $type): void
    {
        $messageRecord = new DisplayMessageRecord($message, $type);
        $renderRecord = new RenderRecord(type: RenderType::DISPLAY_MESSAGE, messageRecord: $messageRecord);
        echo $this->renderTask->execute($renderRecord, RenderType::DISPLAY_MESSAGE);
    }

    // ==================== User Interaction ====================

    public function ask(string $question): string
    {
        $record = new QuestionRecord($question);
        return $this->inputTask->execute($record, InputType::SIMPLE_QUESTION);
    }

    public function confirm(string $question): bool
    {
        $record = new QuestionRecord($question);
        return $this->inputTask->execute($record, InputType::CONFIRMATION);
    }

    public function askUserChoice(string $name, int $max): int
    {
        $record = new UserChoiceRecord(choice: 0, max: $max);
        return $this->inputTask->execute($record, InputType::USER_CHOICE);
    }

    // ==================== Table Display ====================

    public function table(StringTypedCollection $headers, RowCollection $rows): void
    {
        $tableRecord = new DisplayTableRecord($headers, $rows);
        $renderRecord = new RenderRecord(type: RenderType::TABLE, tableRecord: $tableRecord);
        echo $this->renderTask->execute($renderRecord, RenderType::TABLE);
    }
}
