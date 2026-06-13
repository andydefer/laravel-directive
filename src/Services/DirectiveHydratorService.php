<?php

// src/Services/DirectiveHydratorService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\ParameterVOCollection;
use AndyDefer\Directive\Collections\ParsedArgumentCollection;
use AndyDefer\Directive\Collections\ParsedOptionCollection;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;
use AndyDefer\Directive\ValueObjects\ParameterVO;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\PhpServices\Enums\PrimitiveType;
use AndyDefer\PhpServices\Services\PrimitiveTypeConverterService;
use Illuminate\Foundation\Application;

class DirectiveHydratorService
{
    private DirectiveInteractionService $interaction;
    private PrimitiveTypeConverterService $typeConverter;
    private ?Application $application;

    public function __construct(
        ?Application $application = null,
        ?DirectiveInteractionService $interaction = null,
        ?PrimitiveTypeConverterService $typeConverter = null,
    ) {
        $this->application = $application;
        $this->interaction = $interaction ?? new DirectiveInteractionService(
            new RenderDispatcher(),
            new InputDispatcher(),
        );
        $this->typeConverter = $typeConverter ?? new PrimitiveTypeConverterService();
    }

    public function hydrate(string $class, ParsedDirectiveRecord $parsed): DirectiveInterface
    {
        $reflection = new \ReflectionClass($class);

        $tempContext = new DirectiveContext(
            blueprint: new DirectiveBlueprintRecord($class, '', ''),
            aliases: new StringTypedCollection(),
            laravelApplication: $this->application,
        );

        $tempInstance = $reflection->newInstance($tempContext, $this->interaction);

        $blueprint = $tempInstance->getBlueprint();
        $aliases = $tempInstance->getAliases();

        $context = new DirectiveContext(
            blueprint: $blueprint,
            aliases: $aliases,
            laravelApplication: $this->application,
        );

        $context->setArguments($this->normalizeArguments($parsed->arguments));
        $context->setOptions($this->normalizeOptions($parsed->options));
        $context->setVariadicArguments($parsed->variadic_arguments);

        return $reflection->newInstance($context, $this->interaction);
    }

    public function hydrateBlueprint(string $class): DirectiveBlueprintRecord
    {
        $reflection = new \ReflectionClass($class);
        $tempInstance = $reflection->newInstanceWithoutConstructor();

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
            blueprint: new DirectiveBlueprintRecord($class, '', ''),
            aliases: new StringTypedCollection(),
            laravelApplication: $this->application,
        );

        $tempInstance = $reflection->newInstance($tempContext, $this->interaction);

        $blueprint = $tempInstance->getBlueprint();
        $aliases = $tempInstance->getAliases();

        $context = new DirectiveContext(
            blueprint: $blueprint,
            aliases: $aliases,
            laravelApplication: $this->application,
        );

        return $reflection->newInstance($context, $this->interaction);
    }

    private function normalizeArguments(ParsedArgumentCollection $arguments): ParameterVOCollection
    {
        $normalizedArguments = new ParameterVOCollection();

        foreach ($arguments as $argument) {
            $detectedType = $this->typeConverter->detectType($argument->value);
            $convertedValue = $this->typeConverter->convert($argument->value, $detectedType);

            $normalizedArguments->add(new ParameterVO(
                name: $argument->name,
                value: $convertedValue,
                type: $detectedType,
            ));
        }

        return $normalizedArguments;
    }

    private function normalizeOptions(ParsedOptionCollection $options): ParameterVOCollection
    {
        $normalizedOptions = new ParameterVOCollection();

        foreach ($options as $option) {
            $value = $option->value;

            if ($value === 'true') {
                $value = true;
            } elseif ($value === 'false') {
                $value = false;
            }

            $detectedType = $this->typeConverter->detectType($value);

            $normalizedOptions->add(new ParameterVO(
                name: $option->name,
                value: $value,
                type: $detectedType,
            ));
        }

        return $normalizedOptions;
    }
}
