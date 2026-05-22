<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

/**
 * Type-safe collection for column widths.
 *
 * @extends IndexedCollection<int>
 */
final class WidthCollection extends IndexedCollection
{
    public function __construct()
    {
        parent::__construct('int');
    }
}
