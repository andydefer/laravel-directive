<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Records\AbstractRecord;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class ConflictDisplayRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly StringTypedCollection $classNames,
        public readonly StringTypedCollection $signatures,
        public readonly StringTypedCollection $descriptions,
    ) {}
}
