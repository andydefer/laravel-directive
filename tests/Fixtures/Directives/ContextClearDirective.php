<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class ContextClearDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'context:clear';
    }

    public function getDescription(): string
    {
        return 'Clear the entire context';
    }

    protected function execute(): ExitCode
    {
        $this->contextClear();

        return ExitCode::SUCCESS;
    }
}
