<?php

// tests/Fixtures/Directives/TestNestedDirective.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

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

        $args1 = new StringTypedCollection;
        $args1->add('child1');
        $this->call(new DirectiveExecutionRecord('child1', $args1));

        $args2 = new StringTypedCollection;
        $args2->add('child2');
        $this->call(new DirectiveExecutionRecord('child2', $args2));

        $this->info('Nested directive finished');

        return ExitCode::SUCCESS;
    }
}
