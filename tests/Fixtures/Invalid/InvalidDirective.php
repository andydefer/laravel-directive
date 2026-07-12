<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Invalid;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Container\Container;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

final class InvalidDirective implements DirectiveInterface
{
    public function getDescription(): string
    {
        return 'Invalid directive';
    }

    public function getContainer(): Container
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

    // ==================== ARGUMENT METHODS ====================

    public function getArgument(string $key): mixed
    {
        return null;
    }

    public function hasArgument(string $key): bool
    {
        return false;
    }

    public function getRequired(string $key): ?string
    {
        return null;
    }

    public function getRequireds(): array
    {
        return [];
    }

    public function getDefault(string $key): ?string
    {
        return null;
    }

    public function getDefaults(): array
    {
        return [];
    }

    public function getEnum(string $key): mixed
    {
        return null;
    }

    public function getEnums(): array
    {
        return [];
    }

    public function getEnumAllowedValues(string $key): ?array
    {
        return null;
    }

    public function isEnumRequired(string $key): bool
    {
        return false;
    }

    public function isEnumOptional(string $key): bool
    {
        return false;
    }

    public function isEnumValueAllowed(string $key, string $value): bool
    {
        return false;
    }

    public function getVariadic(string $key): array
    {
        return [];
    }

    public function getVariadics(): array
    {
        return [];
    }

    public function hasVariadic(string $key): bool
    {
        return false;
    }

    public function getFlag(string $key): bool
    {
        return false;
    }

    public function getFlags(): array
    {
        return [];
    }

    public function hasFlag(string $key): bool
    {
        return false;
    }

    public function isFlagActive(string $key): bool
    {
        return false;
    }

    public function getActiveFlags(): array
    {
        return [];
    }

    public function getVariadicArguments(): StringTypedCollection
    {
        return new StringTypedCollection;
    }

    public function hasVariadicArguments(): bool
    {
        return false;
    }

    /**
     * @deprecated Use getRequireds() instead
     */
    public function getRequiredArguments(): array
    {
        return [];
    }

    /**
     * @deprecated Use getDefaults() instead
     */
    public function getDefaultArguments(): array
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

    public function hasEnums(): bool
    {
        return false;
    }

    public function hasFlags(): bool
    {
        return false;
    }

    // ==================== OUTPUT METHODS ====================

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

    // ==================== CALL METHODS ====================

    public function getCalls(): array
    {
        return [];
    }

    // ==================== EXECUTION METHODS ====================

    public function run(): ExitCode
    {
        return ExitCode::FAILURE;
    }

    // ==================== BASIC METHODS ====================

    public function getAliases(): StringTypedCollection
    {
        return new StringTypedCollection;
    }

    public function getSignature(): string
    {
        return 'invalid';
    }

    // ==================== ACCESSORS ====================

    public function getKernel(): ?DirectiveKernel
    {
        throw new \RuntimeException('Invalid directive should not have Kernel');
    }
}
