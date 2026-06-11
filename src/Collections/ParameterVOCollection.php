<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\Directive\Enums\PrimitiveType;
use AndyDefer\Directive\Services\PrimitiveTypeConverterService;
use AndyDefer\Directive\ValueObjects\ParameterVO;
use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;

/**
 * Type-safe collection for ParameterVO instances.
 *
 * @extends AbstractTypedCollection<ParameterVO>
 */
final class ParameterVOCollection extends AbstractTypedCollection
{
    private PrimitiveTypeConverterService $converter;

    public function __construct()
    {
        parent::__construct(ParameterVO::class);
        $this->converter = new PrimitiveTypeConverterService;
    }

    /**
     * Create collection from parsed options collection.
     *
     * @param  ParsedOptionCollection  $options  Parsed options collection
     * @return self New collection with ParameterVO objects
     */
    public static function fromParsedOptions(ParsedOptionCollection $options): self
    {
        $result = new self;

        foreach ($options as $record) {
            $type = match (true) {
                $record->is_flag => PrimitiveType::BOOL,
                is_numeric($record->value) => PrimitiveType::INT,
                default => PrimitiveType::STRING,
            };

            $value = match (true) {
                $record->is_flag => true,
                default => $record->value,
            };

            $result->add(new ParameterVO(
                name: $record->name,
                value: $value,
                type: $type,
            ));
        }

        return $result;
    }

    /**
     * Convert to associative array with converted values.
     *
     * @return array<string, mixed>
     */
    public function toAssociativeArray(): array
    {
        $result = [];

        foreach ($this->items as $item) {
            $result[$item->name] = $this->converter->convert($item->value, $item->type);
        }

        return $result;
    }

    /**
     * Get parameter value by name (converted).
     *
     * @param  string  $name  Parameter name
     * @return mixed Value or null if not found
     */
    public function get(string $name): mixed
    {
        foreach ($this->items as $item) {
            if ($item->name === $name) {
                return $this->converter->convert($item->value, $item->type);
            }
        }

        return null;
    }

    /**
     * Get raw parameter VO by name.
     *
     * @param  string  $name  Parameter name
     * @return ParameterVO|null Parameter VO or null if not found
     */
    public function getRaw(string $name): ?ParameterVO
    {
        foreach ($this->items as $item) {
            if ($item->name === $name) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Check if parameter exists.
     *
     * @param  string  $name  Parameter name
     * @return bool True if exists
     */
    public function has(string $name): bool
    {
        foreach ($this->items as $item) {
            if ($item->name === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if option is a flag (boolean).
     *
     * @param  string  $name  Option name
     * @return bool True if flag exists and is true
     */
    public function isFlag(string $name): bool
    {
        foreach ($this->items as $item) {
            if ($item->name === $name && $item->type === PrimitiveType::BOOL) {
                return $this->converter->convert($item->value, $item->type) === true;
            }
        }

        return false;
    }
}
