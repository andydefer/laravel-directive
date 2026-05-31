<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;

/**
 * Abstract base class for collections that store records with first/last item access.
 *
 * @template TRecord
 * @extends AbstractTypedCollection<TRecord>
 */
abstract class AbstractItemCollection extends AbstractTypedCollection
{
    /**
     * Returns the first item in the collection.
     *
     * @return TRecord|null The first record, or null if the collection is empty
     */
    public function firstItem(): mixed
    {
        return $this->items[0] ?? null;
    }

    /**
     * Returns the last item in the collection.
     *
     * @return TRecord|null The last record, or null if the collection is empty
     */
    public function lastItem(): mixed
    {
        $lastKey = array_key_last($this->items);
        return $lastKey !== null ? $this->items[$lastKey] : null;
    }
}
