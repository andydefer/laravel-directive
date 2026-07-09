<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class ContextMergeDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'context:merge';
    }

    public function getDescription(): string
    {
        return 'Merge data into the context';
    }

    protected function execute(): ExitCode
    {
        $this->contextMerge([
            'name' => 'John',
            'age' => 30,
            'city' => 'Paris',
        ]);

        return ExitCode::SUCCESS;
    }
}
