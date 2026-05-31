<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class DirectiveMetadataRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $signature,
        public readonly string $class,
        public readonly string $description,
        public readonly StringTypedCollection $aliases,
    ) {}
}
