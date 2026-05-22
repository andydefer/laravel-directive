<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Config\DirectiveParserConfig;
use AndyDefer\Directive\Enums\ParameterType;
use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;
use AndyDefer\Directive\Records\ParsedParameterRecord;
use AndyDefer\Directive\Records\ParsedResultRecord;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use InvalidArgumentException;

/**
 * Parses console command signatures and extracts arguments and options.
 *
 * This service handles the parsing of directive signatures similar to Laravel
 * Artisan commands, extracting named arguments and options (both long --option
 * and short -o formats) into structured collections.
 */
class DirectiveParserService
{
    public function __construct(
        private readonly DirectiveParserConfig $config = new DirectiveParserConfig(),
    ) {}

    /**
     * Parse a directive signature with its arguments.
     *
     * @param string                $signature The directive signature (e.g., "user:list {--active} {name}")
     * @param StringTypedCollection $argv      Command-line arguments to parse
     *
     * @return ParsedDirectiveRecord Structured record with parsed arguments and options
     *
     * @throws InvalidArgumentException If the signature format is invalid
     */
    public function parse(string $signature, StringTypedCollection $argv): ParsedDirectiveRecord
    {
        $arguments = new StringTypedCollection();
        $options = new StringTypedCollection();
        $parameterNames = $this->extractParameterNames($signature);

        $argIndex = 0;
        foreach ($argv as $arg) {
            if ($this->isLongOption($arg)) {
                $this->parseLongOption($arg, $options);
            } elseif ($this->isShortOption($arg)) {
                $this->parseShortOption($arg, $options);
            } else {
                $this->parseArgument($arg, $arguments, $parameterNames, $argIndex);
            }
        }

        return new ParsedDirectiveRecord($arguments, $options);
    }

    /**
     * Extract help information from a directive signature.
     *
     * @param string $signature The directive signature
     *
     * @return TypedCollection<ParsedParameterRecord> Collection of parameter help data
     */
    public function extractHelp(string $signature): TypedCollection
    {
        $params = new TypedCollection(ParsedParameterRecord::class);
        $matches = $this->findSignatureParameters($signature);

        foreach ($matches as $param) {
            if ($this->isLongOption($param)) {
                $params->add($this->extractOptionHelp($param));
            } else {
                $params->add($this->extractArgumentHelp($param));
            }
        }

        return $params;
    }

    /**
     * Convert a parsed directive record to a ParsedResultRecord.
     *
     * @param ParsedDirectiveRecord $parsed The parsed directive record
     *
     * @return ParsedResultRecord Record containing typed parameter collections
     */
    public function toResult(ParsedDirectiveRecord $parsed): ParsedResultRecord
    {
        return new ParsedResultRecord(
            arguments: $this->argumentsToCollection($parsed->arguments),
            options: $this->optionsToCollection($parsed->options),
        );
    }

