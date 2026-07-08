<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class TestDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'test-directive {name} {email} {format=zip} {files*} {--force} {--verbose}';
    }

    public function getDescription(): string
    {
        return 'Test directive for testing purposes';
    }

    protected function execute(): ExitCode
    {
        return ExitCode::SUCCESS;
    }
}
