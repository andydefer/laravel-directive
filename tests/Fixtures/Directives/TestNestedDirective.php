<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class TestNestedDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'nested';
    }

    public function getDescription(): string
    {
        return 'Test nested directive';
    }

    protected function execute(): ExitCode
    {
        $this->info('Nested directive started');

        $this->call('child1');
        $this->call('child2');

        $this->info('Nested directive finished');

        return ExitCode::SUCCESS;
    }
}
