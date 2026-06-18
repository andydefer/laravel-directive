<?php

// tests/Fixtures/Directives/TestGreetingDirective.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class TestGreetingDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'greeting {name=}';
    }

    public function getDescription(): string
    {
        return 'Say hello to someone';
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name') ?? 'World';
        $this->line("Hello, {$name}!");

        return ExitCode::SUCCESS;
    }
}
