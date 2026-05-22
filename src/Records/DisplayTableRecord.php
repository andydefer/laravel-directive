<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Records\AbstractRecord;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class DisplayTableRecord extends AbstractRecord
{
    public function __construct(
        public readonly StringTypedCollection $headers,
        public readonly RowCollection $rows,
    ) {}
}
