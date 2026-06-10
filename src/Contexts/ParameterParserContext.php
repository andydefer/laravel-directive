<?php

// src/Contexts/ParameterParserContext.php (mis à jour)

declare(strict_types=1);

namespace AndyDefer\Directive\Contexts;

use AndyDefer\Directive\Enums\ParameterTypeOrder;
use AndyDefer\Directive\Records\ParsedParameterRecord;
use AndyDefer\Directive\Strategies\ParameterParsingStrategy;
use InvalidArgumentException;

final class ParameterParserContext
{
    private array $strategies = [];

    public function addStrategy(ParameterParsingStrategy $strategy): self
    {
        $this->strategies[] = $strategy;

        return $this;
    }

    public function getStrategies(): array
    {
        return $this->strategies;
    }

    public function parse(string $parameter): ParsedParameterRecord
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($parameter)) {
                return $strategy->parse($parameter);
            }
        }

        throw new InvalidArgumentException(sprintf('No strategy found for parameter: %s', $parameter));
    }

    public function getTypeOrderForParameter(string $parameter): ParameterTypeOrder
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($parameter)) {
                return $strategy->getTypeOrder();
            }
        }

        throw new InvalidArgumentException(sprintf('No strategy found for parameter: %s', $parameter));
    }

    public function getTypeForParameter(string $parameter): int
    {
        return $this->getTypeOrderForParameter($parameter)->value;
    }

    public function isOption(string $parameter): bool
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($parameter)) {
                return $strategy->isOption();
            }
        }

        return false;
    }

    public function isVariadic(string $parameter): bool
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($parameter)) {
                return $strategy->isVariadic();
            }
        }

        return false;
    }

    public function reset(): void
    {
        $this->strategies = [];
    }
}
