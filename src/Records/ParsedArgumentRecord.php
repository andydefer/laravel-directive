<?php
// src/Records/ParsedArgumentRecord.php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class ParsedArgumentRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
    ) {}
}
