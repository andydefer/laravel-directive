<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class ContextRemoveDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'context:remove {key}';
    }

    public function getDescription(): string
    {
        return 'Remove a key from the context';
    }

    protected function execute(): ExitCode
    {
        $key = $this->getArgument('key');
        $this->contextRemove($key);

        return ExitCode::SUCCESS;
    }
}
