<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Contracts\DirectiveFactoryInterface;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;

class DirectiveHydratorService
{
    public function __construct(
        private readonly DirectiveFactoryInterface $factory,
    ) {}

    public function hydrate(string $class, ParsedDirectiveRecord $parsed): DirectiveInterface
    {
        $directive = $this->factory->make($class);

        if (method_exists($directive, 'setArguments')) {
            $directive->setArguments(
                ParameterCollection::fromFlatArguments($parsed->arguments)
            );
        }

        if (method_exists($directive, 'setOptions')) {
            $directive->setOptions(
                ParameterCollection::fromFlatOptions($parsed->options)
            );
        }

        return $directive;
    }

    public function hydrateBlueprint(string $class): DirectiveBlueprintRecord
    {
        return $this->factory->make($class)->getBlueprint();
    }

    public function hydrateForAliases(string $class): DirectiveInterface
    {
        return $this->factory->make($class);
    }
}
