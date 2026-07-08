<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class Child2Directive extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'child2';
    }

    public function getDescription(): string
    {
        return 'Test child 2 directive';
    }

    protected function execute(): ExitCode
    {
        $this->info('Child 2 directive executed');

        return ExitCode::SUCCESS;
    }
}
