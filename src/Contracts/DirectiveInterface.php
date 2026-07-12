<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Container\Container;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveCallRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

/**
 * Interface for all directives.
 *
 * A directive is a self-contained CLI command that defines a signature,
 * aliases, and execution logic.
 */
interface DirectiveInterface
{
    // ==================== BASIC METHODS ====================

    /**
     * Get the directive signature.
     *
     * The signature defines the command name and its arguments.
     * Example: 'backup {source} {destination} {format=zip} {--force}'
     *
     * @return string The directive signature
     */
    public function getSignature(): string;

    /**
     * Get the directive description.
     *
     * @return string The directive description
     */
    public function getDescription(): string;

    /**
     * Get the directive aliases.
     *
     * @return StringTypedCollection List of aliases
     */
    public function getAliases(): StringTypedCollection;

    // ==================== ACCESSORS ====================

    /**
     * Get the container instance.
     *
     * @return Container|null The container instance
     */
    public function getContainer(): ?Container;

    /**
     * Get the kernel instance.
     *
     * @return DirectiveKernel|null The kernel instance
     */
    public function getKernel(): ?DirectiveKernel;

    /**
     * Get the console instance.
     *
     * @return Console The console instance
     */
    public function getConsole(): Console;

    /**
     * Get the parsed signature record.
     *
     * @return ParsedSignatureRecord The parsed record
     */
    public function getParsed(): ParsedSignatureRecord;

    /**
     * Get the signature structure.
     *
     * @return SignatureStructureVO The signature structure
     */
    public function getStructure(): SignatureStructureVO;

    // ==================== ARGUMENT METHODS ====================

    /**
     * Get an argument value by key, searching in order of priority.
     *
     * Search order:
     * 1. Required arguments
     * 2. Default arguments
     * 3. Enum arguments
     * 4. Variadic arguments (returns array)
     * 5. Flags (returns bool)
     *
     * @param  string  $key  The argument key
     * @return mixed The argument value, or null if not found
     */
    public function getArgument(string $key): mixed;

    /**
     * Check if an argument exists.
     *
     * @param  string  $key  The argument key
     * @return bool True if the argument exists, false otherwise
     */
    public function hasArgument(string $key): bool;

    /**
     * Get the value of a required argument.
     *
     * @param  string  $key  The argument key
     * @return string|null The argument value, or null if not found
     */
    public function getRequired(string $key): ?string;

    /**
     * Get all required arguments.
     *
     * @return array<string, string> Associative array of argument names to values
     */
    public function getRequireds(): array;

    /**
     * Get the value of a default argument.
     *
     * @param  string  $key  The argument key
     * @return string|null The argument value, or null if not found
     */
    public function getDefault(string $key): ?string;

    /**
     * Get all default arguments.
     *
     * @return array<string, string|null> Associative array of argument names to values
     */
    public function getDefaults(): array;

    /**
     * Get the value of an enum argument.
     *
     * @param  string  $key  The enum key
     * @return mixed The enum value, or null if not found
     */
    public function getEnum(string $key): mixed;

    /**
     * Get all enum arguments.
     *
     * @return array<string, mixed> Associative array of enum names to values
     */
    public function getEnums(): array;

    /**
     * Get the allowed values for an enum argument.
     *
     * @param  string  $key  The enum key
     * @return array<string>|null The allowed values, or null if not found
     */
    public function getEnumAllowedValues(string $key): ?array;

    /**
     * Check if an enum is required.
     *
     * @param  string  $key  The enum key
     * @return bool True if required, false otherwise
     */
    public function isEnumRequired(string $key): bool;

    /**
     * Check if an enum is optional.
     *
     * @param  string  $key  The enum key
     * @return bool True if optional, false otherwise
     */
    public function isEnumOptional(string $key): bool;

    /**
     * Check if a value is allowed for an enum.
     *
     * @param  string  $key  The enum key
     * @param  string  $value  The value to check
     * @return bool True if allowed, false otherwise
     */
    public function isEnumValueAllowed(string $key, string $value): bool;

    /**
     * Get the value of a variadic argument.
     *
     * @param  string  $key  The variadic key
     * @return array<string> The variadic values, or empty array if not found
     */
    public function getVariadic(string $key): array;

