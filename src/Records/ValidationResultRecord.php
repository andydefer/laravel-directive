<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record containing validation result.
 */
class ValidationResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $isValid,
        public readonly ?string $error = null,
    ) {}
}
