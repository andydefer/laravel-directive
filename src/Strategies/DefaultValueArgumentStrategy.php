<?php

// src/Strategies/DefaultValueArgumentStrategy.php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Enums\ParameterType;
use AndyDefer\Directive\Enums\ParameterTypeOrder;
use AndyDefer\Directive\Records\ParsedParameterRecord;

final class DefaultValueArgumentStrategy implements ParameterParsingStrategy
{
    public function supports(string $parameter): bool
    {
        return str_contains($parameter, '=')
            && ! str_starts_with($parameter, '--')
            && ! str_starts_with($parameter, '-');
    }

    public function parse(string $parameter, array $context = []): ParsedParameterRecord
    {
        $parts = explode('=', $parameter, 2);
        $name = $parts[0];
        $default = $parts[1];

        return new ParsedParameterRecord(
            name: $name,
            type: ParameterType::ARGUMENT,
            required: false,
            default: $default,
        );
    }

    public function getTypeOrder(): ParameterTypeOrder
    {
        return ParameterTypeOrder::DEFAULT;
    }

    public function getTypeName(): string
    {
        return $this->getTypeOrder()->getLabel();
    }

    public function isOption(): bool
    {
        return false;
    }

    public function isVariadic(): bool
    {
        return false;
    }

    public function isRequired(): bool
    {
        return false;
    }
}
