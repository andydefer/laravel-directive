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
 */
class DirectiveParserService
{
    public function __construct(
        private readonly DirectiveParserConfig $config = new DirectiveParserConfig,
    ) {}

    /**
     * Parse a directive signature with its arguments.
     */
    public function parse(string $signature, StringTypedCollection $argv): ParsedDirectiveRecord
    {
        $arguments = new ScalarTypedCollection;
        $options = new ScalarTypedCollection;

        // Extraire et valider les paramètres
        $parameters = $this->extractAndValidateParameters($signature);

        $providedArgs = [];

        // Séparer les options des arguments
        foreach ($argv as $arg) {
            if ($this->isLongOption($arg)) {
                $this->parseLongOption($arg, $options);
            } elseif ($this->isShortOption($arg)) {
                $this->parseShortOption($arg, $options);
            } else {
                $providedArgs[] = $arg;
            }
        }

        // Appliquer les valeurs par défaut et valider les arguments requis
        $this->applyArgumentDefaultsAndValidation($parameters, $providedArgs, $arguments);

        return new ParsedDirectiveRecord($arguments, $options);
    }

    /**
     * Extract and validate parameters order
     * Ordre imposé:
     * 1. Arguments requis {name}
     * 2. Arguments avec valeur par défaut {role=user}
     * 3. Arguments optionnels sans valeur par défaut {count?}
     * 4. Options {--force} {-v}
     */
    private function extractAndValidateParameters(string $signature): array
    {
        $matches = $this->findSignatureParameters($signature);
        $parameters = [];

        $foundRequired = false;
        $foundDefault = false;
        $foundOptional = false;
        $foundOption = false;

        foreach ($matches as $param) {
            $isOption = $this->isLongOption($param) || $this->isShortOption($param);
            $name = $this->cleanParameterName($param);
            $default = null;
            $required = true;
            $type = 'argument';

            if (! $isOption) {
                // Vérifier si c'est un argument avec valeur par défaut
                if (preg_match('/^([^=]+)=(.+)$/', $param, $matches)) {
                    $name = $matches[1];
                    $default = $matches[2];
                    $required = false;
                    $type = 'argument_with_default';
                }
                // Vérifier si l'argument est optionnel
                elseif (str_ends_with($param, $this->config->optionalMarker)) {
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

            // Valider l'ordre
            if ($type === 'argument_required') {
                if ($foundDefault || $foundOptional || $foundOption) {
                    throw new InvalidArgumentException(
                        'Invalid signature format: Required arguments must come before arguments with default values, optional arguments, and options. ' .
                            "Problem with: {{$param}}"
                    );
                }
                $foundRequired = true;
            } elseif ($type === 'argument_with_default') {
                if ($foundOptional || $foundOption) {
                    throw new InvalidArgumentException(
                        'Invalid signature format: Arguments with default values must come before optional arguments and options. ' .
                            "Problem with: {{$param}}"
                    );
                }
                $foundDefault = true;
            } elseif ($type === 'argument_optional') {
                if ($foundOption) {
                    throw new InvalidArgumentException(
                        'Invalid signature format: Optional arguments must come before options. ' .
                            "Problem with: {{$param}}"
                    );
                }
                $foundOptional = true;
            } elseif ($type === 'option') {
                $foundOption = true;
            }

            $parameters[] = [
                'name' => $name,
                'isOption' => $isOption,
                'required' => $required,
                'default' => $default,
                'raw' => $param,
                'type' => $type,
            ];
        }

        return $parameters;
    }

    /**
     * Apply default values and validate required arguments
     */
    private function applyArgumentDefaultsAndValidation(
        array $parameters,
        array $providedArgs,
        ScalarTypedCollection $arguments
    ): void {
        // Filtrer pour n'avoir que les arguments (pas les options)
        $argumentParams = array_filter($parameters, fn($p) => ! $p['isOption']);
        $argumentParams = array_values($argumentParams);

        $providedIndex = 0;
        $totalProvided = count($providedArgs);

        foreach ($argumentParams as $index => $param) {
            $value = null;

            // Vérifier si un argument a été fourni à cette position
            if ($providedIndex < $totalProvided) {
                $value = $providedArgs[$providedIndex];
                $providedIndex++;
            }
            // Sinon, utiliser la valeur par défaut si disponible
            elseif ($param['default'] !== null) {
                $value = $param['default'];
            }
            // Vérifier si l'argument est requis
            elseif ($param['required']) {
                throw new InvalidArgumentException(
                    sprintf('Not enough arguments (missing: "%s")', $param['name'])
                );
            }

            // Ajouter l'argument SEULEMENT si une valeur existe
            if ($value !== null) {
                $arguments->add((string) $value);
                $arguments->add($param['name']);
            }
        }

        // Vérifier qu'il n'y a pas trop d'arguments
        if ($providedIndex < $totalProvided) {
            throw new InvalidArgumentException('Too many arguments provided');
        }
    }

    /**
     * Extract help information from a directive signature.
     *
     * @return ParsedParameterCollection<ParsedParameterRecord>
     */
    public function extractHelp(string $signature): ParsedParameterCollection
    {
        $params = new ParsedParameterCollection;
        $matches = $this->findSignatureParameters($signature);

        foreach ($matches as $param) {
            if ($this->isLongOption($param) || $this->isShortOption($param)) {
                $params->add($this->extractOptionHelp($param));
            } else {
                $params->add($this->extractArgumentHelp($param));
            }
        }

        return $params;
    }

    /**
     * Convert a parsed directive record to a ParsedResultRecord.
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
     * @return StringTypedCollection<string>
     */
    private function findSignatureParameters(string $signature): StringTypedCollection
    {
        preg_match_all('/\{([^}]+)\}/', $signature, $matches);
        $result = new StringTypedCollection;

        foreach ($matches[1] as $param) {
            $result->add($param);
        }

        return $result;
    }

    private function cleanParameterName(string $param): string
    {
        // Remove leading hyphens for options
        $param = ltrim($param, $this->config->longOptionPrefix);
        $param = ltrim($param, $this->config->shortOptionPrefix);

        // Remove =value part if present
        if (str_contains($param, $this->config->optionValueSeparator)) {
            $param = explode($this->config->optionValueSeparator, $param)[0];
        }

        // Remove =default value for arguments
        if (str_contains($param, '=')) {
            $param = explode('=', $param)[0];
        }

        // Remove trailing ? for optional arguments
        if (str_ends_with($param, $this->config->optionalMarker)) {
            $param = substr($param, 0, -1);
        }

        return $param;
    }

    private function isLongOption(string $arg): bool
    {
        return str_starts_with($arg, $this->config->longOptionPrefix);
    }

    private function isShortOption(string $arg): bool
    {
        return str_starts_with($arg, $this->config->shortOptionPrefix)
            && ! str_starts_with($arg, $this->config->longOptionPrefix)
            && strlen($arg) > 1;
    }

    private function parseLongOption(string $arg, ScalarTypedCollection $options): void
    {
        $parts = explode(
            $this->config->optionValueSeparator,
            substr($arg, strlen($this->config->longOptionPrefix)),
            2
        );

        $options->add($parts[0]);
        $options->add($parts[1] ?? $this->config->trueValue);
    }

    private function parseShortOption(string $arg, ScalarTypedCollection $options): void
    {
        $option = substr($arg, strlen($this->config->shortOptionPrefix));

        if (strlen($option) > 1) {
            $chars = str_split($option);
            foreach ($chars as $char) {
                $options->add($char);
                $options->add($this->config->trueValue);
            }
        } else {
            $options->add($option);
            $options->add($this->config->trueValue);
        }
    }

    private function extractOptionHelp(string $param): ParsedParameterRecord
    {
        $isLong = $this->isLongOption($param);
        $prefix = $isLong ? $this->config->longOptionPrefix : $this->config->shortOptionPrefix;
        $cleanParam = substr($param, strlen($prefix));

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

    private function extractArgumentHelp(string $param): ParsedParameterRecord
    {
        $default = null;
        $name = $param;

        if (preg_match('/^([^=]+)=(.+)$/', $param, $matches)) {
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
            required: ! $isOptional,
            default: $default,
        );
    }

    private function argumentsToCollection(ScalarTypedCollection $arguments): ParameterCollection
    {
        $result = new ParameterCollection;
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

    private function optionsToCollection(ScalarTypedCollection $options): ParameterCollection
    {
        $result = new ParameterCollection;
        $items = $options->toArray();

        for ($i = 0; $i < $options->count(); $i += 2) {
            if (! isset($items[$i])) {
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
