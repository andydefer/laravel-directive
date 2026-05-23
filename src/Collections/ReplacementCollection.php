<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\Directive\Records\ReplacementRecord;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

/**
 * @extends TypedCollection<ReplacementRecord>
 */
final class ReplacementCollection extends TypedCollection
{
    public function __construct()
    {
        parent::__construct(ReplacementRecord::class);
    }

    public function addReplacement(string $placeholder, string $value): self
    {
        $this->add(new ReplacementRecord($placeholder, $value));
        return $this;
    }

    public function getPlaceholders(): StringTypedCollection
    {
        $placeholders = new StringTypedCollection();
        foreach ($this->items as $replacement) {
            $placeholders->add($replacement->placeholder);
        }
        return $placeholders;
    }

    public function getValues(): StringTypedCollection
    {
        $values = new StringTypedCollection();
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
