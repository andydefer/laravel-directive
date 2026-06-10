<?php
// src/Records/VariadicArgumentsRecord.php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class VariadicArgumentsRecord extends AbstractRecord
{
    public function __construct(
        public readonly StringTypedCollection $items,
    ) {}
}
