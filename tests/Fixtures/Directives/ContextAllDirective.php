<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class ContextAllDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'context:all';
    }

    public function getDescription(): string
    {
        return 'Get all context data';
    }

    protected function execute(): ExitCode
    {

        return ExitCode::SUCCESS;
    }
}
