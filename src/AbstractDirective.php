<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Contracts\LaravelBootstrapperInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Abstract base class for all CLI directives.
 */
abstract class AbstractDirective implements DirectiveInterface
{
    protected ParameterCollection $arguments;
    protected ParameterCollection $options;
    protected StringTypedCollection $variadicArguments;
    protected ?LaravelBootstrapperInterface $laravelBootstrapper = null;

    public function __construct(
        protected readonly DirectiveInteractionService $interaction,
    ) {
        $this->arguments = new ParameterCollection;
        $this->options = new ParameterCollection;
        $this->variadicArguments = new StringTypedCollection;
    }

    final public function getBlueprint(): DirectiveBlueprintRecord
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

    public function shouldBootLaravel(): bool
    {
        return false;
    }

    final public function hasLaravel(): bool
    {
        return $this->laravelBootstrapper !== null && $this->laravelBootstrapper->isBootstrapped();
    }

    final public function getLaravel(): ?object
    {
        return $this->laravelBootstrapper?->getApplication();
    }

    final public function setLaravelBootstrapper(?LaravelBootstrapperInterface $bootstrapper): self
    {
        $this->laravelBootstrapper = $bootstrapper;
        return $this;
    }

    final public function setInteraction(DirectiveInteractionService $interaction): self
    {
        $this->interaction = $interaction;
        return $this;
    }

    // ==================== Argument Management ====================

    final public function setArguments(ParameterCollection $arguments): self
    {
        $this->arguments = $arguments;
        return $this;
    }

    final public function argument(string $key): ?string
    {
        $value = $this->arguments->get($key);

        if ($value === null || $value === true || $value === false || $value === '') {
            return null;
        }

        return $value;
    }

    final public function hasArgument(string $key): bool
    {
        $value = $this->arguments->get($key);
        return $value !== null && $value !== '' && $value !== true && $value !== false;
    }

    // ==================== Option Management ====================

    final public function setOptions(ParameterCollection $options): self
    {
        $this->options = $options;
        return $this;
    }

    final public function option(string $key): bool|string|null
    {
        $value = $this->options->get($key);

        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }

    final public function hasOption(string $key): bool
    {
        $value = $this->options->get($key);

        if ($value === null || $value === '') {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        return $value !== '';
    }

    final public function setVariadicArguments(StringTypedCollection $variadicArguments): self
    {
        $this->variadicArguments = $variadicArguments;
        return $this;
    }

    final public function getVariadicArguments(): StringTypedCollection
    {
        return $this->variadicArguments;
    }

    final public function hasVariadicArguments(): bool
    {
        return $this->variadicArguments->isNotEmpty();
    }

    // ==================== Display Methods ====================

    final public function line(string $message): void
    {
        $this->interaction->line($message);
    }

    final public function info(string $message): void
    {
        $this->interaction->info($message);
    }

    final public function error(string $message): void
    {
        $this->interaction->error($message);
    }

    final public function warn(string $message): void
    {
        $this->interaction->warn($message);
    }

    final public function newLine(): void
    {
        $this->interaction->newLine();
    }

    final public function separator(string $character = '-', int $length = 80): void
    {
        $this->interaction->separator($character, $length);
    }

    // ==================== User Interaction Methods ====================

    final public function ask(string $question): string
    {
        return $this->interaction->ask($question);
    }

    final public function confirm(string $question): bool
    {
        return $this->interaction->confirm($question);
    }

    // ==================== Table Display Methods ====================

    final public function table(StringTypedCollection $headers, RowCollection $rows): void
    {
        $this->interaction->table($headers, $rows);
    }

    abstract public function execute(): ExitCode;
}
