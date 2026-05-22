<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Records\AbstractRecord;
use AndyDefer\Records\Collections\TypedCollection;

final class DirectiveExecutionRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $signature,
        public readonly TypedCollection $arguments,
    ) {}
}
