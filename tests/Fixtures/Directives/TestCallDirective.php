<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class TestCallDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'test-call';
    }

    public function getDescription(): string
    {
        return 'Test directive that calls other directives recursively';
    }

    protected function execute(): ExitCode
    {
        $this->info('Test call directive started');

        $this->call('nested');
        $this->call('nested');

        $this->info('Test call directive finished');

        return ExitCode::SUCCESS;
    }
}
