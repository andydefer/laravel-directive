<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Contracts\DirectiveFactoryInterface;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;

/**
 * Service responsible for hydrating directive instances with parsed data.
 *
 * This service takes a parsed directive record and hydrates a directive instance
 * with arguments, options, and Laravel bootstrapper. It uses reflection to create
 * instances without constructors when only metadata or aliases are needed.
 *
 * @author Andy Defer
 */
class DirectiveHydratorService
{
    private ?LaravelBootstrapper $laravelBootstrapper = null;

    public function __construct(
        private readonly DirectiveFactoryInterface $factory,
    ) {}

    /**
     * Sets the Laravel bootstrapper for directives that need it.
     *
     * @param LaravelBootstrapper|null $bootstrapper The bootstrapper instance
     */
    public function setLaravelBootstrapper(?LaravelBootstrapper $bootstrapper): void
    {
        $this->laravelBootstrapper = $bootstrapper;
    }

    /**
     * Fully hydrates a directive instance with parsed arguments and options.
     *
     * @param string                 $class  Fully qualified class name
     * @param ParsedDirectiveRecord $parsed Parsed record containing arguments and options
     *
     * @return DirectiveInterface The hydrated directive instance
     */
    public function hydrate(string $class, ParsedDirectiveRecord $parsed): DirectiveInterface
    {
        $directive = $this->factory->make($class);

        $this->injectLaravelBootstrapper($directive);
        $this->setArguments($directive, $parsed);
        $this->setOptions($directive, $parsed);

        return $directive;
    }

    /**
     * Returns the blueprint record of a directive without instantiating it with its constructor.
     *
     * Uses reflection to create an instance without calling the constructor,
     * which is useful when only metadata is needed.
     *
     * @param string $class Fully qualified class name
     *
     * @return DirectiveBlueprintRecord The blueprint record
     */
    public function hydrateBlueprint(string $class): DirectiveBlueprintRecord
    {
        $directive = $this->createWithoutConstructor($class);

        $this->injectLaravelBootstrapper($directive);

        return $directive->getBlueprint();
    }

    /**
     * Returns a directive instance for alias resolution without using its constructor.
     *
     * Uses reflection to create an instance without calling the constructor,
     * which is useful when only aliases and signature are needed.
     *
     * @param string $class Fully qualified class name
     *
     * @return DirectiveInterface The directive instance (without constructor execution)
     */
    public function hydrateForAliases(string $class): DirectiveInterface
    {
        $directive = $this->createWithoutConstructor($class);

        $this->injectLaravelBootstrapper($directive);

        return $directive;
    }

    /**
     * Injects the Laravel bootstrapper into the directive if available.
     *
     * @param DirectiveInterface $directive The directive instance
     */
    private function injectLaravelBootstrapper(DirectiveInterface $directive): void
    {
        if ($this->laravelBootstrapper !== null && method_exists($directive, 'setLaravelBootstrapper')) {
            $directive->setLaravelBootstrapper($this->laravelBootstrapper);
        }
    }

    /**
     * Sets the parsed arguments on the directive.
     *
     * @param DirectiveInterface   $directive The directive instance
     * @param ParsedDirectiveRecord $parsed   The parsed record
     */
    private function setArguments(DirectiveInterface $directive, ParsedDirectiveRecord $parsed): void
    {
        if (method_exists($directive, 'setArguments')) {
            $directive->setArguments(
                ParameterCollection::fromFlatArguments($parsed->arguments)
            );
        }
    }

    /**
     * Sets the parsed options on the directive.
     *
     * @param DirectiveInterface   $directive The directive instance
     * @param ParsedDirectiveRecord $parsed   The parsed record
     */
    private function setOptions(DirectiveInterface $directive, ParsedDirectiveRecord $parsed): void
    {
        if (method_exists($directive, 'setOptions')) {
            $directive->setOptions(
                ParameterCollection::fromFlatOptions($parsed->options)
            );
        }
    }

    /**
     * Creates a directive instance without calling its constructor.
     *
     * Uses reflection to bypass the constructor, useful when only metadata
     * or aliases are needed.
     *
     * @param string $class Fully qualified class name
     *
     * @return DirectiveInterface The directive instance
     */
    private function createWithoutConstructor(string $class): DirectiveInterface
    {
        $reflection = new \ReflectionClass($class);
        return $reflection->newInstanceWithoutConstructor();
    }
}
