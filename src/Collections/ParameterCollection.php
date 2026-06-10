<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\DomainStructures\Collections\Utility\ScalarTypedCollection;

/**
 * Type-safe collection for ParameterRecord instances.
 *
 * @extends AbstractKeyValueCollection<ParameterRecord>
 */
final class ParameterCollection extends AbstractKeyValueCollection
{
    public function __construct()
    {
        parent::__construct(ParameterRecord::class);
    }

    /**
     * Create collection from flat arguments format.
     *
     * Flat format: [value1, name1, value2, name2, ...]
     *
     * @param  ScalarTypedCollection|ParsedArgumentCollection  $flat  Flat arguments collection
     * @return self New collection with ParameterRecord objects
     */
    public static function fromFlatArguments(ScalarTypedCollection|ParsedArgumentCollection $flat): self
    {
        $result = new self;

        if ($flat instanceof ParsedArgumentCollection) {
            foreach ($flat as $record) {
                $result->add(new ParameterRecord(name: $record->name, value: $record->value));
            }

            return $result;
        }

        $items = $flat->toArray();
        for ($i = 0; $i < $flat->count(); $i += 2) {
            $value = $items[$i] ?? null;
            $name = $items[$i + 1] ?? null;

            if ($name !== null && $value !== null) {
                $result->add(new ParameterRecord(name: $name, value: $value));
            }
        }

        return $result;
    }

    /**
     * Create collection from flat options format.
     *
     * Flat format: [name1, value1, name2, value2, ...]
     *
     * @param  ScalarTypedCollection|ParsedOptionCollection  $flat  Flat options collection
     * @return self New collection with ParameterRecord objects
     */
    public static function fromFlatOptions(ScalarTypedCollection|ParsedOptionCollection $flat): self
    {
        $result = new self;

        if ($flat instanceof ParsedOptionCollection) {
            foreach ($flat as $record) {
                $result->add(new ParameterRecord(name: $record->name, value: $record->value));
            }

            return $result;
        }

        $items = $flat->toArray();
        for ($i = 0; $i < $flat->count(); $i += 2) {
            $name = $items[$i] ?? null;
            $rawValue = $items[$i + 1] ?? null;

            if ($name === null) {
                continue;
            }

            $value = match (true) {
                $rawValue === null => true,
                $rawValue === 'true' => true,
                $rawValue === 'false' => false,
                $rawValue === '' => true,
                default => $rawValue,
            };

            $result->add(new ParameterRecord(name: $name, value: $value));
        }

        return $result;
    }

    /**
     * Convert to associative array.
     *
     * @return array<string, bool|string|int|null>
     */
    public function toAssociativeArray(): array
    {
        $result = [];

        foreach ($this->items as $item) {
            $result[$item->name] = $item->value;
        }

        return $result;
    }

    /**
     * Get parameter value by name.
     *
     * @param  string  $name  Parameter name
     * @return bool|string|int|null Value or null if not found
     */
    public function get(string $name): bool|string|int|null
    {
        foreach ($this->items as $item) {
            if ($item->name === $name) {
                return $item->value;
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
}
