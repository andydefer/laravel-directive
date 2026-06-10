<?php

// src/Records/ArgumentSplitResultRecord.php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class ArgumentSplitResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly StringTypedCollection $regular,
        public readonly StringTypedCollection $variadic,
    ) {}
}
