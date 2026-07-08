<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;

/**
 * Abstract base class for collections that store records with first/last item access.
 *
 * @template TRecord
 *
 * @extends AbstractTypedCollection<TRecord>
 */
class DirectiveMetadataCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(DirectiveMetadataRecord::class);
    }

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

    /**
     * Remove duplicate directives based on signature.
     *
     * @return self New collection with unique directives
     */
    public function unique(): self
    {
        $seen = [];
        $result = new self;

        foreach ($this->items as $item) {
            $key = $item->signature;

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $result->add($item);
            }
        }

        return $result;
    }

    /**
     * Remove duplicate directives based on class name.
     *
     * @return self New collection with unique directives by class
     */
    public function uniqueByClass(): self
    {
        $seen = [];
        $result = new self;

        foreach ($this->items as $item) {
            $key = $item->class;

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $result->add($item);
            }
        }

        return $result;
    }
}
