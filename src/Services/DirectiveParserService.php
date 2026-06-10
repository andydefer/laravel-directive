<?php

// src/Services/DirectiveParserService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\ParsedArgumentCollection;
use AndyDefer\Directive\Collections\ParsedOptionCollection;
use AndyDefer\Directive\Collections\ParsedParameterCollection;
use AndyDefer\Directive\Configs\DirectiveParserConfig;
use AndyDefer\Directive\Contexts\ParameterParserContext;
use AndyDefer\Directive\Contracts\Configs\DirectiveParserConfigInterface;
use AndyDefer\Directive\Records\ArgumentSplitResultRecord;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;
use AndyDefer\Directive\Records\ParsedResultRecord;
use AndyDefer\Directive\Strategies\DefaultValueArgumentStrategy;
use AndyDefer\Directive\Strategies\OptionalArgumentStrategy;
use AndyDefer\Directive\Strategies\OptionStrategy;
use AndyDefer\Directive\Strategies\RequiredArgumentStrategy;
use AndyDefer\Directive\Strategies\VariadicArgumentStrategy;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

class DirectiveParserService
{
    private ParameterParserContext $parserContext;

    private ParameterOrderValidatorService $orderValidator;

    private ParameterExtractorService $extractor;

    private OptionParserService $optionParser;

    private ArgumentApplierService $argumentApplier;

    private ArgumentSplitterService $argumentSplitter;

    public function __construct(?DirectiveParserConfigInterface $config = null)
    {
        $config = $config ?? new DirectiveParserConfig;
        $this->parserContext = $this->buildParserContext();
        $this->orderValidator = new ParameterOrderValidatorService($this->parserContext);
        $this->extractor = new ParameterExtractorService($this->parserContext);
        $this->optionParser = new OptionParserService($config);
        $this->argumentApplier = new ArgumentApplierService;
        $this->argumentSplitter = new ArgumentSplitterService($config);
    }

    private function buildParserContext(): ParameterParserContext
    {
        $context = new ParameterParserContext;
        $context->addStrategy(new RequiredArgumentStrategy);
        $context->addStrategy(new DefaultValueArgumentStrategy);
        $context->addStrategy(new OptionalArgumentStrategy);
        $context->addStrategy(new VariadicArgumentStrategy);
        $context->addStrategy(new OptionStrategy);

        return $context;
    }

    public function parse(string $signature, StringTypedCollection $argv): ParsedDirectiveRecord
    {
        $arguments = new ParsedArgumentCollection;
        $options = new ParsedOptionCollection;

        // Séparer les arguments normaux des variadiques
        $splitResult = $this->argumentSplitter->split($argv);
        $regularArgs = $splitResult->regular->toArray();
        $variadicItems = $splitResult->variadic->toArray();

        // Valider la signature
        $this->orderValidator->validate([], $signature);
        $parameters = $this->extractor->extract($signature);

        // Séparer les options des arguments normaux
        $providedArguments = [];
        $optionArguments = new StringTypedCollection;

        foreach ($regularArgs as $argument) {
            if ($this->optionParser->isOption($argument)) {
                $optionArguments->add($argument);
            } else {
                $providedArguments[] = $argument;
            }
        }

        // Parser les options
        if ($optionArguments->isNotEmpty()) {
            $this->optionParser->parseOptions($optionArguments, $options);
        }

        // Appliquer les arguments (sans variadic)
        $this->argumentApplier->apply($parameters, $providedArguments, $arguments, $variadicItems);

        // Créer la collection des arguments variadiques
        $variadicArgumentsCollection = new StringTypedCollection;
        foreach ($variadicItems as $item) {
            $variadicArgumentsCollection->add($item);
        }

        return new ParsedDirectiveRecord($arguments, $options, $variadicArgumentsCollection);
    }

    public function extractHelp(string $signature): ParsedParameterCollection
    {
        $parameters = new ParsedParameterCollection;
        $matches = $this->extractParameters($signature);

        foreach ($matches as $parameter) {
            $parameters->add($this->parserContext->parse($parameter));
        }

        return $parameters;
    }

    public function toResult(ParsedDirectiveRecord $parsed): ParsedResultRecord
    {
        return new ParsedResultRecord(
            arguments: $parsed->arguments,
            options: $parsed->options,
            variadic_arguments: $parsed->variadic_arguments,
        );
    }

    public function toJson(ParsedDirectiveRecord $parsed): string
    {
        return json_encode([
            'arguments' => $parsed->arguments->toAssociativeArray(),
            'options' => $parsed->options->toAssociativeArray(),
            'variadic_arguments' => $parsed->variadic_arguments->toArray(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function extractParameters(string $signature): StringTypedCollection
    {
        preg_match_all('/\{([^}]+)\}/', $signature, $matches);
        $result = new StringTypedCollection;

        foreach ($matches[1] as $parameter) {
            $result->add($parameter);
        }

        return $result;
    }
}
