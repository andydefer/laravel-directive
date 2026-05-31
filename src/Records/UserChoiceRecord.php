<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record containing user choice for directive conflict resolution.
 */
final class UserChoiceRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $choice,
        public readonly int $max,
    ) {}
}
