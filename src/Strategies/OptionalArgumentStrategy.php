<?php

// src/Strategies/OptionalArgumentStrategy.php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Enums\ParameterType;
use AndyDefer\Directive\Enums\ParameterTypeOrder;
use AndyDefer\Directive\Records\ParsedParameterRecord;

final class OptionalArgumentStrategy implements ParameterParsingStrategy
{
    public function supports(string $parameter): bool
    {
        return str_ends_with($parameter, '?')
            && ! str_contains($parameter, '=')
            && ! str_starts_with($parameter, '--')
            && ! str_starts_with($parameter, '-');
    }

    public function parse(string $parameter, array $context = []): ParsedParameterRecord
    {
        $name = rtrim($parameter, '?');

        return new ParsedParameterRecord(
            name: $name,
            type: ParameterType::ARGUMENT,
            required: false,
            default: null,
        );
    }

    public function getTypeOrder(): ParameterTypeOrder
    {
        return ParameterTypeOrder::OPTIONAL;
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
