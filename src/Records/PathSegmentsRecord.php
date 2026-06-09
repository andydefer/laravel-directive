<?php
// src/Records/PathSegmentsRecord.php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class PathSegmentsRecord extends AbstractRecord
{
    public function __construct(
        public readonly StringTypedCollection $segments,
        public readonly StringTypedCollection $pascalSegments,
        public readonly string $className,
        public readonly string $subPath,
        public readonly string $fullPath,
    ) {}
}
