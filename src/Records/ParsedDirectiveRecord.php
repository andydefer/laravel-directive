<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Records\AbstractRecord;
use AndyDefer\Records\Collections\TypedCollection;

final class ParsedDirectiveRecord extends AbstractRecord
{
    public function __construct(
        public readonly TypedCollection $arguments,
        public readonly TypedCollection $options,
    ) {}
}
