<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;

/**
 * Abstract base collection with index-based access.
 *
 * @template TValue
 *
 * @extends TypedCollection<TValue>
 */
abstract class IndexedCollection extends AbstractTypedCollection
{
    /**
     * Get value at specific index.
     *
     * @param  int  $index  Index position
     * @return TValue|null Value or null if not found
     */
    public function get(int $index): mixed
    {
        $items = $this->toArray();

        return $items[$index] ?? null;
    }

    /**
     * Set value at specific index.
     *
     * @param  int  $index  Index position
     * @param  TValue  $value  Value to set
     */
    public function set(int $index, mixed $value): self
    {
        $items = $this->toArray();
        $items[$index] = $value;

        // Rebuild collection
        $this->items = [];
        foreach ($items as $item) {
            $this->add($item);
        }

        return $this;
    }

    /**
     * Check if index exists.
     *
     * @param  int  $index  Index position
     * @return bool True if index exists
     */
    public function has(int $index): bool
    {
        return $index >= 0 && $index < $this->count();
    }
}
