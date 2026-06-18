<?php

// tests/Fixtures/Directives/TestFailingDirective.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class TestFailingDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'failing';
    }

    public function getDescription(): string
    {
        return 'Test failing directive';
    }

    protected function execute(): ExitCode
    {
        $this->error('This directive always fails');

        return ExitCode::FAILURE;
    }
}
