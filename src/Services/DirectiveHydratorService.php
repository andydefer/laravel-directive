<?php

// src/Services/DirectiveHydratorService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\ParameterVOCollection;
use AndyDefer\Directive\Collections\ParsedArgumentCollection;
use AndyDefer\Directive\Collections\ParsedOptionCollection;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Enums\PrimitiveType;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;
use AndyDefer\Directive\ValueObjects\ParameterVO;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

class DirectiveHydratorService
{
    private DirectiveInteractionService $interaction;
    private PrimitiveTypeConverterService $typeConverter;

    public function __construct(
        private readonly LaravelBootstrapperContext $laravelBootstrapperContext,
        ?DirectiveInteractionService $interaction = null,
    ) {
        $this->interaction = $interaction ?? new DirectiveInteractionService(
            new RenderDispatcher,
            new InputDispatcher,
        );
        $this->typeConverter = new PrimitiveTypeConverterService();
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
        $context->setArguments($this->normalizeArguments($parsed->arguments));
        $context->setOptions($this->normalizeOptions($parsed->options));
        $context->setVariadicArguments($parsed->variadic_arguments);

        // Créer la directive finale
        return $reflection->newInstance($context, $this->interaction);
    }

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

    private function normalizeArguments(ParsedArgumentCollection $arguments): ParameterVOCollection
    {
        $normalizedArguments = new ParameterVOCollection;

        foreach ($arguments as $argument) {
            // Essayer de convertir la string en sa vraie valeur
            $detectedType = $this->detectTypeFromString($argument->value);
            $convertedValue = $this->typeConverter->convert($argument->value, $detectedType);

            $parameter = new ParameterVO(
                $argument->name,
                $convertedValue,
                $detectedType
            );
            $normalizedArguments->add($parameter);
        }

        return $normalizedArguments;
    }

    private function normalizeOptions(ParsedOptionCollection $options): ParameterVOCollection
    {
        $normalizedOptions = new ParameterVOCollection;

        foreach ($options as $option) {
            $value = $option->value;

            // Convertir les strings 'true'/'false' en booléens
            if ($value === 'true') {
                $value = true;
            } elseif ($value === 'false') {
                $value = false;
            }

            $parameter = new ParameterVO(
                $option->name,
                $value,
                $this->typeConverter->detectType($value)
            );
            $normalizedOptions->add($parameter);
        }

        return $normalizedOptions;
    }

    private function detectTypeFromString(string $value): PrimitiveType
    {
        $lowerValue = strtolower($value);

        if ($lowerValue === 'null') {
            return PrimitiveType::NULL;
        }

        if ($lowerValue === 'true' || $lowerValue === 'false') {
            return PrimitiveType::BOOL;
        }

        if (is_numeric($value)) {
            if (str_contains($value, '.') || str_contains($value, 'e')) {
                return PrimitiveType::FLOAT;
            }
            return PrimitiveType::INT;
        }

        return PrimitiveType::STRING;
    }
}
