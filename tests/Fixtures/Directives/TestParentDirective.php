<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class TestParentDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'test-parent';
    }

    public function getDescription(): string
    {
        return 'Test directive that calls other directives';
    }

    protected function execute(): ExitCode
    {
        $this->info('Parent directive started');

        $args1 = new StringTypedCollection;
        $args1->add('add', '10', '5');
        $record1 = new DirectiveExecutionRecord('calc', $args1);
        $this->call($record1);

        $args2 = new StringTypedCollection;
        $args2->add('pow', '2', '3');
        $record2 = new DirectiveExecutionRecord('calc', $args2);
        $this->call($record2);

        $args3 = new StringTypedCollection;
        $args3->add('John');
        $record3 = new DirectiveExecutionRecord('greeting', $args3);
        $this->call($record3);

        $this->info('Parent directive finished');

        return ExitCode::SUCCESS;
    }
}
