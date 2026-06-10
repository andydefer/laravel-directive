<?php

// src/Records/ParsedOptionRecord.php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class ParsedOptionRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
        public readonly bool $is_flag,
    ) {}
}
