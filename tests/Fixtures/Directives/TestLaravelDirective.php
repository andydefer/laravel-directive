<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class TestLaravelDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'test-laravel';
    }

    public function getDescription(): string
    {
        return 'Test directive that needs Laravel';
    }

    public function execute(): ExitCode
    {
        $this->info('Test Laravel directive executed');

        return ExitCode::SUCCESS;
    }
}
