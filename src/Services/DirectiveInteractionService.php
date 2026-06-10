<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

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
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Service for directive user interaction.
 *
 * Provides methods for displaying messages (line, info, error, warn),
 * user input (ask, confirm, askUserChoice), and table rendering.
 * Delegates rendering to RenderDispatcher and user input to InputDispatcher.
 *
 * @author Andy Defer
 */
class DirectiveInteractionService
{
    public function __construct(
        private readonly RenderDispatcher $renderDispatcher,
        private readonly InputDispatcher $inputDispatcher,
    ) {}

    // ==================== Display Methods ====================

    /**
     * Outputs a plain text line.
     *
     * @param  string  $message  The message to display
     */
    public function line(string $message): void
    {
        $this->renderMessage($message, MessageType::LINE);
    }

    /**
     * Outputs an informational message (typically green).
     *
     * @param  string  $message  The message to display
     */
    public function info(string $message): void
    {
        $this->renderMessage($message, MessageType::INFO);
    }

    /**
     * Outputs an error message (typically red).
     *
     * @param  string  $message  The message to display
     */
    public function error(string $message): void
    {
        $this->renderMessage($message, MessageType::ERROR);
    }

    /**
     * Outputs a warning message (typically yellow).
     *
     * @param  string  $message  The message to display
     */
    public function warn(string $message): void
    {
        $this->renderMessage($message, MessageType::WARNING);
    }

    /**
     * Renders a message with the specified type.
     *
     * @param  string  $message  The message content
     * @param  MessageType  $type  The message type (LINE, INFO, ERROR, WARNING)
     */
    private function renderMessage(string $message, MessageType $type): void
    {
        $messageRecord = new DisplayMessageRecord($message, $type);
        $renderRecord = new RenderRecord(type: RenderType::DISPLAY_MESSAGE, messageRecord: $messageRecord);
        echo $this->renderDispatcher->execute($renderRecord, RenderType::DISPLAY_MESSAGE);
    }

    // ==================== User Interaction Methods ====================

    /**
     * Asks a question and returns the user's answer.
     *
     * @param  string  $question  The question to ask
     * @return string The user's answer (trimmed)
     */
    public function ask(string $question): string
    {
        $record = new QuestionRecord($question);

        return $this->inputDispatcher->execute($record, InputType::SIMPLE_QUESTION);
    }

    /**
     * Asks for confirmation and returns the user's choice.
     *
     * @param  string  $question  The confirmation question
     * @return bool True if the user confirms (y/yes), false otherwise (n/no)
     */
    public function confirm(string $question): bool
    {
        $record = new QuestionRecord($question);

        return $this->inputDispatcher->execute($record, InputType::CONFIRMATION);
    }

    /**
     * Asks the user to choose from a numbered list.
     *
     * @param  string  $name  The name of the choice (for display)
     * @param  int  $max  The maximum choice number (1 to max)
     * @return int The chosen number (1 to max), or 0 if invalid
     */
    public function askUserChoice(string $name, int $max): int
    {
        $record = new UserChoiceRecord(choice: 0, max: $max);

        return $this->inputDispatcher->execute($record, InputType::USER_CHOICE);
    }

    // ==================== Table Display Methods ====================

    /**
     * Displays a formatted table with headers and rows.
     *
     * @param  StringTypedCollection  $headers  The table headers
     * @param  RowCollection  $rows  The table rows
     */
    public function table(StringTypedCollection $headers, RowCollection $rows): void
    {
        $tableRecord = new DisplayTableRecord($headers, $rows);
        $renderRecord = new RenderRecord(type: RenderType::TABLE, tableRecord: $tableRecord);
        echo $this->renderDispatcher->execute($renderRecord, RenderType::TABLE);
    }

    /**
     * Outputs a blank line (empty line).
     */
    public function newLine(): void
    {
        $this->line('');
    }

    /**
     * Outputs a separator line.
     *
     * @param  string  $character  The character to use for the separator (default: '-')
     * @param  int  $length  The length of the separator line (default: 80)
     */
    public function separator(string $character = '-', int $length = 80): void
    {
        $this->line(str_repeat($character, $length));
    }
}
