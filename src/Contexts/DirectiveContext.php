<?php

// src/Contexts/DirectiveContext.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contexts;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

class DirectiveContext
{
    private ParameterCollection $arguments;
    private ParameterCollection $options;
    private StringTypedCollection $variadicArguments;
    private LaravelBootstrapperContext $laravelBootstrapper;
    private DirectiveBlueprintRecord $blueprint;
    private StringTypedCollection $aliases;
    private bool $shouldBootLaravel;

    public function __construct(
        LaravelBootstrapperContext $laravelBootstrapper,
        DirectiveBlueprintRecord $blueprint,
        StringTypedCollection $aliases,
        bool $shouldBootLaravel,
    ) {
        $this->laravelBootstrapper = $laravelBootstrapper;
        $this->blueprint = $blueprint;
        $this->aliases = $aliases;
        $this->shouldBootLaravel = $shouldBootLaravel;
        $this->arguments = new ParameterCollection;
        $this->options = new ParameterCollection;
        $this->variadicArguments = new StringTypedCollection;
    }

    public function getBlueprint(): DirectiveBlueprintRecord
    {
        return $this->blueprint;
    }

    public function getAliases(): StringTypedCollection
    {
        return $this->aliases;
    }

    public function shouldBootLaravel(): bool
    {
        return $this->shouldBootLaravel;
    }

    public function getArguments(): ParameterCollection
    {
        return $this->arguments;
    }

    public function setArguments(ParameterCollection $arguments): self
    {
        $this->arguments = $arguments;
        return $this;
    }

    public function getArgument(string $key): ?string
    {
        $value = $this->arguments->get($key);
        if ($value === null || $value === true || $value === false || $value === '') {
            return null;
        }
        return $value;
    }

    public function hasArgument(string $key): bool
    {
        $value = $this->arguments->get($key);
        return $value !== null && $value !== '' && $value !== true && $value !== false;
    }

    public function getOptions(): ParameterCollection
    {
        return $this->options;
    }

    public function setOptions(ParameterCollection $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function getOption(string $key): bool|string|null
    {
        $value = $this->options->get($key);
        if ($value === null || $value === '') {
            return null;
        }
        return $value;
    }

    public function hasOption(string $key): bool
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

    public function getVariadicArguments(): StringTypedCollection
    {
        return $this->variadicArguments;
    }

    public function setVariadicArguments(StringTypedCollection $variadicArguments): self
    {
        $this->variadicArguments = $variadicArguments;
        return $this;
    }

    public function hasVariadicArguments(): bool
    {
        return $this->variadicArguments->isNotEmpty();
    }

    public function getLaravelBootstrapper(): LaravelBootstrapperContext
    {
        return $this->laravelBootstrapper;
    }

    public function hasLaravel(): bool
    {
        return $this->laravelBootstrapper->isBootstrapped();
    }

    public function getLaravel(): object
    {
        return $this->laravelBootstrapper->getApplication();
    }

    public function reset(): void
    {
        $this->arguments = new ParameterCollection;
        $this->options = new ParameterCollection;
        $this->variadicArguments = new StringTypedCollection;
    }
}
