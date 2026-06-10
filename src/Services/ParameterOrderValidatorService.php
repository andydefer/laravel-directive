<?php
// src/Services/ParameterOrderValidatorService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contexts\ParameterParserContext;
use AndyDefer\Directive\Enums\ParameterTypeOrder;
use InvalidArgumentException;

final class ParameterOrderValidatorService
{
    public function __construct(
        private readonly ParameterParserContext $parserContext
    ) {}

    public function validate(array $parameters, string $signature): void
    {

        $lastType = null;
        $matches = $this->extractParameters($signature);


        foreach ($matches as $parameter) {
            $currentType = $this->parserContext->getTypeOrderForParameter($parameter);


            if ($lastType !== null && $currentType->value < $lastType->value) {
                $expected = strtolower($lastType->getLabel());
                $actual = strtolower($currentType->getLabel());

                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid signature format: %s must come before %s. Problem with: {%s}',
                        $actual,
                        $expected,
                        $parameter
                    )
                );
            }

            $lastType = $currentType;
        }
    }

    private function extractParameters(string $signature): array
    {
        preg_match_all('/\{([^}]+)\}/', $signature, $matches);
        return $matches[1] ?? [];
    }
}