    /**
     * Get all variadic arguments.
     *
     * @return array<string, array<string>> Associative array of variadic names to values
     */
    public function getVariadics(): array;

    /**
     * Check if a variadic argument exists.
     *
     * @param  string  $key  The variadic key
     * @return bool True if exists, false otherwise
     */
    public function hasVariadic(string $key): bool;

    /**
     * Get the value of a flag.
     *
     * @param  string  $key  The flag key (without '--' prefix)
     * @return bool True if active, false otherwise
     */
    public function getFlag(string $key): bool;

    /**
     * Get all flags.
     *
     * @return array<string, bool> Associative array of flag names to boolean values
     */
    public function getFlags(): array;

    /**
     * Check if a flag exists.
     *
     * @param  string  $key  The flag key (without '--' prefix)
     * @return bool True if exists, false otherwise
     */
    public function hasFlag(string $key): bool;

    /**
     * Check if a flag is active.
     *
     * @param  string  $key  The flag key (without '--' prefix)
     * @return bool True if active, false otherwise
     */
    public function isFlagActive(string $key): bool;

    /**
     * Get all active flags.
     *
     * @return array<string> List of active flag names
     */
    public function getActiveFlags(): array;

    /**
     * Get all variadic arguments as a flat collection.
     *
     * @return StringTypedCollection All variadic values
     */
    public function getVariadicArguments(): StringTypedCollection;

    /**
     * Check if there are any variadic arguments.
     *
     * @return bool True if there are variadic arguments, false otherwise
     */
    public function hasVariadicArguments(): bool;

    /**
     * Get all required arguments.
     *
     * @deprecated Use getRequireds() instead
     *
     * @return array<string, string> Associative array of argument names to values
     */
    public function getRequiredArguments(): array;

    /**
     * Get all default arguments.
     *
     * @deprecated Use getDefaults() instead
     *
     * @return array<string, string|null> Associative array of argument names to values
     */
    public function getDefaultArguments(): array;

    /**
     * Check if there are required arguments.
     *
     * @return bool True if there are required arguments, false otherwise
     */
    public function hasRequireds(): bool;

    /**
     * Check if there are default arguments.
     *
     * @return bool True if there are default arguments, false otherwise
     */
    public function hasDefaults(): bool;

    /**
     * Check if there are any enums.
     *
     * @return bool True if there are enums, false otherwise
     */
    public function hasEnums(): bool;

    /**
     * Check if there are any flags.
     *
     * @return bool True if there are flags, false otherwise
     */
    public function hasFlags(): bool;

    // ==================== OUTPUT METHODS ====================

    /**
     * Output a line of text.
     *
     * @param  string  $message  The message to output
     */
    public function line(string $message): void;

    /**
     * Output an informational message.
     *
     * @param  string  $message  The message to output
     */
    public function info(string $message): void;

    /**
     * Output an error message.
     *
     * @param  string  $message  The message to output
     */
    public function error(string $message): void;

    /**
     * Output a new line.
     */
    public function newLine(): void;

    /**
     * Output a separator line.
     *
     * @param  string  $character  The character to repeat
     * @param  int  $length  The length of the separator
     */
    public function separator(string $character = '-', int $length = 80): void;

    /**
     * Ask a question and get user input.
     *
     * @param  string  $question  The question to ask
     * @return string The user's answer
     */
    public function ask(string $question): string;

    /**
     * Ask a yes/no question.
     *
     * @param  string  $question  The question to ask
     * @return bool True if confirmed, false otherwise
     */
    public function confirm(string $question): bool;

    /**
     * Output a table.
     *
     * @param  ListCollection|array  $headers  The table headers
     * @param  ListCollection|array  $rows  The table rows
     */
    public function table(ListCollection|array $headers, ListCollection|array $rows): void;

    // ==================== CALL METHODS ====================

    /**
     * Get all queued internal calls.
     *
     * @return array<DirectiveCallRecord> List of queued calls
     */
    public function getCalls(): array;

    // ==================== EXECUTION METHODS ====================

    /**
     * Run the directive.
     *
     * @return ExitCode The exit code
     */
    public function run(): ExitCode;
}
