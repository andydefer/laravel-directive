<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Config;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Configuration for directive parsing behavior.
 *
 * Defines option prefixes, value representations, and parsing rules
 * for console command directive signatures.
 */
final class DirectiveParserConfig extends AbstractRecord
{
    public function __construct(
        /** Prefix for long options (e.g., --verbose) */
        public readonly string $longOptionPrefix = '--',

        /** Prefix for short options (e.g., -v) */
        public readonly string $shortOptionPrefix = '-',

        /** String representation of true value */
        public readonly string $trueValue = 'true',

        /** String representation of false value */
        public readonly string $falseValue = 'false',

        /** Whether empty option values should be treated as true */
        public readonly bool $emptyOptionAsTrue = true,

        /** Character used to separate option name from value in long options */
        public readonly string $optionValueSeparator = '=',

        /** Character used to mark optional parameters in signature */
        public readonly string $optionalMarker = '?',
    ) {}
}
