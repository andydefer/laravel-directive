<?php

// src/Contracts/DirectiveInterface.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Contract for all console directive implementations.
 *
 * Defines the required methods for executing console commands with
 * typed argument and option handling.
 */
interface DirectiveInterface
{
    /**
     * Execute the directive's main logic.
     *
     * @return ExitCode The exit code indicating success or failure
     */
    public function execute(): ExitCode;

    /**
     * Get the directive signature (e.g., "user:list {--active} {name}").
     *
     * @return string The signature string
     */
    public function getSignature(): string;

    /**
     * Get the directive description for help output.
     *
     * @return string The description text
     */
    public function getDescription(): string;

    /**
     * Get all aliases for this directive.
     *
     * @return StringTypedCollection Collection of alias names
     */
    public function getAliases(): StringTypedCollection;

    /**
     * Get the blueprint record containing directive metadata.
     *
     * Used for discovery and introspection without executing the directive.
     *
     * @return DirectiveBlueprintRecord The blueprint metadata
     */
    public function getBlueprint(): DirectiveBlueprintRecord;

    /**
     * Set the arguments for this directive.
     *
     * @param  ParameterCollection  $arguments  Collection of typed argument parameters
     * @return self Returns the directive instance for method chaining
     */
    public function setArguments(ParameterCollection $arguments): self;

    /**
     * Get an argument value by its key.
     *
     * @param  string  $key  The argument name
     * @return string|null The argument value, or null if not found
     */
    public function argument(string $key): ?string;

    /**
     * Check if an argument exists and has a non-empty value.
     *
     * @param  string  $key  The argument name
     * @return bool True if the argument exists and has a non-empty value
     */
    public function hasArgument(string $key): bool;

    /**
     * Set the options for this directive.
     *
     * @param  ParameterCollection  $options  Collection of typed option parameters
     * @return self Returns the directive instance for method chaining
     */
    public function setOptions(ParameterCollection $options): self;

    /**
     * Get an option value by its key.
     *
     * @param  string  $key  The option name
     * @return bool|string|null The option value (boolean for flags, string for values), or null if not found
     */
    public function option(string $key): bool|string|null;

    /**
     * Check if an option exists and has a non-empty value.
     *
     * @param  string  $key  The option name
     * @return bool True if the option exists, false otherwise
     */
    public function hasOption(string $key): bool;

    /**
     * Set the variadic arguments for this directive.
     *
     * @param  StringTypedCollection  $variadicArguments  Collection of variadic argument values
     * @return self Returns the directive instance for method chaining
     */
    public function setVariadicArguments(StringTypedCollection $variadicArguments): self;

    /**
     * Get all variadic arguments as a typed collection.
     *
     * Variadic arguments capture all remaining command-line arguments
     * that are not consumed by named arguments or options.
     *
     * @return StringTypedCollection Collection of variadic argument values
     */
    public function getVariadicArguments(): StringTypedCollection;

    /**
     * Check if the directive has variadic arguments.
     *
     * @return bool True if variadic arguments exist and are not empty
     */
    public function hasVariadicArguments(): bool;

    /**
     * Override this method to enable Laravel bootstrapping for this directive.
     *
     * Set to true if your directive needs:
     * - Eloquent models (User::find(), etc.)
     * - Database connections (DB::table())
     * - Laravel cache, queues, events, or any Laravel service
     *
     * Default is false for optimal performance (no Laravel bootstrap overhead).
     *
     * @return bool True if Laravel should be bootstrapped for this directive
     */
    public function shouldBootLaravel(): bool;

    /**
     * Check if Laravel has been bootstrapped and is available.
     *
     * Use this method in your directive to check if Laravel features
     * (Eloquent, DB, Cache, etc.) are available.
     *
     * @return bool True if Laravel is bootstrapped and available
     */
    public function hasLaravel(): bool;

    /**
     * Get the Laravel application instance if available.
     *
     * @return object|null The Laravel application instance or null if not available
     */
    public function getLaravel(): ?object;

    /**
     * Set the Laravel bootstrapper instance for this directive.
     *
     * This method is used by the framework to inject the bootstrapper
     * when Laravel support is needed. You don't need to call it manually.
     *
     * @param  LaravelBootstrapper|null  $bootstrapper  The Laravel bootstrapper instance
     * @return self Returns the directive instance for method chaining
     */
    public function setLaravelBootstrapper(?LaravelBootstrapper $bootstrapper): self;

    /**
     * Set the interaction service instance for this directive.
     *
     * This method is used by the framework to inject the interaction service
     * when needed. You don't need to call it manually.
     *
     * @param  DirectiveInteractionService  $interaction  The interaction service instance
     * @return self Returns the directive instance for method chaining
     */
    public function setInteraction(DirectiveInteractionService $interaction): self;

    // ==================== Display Methods ====================

    /**
     * Outputs a plain text line.
     *
     * @param  string  $message  The message to display
     */
    public function line(string $message): void;

    /**
     * Outputs an informational message (typically green).
     *
     * @param  string  $message  The message to display
     */
    public function info(string $message): void;

    /**
     * Outputs an error message (typically red).
     *
     * @param  string  $message  The message to display
     */
    public function error(string $message): void;

    /**
     * Outputs a warning message (typically yellow).
     *
     * @param  string  $message  The message to display
     */
    public function warn(string $message): void;

    /**
     * Outputs a blank line (empty line).
     */
    public function newLine(): void;

    /**
     * Outputs a separator line.
     *
     * @param  string  $character  The character to use for the separator (default: '-')
     * @param  int  $length  The length of the separator line (default: 80)
     */
    public function separator(string $character = '-', int $length = 80): void;

    // ==================== User Interaction Methods ====================

    /**
     * Asks a question and returns the user's answer.
     *
     * @param  string  $question  The question to ask
     * @return string The user's answer
     */
    public function ask(string $question): string;

    /**
     * Asks for confirmation and returns the user's choice.
     *
     * @param  string  $question  The confirmation question
     * @return bool True if the user confirms (y/yes), false otherwise
     */
    public function confirm(string $question): bool;

    // ==================== Table Display Methods ====================

    /**
     * Displays a formatted table with headers and rows.
     *
     * @param  StringTypedCollection  $headers  The table headers
     * @param  RowCollection  $rows  The table rows
     */
    public function table(StringTypedCollection $headers, RowCollection $rows): void;
}
