<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class ContextGetDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'context:get';
    }

    public function getDescription(): string
    {
        return 'Get a value from the context';
    }

    protected function execute(): ExitCode
    {
        $this->contextGet('user_name', 'World');
        $this->contextGet('counter', 0);

        return ExitCode::SUCCESS;
    }
}
