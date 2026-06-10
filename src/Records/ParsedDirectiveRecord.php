<?php

// src/Records/ParsedDirectiveRecord.php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Directive\Collections\ParsedArgumentCollection;
use AndyDefer\Directive\Collections\ParsedOptionCollection;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class ParsedDirectiveRecord extends AbstractRecord
{
    public function __construct(
        public readonly ParsedArgumentCollection $arguments,
        public readonly ParsedOptionCollection $options,
        public readonly StringTypedCollection $variadic_arguments,
    ) {}
}
