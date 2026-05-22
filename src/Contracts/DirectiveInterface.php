<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Records\Collections\TypedCollection;

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
     * @return TypedCollection<string> Collection of alias names
     */
    public function getAliases(): TypedCollection;

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
     * Check if an option exists.
     *
     * @param  string  $key  The option name
     * @return bool True if the option exists, false otherwise
     */
    public function hasOption(string $key): bool;
}
