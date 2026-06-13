<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contexts;

use AndyDefer\Directive\Collections\ParameterVOCollection;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use Illuminate\Foundation\Application;

final class DirectiveContext
{
    private ParameterVOCollection $arguments;
    private ParameterVOCollection $options;
    private StringTypedCollection $variadicArguments;
    private DirectiveBlueprintRecord $blueprint;
    private StringTypedCollection $aliases;
    private ?Application $laravelApplication;

    public function __construct(
        DirectiveBlueprintRecord $blueprint,
        StringTypedCollection $aliases,
        ?Application $laravelApplication = null,
    ) {
        $this->blueprint = $blueprint;
        $this->aliases = $aliases;
        $this->laravelApplication = $laravelApplication;
        $this->arguments = new ParameterVOCollection();
        $this->options = new ParameterVOCollection();
        $this->variadicArguments = new StringTypedCollection();
    }

    public function getBlueprint(): DirectiveBlueprintRecord
    {
        return $this->blueprint;
    }

    public function getAliases(): StringTypedCollection
    {
        return $this->aliases;
    }

    public function getArguments(): ParameterVOCollection
    {
        return $this->arguments;
    }

    public function getArgument(string $key): mixed
    {
        $value = $this->arguments->get($key);
        return $value === '' ? null : $value;
    }

    public function hasArgument(string $key): bool
    {
        $value = $this->arguments->get($key);
        return $value !== null && $value !== '';
    }

    public function getOptions(): ParameterVOCollection
    {
        return $this->options;
    }

    public function getOption(string $key): mixed
    {
        $value = $this->options->get($key);
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        return $value;
    }

    public function hasOption(string $key): bool
    {
        $value = $this->options->get($key);
        if ($value === null) {
            return false;
        }
        if (is_bool($value)) {
            return $value === true;
        }
        return $value !== '';
    }

    public function getVariadicArguments(): StringTypedCollection
    {
        return $this->variadicArguments;
    }

    public function hasVariadicArguments(): bool
    {
        return $this->variadicArguments->isNotEmpty();
    }

    public function getLaravel(): Application
    {
        if ($this->laravelApplication === null) {
            throw new \RuntimeException('Laravel application is not available. Make sure to pass it in the constructor.');
        }
        return $this->laravelApplication;
    }

    public function hasLaravel(): bool
    {
        return $this->laravelApplication !== null;
    }

    public function setArguments(ParameterVOCollection $arguments): self
    {
        $this->arguments = $arguments;
        return $this;
    }

    public function setOptions(ParameterVOCollection $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function setVariadicArguments(StringTypedCollection $variadicArguments): self
    {
        $this->variadicArguments = $variadicArguments;
        return $this;
    }

    public function setLaravelApplication(?Application $application): self
    {
        $this->laravelApplication = $application;
        return $this;
    }
}