    /**
     * Convert a parsed directive record to JSON string.
     *
     * @param ParsedDirectiveRecord $parsed The parsed directive record
     *
     * @return string JSON representation of the parsed data
     */
    public function toJson(ParsedDirectiveRecord $parsed): string
    {
        $result = $this->toResult($parsed);

        return json_encode([
            'arguments' => $result->arguments->toAssociativeArray(),
            'options' => $result->options->toAssociativeArray(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // ==================== Private Methods ====================

    /**
     * Extract parameter names from signature braces.
     *
     * @param string $signature Directive signature
     *
     * @return StringTypedCollection Cleaned parameter names
     */
    private function extractParameterNames(string $signature): StringTypedCollection
    {
        $matches = $this->findSignatureParameters($signature);
        $cleanedNames = new StringTypedCollection();

        foreach ($matches as $param) {
            $cleanedNames->add($this->cleanParameterName($param));
        }

        return $cleanedNames;
    }

    /**
     * Find all parameters within curly braces in the signature.
     *
     * @param string $signature Directive signature
     *
     * @return StringTypedCollection Raw parameter strings
     */
    private function findSignatureParameters(string $signature): StringTypedCollection
    {
        preg_match_all('/\{([^}]+)\}/', $signature, $matches);
        $result = new StringTypedCollection();

        foreach ($matches[1] as $param) {
            $result->add($param);
        }

        return $result;
    }

    /**
     * Clean a parameter name by removing option prefixes and optional markers.
     *
     * @param string $param Raw parameter string
     *
     * @return string Cleaned parameter name
     */
    private function cleanParameterName(string $param): string
    {
        // Remove leading hyphens for options
        $param = ltrim($param, $this->config->longOptionPrefix);
        $param = ltrim($param, $this->config->shortOptionPrefix);

        // Remove =value part if present
        if (str_contains($param, $this->config->optionValueSeparator)) {
            $param = explode($this->config->optionValueSeparator, $param)[0];
        }

        // Remove trailing ? for optional arguments
        if (str_ends_with($param, $this->config->optionalMarker)) {
            $param = substr($param, 0, -1);
        }

        return $param;
    }

    /**
     * Check if an argument is a long option (--option).
     *
     * @param string $arg Argument to check
     *
     * @return bool True if it's a long option
     */
    private function isLongOption(string $arg): bool
    {
        return str_starts_with($arg, $this->config->longOptionPrefix);
    }

    /**
     * Check if an argument is a short option (-o).
     *
     * @param string $arg Argument to check
     *
     * @return bool True if it's a short option
     */
    private function isShortOption(string $arg): bool
    {
        return str_starts_with($arg, $this->config->shortOptionPrefix)
            && !str_starts_with($arg, $this->config->longOptionPrefix);
    }

    /**
     * Parse a long option (--name=value or --name).
     *
     * @param string                $arg     The option string
     * @param StringTypedCollection $options Collection to store parsed options
     */
    private function parseLongOption(string $arg, StringTypedCollection $options): void
    {
        $parts = explode(
            $this->config->optionValueSeparator,
            substr($arg, strlen($this->config->longOptionPrefix)),
            2
        );

        $options->add($parts[0]);
        $options->add($parts[1] ?? $this->config->trueValue);
    }

    /**
     * Parse a short option (-f).
     *
     * @param string                $arg     The option string
     * @param StringTypedCollection $options Collection to store parsed options
     */
    private function parseShortOption(string $arg, StringTypedCollection $options): void
    {
        $options->add(substr($arg, strlen($this->config->shortOptionPrefix)));
        $options->add($this->config->trueValue);
    }

    /**
     * Parse a positional argument.
     *
     * @param string                $arg            Argument value
     * @param StringTypedCollection $arguments      Collection for argument values
     * @param StringTypedCollection $parameterNames Expected parameter names
     * @param int                   $argIndex       Current argument index
     */
    private function parseArgument(
        string $arg,
        StringTypedCollection $arguments,
        StringTypedCollection $parameterNames,
        int &$argIndex
    ): void {
        $parameterNamesArray = $parameterNames->toArray();

        if (isset($parameterNamesArray[$argIndex])) {
            $arguments->add($arg);
            $arguments->add($parameterNamesArray[$argIndex]);
        } else {
            $arguments->add($arg);
        }

        $argIndex++;
    }

    /**
     * Extract help information for an option parameter.
     *
     * @param string $param Option parameter string
     *
     * @return ParsedParameterRecord Help data for the option
     */
    private function extractOptionHelp(string $param): ParsedParameterRecord
    {
        $cleanParam = substr($param, strlen($this->config->longOptionPrefix));

        if (str_contains($cleanParam, $this->config->optionValueSeparator)) {
            $parts = explode($this->config->optionValueSeparator, $cleanParam, 2);

            return new ParsedParameterRecord(
                name: $parts[0],
                type: ParameterType::OPTION,
                required: false,
                default: $parts[1] === '' ? null : $parts[1],
            );
        }

        return new ParsedParameterRecord(
            name: $cleanParam,
            type: ParameterType::OPTION,
            required: false,
            default: null,
        );
    }

    /**
     * Extract help information for an argument parameter.
     *
     * @param string $param Argument parameter string
     *
     * @return ParsedParameterRecord Help data for the argument
     */
    private function extractArgumentHelp(string $param): ParsedParameterRecord
    {
        $isOptional = str_ends_with($param, $this->config->optionalMarker);

        return new ParsedParameterRecord(
            name: $isOptional ? substr($param, 0, -1) : $param,
            type: ParameterType::ARGUMENT,
            required: !$isOptional,
            default: null,
        );
    }

    /**
     * Convert arguments collection to a ParameterCollection.
     *
     * @param StringTypedCollection $arguments Arguments in flat format [value1, name1, value2, name2]
     *
     * @return ParameterCollection Collection of ParameterRecord
     */
    private function argumentsToCollection(StringTypedCollection $arguments): ParameterCollection
    {
        $result = new ParameterCollection();
        $items = $arguments->toArray();

        for ($i = 0; $i < $arguments->count(); $i += 2) {
            if (isset($items[$i + 1])) {
                $result->add(new ParameterRecord(
                    name: $items[$i + 1],
                    value: $items[$i],
                ));
            }
        }

        return $result;
    }

    /**
     * Convert options collection to a ParameterCollection.
     *
     * @param StringTypedCollection $options Options in flat format [name1, value1, name2, value2]
     *
     * @return ParameterCollection Collection of ParameterRecord
     */
    private function optionsToCollection(StringTypedCollection $options): ParameterCollection
    {
        $result = new ParameterCollection();
        $items = $options->toArray();

        for ($i = 0; $i < $options->count(); $i += 2) {
            if (!isset($items[$i])) {
                continue;
            }

            $value = $items[$i + 1] ?? $this->config->trueValue;
            $result->add(new ParameterRecord(
                name: $items[$i],
                value: $this->normalizeOptionValue($value),
            ));
        }

        return $result;
    }

    /**
     * Normalize option value to proper boolean or string type.
     *
     * @param string $value Raw option value
     *
     * @return bool|string Normalized value
     */
    private function normalizeOptionValue(string $value): bool|string
    {
        return match ($value) {
            $this->config->trueValue => true,
            $this->config->falseValue => false,
            '' => $this->config->emptyOptionAsTrue,
            default => $value,
        };
    }
}
