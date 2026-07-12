<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

class TestConcreteDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'test:concrete {name} {email} {format=zip} ::level->[low,medium,high]=medium ::status->[pending,finished]=? {files*} {--force} {--verbose}';
    }

    public function getDescription(): string
    {
        return 'Test concrete directive for testing purposes';
    }

    protected function execute(): ExitCode
    {
        return ExitCode::SUCCESS;
    }
}
