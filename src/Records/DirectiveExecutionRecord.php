<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class DirectiveExecutionRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $signature,
        public readonly StringTypedCollection $arguments,
    ) {}
}
