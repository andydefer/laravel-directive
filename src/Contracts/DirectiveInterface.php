<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\ListCollection;
use Illuminate\Foundation\Application;

interface DirectiveInterface
{
    public function getDescription(): string;

    /**
     * Returns the Laravel application instance.
     */
    public function getLaravel(): Application;

    /**
     * Retrieves an argument value by its name.
     * Searches in required arguments first, then in default arguments.
     */
    public function argument(string $key): mixed;

    /**
     * Checks if an argument exists (in required or default arguments).
     */
    public function hasArgument(string $key): bool;

    /**
     * Retrieves an option value by its name.
     * Returns true if the option is active, false otherwise.
     */
    public function option(string $key): bool;

    /**
     * Checks if an option exists.
     */
    public function hasOption(string $key): bool;

    /**
     * Returns all variadic arguments as a collection of strings.
     */
    public function getVariadicArguments(): StringTypedCollection;

    /**
     * Checks if there are any variadic arguments.
     */
    public function hasVariadicArguments(): bool;

    /**
     * Outputs a plain line of text.
     */
    public function line(string $message): void;

    /**
     * Outputs an informational message (blue).
     */
    public function info(string $message): void;

    /**
     * Outputs an error message (red).
     */
    public function error(string $message): void;

    /**
     * Outputs a new line.
     */
    public function newLine(): void;

    /**
     * Outputs a separator line.
     */
    public function separator(string $character = '-', int $length = 80): void;

    /**
     * Asks a question and returns the user's input.
     */
    public function ask(string $question): string;

    /**
     * Asks a confirmation question and returns true if confirmed.
     */
    public function confirm(string $question): bool;

    /**
     * Renders a table with headers and rows.
     */
    public function table(ListCollection|array $headers, ListCollection|array $rows): void;

    /**
     * Returns all recorded calls.
     */
    public function getCalls(): array;

    /**
     * Runs the directive and returns the exit code.
     */
    public function run(): ExitCode;
}
