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
    private ?LaravelBootstrapper $laravelBootstrapper = null;

    public function __construct(
        private readonly DirectiveFactoryInterface $factory,
    ) {}

    public function setLaravelBootstrapper(?LaravelBootstrapper $bootstrapper): void
    {
        $this->laravelBootstrapper = $bootstrapper;
    }

    public function hydrate(string $class, ParsedDirectiveRecord $parsed): DirectiveInterface
    {
        $directive = $this->factory->make($class);

        if ($this->laravelBootstrapper !== null && method_exists($directive, 'setLaravelBootstrapper')) {
            $directive->setLaravelBootstrapper($this->laravelBootstrapper);
        }

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

    /**
     * Hydrate UNIQUEMENT le blueprint sans utiliser le constructeur
     */
    public function hydrateBlueprint(string $class): DirectiveBlueprintRecord
    {
        // 🔥 SOLUTION : Créer l'instance sans constructeur
        $reflection = new \ReflectionClass($class);
        $directive = $reflection->newInstanceWithoutConstructor();

        // Injecter le bootstrapper si disponible (pour hasLaravel etc.)
        if ($this->laravelBootstrapper !== null && method_exists($directive, 'setLaravelBootstrapper')) {
            $directive->setLaravelBootstrapper($this->laravelBootstrapper);
        }

        return $directive->getBlueprint();
    }

    /**
     * Hydrate UNIQUEMENT pour les alias sans utiliser le constructeur
     */
    public function hydrateForAliases(string $class): DirectiveInterface
    {
        // 🔥 SOLUTION : Créer l'instance sans constructeur
        $reflection = new \ReflectionClass($class);
        $directive = $reflection->newInstanceWithoutConstructor();

        if ($this->laravelBootstrapper !== null && method_exists($directive, 'setLaravelBootstrapper')) {
            $directive->setLaravelBootstrapper($this->laravelBootstrapper);
        }

        return $directive;
    }
}
