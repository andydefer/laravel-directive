<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class Child1Directive extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'child1';
    }

    public function getDescription(): string
    {
        return 'Test child 1 directive';
    }

    protected function execute(): ExitCode
    {
        $this->info('Child 1 directive executed');

        return ExitCode::SUCCESS;
    }
}
