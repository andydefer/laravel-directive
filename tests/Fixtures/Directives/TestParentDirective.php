<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class TestParentDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'test:parent';
    }

    public function getDescription(): string
    {
        return 'Test directive that calls other directives';
    }

    protected function execute(): ExitCode
    {
        $this->info('Parent directive started');

        $this->call('calc add 10 5');
        $this->call('calc pow 2 3');
        $this->call('greeting John');

        $this->info('Parent directive finished');

        return ExitCode::SUCCESS;
    }
}
