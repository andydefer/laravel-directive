<?php

// src/Strategies/RequiredArgumentStrategy.php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Enums\ParameterType;
use AndyDefer\Directive\Enums\ParameterTypeOrder;
use AndyDefer\Directive\Records\ParsedParameterRecord;

final class RequiredArgumentStrategy implements ParameterParsingStrategy
{
    public function supports(string $parameter): bool
    {
        return ! str_ends_with($parameter, '?')
            && ! str_contains($parameter, '=')
            && ! str_starts_with($parameter, '--')
            && ! str_starts_with($parameter, '-')
            && ! str_ends_with($parameter, '*');
    }

    public function parse(string $parameter, array $context = []): ParsedParameterRecord
    {
        return new ParsedParameterRecord(
            name: $parameter,
            type: ParameterType::ARGUMENT,
            required: true,
            default: null,
        );
    }

    public function getTypeOrder(): ParameterTypeOrder
    {
        return ParameterTypeOrder::REQUIRED;
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
        return true;
    }
}
