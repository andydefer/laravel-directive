<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\ScalarTypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class ParsedDirectiveRecord extends AbstractRecord
{
    public function __construct(
        public readonly ScalarTypedCollection $arguments,
        public readonly ScalarTypedCollection $options,
    ) {}
}
