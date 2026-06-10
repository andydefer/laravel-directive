<?php

// src/Records/StepResultRecord.php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Directive\Enums\StepResultStatus;
use AndyDefer\Directive\Enums\TestingStep;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class StepResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly TestingStep $step_name,
        public readonly StepResultStatus $status,
        public readonly string $message,
        public readonly DateTimeVO $executed_at,
    ) {}
}
