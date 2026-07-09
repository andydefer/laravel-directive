<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

/**
 * Contract for all directive implementations.
 *
 * A directive is a self-contained CLI command that can be executed from the
 * command line. It defines a signature, aliases, and execution logic.
 */
interface DirectiveInterface
{
    /**
     * Gets the container instance.
     *
     * @return ContainerInterface|null The container instance
     */
    public function getContainer(): ?ContainerInterface;

    /**
     * Gets the console output handler.
     *
     * @return Console The console instance for output operations
     */
    public function getConsole(): Console;

    /**
     * Gets the parsed signature record.
     *
     * Contains the parsed representation of the directive's signature
     * after parsing the user's query.
     *
     * @return ParsedSignatureRecord The parsed signature data
     */
    public function getParsed(): ParsedSignatureRecord;

    /**
     * Gets the signature structure value object.
     *
     * Provides a structured representation of the directive's signature
     * definition.
     *
     * @return SignatureStructureVO The signature structure
     */
    public function getStructure(): SignatureStructureVO;

    /**
     * Gets the value of a required or default argument.
     *
     * @param  string  $key  The argument name
     * @return mixed The argument value, or null if not found
     */
    public function argument(string $key): mixed;

    /**
     * Checks if an argument exists (required or default).
     *
     * @param  string  $key  The argument name
     * @return bool True if the argument exists, false otherwise
     */
    public function hasArgument(string $key): bool;

    /**
     * Gets the value of a flag.
     *
     * @param  string  $key  The flag name
     * @return bool True if the flag is present, false otherwise
     */
    public function flag(string $key): bool;

    /**
     * Checks if a flag exists in the signature.
     *
     * @param  string  $key  The flag name
     * @return bool True if the flag exists, false otherwise
     */
    public function hasFlag(string $key): bool;

    /**
     * Checks if a flag is active in the current query.
     *
     * @param  string  $key  The flag name
     * @return bool True if the flag is active, false otherwise
     */
    public function isFlagActive(string $key): bool;

    /**
     * Gets all variadic arguments from the query.
     *
     * @return StringTypedCollection Collection of variadic argument values
     */
    public function getVariadicArguments(): StringTypedCollection;

    /**
     * Checks if variadic arguments are present in the query.
     *
     * @return bool True if variadic arguments exist, false otherwise
     */
    public function hasVariadicArguments(): bool;

    /**
     * Gets all required arguments with their values.
     *
     * @return array<string, mixed> Associative array of argument names to values
     */
    public function getRequiredArguments(): array;

    /**
     * Gets all default arguments with their values.
     *
     * @return array<string, mixed> Associative array of argument names to values
     */
    public function getDefaultArguments(): array;

    /**
     * Gets all flags with their values.
     *
     * @return array<string, bool> Associative array of flag names to values
     */
    public function getFlags(): array;

    /**
     * Gets the names of all active flags.
     *
     * @return array<int, string> List of active flag names
     */
    public function getActiveFlags(): array;

    /**
     * Checks if the directive has required arguments.
     *
     * @return bool True if required arguments exist, false otherwise
     */
    public function hasRequireds(): bool;

    /**
     * Checks if the directive has default arguments.
     *
     * @return bool True if default arguments exist, false otherwise
     */
    public function hasDefaults(): bool;

    /**
     * Checks if the directive has flags.
     *
     * @return bool True if flags exist, false otherwise
     */
    public function hasFlags(): bool;

    /**
     * Outputs a plain line of text.
     *
     * @param  string  $message  The message to output
     */
    public function line(string $message): void;

    /**
     * Outputs an informational message.
     *
     * @param  string  $message  The message to output
     */
    public function info(string $message): void;

    /**
     * Outputs an error message.
     *
     * @param  string  $message  The message to output
     */
    public function error(string $message): void;

    /**
     * Outputs a blank line.
     */
    public function newLine(): void;

    /**
     * Outputs a separator line.
     *
     * @param  string  $character  The character to repeat
     * @param  int  $length  The length of the separator
     */
    public function separator(string $character = '-', int $length = 80): void;

    /**
     * Prompts the user for input.
     *
     * @param  string  $question  The question to display
     * @return string The user's response
     */
    public function ask(string $question): string;

    /**
     * Prompts the user for confirmation.
     *
     * @param  string  $question  The question to display
     * @return bool True if the user confirms, false otherwise
     */
    public function confirm(string $question): bool;

    /**
     * Displays a table with headers and rows.
     *
     * @param  ListCollection|array<int, string>  $headers  The table headers
     * @param  ListCollection|array<int, array<int, string>>  $rows  The table rows
     */
    public function table(ListCollection|array $headers, ListCollection|array $rows): void;

    /**
     * Gets the list of internal calls made by this directive.
     *
     * @return array<int, object> List of call records
     */
    public function getCalls(): array;

    /**
     * Executes the directive.
     *
     * @return ExitCode The exit code indicating success or failure
     */
    public function run(): ExitCode;

    /**
     * Gets the list of aliases for this directive.
     *
     * @return StringTypedCollection Collection of alias names
     */
    public function getAliases(): StringTypedCollection;

    /**
     * Gets the signature of this directive.
     *
     * The signature defines the command name, arguments, and flags
     * in a syntax similar to Laravel's Artisan commands.
     *
     * @return string The signature string
     */
    public function getSignature(): string;
}
