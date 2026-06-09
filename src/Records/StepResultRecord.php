<?php
// src/Records/StepResultRecord.php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class StepResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $step_name,
        public readonly string $result,
        public readonly DateTimeVO $executed_at,
    ) {}
}
