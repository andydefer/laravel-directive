<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Enums\MessageType;
use AndyDefer\Directive\Records\AskQuestionRecord;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Records\DisplayMessageRecord;
use AndyDefer\Directive\Records\DisplayTableRecord;
use AndyDefer\Directive\Tasks\AskQuestionTask;
use AndyDefer\Directive\Tasks\ConfirmQuestionTask;
use AndyDefer\Directive\Tasks\DisplayMessageTask;
use AndyDefer\Directive\Tasks\DisplayTableTask;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

abstract class AbstractDirective implements DirectiveInterface
{
    protected ParameterCollection $arguments;

    protected ParameterCollection $options;

    public function __construct(
        protected readonly DisplayMessageTask $displayMessage,
        protected readonly AskQuestionTask $askQuestion,
        protected readonly ConfirmQuestionTask $confirmQuestion,
        protected readonly DisplayTableTask $displayTable,
    ) {
        $this->arguments = new ParameterCollection;
        $this->options = new ParameterCollection;
    }

    /**
     * Get the blueprint of the directive (metadata without execution).
     */
    public function getBlueprint(): DirectiveBlueprintRecord
    {
        return new DirectiveBlueprintRecord(
            class: static::class,
            signature: $this->getSignature(),
            description: $this->getDescription(),
        );
    }

    public function getAliases(): StringTypedCollection
    {
        return new StringTypedCollection;
    }

    // ==================== Argument Management ====================

    public function setArguments(ParameterCollection $arguments): self
    {
        $this->arguments = $arguments;

        return $this;
    }

    public function argument(string $key): ?string
    {
        $value = $this->arguments->get($key);

        if ($value === null || $value === true || $value === false) {
            return null;
        }

        return $value;
    }

    // ==================== Option Management ====================

    public function setOptions(ParameterCollection $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function option(string $key): bool|string|null
    {
        return $this->options->get($key);
    }

    public function hasOption(string $key): bool
    {
        return $this->options->has($key);
    }

    // ==================== Display Methods ====================

    public function line(string $message): void
    {
        $this->displayMessage->execute(new DisplayMessageRecord($message, MessageType::LINE));
    }

    public function info(string $message): void
    {
        $this->displayMessage->execute(new DisplayMessageRecord($message, MessageType::INFO));
    }

    public function error(string $message): void
    {
        $this->displayMessage->execute(new DisplayMessageRecord($message, MessageType::ERROR));
    }

    public function warn(string $message): void
    {
        $this->displayMessage->execute(new DisplayMessageRecord($message, MessageType::WARNING));
    }

    // ==================== User Interaction ====================

    public function ask(string $question): string
    {
        return $this->askQuestion->execute(new AskQuestionRecord($question));
    }

    public function confirm(string $question): bool
    {
        return $this->confirmQuestion->execute(new AskQuestionRecord($question));
    }

    // ==================== Table Display ====================

    public function table(StringTypedCollection $headers, RowCollection $rows): void
    {
        $this->displayTable->execute(new DisplayTableRecord($headers, $rows));
    }
}
