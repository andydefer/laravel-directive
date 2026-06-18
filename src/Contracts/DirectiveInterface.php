<?php

// src/Contracts/DirectiveInterface.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

interface DirectiveInterface
{
    public function getSignature(): string;

    public function getDescription(): string;

    public function getAliases(): StringTypedCollection;

    public function getBlueprint(): DirectiveBlueprintRecord;

    public function argument(string $key): mixed;

    public function hasArgument(string $key): bool;

    public function option(string $key): mixed;

    public function hasOption(string $key): bool;

    public function getVariadicArguments(): StringTypedCollection;

    public function hasVariadicArguments(): bool;

    public function shouldBootLaravel(): bool;

    public function hasLaravel(): bool;

    public function getLaravel(): ?object;

    public function line(string $message): void;

    public function info(string $message): void;

    public function error(string $message): void;

    public function warn(string $message): void;

    public function newLine(): void;

    public function separator(string $character = '-', int $length = 80): void;

    public function ask(string $question): string;

    public function confirm(string $question): bool;

    public function table(StringTypedCollection $headers, RowCollection $rows): void;

    public function getCalls(): array;

    public function run(): ExitCode;
}
