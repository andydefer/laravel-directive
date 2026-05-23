<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

/**
 * Abstract base class for all directive implementations.
 *
 * Provides common functionality for argument/option handling, user interaction
 * (asking questions, displaying messages), and table rendering. All concrete
 * directives must extend this class.
 */
abstract class AbstractDirective implements DirectiveInterface
{
    protected ParameterCollection $arguments;
    protected ParameterCollection $options;

    public function __construct(
        protected readonly DirectiveInteractionService $interaction,
    ) {
        $this->arguments = new ParameterCollection();
        $this->options = new ParameterCollection();
    }

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
        return new StringTypedCollection();
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
        $this->interaction->line($message);
    }

    public function info(string $message): void
    {
        $this->interaction->info($message);
    }

    public function error(string $message): void
    {
        $this->interaction->error($message);
    }

    public function warn(string $message): void
    {
        $this->interaction->warn($message);
    }

    // ==================== User Interaction ====================

    public function ask(string $question): string
    {
        return $this->interaction->ask($question);
    }

    public function confirm(string $question): bool
    {
        return $this->interaction->confirm($question);
    }

    // ==================== Table Display ====================

    public function table(StringTypedCollection $headers, RowCollection $rows): void
    {
        $this->interaction->table($headers, $rows);
    }
}
