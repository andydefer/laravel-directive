<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

class TestConcreteDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'test-concrete';
    }

    public function getDescription(): string
    {
        return 'Test concrete directive for AbstractDirective tests';
    }

    public function execute(): ExitCode
    {
        return ExitCode::SUCCESS;
    }
}
