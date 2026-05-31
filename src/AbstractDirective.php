<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

abstract class AbstractDirective implements DirectiveInterface
{
    protected ParameterCollection $arguments;

    protected ParameterCollection $options;

    public function __construct(
        protected readonly DirectiveInteractionService $interaction,
        protected ?LaravelBootstrapper $laravelBootstrapper = null,
    ) {
        $this->arguments = new ParameterCollection;
        $this->options = new ParameterCollection;
    }

    public function getBlueprint(): DirectiveBlueprintRecord
    {
        return new DirectiveBlueprintRecord(
            class: static::class,
            signature: $this->getSignature(),
            description: $this->getDescription(),
        );
    }

    public function getAliases(): StringTypedCollection
    {
        return new StringTypedCollection;
    }

    /**
     * Override this method to enable Laravel bootstrapping for this directive.
     */
    public function shouldBootLaravel(): bool
    {
        return false;
    }

    /**
     * Check if Laravel has been bootstrapped and is available.
     */
    public function hasLaravel(): bool
    {
        return $this->laravelBootstrapper !== null && $this->laravelBootstrapper->isBootstrapped();
    }

    /**
     * Get the Laravel application instance if available.
     */
    public function getLaravel(): ?object
    {
        return $this->laravelBootstrapper?->getApplication();
    }

    /**
     * Set the Laravel bootstrapper instance.
     */
    public function setLaravelBootstrapper(?LaravelBootstrapper $bootstrapper): self
    {
        $this->laravelBootstrapper = $bootstrapper;

        return $this;
    }

    // ==================== Argument Management ====================

    public function setArguments(ParameterCollection $arguments): self
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * Get an argument value by its key.
     * Returns null if the argument is not provided or empty.
     *
     * @param  string  $key  The argument name
     * @return string|null The argument value, or null if not provided or empty
     */
    public function argument(string $key): ?string
    {
        $value = $this->arguments->get($key);

        if ($value === null || $value === true || $value === false || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Check if an argument exists and has a non-empty value.
     * Empty strings are considered as not provided.
     *
     * @param  string  $key  The argument name
     * @return bool True if the argument exists and has a non-empty value, false otherwise
     */
    public function hasArgument(string $key): bool
    {
        $value = $this->arguments->get($key);

        return $value !== null && $value !== '' && $value !== true && $value !== false;
    }

    // ==================== Option Management ====================

    public function setOptions(ParameterCollection $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get an option value by its key.
     *
     * @param  string  $key  The option name
     * @return bool|string|null The option value (boolean for flags, string for values), or null if not found
     */
    public function option(string $key): bool|string|null
    {
        $value = $this->options->get($key);

        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Check if an option exists and has a non-empty value.
     * Empty strings are considered as not provided.
     *
     * @param  string  $key  The option name
     * @return bool True if the option exists and has a non-empty value, false otherwise
     */
    public function hasOption(string $key): bool
    {
        $value = $this->options->get($key);

        if ($value === null || $value === '') {
            return false;
        }

        // Pour les booléens, true est considéré comme présent
        if (is_bool($value)) {
            return $value;
        }

        // Pour les chaînes, on vérifie qu'elles ne sont pas vides
        return $value !== '';
    }

    // ==================== Display Methods ====================

    public function line(string $message): void
    {
        $this->interaction->line($message);
    }

    public function info(string $message): void
    {
        $this->interaction->info($message);
    }

    public function error(string $message): void
    {
        $this->interaction->error($message);
    }

    public function warn(string $message): void
    {
        $this->interaction->warn($message);
    }

    // ==================== User Interaction ====================

    public function ask(string $question): string
    {
        return $this->interaction->ask($question);
    }

    public function confirm(string $question): bool
    {
        return $this->interaction->confirm($question);
    }

    // ==================== Table Display ====================

    public function table(StringTypedCollection $headers, RowCollection $rows): void
    {
        $this->interaction->table($headers, $rows);
    }
}
