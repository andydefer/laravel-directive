<?php
// src/Configs/DirectiveParserConfig.php

declare(strict_types=1);

namespace AndyDefer\Directive\Configs;

use AndyDefer\Directive\Contracts\Configs\DirectiveParserConfigInterface;

/**
 * Configuration for directive parsing behavior.
 *
 * Defines option prefixes, value representations, and parsing rules
 * for console command directive signatures.
 *
 * @author Andy Defer
 */
final class DirectiveParserConfig implements DirectiveParserConfigInterface
{
    public function longOptionPrefix(): string
    {
        return getenv('DIRECTIVE_LONG_OPTION_PREFIX') ?: '--';
    }

    public function shortOptionPrefix(): string
    {
        return getenv('DIRECTIVE_SHORT_OPTION_PREFIX') ?: '-';
    }

    public function trueValue(): string
    {
        return getenv('DIRECTIVE_TRUE_VALUE') ?: 'true';
    }

    public function falseValue(): string
    {
        return getenv('DIRECTIVE_FALSE_VALUE') ?: 'false';
    }

    public function emptyOptionAsTrue(): bool
    {
        $value = getenv('DIRECTIVE_EMPTY_OPTION_AS_TRUE');

        if ($value === null) {
            return true;
        }

        return $value === 'true' || $value === '1';
    }

    public function optionValueSeparator(): string
    {
        return getenv('DIRECTIVE_OPTION_VALUE_SEPARATOR') ?: '=';
    }

    public function optionalMarker(): string
    {
        return getenv('DIRECTIVE_OPTIONAL_MARKER') ?: '?';
    }

    public function variadicMarker(): string
    {
        return getenv('DIRECTIVE_VARIADIC_MARKER') ?: '*';
    }
}
