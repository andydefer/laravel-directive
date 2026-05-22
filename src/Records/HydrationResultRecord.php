<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Directive\Collections\TypedRecords;
use AndyDefer\Records\AbstractRecord;
use AndyDefer\Records\Collections\TypedCollection;

final class HydrationResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $class,
        public readonly string $signature,
        public readonly string $description,
        public readonly TypedCollection $aliases,
        public readonly TypedCollection $arguments,
        public readonly TypedCollection $options,
    ) {}
}
