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
     * @param  string  $value  The replacement value
     */
    public function addReplacement(string $placeholder, string $value): self
    {
        $this->add(new ReplacementRecord($placeholder, $value));

        return $this;
    }

    /**
     * Check if a placeholder exists in the collection.
     *
     * @param  string  $placeholder  The placeholder to check
     * @return bool True if the placeholder exists, false otherwise
     */
    public function hasPlaceholder(string $placeholder): bool
    {
        foreach ($this->items as $replacement) {
            if ($replacement->placeholder === $placeholder) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all placeholders as a string collection.
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
