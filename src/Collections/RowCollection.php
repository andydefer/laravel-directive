<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

/**
 * Type-safe collection for table rows.
 *
 * Each row contains values of mixed types (strings, numbers, booleans, null, or nested collections).
 *
 * @extends IndexedCollection<mixed>
 */
final class RowCollection extends IndexedCollection
{
    public function __construct()
    {
        parent::__construct(
            'string',
            'int',
            'float',
            'bool',
            'null',
            RowCollection::class
        );
    }
}
