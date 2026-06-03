<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Abstract base class for all CLI directives.
 *
 * This class provides the foundation for creating CLI commands with:
 * - Argument and option management
 * - User interaction methods (ask, confirm, line, info, error, warn)
 * - Table display capabilities
 * - Optional Laravel bootstrapping
 *
 * @example
 * final class UserListDirective extends AbstractDirective
 * {
 *     public function getSignature(): string
 *     {
 *         return 'user-list {--role=} {--active}';
 *     }
 *
 *     public function getDescription(): string
 *     {
 *         return 'List all users with optional filters';
 *     }
 *
 *     public function execute(): ExitCode
 *     {
 *         $role = $this->option('role');
 *         $active = $this->option('active');
 *
 *         $this->info("Listing users with role: {$role}");
 *
 *         return ExitCode::SUCCESS;
 *     }
 * }
 *
 * @author Andy Defer
 */
abstract class AbstractDirective implements DirectiveInterface
{
    protected ParameterCollection $arguments;
    protected ParameterCollection $options;
    protected ?LaravelBootstrapper $laravelBootstrapper = null;


    public function __construct(
        protected readonly DirectiveInteractionService $interaction,
    ) {
        $this->arguments = new ParameterCollection();
        $this->options = new ParameterCollection();
    }

    /**
     * Returns the blueprint record for this directive.
     *
     * The blueprint contains metadata about the directive including its class,
     * signature, and description.
     *
     * @return DirectiveBlueprintRecord The blueprint record
     */
    final public function getBlueprint(): DirectiveBlueprintRecord
    {
        return new DirectiveBlueprintRecord(
            class: static::class,
            signature: $this->getSignature(),
            description: $this->getDescription(),
        );
    }

    /**
     * Returns the aliases for this directive.
     *
     * Aliases are alternative names that can be used to invoke this directive.
     * Override this method to provide custom aliases.
     *
     * @return StringTypedCollection Collection of alias strings
     */
    public function getAliases(): StringTypedCollection
    {
        return new StringTypedCollection();
    }

    /**
     * Determines whether Laravel should be bootstrapped before executing this directive.
     *
     * Override this method to return true if the directive needs Laravel features
     * such as Eloquent, database connections, or caching.
     *
     * @return bool True if Laravel bootstrapping is required
     */
    public function shouldBootLaravel(): bool
    {
        return false;
    }

    /**
     * Checks if Laravel has been bootstrapped and is available.
     *
     * @return bool True if Laravel is available
     */
    final public function hasLaravel(): bool
    {
        return $this->laravelBootstrapper !== null && $this->laravelBootstrapper->isBootstrapped();
    }

    /**
     * Returns the Laravel application instance if available.
     *
     * @return object|null The Laravel application instance, or null if not available
     */
    final public function getLaravel(): ?object
    {
        return $this->laravelBootstrapper?->getApplication();
    }

    /**
     * Sets the Laravel bootstrapper instance.
     *
     * @param LaravelBootstrapper|null $bootstrapper The bootstrapper instance
     *
     * @return self Returns the current instance for method chaining
     */
    final public function setLaravelBootstrapper(?LaravelBootstrapper $bootstrapper): self
    {
        $this->laravelBootstrapper = $bootstrapper;

        return $this;
    }

    /**
     * Sets the interaction service instance.
     *
     * @param DirectiveInteractionService $interaction The interaction service instance
     *
     * @return self Returns the current instance for method chaining
     */
    final public function setInteraction(DirectiveInteractionService $interaction): self
    {
        $this->interaction = $interaction;

        return $this;
    }

    // ==================== Argument Management ====================

    /**
     * Sets the arguments for this directive.
     *
     * @param ParameterCollection $arguments The argument collection
     *
     * @return self Returns the current instance for method chaining
     */
    final public function setArguments(ParameterCollection $arguments): self
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * Returns the value of an argument by its key.
     *
     * Returns null if the argument is not provided, is empty, or is a boolean value.
     *
     * @param string $key The argument name
     *
     * @return string|null The argument value, or null if not available
     */
    final public function argument(string $key): ?string
    {
        $value = $this->arguments->get($key);

        if ($value === null || $value === true || $value === false || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Checks if an argument exists and has a non-empty value.
     *
     * Empty strings and boolean values are considered not provided.
     *
     * @param string $key The argument name
     *
     * @return bool True if the argument exists and has a non-empty value
     */
    final public function hasArgument(string $key): bool
    {
        $value = $this->arguments->get($key);

        return $value !== null && $value !== '' && $value !== true && $value !== false;
    }

    // ==================== Option Management ====================

    /**
     * Sets the options for this directive.
     *
     * @param ParameterCollection $options The option collection
     *
     * @return self Returns the current instance for method chaining
     */
    final public function setOptions(ParameterCollection $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Returns the value of an option by its key.
     *
     * Returns null if the option is not provided or is an empty string.
     * Returns boolean for flag options (--force) and string for valued options (--role=admin).
     *
     * @param string $key The option name
     *
     * @return bool|string|null The option value, or null if not available
     */
    final public function option(string $key): bool|string|null
    {
        $value = $this->options->get($key);

        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Checks if an option exists and has a non-empty value.
     *
     * @param string $key The option name
     *
     * @return bool True if the option exists and has a non-empty value
     */
    final public function hasOption(string $key): bool
    {
        $value = $this->options->get($key);

        if ($value === null || $value === '') {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        return $value !== '';
    }

    // ==================== Display Methods ====================

    /**
     * Outputs a plain text line.
     *
     * @param string $message The message to display
     */
    final public function line(string $message): void
    {
        $this->interaction->line($message);
    }

    /**
     * Outputs an informational message (typically green).
     *
     * @param string $message The message to display
     */
    final public function info(string $message): void
    {
        $this->interaction->info($message);
    }

    /**
     * Outputs an error message (typically red).
     *
     * @param string $message The message to display
     */
    final public function error(string $message): void
    {
        $this->interaction->error($message);
    }

    /**
     * Outputs a warning message (typically yellow).
     *
     * @param string $message The message to display
     */
    final public function warn(string $message): void
    {
        $this->interaction->warn($message);
    }

    // ==================== User Interaction Methods ====================

    /**
     * Asks a question and returns the user's answer.
     *
     * @param string $question The question to ask
     *
     * @return string The user's answer
     */
    final public function ask(string $question): string
    {
        return $this->interaction->ask($question);
    }

    /**
     * Asks for confirmation and returns the user's choice.
     *
     * @param string $question The confirmation question
     *
     * @return bool True if the user confirms (y/yes), false otherwise
     */
    final public function confirm(string $question): bool
    {
        return $this->interaction->confirm($question);
    }

    // ==================== Table Display Methods ====================

    /**
     * Displays a formatted table with headers and rows.
     *
     * @param StringTypedCollection $headers The table headers
     * @param RowCollection         $rows    The table rows
     */
    final public function table(StringTypedCollection $headers, RowCollection $rows): void
    {
        $this->interaction->table($headers, $rows);
    }

    /**
     * Outputs a blank line (empty line).
     */
    final public function newLine(): void
    {
        $this->interaction->newLine();
    }

    /**
     * Outputs a separator line.
     * 
     * @param string $character The character to use for the separator (default: '-')
     * @param int $length The length of the separator line (default: 80)
     */
    final public function separator(string $character = '-', int $length = 80): void
    {
        $this->interaction->separator($character, $length);
    }

    /**
     * Execute the directive's main logic.
     *
     * @return ExitCode The exit code indicating success or failure
     */
    abstract public function execute(): ExitCode;
}
