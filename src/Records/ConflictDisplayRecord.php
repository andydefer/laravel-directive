<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class ConflictDisplayRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly StringTypedCollection $classNames,
        public readonly StringTypedCollection $signatures,
        public readonly StringTypedCollection $descriptions,
    ) {}
}
