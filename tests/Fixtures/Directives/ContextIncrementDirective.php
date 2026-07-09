<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class ContextIncrementDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'context:increment {step=?}';
    }

    public function getDescription(): string
    {
        return 'Increment a counter in the context';
    }

    protected function execute(): ExitCode
    {
        $step = (int) ($this->argument('step') ?? 1);
        $this->contextIncrement('counter', $step);

        return ExitCode::SUCCESS;
    }
}
