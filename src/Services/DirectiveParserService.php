<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Collections\ParsedParameterCollection;
use AndyDefer\Directive\Config\DirectiveParserConfig;
use AndyDefer\Directive\Enums\ParameterType;
use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;
use AndyDefer\Directive\Records\ParsedParameterRecord;
use AndyDefer\Directive\Records\ParsedResultRecord;
use AndyDefer\DomainStructures\Collections\Utility\ScalarTypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use InvalidArgumentException;

/**
 * Parses console command signatures and extracts arguments and options.
 *
 * This service handles the parsing of directive signatures following a specific
 * parameter order: required arguments, arguments with defaults, optional arguments,
 * and finally options.
 */
class DirectiveParserService
{
    public function __construct(
        private readonly DirectiveParserConfig $config = new DirectiveParserConfig(),
    ) {}

    /**
     * Parse a directive signature with its arguments.
     *
     * @param string $signature The directive signature containing parameters in curly braces
     * @param StringTypedCollection<string> $argv The raw arguments passed to the directive
     *
     * @return ParsedDirectiveRecord The parsed directive containing separated arguments and options
     *
     * @throws InvalidArgumentException If the signature format is invalid or argument count mismatches
     */
    public function parse(string $signature, StringTypedCollection $argv): ParsedDirectiveRecord
    {
        $arguments = new ScalarTypedCollection();
        $options = new ScalarTypedCollection();

        $parameters = $this->extractAndValidateParameters($signature);

        $providedArguments = [];

        foreach ($argv as $argument) {
            if ($this->isLongOption($argument)) {
                $this->parseLongOption($argument, $options);
            } elseif ($this->isShortOption($argument)) {
                $this->parseShortOption($argument, $options);
            } else {
                $providedArguments[] = $argument;
            }
        }

        $this->applyArgumentDefaultsAndValidation($parameters, $providedArguments, $arguments);

        return new ParsedDirectiveRecord($arguments, $options);
    }

    /**
     * Extract help information from a directive signature.
     *
     * @param string $signature The directive signature containing parameters in curly braces
     *
     * @return ParsedParameterCollection<ParsedParameterRecord> Collection of parsed parameters for help display
     */
    public function extractHelp(string $signature): ParsedParameterCollection
    {
        $parameters = new ParsedParameterCollection();
        $matches = $this->findSignatureParameters($signature);

        foreach ($matches as $parameter) {
            if ($this->isLongOption($parameter) || $this->isShortOption($parameter)) {
                $parameters->add($this->extractOptionHelp($parameter));
            } else {
                $parameters->add($this->extractArgumentHelp($parameter));
            }
        }

        return $parameters;
    }

