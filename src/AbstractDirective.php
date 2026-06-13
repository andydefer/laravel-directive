<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

abstract class AbstractDirective implements DirectiveInterface
{
    public function __construct(
        protected DirectiveContext $context,
        protected DirectiveInteractionService $interaction
    ) {}

    final public function getBlueprint(): DirectiveBlueprintRecord
    {
        return $this->context->getBlueprint();
    }

    public function getAliases(): StringTypedCollection
    {
        return $this->context->getAliases();
    }

    public function shouldBootLaravel(): bool
    {
        return false;
    }

    final public function hasLaravel(): bool
    {
        return $this->context->hasLaravel();
    }

    final public function getLaravel(): ?object
    {
        return $this->context->getLaravel();
    }

    final public function argument(string $key): mixed
    {
        return $this->context->getArgument($key);
    }

    final public function hasArgument(string $key): bool
    {
        return $this->context->hasArgument($key);
    }

    final public function option(string $key): mixed
    {
        return $this->context->getOption($key);
    }

    final public function hasOption(string $key): bool
    {
        return $this->context->hasOption($key);
    }

    final public function getVariadicArguments(): StringTypedCollection
    {
        return $this->context->getVariadicArguments();
    }

    final public function hasVariadicArguments(): bool
    {
        return $this->context->hasVariadicArguments();
    }

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

    final public function ask(string $question): string
    {
        return $this->interaction->ask($question);
    }

    final public function confirm(string $question): bool
    {
        return $this->interaction->confirm($question);
    }

    final public function table(StringTypedCollection $headers, RowCollection $rows): void
    {
        $this->interaction->table($headers, $rows);
    }

    abstract public function execute(): ExitCode;
}
