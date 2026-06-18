<?php

// src/Contracts/ExecutionStrategyInterface.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;

interface ExecutionStrategyInterface
{
    public function supports(DirectiveExecutionRecord $record): bool;

    public function execute(DirectiveExecutionRecord $record): ExitCode;

    public function getPriority(): int;
}
