<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class ContextDecrementDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'context:decrement {step=?}';
    }

    public function getDescription(): string
    {
        return 'Decrement a counter in the context';
    }

    protected function execute(): ExitCode
    {
        $step = (int) ($this->getArgument('step') ?? 1);
        $this->contextDecrement('counter', $step);

        return ExitCode::SUCCESS;
    }
}
