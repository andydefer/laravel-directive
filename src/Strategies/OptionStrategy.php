<?php
// src/Strategies/OptionStrategy.php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Configs\DirectiveParserConfig;
use AndyDefer\Directive\Contracts\Configs\DirectiveParserConfigInterface;
use AndyDefer\Directive\Enums\ParameterTypeOrder;
use AndyDefer\Directive\Enums\ParameterType;
use AndyDefer\Directive\Records\ParsedParameterRecord;

final class OptionStrategy implements ParameterParsingStrategy
{
    private DirectiveParserConfigInterface $config;

    public function __construct(?DirectiveParserConfigInterface $config = null)
    {
        $this->config = $config ?? new DirectiveParserConfig();
    }

    public function supports(string $parameter): bool
    {
        return str_starts_with($parameter, $this->config->longOptionPrefix())
            || str_starts_with($parameter, $this->config->shortOptionPrefix());
    }

    public function parse(string $parameter, array $context = []): ParsedParameterRecord
    {
        $isLong = str_starts_with($parameter, $this->config->longOptionPrefix());
        $prefix = $isLong ? $this->config->longOptionPrefix() : $this->config->shortOptionPrefix();
        $cleaned = substr($parameter, strlen($prefix));

        if (str_contains($cleaned, $this->config->optionValueSeparator())) {
            $parts = explode($this->config->optionValueSeparator(), $cleaned, 2);

            return new ParsedParameterRecord(
                name: $parts[0],
                type: ParameterType::OPTION,
                required: false,
                default: $parts[1] === '' ? null : $parts[1],
            );
        }

        return new ParsedParameterRecord(
            name: $cleaned,
            type: ParameterType::OPTION,
            required: false,
            default: null,
        );
    }

    public function getTypeOrder(): ParameterTypeOrder
    {
        return ParameterTypeOrder::OPTION;
    }

    public function getTypeName(): string
    {
        return $this->getTypeOrder()->getLabel();
    }

    public function isOption(): bool
    {
        return true;
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
