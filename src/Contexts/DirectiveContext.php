<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contexts;

use AndyDefer\Directive\Collections\ParameterVOCollection;
use AndyDefer\Directive\Enums\PrimitiveType;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

class DirectiveContext
{
    private ParameterVOCollection $arguments;

    private ParameterVOCollection $options;

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
        $this->arguments = new ParameterVOCollection;
        $this->options = new ParameterVOCollection;
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

    public function getArguments(): ParameterVOCollection
    {
        return $this->arguments;
    }

    public function setArguments(ParameterVOCollection $arguments): self
    {
        $this->arguments = $arguments;

        return $this;
    }

    public function getArgument(string $key): mixed
    {
        $value = $this->arguments->get($key);

        // Retourner null si la valeur est une chaîne vide
        if ($value === '') {
            return null;
        }

        // La valeur est déjà convertie par ParameterVOCollection::get()
        return $value;
    }

    public function hasArgument(string $key): bool
    {
        $value = $this->arguments->get($key);

        // Un argument existe si la valeur n'est pas null et n'est pas une chaîne vide
        return $value !== null && $value !== '';
    }

    public function getOptions(): ParameterVOCollection
    {
        return $this->options;
    }

    public function setOptions(ParameterVOCollection $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function getOption(string $key): mixed
    {
        $value = $this->options->get($key);

        // Retourner null si la valeur est null ou une chaîne vide
        if ($value === null || $value === '') {
            return null;
        }

        // La valeur est déjà convertie par ParameterVOCollection::get()
        return $value;
    }

    public function hasOption(string $key): bool
    {
        $value = $this->options->get($key);

        if ($value === null) {
            return false;
        }

        // Pour les booléens, on vérifie si c'est true
        if (is_bool($value)) {
            return $value === true;
        }

        // Pour les autres types, on vérifie que ce n'est pas une chaîne vide
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
        $this->arguments = new ParameterVOCollection;
        $this->options = new ParameterVOCollection;
        $this->variadicArguments = new StringTypedCollection;
    }
}
