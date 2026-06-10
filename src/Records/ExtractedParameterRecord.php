<?php

// src/Records/ExtractedParameterRecord.php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class ExtractedParameterRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly bool $isOption,
        public readonly bool $required,
        public readonly ?string $default,
        public readonly string $raw,
        public readonly bool $isVariadic,
    ) {}
}
