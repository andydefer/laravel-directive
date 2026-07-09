<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class ContextSetDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'context:set {name}';
    }

    public function getDescription(): string
    {
        return 'Set a value in the context';
    }

    protected function execute(): ExitCode
    {
        $name = $this->argument('name');

        $this->contextSet('user_name', $name);
        $this->contextIncrement('counter');

        return ExitCode::SUCCESS;
    }
}
