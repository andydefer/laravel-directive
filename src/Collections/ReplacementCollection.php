<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\Directive\Records\ReplacementRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * @extends AbstractKeyValueCollection<ReplacementRecord>
 */
final class ReplacementCollection extends AbstractKeyValueCollection
{
    public function __construct()
    {
        parent::__construct(ReplacementRecord::class);
    }

    /**
     * Add a replacement pair.
     *
     * @param  string  $placeholder  The placeholder to replace (e.g., {{name}})
     * @param  string  $value        The replacement value
     * @return self
     */
    public function addReplacement(string $placeholder, string $value): self
    {
        $this->add(new ReplacementRecord($placeholder, $value));

        return $this;
    }

    /**
     * Get all placeholders as a string collection.
     *
     * @return StringTypedCollection
     */
    public function getPlaceholders(): StringTypedCollection
    {
        $placeholders = new StringTypedCollection;
        foreach ($this->items as $replacement) {
            $placeholders->add($replacement->placeholder);
        }

        return $placeholders;
    }

    /**
     * Get all values as a string collection.
     *
     * @return StringTypedCollection
     */
    public function getValues(): StringTypedCollection
    {
        $values = new StringTypedCollection;
        foreach ($this->items as $replacement) {
            $values->add($replacement->value);
        }

        return $values;
    }

    /**
     * Convert collection to associative array.
     *
     * @return array<string, string>
     */
    public function toAssociativeArray(): array
    {
        $result = [];
        foreach ($this->items as $replacement) {
            $result[$replacement->placeholder] = $replacement->value;
        }

        return $result;
    }
}
