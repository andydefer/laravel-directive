<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;
use Illuminate\Foundation\Application;

interface DirectiveInterface
{
    public function getLaravel(): Application;

    public function getConsole(): Console;

    public function getParsed(): ParsedSignatureRecord;

    public function getStructure(): SignatureStructureVO;

    public function argument(string $key): mixed;

    public function hasArgument(string $key): bool;

    public function flag(string $key): bool;

    public function hasFlag(string $key): bool;

    public function isFlagActive(string $key): bool;

    public function getVariadicArguments(): StringTypedCollection;

    public function hasVariadicArguments(): bool;

    public function getRequiredArguments(): array;

    public function getDefaultArguments(): array;

    public function getFlags(): array;

    public function getActiveFlags(): array;

    public function hasRequireds(): bool;

    public function hasDefaults(): bool;

    public function hasFlags(): bool;

    public function line(string $message): void;

    public function info(string $message): void;

    public function error(string $message): void;

    public function newLine(): void;

    public function separator(string $character = '-', int $length = 80): void;

    public function ask(string $question): string;

    public function confirm(string $question): bool;

    public function table(ListCollection|array $headers, ListCollection|array $rows): void;

    public function getCalls(): array;

    public function run(): ExitCode;

    public function getAliases(): StringTypedCollection;

    public function getSignature(): string;
}
