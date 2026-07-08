<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Invalid;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;
use Illuminate\Foundation\Application;

final class InvalidDirective implements DirectiveInterface
{
    public function getDescription(): string
    {
        return 'Invalid directive';
    }

    public function getLaravel(): Application
    {
        throw new \RuntimeException('Invalid directive should not have Laravel');
    }

    public function getConsole(): Console
    {
        throw new \RuntimeException('Invalid directive should not have Console');
    }

    public function getParsed(): ParsedSignatureRecord
    {
        throw new \RuntimeException('Invalid directive should not have Parsed');
    }

    public function getStructure(): SignatureStructureVO
    {
        throw new \RuntimeException('Invalid directive should not have Structure');
    }

    public function argument(string $key): mixed
    {
        return null;
    }

    public function hasArgument(string $key): bool
    {
        return false;
    }

    public function flag(string $key): bool
    {
        return false;
    }

    public function hasFlag(string $key): bool
    {
        return false;
    }

    public function isFlagActive(string $key): bool
    {
        return false;
    }

    public function getVariadicArguments(): StringTypedCollection
    {
        return new StringTypedCollection;
    }

    public function hasVariadicArguments(): bool
    {
        return false;
    }

    public function getRequiredArguments(): array
    {
        return [];
    }

    public function getDefaultArguments(): array
    {
        return [];
    }

    public function getFlags(): array
    {
        return [];
    }

    public function getActiveFlags(): array
    {
        return [];
    }

    public function hasRequireds(): bool
    {
        return false;
    }

    public function hasDefaults(): bool
    {
        return false;
    }

    public function hasFlags(): bool
    {
        return false;
    }

    public function line(string $message): void
    {
        // Ne fait rien pour le test
    }

    public function info(string $message): void
    {
        // Ne fait rien pour le test
    }

    public function error(string $message): void
    {
        // Ne fait rien pour le test
    }

    public function newLine(): void
    {
        // Ne fait rien pour le test
    }

    public function separator(string $character = '-', int $length = 80): void
    {
        // Ne fait rien pour le test
    }

    public function ask(string $question): string
    {
        return '';
    }

    public function confirm(string $question): bool
    {
        return false;
    }

    public function table(ListCollection|array $headers, ListCollection|array $rows): void
    {
        // Ne fait rien pour le test
    }

    public function getCalls(): array
    {
        return [];
    }

    public function run(): ExitCode
    {
        return ExitCode::FAILURE;
    }

    public function getAliases(): StringTypedCollection
    {
        return new StringTypedCollection;
    }

    public function getSignature(): string
    {
        return 'invalid';
    }
}