    /**
     * Convert a parsed directive record to a result record.
     *
     * @param ParsedDirectiveRecord $parsed The parsed directive record
     *
     * @return ParsedResultRecord The result record with proper collections
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
     * @return string JSON representation of the parsed directive
     */
    public function toJson(ParsedDirectiveRecord $parsed): string
    {
        $result = $this->toResult($parsed);

        return json_encode([
            'arguments' => $result->arguments->toAssociativeArray(),
            'options' => $result->options->toAssociativeArray(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Extract and validate parameters order.
     *
     * Enforced order:
     * 1. Required arguments {name}
     * 2. Arguments with default values {role=user}
     * 3. Optional arguments without default values {count?}
     * 4. Options {--force} {-v}
     *
     * @param string $signature The directive signature
     *
     * @return array<array{name: string, isOption: bool, required: bool, default: string|null, raw: string, type: string}>
     *
     * @throws InvalidArgumentException If parameter order is invalid
     */
    private function extractAndValidateParameters(string $signature): array
    {
        $matches = $this->findSignatureParameters($signature);
        $parameters = [];

        $foundRequired = false;
        $foundDefault = false;
        $foundOptional = false;
        $foundOption = false;

        foreach ($matches as $parameter) {
            $isOption = $this->isLongOption($parameter) || $this->isShortOption($parameter);
            $name = $this->cleanParameterName($parameter);
            $default = null;
            $required = true;
            $type = 'argument';

            if (!$isOption) {
                if (preg_match('/^([^=]+)=(.+)$/', $parameter, $matches)) {
                    $name = $matches[1];
                    $default = $matches[2];
                    $required = false;
                    $type = 'argument_with_default';
                } elseif (str_ends_with($parameter, $this->config->optionalMarker)) {
                    $name = rtrim($name, $this->config->optionalMarker);
                    $required = false;
                    $type = 'argument_optional';
                } else {
                    $type = 'argument_required';
                }
            } else {
                $type = 'option';
                $required = false;
            }

            $this->validateParameterOrder($type, $parameter, $foundRequired, $foundDefault, $foundOptional, $foundOption);

            $this->updateOrderFlags($type, $foundRequired, $foundDefault, $foundOptional, $foundOption);

            $parameters[] = [
                'name' => $name,
                'isOption' => $isOption,
                'required' => $required,
                'default' => $default,
                'raw' => $parameter,
                'type' => $type,
            ];
        }

        return $parameters;
    }

    /**
     * Validate parameter order based on type.
     *
     * @throws InvalidArgumentException If order is invalid
     */
    private function validateParameterOrder(
        string $type,
        string $parameter,
        bool $foundRequired,
        bool $foundDefault,
        bool $foundOptional,
        bool $foundOption,
    ): void {
        if ($type === 'argument_required') {
            if ($foundDefault || $foundOptional || $foundOption) {
                throw new InvalidArgumentException(
                    'Invalid signature format: Required arguments must come before arguments with default values, optional arguments, and options. ' .
                        "Problem with: {{$parameter}}"
                );
            }
        } elseif ($type === 'argument_with_default') {
            if ($foundOptional || $foundOption) {
                throw new InvalidArgumentException(
                    'Invalid signature format: Arguments with default values must come before optional arguments and options. ' .
                        "Problem with: {{$parameter}}"
                );
            }
        } elseif ($type === 'argument_optional') {
            if ($foundOption) {
                throw new InvalidArgumentException(
                    'Invalid signature format: Optional arguments must come before options. ' .
                        "Problem with: {{$parameter}}"
                );
            }
        }
    }

    /**
     * Update order tracking flags after validating a parameter.
     */
    private function updateOrderFlags(
        string $type,
        bool &$foundRequired,
        bool &$foundDefault,
        bool &$foundOptional,
        bool &$foundOption,
    ): void {
        if ($type === 'argument_required') {
            $foundRequired = true;
        } elseif ($type === 'argument_with_default') {
            $foundDefault = true;
        } elseif ($type === 'argument_optional') {
            $foundOptional = true;
        } elseif ($type === 'option') {
            $foundOption = true;
        }
    }

    /**
     * Apply default values and validate required arguments.
     *
     * @param array<array{name: string, isOption: bool, required: bool, default: string|null}> $parameters
     * @param array<string> $providedArguments
     * @param ScalarTypedCollection $arguments
     *
     * @throws InvalidArgumentException If argument count mismatches
     */
    private function applyArgumentDefaultsAndValidation(
        array $parameters,
        array $providedArguments,
        ScalarTypedCollection $arguments,
    ): void {
        $argumentParameters = array_values(array_filter($parameters, fn($parameter) => !$parameter['isOption']));

        $providedIndex = 0;
        $totalProvided = count($providedArguments);

        foreach ($argumentParameters as $index => $parameter) {
            $value = null;

            if ($providedIndex < $totalProvided) {
                $value = $providedArguments[$providedIndex];
                $providedIndex++;
            } elseif ($parameter['default'] !== null) {
                $value = $parameter['default'];
            } elseif ($parameter['required']) {
                throw new InvalidArgumentException(
                    sprintf('Not enough arguments (missing: "%s")', $parameter['name'])
                );
            }

            if ($value !== null) {
                $arguments->add((string) $value);
                $arguments->add($parameter['name']);
            }
        }

        if ($providedIndex < $totalProvided) {
            throw new InvalidArgumentException('Too many arguments provided');
        }
    }

    /**
     * Find all parameters in the signature.
     *
     * @return StringTypedCollection<string>
     */
    private function findSignatureParameters(string $signature): StringTypedCollection
    {
        preg_match_all('/\{([^}]+)\}/', $signature, $matches);
        $result = new StringTypedCollection();

        foreach ($matches[1] as $parameter) {
            $result->add($parameter);
        }

        return $result;
    }

    /**
     * Clean parameter name by removing syntax markers.
     */
    private function cleanParameterName(string $parameter): string
    {
        $cleaned = ltrim($parameter, $this->config->longOptionPrefix);
        $cleaned = ltrim($cleaned, $this->config->shortOptionPrefix);

        if (str_contains($cleaned, $this->config->optionValueSeparator)) {
            $cleaned = explode($this->config->optionValueSeparator, $cleaned)[0];
        }

        if (str_contains($cleaned, '=')) {
            $cleaned = explode('=', $cleaned)[0];
        }

        if (str_ends_with($cleaned, $this->config->optionalMarker)) {
            $cleaned = substr($cleaned, 0, -1);
        }

        return $cleaned;
    }

    /**
     * Check if an argument is a long option.
     */
    private function isLongOption(string $argument): bool
    {
        return str_starts_with($argument, $this->config->longOptionPrefix);
    }

    /**
     * Check if an argument is a short option.
     */
    private function isShortOption(string $argument): bool
    {
        return str_starts_with($argument, $this->config->shortOptionPrefix)
            && !str_starts_with($argument, $this->config->longOptionPrefix)
            && strlen($argument) > 1;
    }

    /**
     * Parse a long option and add it to the options collection.
     */
    private function parseLongOption(string $argument, ScalarTypedCollection $options): void
    {
        $parts = explode(
            $this->config->optionValueSeparator,
            substr($argument, strlen($this->config->longOptionPrefix)),
            2
        );

        $options->add($parts[0]);
        $options->add($parts[1] ?? $this->config->trueValue);
    }

    /**
     * Parse a short option and add it to the options collection.
     */
    private function parseShortOption(string $argument, ScalarTypedCollection $options): void
    {
        $option = substr($argument, strlen($this->config->shortOptionPrefix));

        if (strlen($option) > 1) {
            $characters = str_split($option);
            foreach ($characters as $character) {
                $options->add($character);
                $options->add($this->config->trueValue);
            }
        } else {
            $options->add($option);
            $options->add($this->config->trueValue);
        }
    }

    /**
     * Extract help information for an option parameter.
     */
    private function extractOptionHelp(string $parameter): ParsedParameterRecord
    {
        $isLong = $this->isLongOption($parameter);
        $prefix = $isLong ? $this->config->longOptionPrefix : $this->config->shortOptionPrefix;
        $cleanedParameter = substr($parameter, strlen($prefix));

        if (str_contains($cleanedParameter, $this->config->optionValueSeparator)) {
            $parts = explode($this->config->optionValueSeparator, $cleanedParameter, 2);

            return new ParsedParameterRecord(
                name: $parts[0],
                type: ParameterType::OPTION,
                required: false,
                default: $parts[1] === '' ? null : $parts[1],
            );
        }

        return new ParsedParameterRecord(
            name: $cleanedParameter,
            type: ParameterType::OPTION,
            required: false,
            default: null,
        );
    }

    /**
     * Extract help information for an argument parameter.
     */
    private function extractArgumentHelp(string $parameter): ParsedParameterRecord
    {
        $default = null;
        $name = $parameter;

        if (preg_match('/^([^=]+)=(.+)$/', $parameter, $matches)) {
            $name = $matches[1];
            $default = $matches[2];
        }

        $isOptional = str_ends_with($name, $this->config->optionalMarker) || $default !== null;

        if (str_ends_with($name, $this->config->optionalMarker)) {
            $name = substr($name, 0, -1);
        }

        return new ParsedParameterRecord(
            name: $name,
            type: ParameterType::ARGUMENT,
            required: !$isOptional,
            default: $default,
        );
    }

    /**
     * Convert a scalar collection to a parameter collection for arguments.
     */
    private function argumentsToCollection(ScalarTypedCollection $arguments): ParameterCollection
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
     * Convert a scalar collection to a parameter collection for options.
     */
    private function optionsToCollection(ScalarTypedCollection $options): ParameterCollection
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
     * Normalize option value to boolean or string.
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
