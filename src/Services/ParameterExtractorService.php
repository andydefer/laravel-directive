<?php

// src/Services/ParameterExtractorService.php (mis à jour)

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\ExtractedParameterCollection;
use AndyDefer\Directive\Contexts\ParameterParserContext;
use AndyDefer\Directive\Records\ExtractedParameterRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

class ParameterExtractorService
{
    public function __construct(
        private readonly ParameterParserContext $parserContext
    ) {}

    public function extract(string $signature): ExtractedParameterCollection
    {
        $matches = $this->findSignatureParameters($signature);
        $collection = new ExtractedParameterCollection;

        foreach ($matches as $parameter) {
            $parsed = $this->parserContext->parse($parameter);
            $isOption = $this->parserContext->isOption($parameter);
            $isVariadic = $this->parserContext->isVariadic($parameter);

            $collection->add(new ExtractedParameterRecord(
                name: $parsed->name,
                isOption: $isOption,
                required: $parsed->required,
                default: $parsed->default,
                raw: $parameter,
                isVariadic: $isVariadic,
            ));
        }

        return $collection;
    }

    private function findSignatureParameters(string $signature): StringTypedCollection
    {
        preg_match_all('/\{([^}]+)\}/', $signature, $matches);
        $result = new StringTypedCollection;

        foreach ($matches[1] as $parameter) {
            $result->add($parameter);
        }

        return $result;
    }
}
