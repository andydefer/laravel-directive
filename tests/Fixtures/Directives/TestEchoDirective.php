<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class TestEchoDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'test-echo {message?} {extra?}';
    }

    public function getDescription(): string
    {
        return 'Test echo directive';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('echo');

        return $aliases;
    }

    public function execute(): ExitCode
    {
        $message = $this->argument('message') ?? 'Hello World';
        $this->line($message);

        if ($this->hasArgument('extra')) {
            $this->line($this->argument('extra'));
        }

        return ExitCode::SUCCESS;
    }
}
