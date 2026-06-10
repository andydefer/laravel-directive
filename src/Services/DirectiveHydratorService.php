<?php

// src/Services/DirectiveHydratorService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Collections\ParsedOptionCollection;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

class DirectiveHydratorService
{
    private DirectiveInteractionService $interaction;

    public function __construct(
        private readonly LaravelBootstrapperContext $laravelBootstrapperContext,
        ?DirectiveInteractionService $interaction = null,
    ) {
        $this->interaction = $interaction ?? new DirectiveInteractionService(
            new \AndyDefer\Directive\Dispatchers\RenderDispatcher,
            new \AndyDefer\Directive\Dispatchers\InputDispatcher,
        );
    }

    public function hydrate(string $class, ParsedDirectiveRecord $parsed): DirectiveInterface
    {
        $reflection = new \ReflectionClass($class);

        // Créer un contexte temporaire pour les métadonnées
        $tempContext = new DirectiveContext(
            laravelBootstrapper: $this->laravelBootstrapperContext,
            blueprint: new DirectiveBlueprintRecord($class, '', ''),
            aliases: new StringTypedCollection,
            shouldBootLaravel: false,
        );

        // Créer une instance temporaire pour lire les métadonnées
        $tempInstance = $reflection->newInstance($tempContext, $this->interaction);

        // Récupérer les métadonnées
        $blueprint = $tempInstance->getBlueprint();
        $aliases = $tempInstance->getAliases();
        $shouldBootLaravel = $tempInstance->shouldBootLaravel();

        // Créer le vrai contexte avec les métadonnées
        $context = new DirectiveContext(
            laravelBootstrapper: $this->laravelBootstrapperContext,
            blueprint: $blueprint,
            aliases: $aliases,
            shouldBootLaravel: $shouldBootLaravel,
        );

        // Remplir les données parsées dans le contexte
        $context->setArguments(ParameterCollection::fromFlatArguments($parsed->arguments));
        $context->setOptions($this->normalizeOptions($parsed->options));
        $context->setVariadicArguments($parsed->variadic_arguments);

        // Créer la directive finale
        return $reflection->newInstance($context, $this->interaction);
    }

    // src/Services/DirectiveHydratorService.php

    // src/Services/DirectiveHydratorService.php

    public function hydrateBlueprint(string $class): DirectiveBlueprintRecord
    {
        // Créer une instance sans constructeur pour accéder aux méthodes
        $reflection = new \ReflectionClass($class);
        $tempInstance = $reflection->newInstanceWithoutConstructor();

        // Appeler directement les méthodes de la classe (elles ne dépendent pas du contexte)
        $signature = $tempInstance->getSignature();
        $description = $tempInstance->getDescription();

        return new DirectiveBlueprintRecord(
            class: $class,
            signature: $signature,
            description: $description,
        );
    }

    public function hydrateForAliases(string $class): DirectiveInterface
    {
        $reflection = new \ReflectionClass($class);

        $tempContext = new DirectiveContext(
            laravelBootstrapper: $this->laravelBootstrapperContext,
            blueprint: new DirectiveBlueprintRecord($class, '', ''),
            aliases: new StringTypedCollection,
            shouldBootLaravel: false,
        );

        $tempInstance = $reflection->newInstance($tempContext, $this->interaction);

        $blueprint = $tempInstance->getBlueprint();
        $aliases = $tempInstance->getAliases();
        $shouldBootLaravel = $tempInstance->shouldBootLaravel();

        $context = new DirectiveContext(
            laravelBootstrapper: $this->laravelBootstrapperContext,
            blueprint: $blueprint,
            aliases: $aliases,
            shouldBootLaravel: $shouldBootLaravel,
        );

        return $reflection->newInstance($context, $this->interaction);
    }

    private function normalizeOptions(ParsedOptionCollection $options): ParameterCollection
    {
        $normalizedOptions = new ParameterCollection;

        foreach ($options as $option) {
            $value = $option->value;
            if ($value === 'true') {
                $value = true;
            } elseif ($value === 'false') {
                $value = false;
            }
            $normalizedOptions->add(new ParameterRecord($option->name, $value));
        }

        return $normalizedOptions;
    }
}
