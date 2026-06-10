<?php

// src/Services/DirectiveHydratorService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Contracts\DirectiveFactoryInterface;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;

class DirectiveHydratorService
{
    private ?LaravelBootstrapperContext $laravelBootstrapperContext = null;

    public function __construct(
        private readonly DirectiveFactoryInterface $factory,
    ) {}

    public function setLaravelBootstrapper(?LaravelBootstrapperContext $bootstrapperContext): void
    {
        $this->laravelBootstrapperContext = $bootstrapperContext;
    }

    public function hydrate(string $class, ParsedDirectiveRecord $parsed): DirectiveInterface
    {
        $directive = $this->factory->make($class);

        $this->injectLaravelBootstrapper($directive);
        $this->setArguments($directive, $parsed);
        $this->setOptions($directive, $parsed);
        $this->setVariadicArguments($directive, $parsed);

        return $directive;
    }

    public function hydrateBlueprint(string $class): DirectiveBlueprintRecord
    {
        $directive = $this->createWithoutConstructor($class);
        $this->injectLaravelBootstrapper($directive);

        return $directive->getBlueprint();
    }

    public function hydrateForAliases(string $class): DirectiveInterface
    {
        $directive = $this->createWithoutConstructor($class);
        $this->injectLaravelBootstrapper($directive);

        return $directive;
    }

    private function injectLaravelBootstrapper(DirectiveInterface $directive): void
    {
        if ($this->laravelBootstrapperContext !== null && method_exists($directive, 'setLaravelBootstrapper')) {
            $directive->setLaravelBootstrapper($this->laravelBootstrapperContext);
        }
    }

    private function setArguments(DirectiveInterface $directive, ParsedDirectiveRecord $parsed): void
    {
        if (method_exists($directive, 'setArguments')) {
            $collection = ParameterCollection::fromFlatArguments($parsed->arguments);
            $directive->setArguments($collection);
        }
    }

    private function setOptions(DirectiveInterface $directive, ParsedDirectiveRecord $parsed): void
    {
        if (! method_exists($directive, 'setOptions')) {
            return;
        }

        $normalizedOptions = new ParameterCollection;
        foreach ($parsed->options as $option) {
            $value = $option->value;
            if ($value === 'true') {
                $value = true;
            } elseif ($value === 'false') {
                $value = false;
            }
            $normalizedOptions->add(new ParameterRecord($option->name, $value));
        }

        $directive->setOptions($normalizedOptions);
    }

    private function setVariadicArguments(DirectiveInterface $directive, ParsedDirectiveRecord $parsed): void
    {
        if (! method_exists($directive, 'setVariadicArguments')) {
            return;
        }

        $directive->setVariadicArguments($parsed->variadic_arguments);
    }

    private function createWithoutConstructor(string $class): DirectiveInterface
    {
        $reflection = new \ReflectionClass($class);

        return $reflection->newInstanceWithoutConstructor();
    }
}
