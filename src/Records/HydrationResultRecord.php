<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;

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
