<?php

// src/Records/ExecutionResultRecord.php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class ExecutionResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $directive_class,
        public readonly mixed $result,
        public readonly DateTimeVO $executed_at,
    ) {}
}
